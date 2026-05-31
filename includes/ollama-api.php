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
 * Get the model used for text-only generation (image prompts, URL-based
 * descriptions).
 *
 * Falls back to the main model when no dedicated text model is configured, so
 * existing setups are unaffected. Set a text/chat model here when the main
 * model is a vision model that can't (or shouldn't) handle plain text work.
 *
 * @return string
 */
function kwik_ai_tags_get_text_model(): string
{
  $text_model = get_option('kwik_ai_text_model', '');
  $text_model = is_string($text_model) ? trim($text_model) : '';

  return $text_model !== '' ? $text_model : kwik_ai_tags_get_ai_model();
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
    $api_key = kwik_ai_tags_get_custom_api_key();
    if (!empty($api_key)) {
      $headers['Authorization'] = 'Bearer ' . $api_key;
    }
  }

  return $headers;
}

/**
 * Get the custom/Ollama API key, decrypting it from secure storage if available.
 *
 * @return string
 */
function kwik_ai_tags_get_custom_api_key(): string
{
  if (function_exists('kwik_ai_retrieve_credential')) {
    return kwik_ai_retrieve_credential('kwik_ai_custom_api_key', '');
  }

  return get_option('kwik_ai_custom_api_key', '');
}

/**
 * Get custom endpoint config
 *
 * @return array
 */
function kwik_ai_tags_get_ollama_config()
{
  $url = get_option('kwik_ai_api_endpoint', 'http://localhost:11434');
  $api_key = kwik_ai_tags_get_custom_api_key();

  // Ensure URL doesn't have trailing slash
  $url = rtrim($url, '/');

  return array(
    'url' => $url,
    'api_key' => $api_key,
    'has_auth' => !empty($api_key)
  );
}

/**
 * POST an OpenAI-compatible chat completion, retrying with
 * 'max_completion_tokens' when the server rejects 'max_tokens'.
 *
 * Newer OpenAI (GPT-5-class) models — and proxies that front them — reject the
 * legacy 'max_tokens' parameter with a 400 and ask for 'max_completion_tokens'.
 * Older models/servers only understand 'max_tokens'. This sends 'max_tokens'
 * first (widest compatibility) and transparently retries with the new field
 * name when that specific error is returned.
 *
 * @param string $url        Full /chat/completions URL.
 * @param array  $headers    Request headers.
 * @param array  $payload    Payload without any token-limit field.
 * @param int    $max_tokens Token limit to apply.
 * @return array|WP_Error    wp_remote_post() response.
 */
