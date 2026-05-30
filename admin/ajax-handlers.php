<?php
/**
 * AJAX handlers
 *
 * @package KwikAI
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Rate limit AJAX requests per user.
 * Prevents abusive users from hammering AI API endpoints.
 *
 * @param string $action Unique action identifier (e.g., 'kwik_ai_tags_generate')
 * @param int    $cooldown_seconds Minimum seconds between requests (default: 15)
 * @return bool True if request is allowed, false if rate limited
 */
function kwik_ai_check_rate_limit(string $action, int $cooldown_seconds = 15): bool
{
  $user_id = get_current_user_id();
  $transient_key = 'kwik_ai_rate_' . md5($action . '_' . $user_id);

  $last_request = get_transient($transient_key);
  if ($last_request !== false) {
    return false; // Rate limited
  }

  // Set transient for cooldown period
  set_transient($transient_key, time(), $cooldown_seconds);
  return true;
}

/**
 * AJAX handler for generating tags
 */
function kwik_ai_tags_ajax_generate()
{
  check_ajax_referer('kwik_ai_tags_ajax', 'nonce');

  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Generate AJAX called');
  }

  if (!current_user_can('edit_posts')) {
    kwik_ai_log('Kwik AI: User does not have edit_posts capability');
    wp_die(esc_html__('You do not have sufficient permissions.', KWIK_AI_DOMAIN));
  }

  // Rate limiting: 1 request per 15 seconds per user
  if (!kwik_ai_check_rate_limit('kwik_ai_tags_generate', 15)) {
    wp_send_json_error(__('Please wait a moment before generating tags again.', KWIK_AI_DOMAIN));
  }

  if (!isset($_POST['post_id'])) {
    wp_send_json_error(__('Missing post ID.', KWIK_AI_DOMAIN));
  }
  $post_id = intval($_POST['post_id']);
  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Post ID: ' . $post_id);
  }

  if (!$post_id || get_post_status($post_id) === false) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      kwik_ai_log('Kwik AI: Invalid post ID');
    }
    wp_send_json_error(__('Invalid post ID.', KWIK_AI_DOMAIN));
  }

  // Check if post has images or sufficient text content
  $attachments = get_attached_media('image', $post_id);
  $post_content = get_post_field('post_content', $post_id);
  $word_count = str_word_count(wp_strip_all_tags($post_content));
  
  // Also check for block images (including TRB belt gallery)
  $block_images = kwik_ai_tags_extract_block_images($post_content, $post_id);
  $total_images = count($attachments) + count($block_images);

  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Found ' . count($attachments) . ' attached images, ' . count($block_images) . ' block images, and ' . $word_count . ' words');
  }

  if ($total_images === 0 && $word_count < KWIK_AI_MIN_WORDS) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      kwik_ai_log('Kwik AI: Insufficient content for analysis');
    }
    wp_send_json_error(__('Please add images or write at least 50 words to generate AI tags.', KWIK_AI_DOMAIN));
  }

  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Calling kwik_ai_tags_generate_for_post');
  }
  $tags = kwik_ai_tags_generate_for_post($post_id);
  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Generated tags: ' . print_r($tags, true));
  }

  if ($tags === false) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      kwik_ai_log('Kwik AI: Tag generation failed');
    }
    wp_send_json_error(__('Failed to generate tags. Please check your AI provider connection and try again.', KWIK_AI_DOMAIN));
  }

  if (empty($tags)) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      kwik_ai_log('Kwik AI: No tags generated');
    }
    wp_send_json_error(__('No tags were generated. Try adding more descriptive images.', KWIK_AI_DOMAIN));
  }

  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Sending success response');
  }
  wp_send_json_success(['tags' => $tags]);
}

/**
 * AJAX handler for applying tags
 */
