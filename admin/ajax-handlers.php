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
    wp_send_json_error(__('Failed to generate tags. Please check if Ollama is running and try again.', KWIK_AI_DOMAIN));
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

  // Get URLs if provided -- validate and sanitize each URL
  $urls = [];
  if (isset($_POST['urls']) && is_array($_POST['urls'])) {
    $raw_urls = array_map('sanitize_text_field', wp_unslash($_POST['urls']));
    foreach ($raw_urls as $url) {
      $sanitized = esc_url_raw(trim($url));
      if (filter_var($sanitized, FILTER_VALIDATE_URL)) {
        $urls[] = $sanitized;
      }
    }
  }

  // Get word count parameters if provided
  $min_words = 50;
  $max_words = 200;
  if (isset($_POST['min_words']) && is_numeric($_POST['min_words'])) {
    $min_words = max(50, intval($_POST['min_words'])); // Ensure minimum of 50
  }
  if (isset($_POST['max_words']) && is_numeric($_POST['max_words'])) {
    $max_words = max($min_words, intval($_POST['max_words'])); // Ensure max is not less than min
  }

  // Check if post has images
  $attachments = get_attached_media('image', $post_id);
  $post_content = get_post_field('post_content', $post_id);
  $block_images = kwik_ai_tags_extract_block_images($post_content, $post_id);
  $total_images = count($attachments) + count($block_images);

  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Found ' . count($attachments) . ' attached images, ' . count($block_images) . ' block images');
  }

  // Check if we have either images or URLs
  if ($total_images === 0 && empty($urls)) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      kwik_ai_log('Kwik AI: No images or URLs found for description generation');
    }
    wp_send_json_error(__('Please add images or provide URLs to generate a description.', KWIK_AI_DOMAIN));
  }

  // If URLs are provided, use the new URL-based generation
  if (!empty($urls)) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      kwik_ai_log('Kwik AI: Generating description from URLs: ' . print_r($urls, true));
    }
    $description = kwik_ai_description_generate_from_urls($post_id, $urls, $min_words, $max_words);
  } else {
    // Fall back to image-based generation
    if (defined('WP_DEBUG') && WP_DEBUG) {
      kwik_ai_log('Kwik AI: Calling kwik_ai_description_generate_for_post');
    }
    $description = kwik_ai_description_generate_for_post($post_id, $min_words, $max_words);
  }

  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Generated description: ' . substr($description, 0, 100) . '...');
  }

  if ($description === false) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      kwik_ai_log('Kwik AI: Description generation failed');
    }
    wp_send_json_error(__('Failed to generate description. Please check if Ollama is running and try again.', KWIK_AI_DOMAIN));
  }

  if (empty($description)) {
    kwik_ai_log('Kwik AI: No description generated');
    wp_send_json_error(__('No description was generated. Try adding more descriptive images or URLs.', KWIK_AI_DOMAIN));
  }

  // Sanitize AI-generated content before storing in block attributes (LL-2)
  // Strip all HTML tags to prevent stored XSS via AI-generated content
  $sanitized_description = sanitize_textarea_field($description);

  kwik_ai_log('Kwik AI: Sending success response');
  wp_send_json_success(['description' => $sanitized_description]);
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
 * AJAX handler for fetching available Ollama models
 */
function kwik_ai_tags_ajax_fetch_models()
{
  check_ajax_referer('kwik_ai_tags_ajax', 'nonce');

  if (!current_user_can('manage_options')) {
    wp_send_json_error(__('You do not have sufficient permissions.', KWIK_AI_DOMAIN));
  }

  $models = kwik_ai_tags_fetch_ollama_models();

  if ($models === false) {
    wp_send_json_error(__('Failed to fetch models from Ollama server. Please check your connection settings.', KWIK_AI_DOMAIN));
  }

  wp_send_json_success(array(
    'models' => $models,
    'selected' => get_option('kwik_ai_tags_ollama_model', 'gemma3:27b')
  ));
}
