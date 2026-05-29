<?php
/**
 * AI API integration functions (supports Ollama, OpenRouter, OpenAI)
 *
 * @package KwikAI
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

// Load security utilities if available
if (file_exists(dirname(__DIR__) . '/includes/security-utilities.php')) {
  require_once dirname(__DIR__) . '/includes/security-utilities.php';
}

/**
 * Get the configured AI model
 *
 * @return string
 */
function kwik_ai_tags_get_ai_model(): string
{
  return get_option('kwik_ai_model', 'gemma3:27b');
}

/**
 * Get the configured AI provider
 *
 * @return string
 */
function kwik_ai_tags_get_ai_provider(): string
{
  return get_option('kwik_ai_ai_provider', 'custom');
}

/**
 * Get API endpoint based on provider
 *
 * @return string
 */
function kwik_ai_tags_get_api_endpoint(): string
{
  $provider = kwik_ai_tags_get_ai_provider();
  $endpoint = get_option('kwik_ai_api_endpoint', '');
  
  // Set default endpoint based on provider
  if ($provider === 'openrouter' && empty($endpoint)) {
    $endpoint = 'https://openrouter.ai/api/v1';
  } elseif ($provider === 'openai' && empty($endpoint)) {
    $endpoint = 'https://api.openai.com/v1';
  } elseif ($endpoint === '') {
    $endpoint = 'http://localhost:11434';
  }
  
  return rtrim($endpoint, '/');
}

/**
 * Get API key based on provider
 * Uses secure credential storage if available
 *
 * @return string
 */
function kwik_ai_tags_get_api_key(): string
{
  $provider = kwik_ai_tags_get_ai_provider();
  
  if ($provider === 'openrouter') {
    // Use secure credential retrieval if available
    if (function_exists('kwik_ai_retrieve_credential')) {
      return kwik_ai_retrieve_credential('kwik_ai_openrouter_api_key', '');
    }
    return get_option('kwik_ai_openrouter_api_key', '');
  } elseif ($provider === 'openai') {
    // Use secure credential retrieval if available
    if (function_exists('kwik_ai_retrieve_credential')) {
      return kwik_ai_retrieve_credential('kwik_ai_openai_api_key', '');
    }
    return get_option('kwik_ai_openai_api_key', '');
  }
  
  return '';
}

/**
 * Get API headers based on provider
 *
 * @return array
 */
function kwik_ai_tags_get_api_headers(): array
{
  $provider = kwik_ai_tags_get_ai_provider();
  $headers = array(
    'Content-Type' => 'application/json',
  );
  
  if ($provider === 'openrouter') {
    $api_key = kwik_ai_tags_get_api_key();
    $endpoint = get_option('kwik_ai_api_endpoint', 'https://openrouter.ai/api/v1');
    
    if (!empty($api_key)) {
      $headers['Authorization'] = 'Bearer ' . $api_key;
      $headers['HTTP-Referer'] = home_url();
      $headers['X-Title'] = get_bloginfo('name');
    }
  } elseif ($provider === 'openai') {
    $api_key = kwik_ai_tags_get_api_key();
    
    if (!empty($api_key)) {
      $headers['Authorization'] = 'Bearer ' . $api_key;
    }
  } else {
    // Custom - check for API key
    $api_key = get_option('kwik_ai_custom_api_key', '');
    if (!empty($api_key)) {
      $headers['Authorization'] = 'Bearer ' . $api_key;
    }
  }
  
  return $headers;
}

/**
 * Get custom endpoint config
 *
 * @return array
 */
function kwik_ai_tags_get_ollama_config()
{
  $url = get_option('kwik_ai_api_endpoint', 'http://localhost:11434');
  $api_key = get_option('kwik_ai_custom_api_key', '');

  // Ensure URL doesn't have trailing slash
  $url = rtrim($url, '/');

  return array(
    'url' => $url,
    'api_key' => $api_key,
    'has_auth' => !empty($api_key)
  );
}