function kwik_ai_tags_ajax_apply()
{
  check_ajax_referer('kwik_ai_tags_ajax', 'nonce');

  if (!current_user_can('edit_posts')) {
    wp_die(esc_html__('You do not have sufficient permissions.', KWIK_AI_DOMAIN));
  }

  if (!isset($_POST['post_id']) || !isset($_POST['tags'])) {
    wp_send_json_error(__('Missing required fields.', KWIK_AI_DOMAIN));
  }

  $post_id = intval($_POST['post_id']);
  $tags = sanitize_text_field(wp_unslash($_POST['tags']));

  if (!$post_id || get_post_status($post_id) === false) {
    wp_send_json_error(__('Invalid post ID.', KWIK_AI_DOMAIN));
  }

  if (!$tags) {
    wp_send_json_error(__('No tags provided.', KWIK_AI_DOMAIN));
  }

  $tag_array = array_filter(array_map('trim', explode(',', $tags)));

  if (empty($tag_array)) {
    wp_send_json_error(__('No valid tags to apply.', KWIK_AI_DOMAIN));
  }

  // Sanitize each tag
  $tag_array = array_map('sanitize_text_field', $tag_array);

  $result = wp_set_post_terms($post_id, $tag_array, 'post_tag', true);

  if (is_wp_error($result)) {
    wp_send_json_error($result->get_error_message());
  }

  wp_send_json_success(sprintf(__('Successfully applied %d tags to the post!', KWIK_AI_DOMAIN), count($tag_array)));
}

/**
 * AJAX handler for generating description
 */
function kwik_ai_description_ajax_generate()
{
  check_ajax_referer('kwik_ai_description_ajax', 'nonce');

  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Description generate AJAX called');
  }

  if (!current_user_can('edit_posts')) {
    kwik_ai_log('Kwik AI: User does not have edit_posts capability');
    wp_die(esc_html__('You do not have sufficient permissions.', KWIK_AI_DOMAIN));
  }

  // Rate limiting: 1 request per 15 seconds per user
  if (!kwik_ai_check_rate_limit('kwik_ai_description_generate', 15)) {
    wp_send_json_error(__('Please wait a moment before generating descriptions again.', KWIK_AI_DOMAIN));
  }

  if (!isset($_POST['post_id'])) {
    wp_send_json_error(__('Missing post ID.', KWIK_AI_DOMAIN));
  }
  $post_id = intval($_POST['post_id']);
  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Post ID: ' . $post_id);
  }

  if (!$post_id || get_post_status($post_id) === false) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      kwik_ai_log('Kwik AI: Invalid post ID');
    }
    wp_send_json_error(__('Invalid post ID.', KWIK_AI_DOMAIN));
  }

  $max_words = isset($_POST['max_words']) ? absint($_POST['max_words']) : 35;
  $max_words = max(20, min(80, $max_words));

  $tone = isset($_POST['tone']) ? sanitize_key(wp_unslash($_POST['tone'])) : 'straight';
  if (!in_array($tone, array('technical', 'straight', 'excerpt', 'pithy'), true)) {
    $tone = 'straight';
  }

  // Generate an excerpt from the post's title and text content.
  $excerpt = kwik_ai_excerpt_generate_for_post($post_id, $max_words, $tone);

  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Generated excerpt: ' . substr((string) $excerpt, 0, 100) . '...');
  }

  if ($excerpt === false) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      kwik_ai_log('Kwik AI: Excerpt generation failed');
    }
    wp_send_json_error(__('Failed to generate excerpt. Please check your AI provider connection and try again.', KWIK_AI_DOMAIN));
  }

  if (empty($excerpt)) {
    kwik_ai_log('Kwik AI: No excerpt generated');
    wp_send_json_error(__('No excerpt was generated. Try adding more content to the post first.', KWIK_AI_DOMAIN));
  }

  // Sanitize AI-generated content before returning it to the editor.
  $sanitized_excerpt = sanitize_textarea_field($excerpt);

  kwik_ai_log('Kwik AI: Sending success response');
  // Keep the 'description' key for backward compatibility with the admin JS.
  wp_send_json_success(['description' => $sanitized_excerpt]);
}

/**
 * AJAX handler for applying description
 */
function kwik_ai_description_ajax_apply()
{
  check_ajax_referer('kwik_ai_description_ajax', 'nonce');

  if (!current_user_can('edit_posts')) {
    wp_die(esc_html__('You do not have sufficient permissions.', KWIK_AI_DOMAIN));
  }

  if (!isset($_POST['post_id']) || !isset($_POST['description'])) {
    wp_send_json_error(__('Missing required fields.', KWIK_AI_DOMAIN));
  }

  $post_id = intval($_POST['post_id']);
  $description = sanitize_textarea_field(wp_unslash($_POST['description']));

  if (!$post_id || get_post_status($post_id) === false) {
    wp_send_json_error(__('Invalid post ID.', KWIK_AI_DOMAIN));
  }

  if (!$description) {
    wp_send_json_error(__('No description provided.', KWIK_AI_DOMAIN));
  }

  // Description will be inserted into the editor by JavaScript
  // No need to modify post_content here to avoid duplication

  wp_send_json_success(__('Description ready to be inserted into the editor!', KWIK_AI_DOMAIN));
}

