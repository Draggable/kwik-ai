<?php
/**
 * FAL.AI image generation API integration.
 *
 * Uses FAL's asynchronous queue API so long-running image models don't block a
 * single HTTP request: submit a job, then poll for status and fetch the result.
 *
 * @package KwikAI
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

// Load security utilities if available (for credential decryption).
if (file_exists(dirname(__DIR__) . '/includes/security-utilities.php')) {
  require_once dirname(__DIR__) . '/includes/security-utilities.php';
}

// Base host for FAL's queue API. Status/result URLs returned by the submit
// call are validated against this host before we fetch them.
define('KWIK_AI_FAL_QUEUE_HOST', 'queue.fal.run');

// Default image model and size used when none is configured.
define('KWIK_AI_FAL_DEFAULT_MODEL', 'fal-ai/flux/dev');
define('KWIK_AI_FAL_DEFAULT_IMAGE_SIZE', 'landscape_16_9');

/**
 * Curated list of FAL.AI text-to-image models offered in the settings dropdown.
 *
 * The list is intentionally small and well-known; the saved model is always
 * kept selectable even if it isn't in this list, so custom model IDs work too.
 *
 * @return array<string,string> Model ID => human-readable label.
 */
function kwik_ai_fal_get_models(): array
{
  return array(
    'fal-ai/flux/schnell'        => 'FLUX.1 [schnell] — fastest',
    'fal-ai/flux/dev'            => 'FLUX.1 [dev] — balanced quality',
    'fal-ai/flux-pro/v1.1'       => 'FLUX1.1 [pro]',
    'fal-ai/flux-pro/v1.1-ultra' => 'FLUX1.1 [pro] ultra',
    'fal-ai/recraft-v3'          => 'Recraft V3',
    'fal-ai/stable-diffusion-v35-large' => 'Stable Diffusion 3.5 Large',
    'fal-ai/ideogram/v2'         => 'Ideogram V2',
  );
}

// Transient key + lifetime for the live model list fetched from FAL.
define('KWIK_AI_FAL_MODELS_CACHE_KEY', 'kwik_ai_fal_models');
define('KWIK_AI_FAL_MODELS_CACHE_TTL', 12 * HOUR_IN_SECONDS);

/**
 * Fetch the live list of text-to-image models from FAL.AI.
 *
 * Uses FAL's public model catalog endpoint (the same one that powers
 * https://fal.ai/models). It is unauthenticated and undocumented, so this is
 * best-effort: results are cached, and on any failure the caller falls back to
 * the curated list in kwik_ai_fal_get_models().
 *
 * @param bool $force Bypass the cache and refetch.
 * @return array<string,string> Model ID => title, or empty array on failure.
 */
function kwik_ai_fal_fetch_models(bool $force = false): array
{
  if (!$force) {
    $cached = get_transient(KWIK_AI_FAL_MODELS_CACHE_KEY);
    if (is_array($cached) && !empty($cached)) {
      return $cached;
    }
  }

  $models = array();
  $page = 1;
  $pages = 1;
  $max_pages = 10; // Safety cap so a growing catalog can't cause endless requests.

  do {
    $url = add_query_arg(
      array(
        'categories' => 'text-to-image',
        'page'       => $page,
      ),
      'https://fal.ai/api/models'
    );

    $response = wp_remote_get($url, array('timeout' => 15));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
      break;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data) || empty($data['items']) || !is_array($data['items'])) {
      break;
    }

    foreach ($data['items'] as $item) {
      if (empty($item['id'])) {
        continue;
      }
      $id = (string) $item['id'];
      $models[$id] = !empty($item['title']) ? (string) $item['title'] : $id;
    }

    $pages = isset($data['pages']) ? (int) $data['pages'] : 1;
    $page++;
  } while ($page <= $pages && $page <= $max_pages);

  if (empty($models)) {
    return array();
  }

  set_transient(KWIK_AI_FAL_MODELS_CACHE_KEY, $models, KWIK_AI_FAL_MODELS_CACHE_TTL);

  return $models;
}

/**
 * Get the model list for the settings dropdown: the live FAL catalog when
 * available, otherwise the curated fallback list.
 *
 * @param bool $force Force a refresh of the live list.
 * @return array<string,string> Model ID => label.
 */
function kwik_ai_fal_get_available_models(bool $force = false): array
{
  $live = kwik_ai_fal_fetch_models($force);
  if (!empty($live)) {
    return $live;
  }

  return kwik_ai_fal_get_models();
}

/**
 * Supported FAL image_size enum values offered in the settings dropdown.
 *
 * @return array<string,string> Value => human-readable label.
 */