function kwik_ai_tags_post_chat_completion(string $url, array $headers, array $payload, int $max_tokens)
{
  $headers = array_merge($headers, array('Content-Type' => 'application/json'));

  $post = function (array $payload) use ($url, $headers) {
    return wp_remote_post($url, array(
      'body' => wp_json_encode($payload),
      'headers' => $headers,
      'timeout' => 120,
    ));
  };

  // Most models accept the legacy 'max_tokens'; start there for compatibility.
  $token_field = 'max_tokens';
  $payload[$token_field] = $max_tokens;
  $response = $post($payload);

  // Newer OpenAI (GPT-5-class) models reject 'max_tokens' with a 400 asking for
  // 'max_completion_tokens'. Switch field names and retry.
  if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 400
      && stripos(wp_remote_retrieve_body($response), 'max_completion_tokens') !== false) {
    kwik_ai_log('Kwik AI: Server rejected max_tokens; retrying with max_completion_tokens');
    unset($payload['max_tokens']);
    $token_field = 'max_completion_tokens';
    $payload[$token_field] = $max_tokens;
    $response = $post($payload);
  }

  // Reasoning models spend tokens on hidden reasoning and can return empty
  // content with finish_reason "length" when the budget is too small. Retry
  // once with a much larger budget so the actual answer has room.
  if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
    $data = json_decode(wp_remote_retrieve_body($response), true);
    $content = isset($data['choices'][0]['message']['content'])
      ? trim((string) $data['choices'][0]['message']['content'])
      : '';
    $finish = isset($data['choices'][0]['finish_reason'])
      ? $data['choices'][0]['finish_reason']
      : '';

    if ($content === '' && $finish === 'length') {
      $bigger = max($max_tokens * 8, 2048);
      kwik_ai_log('Kwik AI: Empty content with finish_reason=length (likely a reasoning model); retrying with ' . $token_field . '=' . $bigger);
      $payload[$token_field] = $bigger;
      $response = $post($payload);
    }
  }

  return $response;
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
  kwik_ai_log('Kwik AI: Provider: ' . $provider);
  kwik_ai_log('Kwik AI: Endpoint: ' . $endpoint);
  kwik_ai_log('Kwik AI: Model: ' . $model);
  kwik_ai_log('Kwik AI: Number of images: ' . count($images));
  kwik_ai_log('Kwik AI: Auth configured: ' . (count($headers) > 1 ? 'Yes' : 'No'));

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
    kwik_ai_log('Kwik AI: Payload (without images): ' . json_encode(array_merge($payload, ['images' => '[' . count($images) . ' images]'])));
    
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
    
    // Build payload (token limit added by the helper, which handles the
    // max_tokens vs max_completion_tokens difference between models).
    $payload = array(
      'model' => $model,
      'messages' => $messages,
      'temperature' => 0.7,
    );

    // Log payload summary, not full content
    kwik_ai_log('Kwik AI: Payload: ' . json_encode(array_merge($payload, array('messages' => '[' . count($messages) . ' messages]'))));

    $response = kwik_ai_tags_post_chat_completion($url, $headers, $payload, 100);

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
    kwik_ai_log('Kwik AI: WP Error: ' . $response->get_error_message());
    return null;
  }

  $response_code = wp_remote_retrieve_response_code($response);
  kwik_ai_log('Kwik AI: Response code: ' . $response_code);

  if ($response_code !== 200) {
    $body = wp_remote_retrieve_body($response);
    kwik_ai_log('Kwik AI: HTTP error: ' . $response_code . ' - Response body: ' . substr($body, 0, 200));
    return null;
  }

  $body = wp_remote_retrieve_body($response);
  kwik_ai_log('Kwik AI: Response body (' . strlen($body) . ' chars)');

  $data = json_decode($body, true);

  if (json_last_error() !== JSON_ERROR_NONE) {
    kwik_ai_log('Kwik AI: JSON decode error: ' . json_last_error_msg());

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
      kwik_ai_log('Kwik AI: Reconstructed response: ' . $full_response);
      return trim($full_response);
    }

    return null;
  }

  if (!isset($data['response'])) {
    kwik_ai_log('Kwik AI: No response field in data');
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
    kwik_ai_log('Kwik AI: WP Error: ' . $response->get_error_message());
    return null;
  }

  $response_code = wp_remote_retrieve_response_code($response);
  kwik_ai_log('Kwik AI: Response code: ' . $response_code);

  if ($response_code !== 200) {
    $body = wp_remote_retrieve_body($response);
    kwik_ai_log('Kwik AI: HTTP error: ' . $response_code);
    
    // Try to parse error message
    $error_data = json_decode($body, true);
    if (isset($error_data['error']['message'])) {
      kwik_ai_log('Kwik AI: Error message: ' . $error_data['error']['message']);
    }
    
    return null;
  }

  $body = wp_remote_retrieve_body($response);
  kwik_ai_log('Kwik AI: Response body (' . strlen($body) . ' chars)');

  $data = json_decode($body, true);

  if (json_last_error() !== JSON_ERROR_NONE) {
    kwik_ai_log('Kwik AI: JSON decode error: ' . json_last_error_msg());
    return null;
  }

  if (!isset($data['choices'][0]['message']['content'])) {
    kwik_ai_log('Kwik AI: No content in response');
    return null;
  }

  return trim($data['choices'][0]['message']['content']);
}

/**
 * Send a request to the AI provider's generate endpoint with text only (no images).
 *
 * @param string      $prompt
 * @param string|null $system     Optional system instruction. Defaults to the
 *                                tag-generation system prompt when null, so
 *                                existing callers behave unchanged.
 * @param int         $max_tokens Maximum tokens for hosted providers (default 100).
 * @return string|null            Raw response string
 */