/**
 * Read provider connection parameters from the request.
 *
 * Falls back to saved options when a value isn't supplied, so the same handler
 * works both from the live settings form (unsaved values) and elsewhere.
 *
 * @return array{0:string,1:string,2:string} [provider, endpoint, api_key]
 */
function kwik_ai_tags_get_connection_params_from_request()
{
  // The nonce is verified by the calling AJAX handler via check_ajax_referer().
  // phpcs:disable WordPress.Security.NonceVerification.Missing
  $allowed = array('custom', 'openrouter', 'openai');

  $provider = isset($_POST['provider'])
    ? sanitize_text_field(wp_unslash($_POST['provider']))
    : get_option('kwik_ai_ai_provider', 'custom');
  if (!in_array($provider, $allowed, true)) {
    $provider = 'custom';
  }

  if (isset($_POST['endpoint'])) {
    $endpoint = trim(esc_url_raw(wp_unslash($_POST['endpoint'])));
  } else {
    $endpoint = get_option('kwik_ai_api_endpoint', '');
  }

  if (isset($_POST['api_key'])) {
    $api_key = sanitize_text_field(wp_unslash($_POST['api_key']));
  } else {
    $api_key = kwik_ai_tags_get_provider_api_key($provider);
  }

  // phpcs:enable WordPress.Security.NonceVerification.Missing
  return array($provider, $endpoint, $api_key);
}

/**
 * AJAX handler for fetching available models from the configured provider.
 *
 * Accepts live provider/endpoint/api_key values from the settings form so the
 * model list can be populated without saving first.
 */
function kwik_ai_tags_ajax_fetch_models()
{
  check_ajax_referer('kwik_ai_tags_ajax', 'nonce');

  if (!current_user_can('manage_options')) {
    wp_send_json_error(__('You do not have sufficient permissions.', KWIK_AI_DOMAIN));
  }

  list($provider, $endpoint, $api_key) = kwik_ai_tags_get_connection_params_from_request();

  $models = kwik_ai_tags_fetch_models_live($provider, $endpoint, $api_key);

  // Fall back to a curated list for hosted providers when the live list
  // can't be retrieved.
  if ((!is_array($models) || empty($models)) && ($provider === 'openrouter' || $provider === 'openai')) {
    $models = kwik_ai_tags_get_provider_models($provider);
  }

  if (!is_array($models) || empty($models)) {
    wp_send_json_error(__('Failed to fetch models. Please check your endpoint URL and API key.', KWIK_AI_DOMAIN));
  }

  wp_send_json_success(array(
    'models' => $models,
    'selected' => get_option('kwik_ai_model', 'gemma3:27b'),
  ));
}

/**
 * AJAX handler for testing the provider connection from the settings form.
 *
 * Uses the values currently entered in the form (provider, endpoint, API key)
 * so the connection can be verified without saving.
 */
function kwik_ai_tags_ajax_test_connection()
{
  check_ajax_referer('kwik_ai_tags_ajax', 'nonce');

  if (!current_user_can('manage_options')) {
    wp_send_json_error(__('You do not have sufficient permissions.', KWIK_AI_DOMAIN));
  }

  list($provider, $endpoint, $api_key) = kwik_ai_tags_get_connection_params_from_request();
  $model = isset($_POST['model']) ? sanitize_text_field(wp_unslash($_POST['model'])) : '';

  if (($provider === 'openrouter' || $provider === 'openai') && empty($api_key)) {
    wp_send_json_error(__('An API key is required for this provider.', KWIK_AI_DOMAIN));
  }

  $models = kwik_ai_tags_fetch_models_live($provider, $endpoint, $api_key);

  if (!is_array($models) || empty($models)) {
    wp_send_json_error(__('Could not connect. Please check your endpoint URL and API key.', KWIK_AI_DOMAIN));
  }

  // Verify the selected model is actually offered by the provider.
  $model_found = false;
  if ($model !== '') {
    foreach ($models as $available) {
      if ($available['name'] === $model) {
        $model_found = true;
        break;
      }
    }
  }

  $count = count($models);
  $message = sprintf(
    /* translators: %d: number of available models */
    _n('Connected — %d model available.', 'Connected — %d models available.', $count, KWIK_AI_DOMAIN),
    $count
  );

  if ($model !== '' && !$model_found) {
    $message .= ' ' . sprintf(
      /* translators: %s: model name */
      __('Note: the selected model "%s" was not found in the list.', KWIK_AI_DOMAIN),
      $model
    );
  }

  wp_send_json_success(array(
    'message' => $message,
    'count' => $count,
    'models' => $models,
    'selected' => $model,
    'model_found' => $model_found,
  ));
}