/**
 * Send a request to the AI provider's generate endpoint.
 *
 * Supports Ollama, OpenRouter, and OpenAI.
 *
 * @param string $prompt
 * @param array  $images   Array of data‑URI strings
 * @return string|null     Raw response string (e.g., "cat, outdoor")
 */
function kwik_ai_tags_query_ai(string $prompt, array $images): ?string
{
  $provider = kwik_ai_tags_get_ai_provider();
  $endpoint = kwik_ai_tags_get_api_endpoint();
  $model = kwik_ai_tags_get_ai_model();
  $headers = kwik_ai_tags_get_api_headers();

  // Log only non-sensitive information
  error_log('Kwik AI: Provider: ' . $provider);
  error_log('Kwik AI: Endpoint: ' . $endpoint);
  error_log('Kwik AI: Model: ' . $model);
  error_log('Kwik AI: Number of images: ' . count($images));
  error_log('Kwik AI: Auth configured: ' . (count($headers) > 1 ? 'Yes' : 'No'));

  // Build payload based on provider
  $payload = array();
  
  if ($provider === 'custom') {
    // Custom /api/generate endpoint
    $url = $endpoint . '/api/generate';
    
    $payload = [
      'model' => $model,
      'prompt' => $prompt,
      'images' => $images,
      'stream' => false,
    ];
    
    // Log payload without images (too large)
    error_log('Kwik AI: Payload (without images): ' . json_encode(array_merge($payload, ['images' => '[' . count($images) . ' images]'])));
    
    $response = wp_remote_post(
      $url,
      [
        'body' => wp_json_encode($payload),
        'headers' => array_merge($headers, array('Content-Type' => 'application/json')),
        'timeout' => 120,
      ]
    );
    
    return kwik_ai_tags_process_ollama_response($response, $prompt);
    
  } elseif ($provider === 'openrouter' || $provider === 'openai') {
    // OpenRouter/OpenAI /chat/completions endpoint
    $url = $endpoint . '/chat/completions';
    
    // Build messages array
    $messages = array();
    
    // System message
    $messages[] = array(
      'role' => 'system',
      'content' => 'You are an expert at generating concise, relevant tags for content. Respond ONLY with a comma-separated list of tags.',
    );
    
    // User message - build content based on whether we have images
    $user_content = array();
    
    if (!empty($images)) {
      // Add image data
      foreach ($images as $image) {
        $user_content[] = array(
          'type' => 'image_url',
          'image_url' => array(
            'url' => $image, // data URI
          ),
        );
      }
    }
    
    // Add text prompt
    $user_content[] = array(
      'type' => 'text',
      'text' => $prompt,
    );
    
    $messages[] = array(
      'role' => 'user',
      'content' => $user_content,
    );
    
    // Build payload
    $payload = array(
      'model' => $model,
      'messages' => $messages,
      'temperature' => 0.7,
      'max_tokens' => 100,
    );
    
    // Log payload summary, not full content
    error_log('Kwik AI: Payload: ' . json_encode(array_merge($payload, array('messages' => '[' . count($messages) . ' messages]'))));
    
    $response = wp_remote_post(
      $url,
      [
        'body' => wp_json_encode($payload),
        'headers' => array_merge($headers, array('Content-Type' => 'application/json')),
        'timeout' => 120,
      ]
    );
    
    return kwik_ai_tags_process_openai_response($response);
  }
  
  return null;
}

/**
 * Process Ollama response
 *
 * @param mixed $response
 * @param string $prompt
 * @return string|null
 */