function kwik_ai_fal_get_image_sizes(): array
{
  return array(
    'square_hd'      => 'Square HD (1:1)',
    'square'         => 'Square (1:1)',
    'portrait_4_3'   => 'Portrait 4:3',
    'portrait_16_9'  => 'Portrait 16:9',
    'landscape_4_3'  => 'Landscape 4:3',
    'landscape_16_9' => 'Landscape 16:9',
  );
}

/**
 * Get the configured FAL model ID.
 *
 * @return string
 */
function kwik_ai_fal_get_model(): string
{
  $model = get_option('kwik_ai_fal_model', KWIK_AI_FAL_DEFAULT_MODEL);
  return $model !== '' ? $model : KWIK_AI_FAL_DEFAULT_MODEL;
}

/**
 * Get the configured FAL image size.
 *
 * @return string
 */
function kwik_ai_fal_get_image_size(): string
{
  $size = get_option('kwik_ai_fal_image_size', KWIK_AI_FAL_DEFAULT_IMAGE_SIZE);
  $allowed = kwik_ai_fal_get_image_sizes();
  return isset($allowed[$size]) ? $size : KWIK_AI_FAL_DEFAULT_IMAGE_SIZE;
}

/**
 * Get the FAL API key, decrypting it from secure storage if available.
 *
 * @return string
 */
function kwik_ai_fal_get_api_key(): string
{
  if (function_exists('kwik_ai_retrieve_credential')) {
    return kwik_ai_retrieve_credential('kwik_ai_fal_api_key', '');
  }

  return get_option('kwik_ai_fal_api_key', '');
}

/**
 * Whether a FAL API key has been configured.
 *
 * @return bool
 */
function kwik_ai_fal_has_api_key(): bool
{
  return kwik_ai_fal_get_api_key() !== '';
}

/**
 * Build the authorization headers for a FAL request.
 *
 * @param string $api_key
 * @return array
 */
function kwik_ai_fal_request_headers(string $api_key): array
{
  return array(
    'Authorization' => 'Key ' . $api_key,
    'Content-Type'  => 'application/json',
  );
}

/**
 * Submit an image generation job to the FAL queue.
 *
 * @param string $prompt Text-to-image prompt.
 * @param array  $args   Optional overrides: model, image_size.
 * @return array|WP_Error ['request_id','status_url','response_url'] or error.
 */
function kwik_ai_fal_submit_request(string $prompt, array $args = array())
{
  $api_key = kwik_ai_fal_get_api_key();
  if ($api_key === '') {
    return new WP_Error('kwik_ai_fal_no_key', __('No FAL.AI API key is configured. Add one in Settings > KWIK AI.', 'kwik-ai'));
  }

  $prompt = trim($prompt);
  if ($prompt === '') {
    return new WP_Error('kwik_ai_fal_no_prompt', __('No prompt was generated for the image.', 'kwik-ai'));
  }

  $model = isset($args['model']) && $args['model'] !== '' ? $args['model'] : kwik_ai_fal_get_model();
  $image_size = isset($args['image_size']) && $args['image_size'] !== '' ? $args['image_size'] : kwik_ai_fal_get_image_size();

  $body = array(
    'prompt'     => $prompt,
    'image_size' => $image_size,
    'num_images' => 1,
  );

  $url = 'https://' . KWIK_AI_FAL_QUEUE_HOST . '/' . ltrim($model, '/');

  kwik_ai_log('Kwik AI FAL: Submitting job to ' . $url . ' (image_size: ' . $image_size . ')');

  $response = wp_remote_post($url, array(
    'headers' => kwik_ai_fal_request_headers($api_key),
    'body'    => wp_json_encode($body),
    'timeout' => 30,
  ));

  if (is_wp_error($response)) {
    kwik_ai_log('Kwik AI FAL: Submit WP error: ' . $response->get_error_message());
    return $response;
  }

  $code = wp_remote_retrieve_response_code($response);
  $raw  = wp_remote_retrieve_body($response);

  if ($code < 200 || $code >= 300) {
    kwik_ai_log('Kwik AI FAL: Submit HTTP ' . $code . ' - ' . substr($raw, 0, 200));
    return new WP_Error('kwik_ai_fal_http', kwik_ai_fal_extract_error_message($raw, $code));
  }

  $data = json_decode($raw, true);
  if (!is_array($data) || empty($data['request_id'])) {
    return new WP_Error('kwik_ai_fal_bad_response', __('FAL.AI returned an unexpected response when submitting the job.', 'kwik-ai'));
  }

  return array(
    'request_id'   => (string) $data['request_id'],
    'status_url'   => isset($data['status_url']) ? (string) $data['status_url'] : '',
    'response_url' => isset($data['response_url']) ? (string) $data['response_url'] : '',
  );
}

/**
 * Check the status of a queued FAL request.
 *
 * @param string $status_url The status_url returned by the submit call.
 * @return string|WP_Error Status string (IN_QUEUE|IN_PROGRESS|COMPLETED) or error.
 */
