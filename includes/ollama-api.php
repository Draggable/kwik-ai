<?php
/**
 * Ollama API integration functions
 *
 * @package KwikAI
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Send a request to Ollama's /api/generate endpoint.
 *
 * @param string $prompt
 * @param array  $images   Array of data‑URI strings
 * @return string|null     Raw response string (e.g., "cat, outdoor")
 */
function kwik_ai_tags_query_ollama(string $prompt, array $images): ?string
{
  $url = KWIK_AI_OLLAMA_HOST . '/api/generate';
  error_log('Kwik AI: Ollama URL: ' . $url);
  error_log('Kwik AI: Number of images: ' . count($images));

  $payload = [
    'model' => 'gemma3:27b',
    'prompt' => $prompt,
    'images' => $images,
    'stream' => false, // Important: disable streaming for easier parsing
  ];

  error_log('Kwik AI: Payload (without images): ' . json_encode(array_merge($payload, ['images' => '[' . count($images) . ' images]'])));

  $response = wp_remote_post(
    $url,
    [
      'body' => wp_json_encode($payload),
      'headers' => ['Content-Type' => 'application/json'],
      'timeout' => 120, // Increased timeout for vision processing
    ]
  );

  if (is_wp_error($response)) {
    error_log('Kwik AI: WP Error: ' . $response->get_error_message());
    return null; // Network or server error.
  }

  $response_code = wp_remote_retrieve_response_code($response);
  error_log('Kwik AI: Response code: ' . $response_code);

  if ($response_code !== 200) {
    $body = wp_remote_retrieve_body($response);
    error_log('Kwik AI: HTTP error: ' . $response_code . ' - Response body: ' . $body);
    return null;
  }

  $body = wp_remote_retrieve_body($response);
  error_log('Kwik AI: Response body: ' . substr($body, 0, 500)); // Log first 500 chars

  $data = json_decode($body, true);

  if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('Kwik AI: JSON decode error: ' . json_last_error_msg());

    // Try to handle streaming response manually
    $lines = explode("\n", trim($body));
    $full_response = '';

    foreach ($lines as $line) {
      $line = trim($line);
      if (empty($line))
        continue;

      $json = json_decode($line, true);
      if ($json && isset($json['response'])) {
        $full_response .= $json['response'];

        // If this is the final chunk
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
    error_log('Kwik AI: No response field in data: ' . print_r($data, true));
    return null;
  }

  return trim($data['response']);
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
  $cleaned_parts = [];
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
  $unique_tags = [];
  $lower_tags = [];
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
 * Test Ollama connection
 */
function kwik_ai_tags_test_ollama_connection()
{
  $url = KWIK_AI_OLLAMA_HOST . '/api/tags';
  
  $response = wp_remote_get($url, array(
    'timeout' => 5,
    'headers' => array('Content-Type' => 'application/json')
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
  
  // Check if gemma3:27b model is available
  $has_gemma = false;
  foreach ($data['models'] as $model) {
    if (isset($model['name']) && strpos($model['name'], 'gemma3:27b') !== false) {
      $has_gemma = true;
      break;
    }
  }
  
  if (!$has_gemma) {
    return __('Connected, but gemma3:27b model not found. Run: ollama pull gemma3:27b', KWIK_AI_DOMAIN);
  }
  
  return true;
}