function kwik_ai_tags_process_ollama_response($response, $prompt)
{
  if (is_wp_error($response)) {
    error_log('Kwik AI: WP Error: ' . $response->get_error_message());
    return null;
  }

  $response_code = wp_remote_retrieve_response_code($response);
  error_log('Kwik AI: Response code: ' . $response_code);

  if ($response_code !== 200) {
    $body = wp_remote_retrieve_body($response);
    error_log('Kwik AI: HTTP error: ' . $response_code . ' - Response body: ' . substr($body, 0, 200));
    return null;
  }

  $body = wp_remote_retrieve_body($response);
  error_log('Kwik AI: Response body (' . strlen($body) . ' chars)');

  $data = json_decode($body, true);

  if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('Kwik AI: JSON decode error: ' . json_last_error_msg());

    // Try to handle streaming response manually
    $lines = explode("\n", trim($body));
    $full_response = '';

    foreach ($lines as $line) {
      $line = trim($line);
      if (empty($line)) continue;

      $json = json_decode($line, true);
      if ($json && isset($json['response'])) {
        $full_response .= $json['response'];

        if (isset($json['done']) && $json['done'] === true) {
          break;
        }
      }
    }

    if (!empty($full_response)) {
      error_log('Kwik AI: Reconstructed response: ' . $full_response);
      return trim($full_response);
    }

    return null;
  }

  if (!isset($data['response'])) {
    error_log('Kwik AI: No response field in data');
    return null;
  }

  return trim($data['response']);
}

/**
 * Process OpenAI/OpenRouter response
 *
 * @param mixed $response
 * @return string|null
 */
function kwik_ai_tags_process_openai_response($response)
{
  if (is_wp_error($response)) {
    error_log('Kwik AI: WP Error: ' . $response->get_error_message());
    return null;
  }

  $response_code = wp_remote_retrieve_response_code($response);
  error_log('Kwik AI: Response code: ' . $response_code);

  if ($response_code !== 200) {
    $body = wp_remote_retrieve_body($response);
    error_log('Kwik AI: HTTP error: ' . $response_code);
    
    // Try to parse error message
    $error_data = json_decode($body, true);
    if (isset($error_data['error']['message'])) {
      error_log('Kwik AI: Error message: ' . $error_data['error']['message']);
    }
    
    return null;
  }

  $body = wp_remote_retrieve_body($response);
  error_log('Kwik AI: Response body (' . strlen($body) . ' chars)');

  $data = json_decode($body, true);

  if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('Kwik AI: JSON decode error: ' . json_last_error_msg());
    return null;
  }

  if (!isset($data['choices'][0]['message']['content'])) {
    error_log('Kwik AI: No content in response');
    return null;
  }

  return trim($data['choices'][0]['message']['content']);
}

/**
 * Send a request to the AI provider's generate endpoint with text only (no images).
 *
 * @param string $prompt
 * @return string|null     Raw response string
 */
function kwik_ai_tags_query_ai_text_only(string $prompt): ?string
{
  $provider = kwik_ai_tags_get_ai_provider();
  $endpoint = kwik_ai_tags_get_api_endpoint();
  $model = kwik_ai_tags_get_ai_model();
  $headers = kwik_ai_tags_get_api_headers();

  error_log('Kwik AI: Provider: ' . $provider);
  error_log('Kwik AI: Endpoint: ' . $endpoint);
  error_log('Kwik AI: Model: ' . $model);
  error_log('Kwik AI: Auth configured: ' . (count($headers) > 1 ? 'Yes' : 'No'));

  // Build payload based on provider
  if ($provider === 'custom') {
    // Custom /api/generate endpoint
    $url = $endpoint . '/api/generate';
    
    $payload = [
      'model' => $model,
      'prompt' => $prompt,
      'stream' => false,
    ];
    
    error_log('Kwik AI: Payload: ' . json_encode($payload));
    
    $response = wp_remote_post(
      $url,
      [
        'body' => wp_json_encode($payload),
        'headers' => array_merge($headers, array('Content-Type' => 'application/json')),
        'timeout' => 120,
      ]
    );
    
    return kwik_ai_tags_process_ollama_response($response, $prompt);
    
  } elseif ($provider === 'openrouter' || $provider === 'openai') {
    // OpenRouter/OpenAI /chat/completions endpoint
    $url = $endpoint . '/chat/completions';
    
    // Build messages array
    $messages = array(
      array(
        'role' => 'system',
        'content' => 'You are an expert at generating concise, relevant tags for content. Respond ONLY with a comma-separated list of tags.',
      ),
      array(
        'role' => 'user',
        'content' => $prompt,
      ),
    );
    
    // Build payload
    $payload = array(
      'model' => $model,
      'messages' => $messages,
      'temperature' => 0.7,
      'max_tokens' => 100,
    );
    
    error_log('Kwik AI: Payload: ' . json_encode($payload));
    
    $response = wp_remote_post(
      $url,
      [
        'body' => wp_json_encode($payload),
        'headers' => array_merge($headers, array('Content-Type' => 'application/json')),
        'timeout' => 120,
      ]
    );
    
    return kwik_ai_tags_process_openai_response($response);
  }
  
  return null;
}