function kwik_ai_tags_query_ai_text_only(string $prompt, ?string $system = null, int $max_tokens = 100): ?string
{
  $provider = kwik_ai_tags_get_ai_provider();
  $endpoint = kwik_ai_tags_get_api_endpoint();
  $model = kwik_ai_tags_get_text_model();
  $headers = kwik_ai_tags_get_api_headers();

  kwik_ai_log('Kwik AI: Provider: ' . $provider);
  kwik_ai_log('Kwik AI: Endpoint: ' . $endpoint);
  kwik_ai_log('Kwik AI: Model: ' . $model);
  kwik_ai_log('Kwik AI: Auth configured: ' . (count($headers) > 1 ? 'Yes' : 'No'));

  // Build payload based on provider
  if ($provider === 'custom') {
    // "Custom" servers come in two flavors: OpenAI-compatible (Open WebUI,
    // LM Studio, vLLM, LocalAI — expose /chat/completions) and native Ollama
    // (exposes /api/generate). Try the OpenAI-compatible endpoint first, to
    // match how the model list is fetched (/models first), and fall back to
    // Ollama's native endpoint only when that path is absent (404/405/error).
    $messages = array();
    if ($system !== null && $system !== '') {
      $messages[] = array('role' => 'system', 'content' => $system);
    }
    $messages[] = array('role' => 'user', 'content' => $prompt);

    $oai_payload = array(
      'model' => $model,
      'messages' => $messages,
      'stream' => false,
    );

    kwik_ai_log('Kwik AI: Trying OpenAI-compatible /chat/completions for custom provider');

    $oai_response = kwik_ai_tags_post_chat_completion(
      $endpoint . '/chat/completions',
      $headers,
      $oai_payload,
      $max_tokens
    );

    // Use the OpenAI-compatible result unless the endpoint clearly isn't there.
    // A 404/405 means "wrong endpoint" (fall back); other codes (200, 400, 401,
    // 500…) mean the endpoint exists, so let process_openai_response handle it
    // rather than masking a real error by retrying the Ollama path.
    if (!is_wp_error($oai_response)) {
      $oai_code = wp_remote_retrieve_response_code($oai_response);
      if ($oai_code !== 404 && $oai_code !== 405) {
        return kwik_ai_tags_process_openai_response($oai_response);
      }
      kwik_ai_log('Kwik AI: /chat/completions returned ' . $oai_code . '; falling back to /api/generate');
    } else {
      kwik_ai_log('Kwik AI: /chat/completions error: ' . $oai_response->get_error_message() . '; falling back to /api/generate');
    }

    // Fall back to native Ollama /api/generate.
    $ollama_payload = [
      'model' => $model,
      'prompt' => $prompt,
      'stream' => false,
    ];
    if ($system !== null && $system !== '') {
      $ollama_payload['system'] = $system;
    }

    $ollama_response = wp_remote_post(
      $endpoint . '/api/generate',
      [
        'body' => wp_json_encode($ollama_payload),
        'headers' => array_merge($headers, array('Content-Type' => 'application/json')),
        'timeout' => 120,
      ]
    );

    return kwik_ai_tags_process_ollama_response($ollama_response, $prompt);

  } elseif ($provider === 'openrouter' || $provider === 'openai') {
    // OpenRouter/OpenAI /chat/completions endpoint
    $url = $endpoint . '/chat/completions';

    $system_message = ($system !== null && $system !== '')
      ? $system
      : 'You are an expert at generating concise, relevant tags for content. Respond ONLY with a comma-separated list of tags.';

    // Build messages array
    $messages = array(
      array(
        'role' => 'system',
        'content' => $system_message,
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
    );

    kwik_ai_log('Kwik AI: Payload: ' . json_encode($payload));

    $response = kwik_ai_tags_post_chat_completion($url, $headers, $payload, $max_tokens);

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
  $selected_model = kwik_ai_tags_get_ai_model();

  // Use the robust model fetch, which tries the OpenAI-compatible /models
  // endpoint first and falls back to Ollama's /api/tags. This makes the test
  // work for any custom provider, not just Ollama.
  $models = kwik_ai_tags_fetch_models_live('custom', $config['url'], $config['api_key']);

  if (!is_array($models) || empty($models)) {
    return __('Could not connect to the AI provider. Please check the endpoint URL and API key.', 'kwik-ai-tags');
  }

  // Check if the selected model is available.
  $has_model = false;
  foreach ($models as $model) {
    if (isset($model['name']) && $model['name'] === $selected_model) {
      $has_model = true;
      break;
    }
  }

  if (!$has_model) {
    return sprintf(
      /* translators: %s: model name */
      __('Connected, but the "%s" model was not found. Please select a different model.', 'kwik-ai-tags'),
      $selected_model
    );
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
    /* translators: %d: HTTP response status code */
    return sprintf(__('HTTP %d', 'kwik-ai-tags'), $response_code);
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
    /* translators: %s: AI model name */
    return sprintf(__('Connected, but "%s" model not found. Please check the model name.', 'kwik-ai-tags'), $selected_model);
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
    /* translators: %d: HTTP response status code */
    return sprintf(__('HTTP %d', 'kwik-ai-tags'), $response_code);
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
    /* translators: %s: AI model name */
    return sprintf(__('Connected, but "%s" model not found. Please check the model name.', 'kwik-ai-tags'), $selected_model);
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

/**
 * Resolve the default API endpoint for a provider when none is supplied.
 *
 * @param string $provider
 * @return string
 */
function kwik_ai_tags_default_endpoint_for_provider($provider)
{
  if ($provider === 'openrouter') {
    return 'https://openrouter.ai/api/v1';
  }

  if ($provider === 'openai') {
    return 'https://api.openai.com/v1';
  }

  return 'http://localhost:11434';
}

/**
 * Build request headers for a models/test request from explicit values.
 *
 * @param string $provider
 * @param string $api_key
 * @return array
 */
function kwik_ai_tags_build_request_headers($provider, $api_key)
{
  $headers = array('Content-Type' => 'application/json');

  if (!empty($api_key)) {
    $headers['Authorization'] = 'Bearer ' . $api_key;
  }

  if ($provider === 'openrouter') {
    $headers['HTTP-Referer'] = home_url();
    $headers['X-Title'] = get_bloginfo('name');
  }

  return $headers;
}

/**
 * Sort a model list: vision-capable models first, then alphabetically.
 *
 * @param array $models
 * @return array
 */
function kwik_ai_tags_sort_model_list(array $models)
{
  usort($models, function ($a, $b) {
    if ($a['has_vision'] !== $b['has_vision']) {
      return $b['has_vision'] ? 1 : -1;
    }
    return strcasecmp($a['name'], $b['name']);
  });

  return $models;
}

/**
 * Fetch models from an OpenAI-compatible /models endpoint.
 *
 * Works for OpenAI, OpenRouter, and any custom provider that exposes the
 * standard /models endpoint (LM Studio, vLLM, LocalAI, etc.).
 *
 * @param string $endpoint Base API URL (e.g. https://api.openai.com/v1)
 * @param string $api_key  Optional bearer token
 * @param string $provider Provider slug (affects extra headers)
 * @return array|false     Array of model info or false on error
 */
function kwik_ai_tags_request_openai_models($endpoint, $api_key, $provider = 'custom')
{
  $url = rtrim($endpoint, '/') . '/models';

  $response = wp_remote_get($url, array(
    'timeout' => 10,
    'headers' => kwik_ai_tags_build_request_headers($provider, $api_key),
  ));

  if (is_wp_error($response)) {
    return false;
  }

  if (wp_remote_retrieve_response_code($response) !== 200) {
    return false;
  }

  $data = json_decode(wp_remote_retrieve_body($response), true);

  if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
    return false;
  }

  $models = array();
  foreach ($data['data'] as $model) {
    $id = '';
    if (isset($model['id'])) {
      $id = $model['id'];
    } elseif (isset($model['name'])) {
      $id = $model['name'];
    }

    if ($id === '') {
      continue;
    }

    $models[] = array(
      'name' => $id,
      'has_vision' => kwik_ai_tags_model_has_vision($id),
    );
  }

  if (empty($models)) {
    return false;
  }

  return kwik_ai_tags_sort_model_list($models);
}

/**
 * Fetch models from an Ollama /api/tags endpoint.
 *
 * @param string $endpoint Base API URL (e.g. http://localhost:11434)
 * @param string $api_key  Optional bearer token
 * @return array|false     Array of model info or false on error
 */
function kwik_ai_tags_request_ollama_models($endpoint, $api_key)
{
  $url = rtrim($endpoint, '/') . '/api/tags';

  $headers = array('Content-Type' => 'application/json');
  if (!empty($api_key)) {
    $headers['Authorization'] = 'Bearer ' . $api_key;
  }

  $response = wp_remote_get($url, array(
    'timeout' => 10,
    'headers' => $headers,
  ));

  if (is_wp_error($response)) {
    return false;
  }

  if (wp_remote_retrieve_response_code($response) !== 200) {
    return false;
  }

  $data = json_decode(wp_remote_retrieve_body($response), true);

  if (!is_array($data) || !isset($data['models']) || !is_array($data['models'])) {
    return false;
  }

  $models = array();
  foreach ($data['models'] as $model) {
    if (!isset($model['name'])) {
      continue;
    }

    $models[] = array(
      'name' => $model['name'],
      'has_vision' => kwik_ai_tags_model_has_vision($model['name']),
      'size' => isset($model['size']) ? $model['size'] : null,
      'modified_at' => isset($model['modified_at']) ? $model['modified_at'] : null,
    );
  }

  if (empty($models)) {
    return false;
  }

  return kwik_ai_tags_sort_model_list($models);
}

/**
 * Fetch available models from a provider using explicit connection values.
 *
 * This does not depend on saved settings, so it can be used to populate the
 * model dropdown live while the settings form is being edited.
 *
 * For custom providers it tries the OpenAI-compatible /models endpoint first
 * (where most providers expose their model list), then falls back to Ollama's
 * /api/tags endpoint.
 *
 * @param string $provider Provider slug (custom|openrouter|openai)
 * @param string $endpoint Base API URL (empty to use provider default)
 * @param string $api_key  Optional bearer token
 * @return array|false     Array of model info or false on error
 */
function kwik_ai_tags_fetch_models_live($provider, $endpoint, $api_key)
{
  if (empty($endpoint)) {
    $endpoint = kwik_ai_tags_default_endpoint_for_provider($provider);
  }

  if ($provider === 'custom') {
    $models = kwik_ai_tags_request_openai_models($endpoint, $api_key, 'custom');
    if ($models !== false) {
      return $models;
    }

    // Fall back to Ollama's native model listing.
    return kwik_ai_tags_request_ollama_models($endpoint, $api_key);
  }

  return kwik_ai_tags_request_openai_models($endpoint, $api_key, $provider);
}