/**
 * AJAX handler: generate an image prompt from the post for review.
 *
 * Returns the prompt text (without contacting FAL) so the editor can see and
 * edit it before generating an image. Also reports whether the prompt came from
 * the text AI ('ai') or the verbatim-excerpt fallback ('fallback'), which helps
 * diagnose an unreachable text AI provider.
 */
function kwik_ai_featured_image_ajax_prompt()
{
  check_ajax_referer('kwik_ai_featured_image_ajax', 'nonce');

  if (!current_user_can('edit_posts')) {
    wp_die(esc_html__('You do not have sufficient permissions.', KWIK_AI_DOMAIN));
  }

  // Rate limit prompt generation since it calls the text AI provider.
  if (!kwik_ai_check_rate_limit('kwik_ai_featured_image_prompt', 10)) {
    wp_send_json_error(__('Please wait a moment before generating another prompt.', KWIK_AI_DOMAIN));
  }

  if (!isset($_POST['post_id'])) {
    wp_send_json_error(__('Missing post ID.', KWIK_AI_DOMAIN));
  }
  $post_id = intval($_POST['post_id']);

  if (!$post_id || get_post_status($post_id) === false) {
    wp_send_json_error(__('Invalid post ID.', KWIK_AI_DOMAIN));
  }

  if (!current_user_can('edit_post', $post_id)) {
    wp_send_json_error(__('You cannot edit this post.', KWIK_AI_DOMAIN));
  }

  $guidance = isset($_POST['guidance'])
    ? sanitize_textarea_field(wp_unslash($_POST['guidance']))
    : '';

  $result = kwik_ai_featured_image_generate_prompt($post_id, $guidance);

  if (empty($result['prompt'])) {
    wp_send_json_error(__('Could not build a prompt. Add a title or content to the post.', KWIK_AI_DOMAIN));
  }

  wp_send_json_success(array(
    'prompt'   => $result['prompt'],
    'source'   => $result['source'],
    'provider' => $result['provider'],
  ));
}

/**
 * AJAX handler: submit a FAL.AI featured image generation job.
 *
 * Sends the supplied (reviewed/edited) prompt to FAL's queue and returns the
 * request_id the browser then polls with kwik_ai_featured_image_ajax_status().
 */
function kwik_ai_featured_image_ajax_generate()
{
  check_ajax_referer('kwik_ai_featured_image_ajax', 'nonce');

  if (!current_user_can('edit_posts')) {
    wp_die(esc_html__('You do not have sufficient permissions.', KWIK_AI_DOMAIN));
  }

  // Rate limit job submission (not status polls) to avoid hammering FAL.
  if (!kwik_ai_check_rate_limit('kwik_ai_featured_image_generate', 15)) {
    wp_send_json_error(__('Please wait a moment before generating another image.', KWIK_AI_DOMAIN));
  }

  if (!isset($_POST['post_id'])) {
    wp_send_json_error(__('Missing post ID.', KWIK_AI_DOMAIN));
  }
  $post_id = intval($_POST['post_id']);

  if (!$post_id || get_post_status($post_id) === false) {
    wp_send_json_error(__('Invalid post ID.', KWIK_AI_DOMAIN));
  }

  if (!current_user_can('edit_post', $post_id)) {
    wp_send_json_error(__('You cannot edit this post.', KWIK_AI_DOMAIN));
  }

  $prompt = isset($_POST['prompt'])
    ? sanitize_textarea_field(wp_unslash($_POST['prompt']))
    : '';

  if (trim($prompt) === '') {
    wp_send_json_error(__('Please generate or enter a prompt before creating an image.', KWIK_AI_DOMAIN));
  }

  $result = kwik_ai_featured_image_submit($post_id, $prompt);

  if (is_wp_error($result)) {
    wp_send_json_error($result->get_error_message());
  }

  wp_send_json_success(array(
    'request_id' => $result['request_id'],
    'prompt'     => $result['prompt'],
  ));
}