/**
 * Parse the comma‑separated tag list from the model response.
 *
 * @param string $raw_response
 * @return array
 */
function kwik_ai_tags_parse_tags(string $raw_response): array
{
  // Clean up the response - remove quotes, extra whitespace, etc.
  $clean_response = trim($raw_response, '"\'');
  $clean_response = preg_replace('/\s*,\s*/', ',', $clean_response);

  $parts = array_filter(array_map('trim', explode(',', $clean_response)));

  // Clean each tag - remove quotes, excessive whitespace, and invalid characters
  $cleaned_parts = array();
  foreach ($parts as $part) {
    $tag = trim($part, '"\'');
    $tag = preg_replace('/[^\w\s-]/', '', $tag); // Keep only letters, numbers, spaces, hyphens
    $tag = preg_replace('/\s+/', ' ', $tag); // Normalize whitespace
    $tag = trim($tag);

    // Skip empty tags or tags that are too short/long
    if (!empty($tag) && strlen($tag) >= 2 && strlen($tag) <= 50) {
      $cleaned_parts[] = $tag;
    }
  }

  // Deduplicate (case-insensitive)
  $unique_tags = array();
  $lower_tags = array();
  foreach ($cleaned_parts as $tag) {
    $lower = strtolower($tag);
    if (!in_array($lower, $lower_tags)) {
      $unique_tags[] = $tag;
      $lower_tags[] = $lower;
    }
  }

  return $unique_tags;
}

/**
 * Test AI provider connection
 *
 * @return bool|string True if connected, error message on failure
 */
function kwik_ai_tags_test_ai_connection()
{
  $provider = kwik_ai_tags_get_ai_provider();
  
  if ($provider === 'custom') {
    return kwik_ai_tags_test_ollama_connection();
  } elseif ($provider === 'openrouter') {
    return kwik_ai_tags_test_openrouter_connection();
  } elseif ($provider === 'openai') {
    return kwik_ai_tags_test_openai_connection();
  }
  
  return 'Unknown provider';
}

/**
 * Test Ollama connection
 *
 * @return bool|string True if connected, error message on failure
 */
function kwik_ai_tags_test_ollama_connection()
{
  $config = kwik_ai_tags_get_ollama_config();
  $url = $config['url'] . '/api/tags';
  $auth_headers = kwik_ai_tags_get_ollama_auth_header();
  $selected_model = kwik_ai_tags_get_ai_model();

  error_log('Kwik AI: Testing connection to: ' . $url);
  error_log('Kwik AI: Auth configured: ' . ($config['has_auth'] ? 'Yes' : 'No'));
  error_log('Kwik AI: Selected model: ' . $selected_model);

  $headers = array_merge(
    array('Content-Type' => 'application/json'),
    $auth_headers
  );

  $response = wp_remote_get($url, array(
    'timeout' => 5,
    'headers' => $headers
  ));

  if (is_wp_error($response)) {
    return __('Connection failed: ', KWIK_AI_DOMAIN) . $response->get_error_message();
  }

  $response_code = wp_remote_retrieve_response_code($response);
  if ($response_code !== 200) {
    return sprintf(__('HTTP Error %d', KWIK_AI_DOMAIN), $response_code);
  }

  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body, true);

  if (!$data || !isset($data['models'])) {
    return __('Invalid response from Ollama', KWIK_AI_DOMAIN);
  }

  // Check if selected model is available
  $has_model = false;
  foreach ($data['models'] as $model) {
    if (isset($model['name']) && $model['name'] === $selected_model) {
      $has_model = true;
      break;
    }
  }

  if (!$has_model) {
    return sprintf(__('Connected, but "%s" model not found. Please pull the model or select a different one.', KWIK_AI_DOMAIN), $selected_model);
  }

  return true;
}