function kwik_ai_fal_check_status(string $status_url)
{
  $validated = kwik_ai_fal_validate_queue_url($status_url);
  if (is_wp_error($validated)) {
    return $validated;
  }

  $api_key = kwik_ai_fal_get_api_key();
  if ($api_key === '') {
    return new WP_Error('kwik_ai_fal_no_key', __('No FAL.AI API key is configured.', 'kwik-ai'));
  }

  $response = wp_remote_get($status_url, array(
    'headers' => kwik_ai_fal_request_headers($api_key),
    'timeout' => 15,
  ));

  if (is_wp_error($response)) {
    return $response;
  }

  $code = wp_remote_retrieve_response_code($response);
  $raw  = wp_remote_retrieve_body($response);

  if ($code < 200 || $code >= 300) {
    return new WP_Error('kwik_ai_fal_http', kwik_ai_fal_extract_error_message($raw, $code));
  }

  $data = json_decode($raw, true);
  if (!is_array($data) || empty($data['status'])) {
    return new WP_Error('kwik_ai_fal_bad_response', __('FAL.AI returned an unexpected status response.', 'kwik-ai'));
  }

  return (string) $data['status'];
}

/**
 * Fetch the final result of a completed FAL request and return the image URL.
 *
 * @param string $response_url The response_url returned by the submit call.
 * @return string|WP_Error Image URL or error.
 */
function kwik_ai_fal_get_result(string $response_url)
{
  $validated = kwik_ai_fal_validate_queue_url($response_url);
  if (is_wp_error($validated)) {
    return $validated;
  }

  $api_key = kwik_ai_fal_get_api_key();
  if ($api_key === '') {
    return new WP_Error('kwik_ai_fal_no_key', __('No FAL.AI API key is configured.', 'kwik-ai'));
  }

  $response = wp_remote_get($response_url, array(
    'headers' => kwik_ai_fal_request_headers($api_key),
    'timeout' => 30,
  ));

  if (is_wp_error($response)) {
    return $response;
  }

  $code = wp_remote_retrieve_response_code($response);
  $raw  = wp_remote_retrieve_body($response);

  if ($code < 200 || $code >= 300) {
    return new WP_Error('kwik_ai_fal_http', kwik_ai_fal_extract_error_message($raw, $code));
  }

  $data = json_decode($raw, true);
  if (!is_array($data) || empty($data['images'][0]['url'])) {
    return new WP_Error('kwik_ai_fal_no_image', __('FAL.AI did not return an image. Try regenerating.', 'kwik-ai'));
  }

  return (string) $data['images'][0]['url'];
}

/**
 * Validate that a URL points at the FAL queue host before we fetch it.
 *
 * The status/response URLs originate from FAL's own submit response (not user
 * input), but validating the host is cheap defense against SSRF if a stored
 * value were ever tampered with.
 *
 * @param string $url
 * @return true|WP_Error
 */
function kwik_ai_fal_validate_queue_url(string $url)
{
  $host = wp_parse_url($url, PHP_URL_HOST);
  if ($host !== KWIK_AI_FAL_QUEUE_HOST) {
    return new WP_Error('kwik_ai_fal_bad_url', __('Refusing to contact an unexpected FAL.AI URL.', 'kwik-ai'));
  }
  return true;
}

/**
 * Extract a human-friendly error message from a FAL error response body.
 *
 * @param string $raw  Raw response body.
 * @param int    $code HTTP status code.
 * @return string
 */
function kwik_ai_fal_extract_error_message(string $raw, int $code): string
{
  $data = json_decode($raw, true);

  if (is_array($data)) {
    if (!empty($data['detail']) && is_string($data['detail'])) {
      return $data['detail'];
    }
    if (!empty($data['detail'][0]['msg'])) {
      return (string) $data['detail'][0]['msg'];
    }
    if (!empty($data['error'])) {
      return is_string($data['error']) ? $data['error'] : wp_json_encode($data['error']);
    }
    if (!empty($data['message'])) {
      return (string) $data['message'];
    }
  }

  if ($code === 401 || $code === 403) {
    return __('FAL.AI rejected the API key. Check your key in Settings > KWIK AI.', 'kwik-ai');
  }

  /* translators: %d: HTTP status code */
  return sprintf(__('FAL.AI request failed (HTTP %d).', 'kwik-ai'), $code);
}

/**
 * Report whether FAL.AI is configured, for the settings status panel.
 *
 * There is no lightweight FAL endpoint to validate a key without submitting a
 * job, so this only confirms a key is present.
 *
 * @return true|string True when a key is configured, otherwise a message.
 */
function kwik_ai_fal_test_connection()
{
  if (!kwik_ai_fal_has_api_key()) {
    return __('No FAL.AI API key configured.', 'kwik-ai');
  }

  return true;
}