/**
 * AJAX handler: poll the status of a queued FAL.AI image job.
 *
 * Looks up the job's tracking URLs from the transient stored at submit time
 * (verifying ownership) so the browser never supplies FAL URLs directly.
 */
function kwik_ai_featured_image_ajax_status()
{
  check_ajax_referer('kwik_ai_featured_image_ajax', 'nonce');

  if (!current_user_can('edit_posts')) {
    wp_die(esc_html__('You do not have sufficient permissions.', KWIK_AI_DOMAIN));
  }

  if (empty($_POST['request_id'])) {
    wp_send_json_error(__('Missing request ID.', KWIK_AI_DOMAIN));
  }
  $request_id = sanitize_text_field(wp_unslash($_POST['request_id']));

  $transient_key = kwik_ai_featured_image_transient_key($request_id);
  $job = get_transient($transient_key);

  if (!is_array($job) || (int) $job['user_id'] !== get_current_user_id()) {
    wp_send_json_error(__('This image request could not be found. Please generate again.', KWIK_AI_DOMAIN));
  }

  $status = kwik_ai_fal_check_status($job['status_url']);
  if (is_wp_error($status)) {
    wp_send_json_error($status->get_error_message());
  }

  if ($status !== 'COMPLETED') {
    wp_send_json_success(array(
      'status' => 'pending',
      'state'  => $status,
    ));
  }

  $image_url = kwik_ai_fal_get_result($job['response_url']);
  if (is_wp_error($image_url)) {
    wp_send_json_error($image_url->get_error_message());
  }

  // Persist the resolved image URL so the apply step doesn't trust client input.
  $job['image_url'] = $image_url;
  set_transient($transient_key, $job, KWIK_AI_FAL_REQUEST_TTL);

  wp_send_json_success(array(
    'status'    => 'completed',
    'image_url' => $image_url,
    'prompt'    => $job['prompt'],
  ));
}

/**
 * AJAX handler: sideload the generated image and set it as the featured image.
 *
 * Uses the image URL stored server-side for the request rather than a
 * client-supplied URL.
 */
function kwik_ai_featured_image_ajax_apply()
{
  check_ajax_referer('kwik_ai_featured_image_ajax', 'nonce');

  if (!current_user_can('edit_posts') || !current_user_can('upload_files')) {
    wp_die(esc_html__('You do not have sufficient permissions.', KWIK_AI_DOMAIN));
  }

  if (empty($_POST['request_id'])) {
    wp_send_json_error(__('Missing request ID.', KWIK_AI_DOMAIN));
  }
  $request_id = sanitize_text_field(wp_unslash($_POST['request_id']));

  $transient_key = kwik_ai_featured_image_transient_key($request_id);
  $job = get_transient($transient_key);

  if (!is_array($job) || (int) $job['user_id'] !== get_current_user_id()) {
    wp_send_json_error(__('This image request could not be found. Please generate again.', KWIK_AI_DOMAIN));
  }

  $post_id = (int) $job['post_id'];
  if (!$post_id || !current_user_can('edit_post', $post_id)) {
    wp_send_json_error(__('You cannot edit this post.', KWIK_AI_DOMAIN));
  }

  if (empty($job['image_url'])) {
    wp_send_json_error(__('The image is not ready yet. Please wait for generation to finish.', KWIK_AI_DOMAIN));
  }

  $attachment_id = kwik_ai_featured_image_set_as_thumbnail($post_id, $job['image_url']);
  if (is_wp_error($attachment_id)) {
    wp_send_json_error($attachment_id->get_error_message());
  }

  // The job is consumed once applied.
  delete_transient($transient_key);

  wp_send_json_success(array(
    'attachment_id' => $attachment_id,
    'thumbnail'     => wp_get_attachment_image_url($attachment_id, 'medium'),
    'message'       => __('Featured image set!', KWIK_AI_DOMAIN),
  ));
}