/**
 * Test OpenRouter connection
 *
 * @return bool|string True if connected, error message on failure
 */
function kwik_ai_tags_test_openrouter_connection()
{
  $api_key = kwik_ai_tags_get_api_key();
  $endpoint = get_option('kwik_ai_api_endpoint', 'https://openrouter.ai/api/v1');
  $selected_model = kwik_ai_tags_get_ai_model();
  
  if (empty($api_key)) {
    return 'No API key configured';
  }
  
  $url = $endpoint . '/models';
  
  $headers = array(
    'Content-Type' => 'application/json',
    'Authorization' => 'Bearer ' . $api_key,
    'HTTP-Referer' => home_url(),
    'X-Title' => get_bloginfo('name'),
  );

  $response = wp_remote_get($url, array(
    'timeout' => 10,
    'headers' => $headers
  ));

  if (is_wp_error($response)) {
    return $response->get_error_message();
  }

  $response_code = wp_remote_retrieve_response_code($response);
  if ($response_code !== 200) {
    return sprintf(__('HTTP %d', KWIK_AI_DOMAIN), $response_code);
  }

  // Check if model is available
  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body, true);
  
  if (!isset($data['data'])) {
    return 'Invalid response from OpenRouter';
  }
  
  $has_model = false;
  foreach ($data['data'] as $model) {
    if (isset($model['id']) && strpos($model['id'], $selected_model) !== false) {
      $has_model = true;
      break;
    }
  }
  
  if (!$has_model) {
    return sprintf(__('Connected, but "%s" model not found. Please check the model name.', KWIK_AI_DOMAIN), $selected_model);
  }
  
  return true;
}

/**
 * Test OpenAI connection
 *
 * @return bool|string True if connected, error message on failure
 */
function kwik_ai_tags_test_openai_connection()
{
  $api_key = kwik_ai_tags_get_api_key();
  $endpoint = get_option('kwik_ai_api_endpoint', 'https://api.openai.com/v1');
  $selected_model = kwik_ai_tags_get_ai_model();
  
  if (empty($api_key)) {
    return 'No API key configured';
  }
  
  $url = $endpoint . '/models';
  
  $headers = array(
    'Content-Type' => 'application/json',
    'Authorization' => 'Bearer ' . $api_key,
  );

  $response = wp_remote_get($url, array(
    'timeout' => 10,
    'headers' => $headers
  ));

  if (is_wp_error($response)) {
    return $response->get_error_message();
  }

  $response_code = wp_remote_retrieve_response_code($response);
  if ($response_code !== 200) {
    return sprintf(__('HTTP %d', KWIK_AI_DOMAIN), $response_code);
  }

  // Check if model is available
  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body, true);
  
  if (!isset($data['data'])) {
    return 'Invalid response from OpenAI';
  }
  
  $has_model = false;
  foreach ($data['data'] as $model) {
    if (isset($model['id']) && $model['id'] === $selected_model) {
      $has_model = true;
      break;
    }
  }
  
  if (!$has_model) {
    return sprintf(__('Connected, but "%s" model not found. Please check the model name.', KWIK_AI_DOMAIN), $selected_model);
  }
  
  return true;
}

/**
 * Get auth header for custom endpoint requests
 *
 * @return array
 */
function kwik_ai_tags_get_ollama_auth_header()
{
  $config = kwik_ai_tags_get_ollama_config();

  if (!$config['has_auth']) {
    return array();
  }

  return array('Authorization' => 'Bearer ' . $config['api_key']);
}
