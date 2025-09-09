<?php
/**
 * Plugin Name: Kwik AI
 * Description: Auto‑tag posts with the Gemma3:27b model via Ollama. Analyzes both images (including block images and custom TRB belt gallery blocks) and text content (50+ words) with preview functionality. Also generates AI descriptions for belt posts based on attached images and appends them to post_content. Configure which post types to enable via Settings > KWIK AI.
 * Version: 2.6
 * Author: Your Name
 * Text Domain: kwik-ai-tags
 *
 * This plugin requires:
 *   • WordPress 5.6+ (any recent LTS)
 *   • Ollama running on http://localhost:11434
 *   • gemma3:27b model pulled (`ollama pull gemma3:27b`)
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

// Include debug test if enabled
if (defined('WP_DEBUG') && WP_DEBUG) {
  include_once __DIR__ . '/debug-test.php';
}



/**
 * Constants
 */
define('KWIK_AI_OLLAMA_HOST', 'http://localhost:11434'); // Change if Ollama runs elsewhere.
define('KWIK_AI_MAX_TAGS', 8); // Max tags to apply per post (increased for text+image)
define('KWIK_AI_MIN_WORDS', 50); // Minimum words required for text analysis
define('KWIK_AI_MAX_DESCRIPTION_LENGTH', 2000); // Maximum length for generated descriptions
define('KWIK_AI_DOMAIN', 'kwik-ai');


/**
 * Initialize the plugin
 */
add_action('init', 'kwik_ai_tags_init');
function kwik_ai_tags_init()
{
  error_log('Kwik AI: Plugin initializing');

  // Add meta box for AI tags preview
  add_action('add_meta_boxes', 'kwik_ai_tags_add_meta_box');
  add_action('add_meta_boxes', 'kwik_ai_description_add_meta_box');

  // Enqueue scripts and styles
  add_action('admin_enqueue_scripts', 'kwik_ai_tags_enqueue_scripts');

  // AJAX handlers
  add_action('wp_ajax_kwik_ai_tags_generate', 'kwik_ai_tags_ajax_generate');
  add_action('wp_ajax_kwik_ai_tags_apply', 'kwik_ai_tags_ajax_apply');
  add_action('wp_ajax_kwik_ai_description_generate', 'kwik_ai_description_ajax_generate');
  add_action('wp_ajax_kwik_ai_description_apply', 'kwik_ai_description_ajax_apply');

  // Add admin menu
  add_action('admin_menu', 'kwik_ai_tags_add_admin_menu');

  // Initialize settings
  add_action('admin_init', 'kwik_ai_tags_settings_init');

  // Add settings link to plugin page
  add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'kwik_ai_tags_add_settings_link');

  error_log('Kwik AI: Plugin initialized with hooks');
}

/**
 * Add settings link to plugin page
 */
function kwik_ai_tags_add_settings_link($links)
{
  $settings_link = sprintf(
    '<a href="%s">%s</a>',
    esc_url(admin_url('options-general.php?page=kwik-ai-tags-settings')),
    esc_html__('Settings', KWIK_AI_DOMAIN)
  );
  
  array_unshift($links, $settings_link);
  return $links;
}

/**
 * Get enabled post types from settings
 * 
 * @return array Array of enabled post types
 */
function kwik_ai_tags_get_enabled_post_types()
{
  $default_post_types = array('post', 'belt');
  $enabled_post_types = get_option('kwik_ai_tags_enabled_post_types', $default_post_types);
  
  // Ensure it's always an array
  if (!is_array($enabled_post_types)) {
    $enabled_post_types = $default_post_types;
  }
  
  // Remove any post types that no longer exist
  $valid_post_types = array();
  foreach ($enabled_post_types as $post_type) {
    if (post_type_exists($post_type)) {
      $valid_post_types[] = $post_type;
    }
  }
  
  // If no valid post types remain, fall back to default
  if (empty($valid_post_types)) {
    $valid_post_types = $default_post_types;
  }
  
  return $valid_post_types;
}

/**
 * Add meta box to post editor
 */
function kwik_ai_tags_add_meta_box()
{
  error_log('Kwik AI: Adding meta box');

  $enabled_post_types = kwik_ai_tags_get_enabled_post_types();
  
  foreach ($enabled_post_types as $post_type) {
    add_meta_box(
      'kwik-ai-tags-preview-box',
      __('AI Tags', KWIK_AI_DOMAIN),
      'kwik_ai_tags_meta_box_callback',
      $post_type,
      'side',
      'high'
    );
  }

  error_log('Kwik AI: Meta box added for post types: ' . implode(', ', $enabled_post_types));
}

/**
 * Add meta box for AI description generation
 */
function kwik_ai_description_add_meta_box()
{
  error_log('Kwik AI: Adding description meta box');

  $enabled_post_types = kwik_ai_tags_get_enabled_post_types();
  
  foreach ($enabled_post_types as $post_type) {
    add_meta_box(
      'kwik-ai-description-preview-box',
      __('AI Description', KWIK_AI_DOMAIN),
      'kwik_ai_description_meta_box_callback',
      $post_type,
      'side',
      'high'
    );
  }

  error_log('Kwik AI: Description meta box added for post types: ' . implode(', ', $enabled_post_types));
}

/**
 * Meta box callback function
 */
function kwik_ai_tags_meta_box_callback($post)
{
  error_log('Kwik AI: Meta box callback called for post ' . $post->ID);

  wp_nonce_field('kwik_ai_tags_meta_box', 'kwik_ai_tags_nonce');
  ?>
  <div id="kwik-ai-tags-container">
    <p>
      <button type="button" id="kwik-ai-tags-generate" class="button button-secondary">
        <?php esc_html_e('Generate AI Tags', KWIK_AI_DOMAIN); ?>
      </button>
    </p>

    <div id="kwik-ai-tags-loading" style="display: none;">
      <p><?php esc_html_e('Analyzing content...', KWIK_AI_DOMAIN); ?></p>
      <div class="kwik-ai-tags-spinner"></div>
    </div>

    <div id="kwik-ai-tags-preview" style="display: none;">
      <h4><?php esc_html_e('Suggested Tags:', KWIK_AI_DOMAIN); ?></h4>
      <div id="kwik-ai-tags-list"></div>
      <p>
        <button type="button" id="kwik-ai-tags-apply" class="button button-primary">
          <?php esc_html_e('Apply Tags', KWIK_AI_DOMAIN); ?>
        </button>
        <button type="button" id="kwik-ai-tags-regenerate" class="button button-secondary">
          <?php esc_html_e('Regenerate', KWIK_AI_DOMAIN); ?>
        </button>
      </p>
    </div>

    <div id="kwik-ai-tags-error" style="display: none;">
      <p class="error-message"></p>
    </div>

    <?php if (WP_DEBUG): ?>
      <div id="kwik-ai-tags-debug" style="margin-top: 10px; font-size: 11px; color: #666;">
        <strong>Debug Info:</strong><br>
        Post ID: <?php echo esc_html($post->ID); ?><br>
        Ajax URL: <?php echo esc_html(admin_url('admin-ajax.php')); ?><br>
        Ollama Host: <?php echo esc_html(KWIK_AI_OLLAMA_HOST); ?><br>
        Attached Images: <?php
        $attachments = get_attached_media('image', $post->ID);
        echo esc_html(count($attachments));
        ?><br>
        Block Images: <?php
        $content = get_post_field('post_content', $post->ID);
        $block_images = kwik_ai_tags_extract_block_images($content, $post->ID);
        echo esc_html(count($block_images));
        
        // Check for TRB belt gallery blocks specifically
        $trb_belt_gallery_count = 0;
        $trb_belt_gallery_images = 0;
        if (has_blocks($content)) {
          $blocks = parse_blocks($content);
          foreach ($blocks as $block) {
            if ($block['blockName'] === 'trb/belt-gallery') {
              $trb_belt_gallery_count++;
              if (isset($block['attrs']['images']) && is_array($block['attrs']['images'])) {
                foreach ($block['attrs']['images'] as $image) {
                  if (isset($image['id']) || (isset($image['url']) && !preg_match('/\.(mp4|webm|ogg|mov|avi)$/i', $image['url']))) {
                    $trb_belt_gallery_images++;
                  }
                }
              }
            }
          }
        }
        
        if ($trb_belt_gallery_count > 0) {
          echo '<br>TRB Belt Galleries: ' . esc_html($trb_belt_gallery_count) . ' (with ' . esc_html($trb_belt_gallery_images) . ' images)';
        }
        ?><br>
        Total Images: <?php
        $image_urls = [];
        foreach ($attachments as $attachment) {
          $url = wp_get_attachment_url($attachment->ID);
          if ($url)
            $image_urls[] = $url;
        }
        $image_urls = array_merge($image_urls, $block_images);
        $image_urls = array_unique($image_urls);
        $image_urls = kwik_ai_tags_deduplicate_sized_images($image_urls);
        echo esc_html(count($image_urls));
        ?><br>
        Word Count: <?php
        $word_count = str_word_count(wp_strip_all_tags($content));
        echo esc_html($word_count);
        echo $word_count >= KWIK_AI_MIN_WORDS ? ' (✓ text analysis enabled)' : ' (text analysis disabled)';
        ?>
      </div>
    <?php endif; ?>
  </div>
  <?php

  error_log('Kwik AI: Meta box HTML rendered');
}

/**
 * Description meta box callback function
 */
function kwik_ai_description_meta_box_callback($post)
{
  error_log('Kwik AI: Description meta box callback called for post ' . $post->ID);

  wp_nonce_field('kwik_ai_description_meta_box', 'kwik_ai_description_nonce');
  
  // Get existing description
  $existing_description = kwik_ai_get_description($post->ID);
  ?>
  <div id="kwik-ai-description-container">
    <?php if (!empty($existing_description)): ?>
      <div id="kwik-ai-description-current" style="margin-bottom: 15px; padding: 10px; background: #e8f4fd; border: 1px solid #4f94d4; border-radius: 3px;">
        <h4 style="margin: 0 0 8px 0; color: #1d2327;"><?php esc_html_e('Current AI Description:', KWIK_AI_DOMAIN); ?></h4>
        <div style="font-size: 13px; line-height: 1.4; white-space: pre-wrap;"><?php echo esc_html($existing_description); ?></div>
      </div>
    <?php endif; ?>
    
    <p>
      <button type="button" id="kwik-ai-description-generate" class="button button-secondary">
        <?php esc_html_e('Generate AI Description', KWIK_AI_DOMAIN); ?>
      </button>
    </p>

    <div id="kwik-ai-description-loading" style="display: none;">
      <p><?php esc_html_e('Analyzing content...', KWIK_AI_DOMAIN); ?></p>
      <div class="kwik-ai-tags-spinner"></div>
    </div>

    <div id="kwik-ai-description-preview" style="display: none;">
      <h4><?php esc_html_e('Generated Description:', KWIK_AI_DOMAIN); ?></h4>
      <div id="kwik-ai-description-text" style="margin-bottom: 15px; padding: 10px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 3px; font-size: 13px; line-height: 1.4;"></div>
      <p>
        <button type="button" id="kwik-ai-description-apply" class="button button-primary">
          <?php esc_html_e('Apply', KWIK_AI_DOMAIN); ?>
        </button>
        <button type="button" id="kwik-ai-description-regenerate" class="button button-secondary">
          <?php esc_html_e('Regenerate', KWIK_AI_DOMAIN); ?>
        </button>
      </p>
    </div>

    <div id="kwik-ai-description-error" style="display: none;">
      <p class="error-message"></p>
    </div>

    <?php if (WP_DEBUG): ?>
      <div id="kwik-ai-description-debug" style="margin-top: 10px; font-size: 11px; color: #666;">
        <strong>Description Debug Info:</strong><br>
        Post ID: <?php echo esc_html($post->ID); ?><br>
        Ajax URL: <?php echo esc_html(admin_url('admin-ajax.php')); ?><br>
        Current Description: <?php 
        $current_desc = kwik_ai_get_description($post->ID);
        echo $current_desc ? esc_html('Yes (' . strlen($current_desc) . ' chars)') : 'No'; 
        ?><br>
        Attached Images: <?php
        $attachments = get_attached_media('image', $post->ID);
        echo esc_html(count($attachments));
        ?><br>
        Block Images: <?php
        $content = get_post_field('post_content', $post->ID);
        $block_images = kwik_ai_tags_extract_block_images($content, $post->ID);
        echo esc_html(count($block_images));
        ?><br>
        Total Images: <?php
        $image_urls = [];
        foreach ($attachments as $attachment) {
          $url = wp_get_attachment_url($attachment->ID);
          if ($url)
            $image_urls[] = $url;
        }
        $image_urls = array_merge($image_urls, $block_images);
        $image_urls = array_unique($image_urls);
        $image_urls = kwik_ai_tags_deduplicate_sized_images($image_urls);
        echo esc_html(count($image_urls));
        ?>
      </div>
    <?php endif; ?>
  </div>
  <?php

  error_log('Kwik AI: Description meta box HTML rendered');
}

/**
 * Enqueue scripts and styles for the admin
 */
function kwik_ai_tags_enqueue_scripts($hook)
{
  $enabled_post_types = kwik_ai_tags_get_enabled_post_types();
  
  // Debug logging
  error_log('Kwik AI: Hook = ' . $hook);

  if ($hook !== 'post.php' && $hook !== 'post-new.php') {
    error_log('Kwik AI: Wrong hook, returning');
    return;
  }

  $screen = get_current_screen();
  error_log('Kwik AI: Screen post_type = ' . $screen->post_type);

  if (!in_array($screen->post_type, $enabled_post_types)) {
    error_log('Kwik AI: Post type not enabled, returning');
    return;
  }

  $js_url = plugin_dir_url(__FILE__) . 'assets/admin.js';
  $css_url = plugin_dir_url(__FILE__) . 'assets/admin.css';

  error_log('Kwik AI: Enqueueing JS from: ' . $js_url);
  error_log('Kwik AI: Enqueueing CSS from: ' . $css_url);

  wp_enqueue_script(
    'kwik-ai-tags-admin',
    $js_url,
    ['jquery'],
    '2.6',
    true
  );

  wp_enqueue_style(
    'kwik-ai-tags-admin',
    $css_url,
    [],
    '2.6'
  );

  $post_id = get_the_ID();
  error_log('Kwik AI: Post ID = ' . $post_id);

  wp_localize_script('kwik-ai-tags-admin', 'kwikAiTags', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('kwik_ai_tags_ajax'),
    'postId' => $post_id,
    'debug' => WP_DEBUG,
    'strings' => [
      'error' => __('An error occurred. Please try again.', KWIK_AI_DOMAIN),
      'noContent' => __('No images or insufficient text found. Please add images or write at least 50 words.', KWIK_AI_DOMAIN),
      'success' => __('Tags applied successfully!', KWIK_AI_DOMAIN),
    ]
  ]);

  wp_localize_script('kwik-ai-tags-admin', 'kwikAiDescription', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('kwik_ai_description_ajax'),
    'postId' => $post_id,
    'debug' => WP_DEBUG,
    'strings' => [
      'error' => __('An error occurred. Please try again.', KWIK_AI_DOMAIN),
      'noContent' => __('No images found. Please add images to generate a description.', KWIK_AI_DOMAIN),
      'success' => __('Description applied successfully!', KWIK_AI_DOMAIN),
    ]
  ]);

  error_log('Kwik AI: Scripts and styles enqueued successfully');
}

/**
 * AJAX handler for generating tags
 */
function kwik_ai_tags_ajax_generate()
{
  error_log('Kwik AI: Generate AJAX called');
  error_log('Kwik AI: POST data: ' . print_r($_POST, true));

  check_ajax_referer('kwik_ai_tags_ajax', 'nonce');

  if (!current_user_can('edit_posts')) {
    error_log('Kwik AI: User does not have edit_posts capability');
    wp_die(__('You do not have sufficient permissions.', KWIK_AI_DOMAIN));
  }

  $post_id = intval($_POST['post_id']);
  error_log('Kwik AI: Post ID: ' . $post_id);

  if (!$post_id || get_post_status($post_id) === false) {
    error_log('Kwik AI: Invalid post ID');
    wp_send_json_error(__('Invalid post ID.', KWIK_AI_DOMAIN));
  }

  // Check if post has images or sufficient text content
  $attachments = get_attached_media('image', $post_id);
  $post_content = get_post_field('post_content', $post_id);
  $word_count = str_word_count(wp_strip_all_tags($post_content));
  
  // Also check for block images (including TRB belt gallery)
  $block_images = kwik_ai_tags_extract_block_images($post_content, $post_id);
  $total_images = count($attachments) + count($block_images);

  error_log('Kwik AI: Found ' . count($attachments) . ' attached images, ' . count($block_images) . ' block images, and ' . $word_count . ' words');

  if ($total_images === 0 && $word_count < KWIK_AI_MIN_WORDS) {
    error_log('Kwik AI: Insufficient content for analysis');
    wp_send_json_error(__('Please add images or write at least 50 words to generate AI tags.', KWIK_AI_DOMAIN));
  }

  error_log('Kwik AI: Calling kwik_ai_tags_generate_for_post');
  $tags = kwik_ai_tags_generate_for_post($post_id);
  error_log('Kwik AI: Generated tags: ' . print_r($tags, true));

  if ($tags === false) {
    error_log('Kwik AI: Tag generation failed');
    wp_send_json_error(__('Failed to generate tags. Please check if Ollama is running and try again.', KWIK_AI_DOMAIN));
  }

  if (empty($tags)) {
    error_log('Kwik AI: No tags generated');
    wp_send_json_error(__('No tags were generated. Try adding more descriptive images.', KWIK_AI_DOMAIN));
  }

  error_log('Kwik AI: Sending success response');
  wp_send_json_success(['tags' => $tags]);
}

/**
 * AJAX handler for applying tags
 */
function kwik_ai_tags_ajax_apply()
{
  check_ajax_referer('kwik_ai_tags_ajax', 'nonce');

  if (!current_user_can('edit_posts')) {
    wp_die(__('You do not have sufficient permissions.', KWIK_AI_DOMAIN));
  }

  $post_id = intval($_POST['post_id']);
  $tags = sanitize_text_field($_POST['tags']);

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
  error_log('Kwik AI: Description generate AJAX called');
  error_log('Kwik AI: POST data: ' . print_r($_POST, true));

  check_ajax_referer('kwik_ai_description_ajax', 'nonce');

  if (!current_user_can('edit_posts')) {
    error_log('Kwik AI: User does not have edit_posts capability');
    wp_die(__('You do not have sufficient permissions.', KWIK_AI_DOMAIN));
  }

  $post_id = intval($_POST['post_id']);
  error_log('Kwik AI: Post ID: ' . $post_id);

  if (!$post_id || get_post_status($post_id) === false) {
    error_log('Kwik AI: Invalid post ID');
    wp_send_json_error(__('Invalid post ID.', KWIK_AI_DOMAIN));
  }

  // Check if post has images
  $attachments = get_attached_media('image', $post_id);
  $post_content = get_post_field('post_content', $post_id);
  $block_images = kwik_ai_tags_extract_block_images($post_content, $post_id);
  $total_images = count($attachments) + count($block_images);

  error_log('Kwik AI: Found ' . count($attachments) . ' attached images, ' . count($block_images) . ' block images');

  if ($total_images === 0) {
    error_log('Kwik AI: No images found for description generation');
    wp_send_json_error(__('Please add images to generate a description.', KWIK_AI_DOMAIN));
  }

  error_log('Kwik AI: Calling kwik_ai_description_generate_for_post');
  $description = kwik_ai_description_generate_for_post($post_id);
  error_log('Kwik AI: Generated description: ' . substr($description, 0, 100) . '...');

  if ($description === false) {
    error_log('Kwik AI: Description generation failed');
    wp_send_json_error(__('Failed to generate description. Please check if Ollama is running and try again.', KWIK_AI_DOMAIN));
  }

  if (empty($description)) {
    error_log('Kwik AI: No description generated');
    wp_send_json_error(__('No description was generated. Try adding more descriptive images.', KWIK_AI_DOMAIN));
  }

  error_log('Kwik AI: Sending success response');
  wp_send_json_success(['description' => $description]);
}

/**
 * AJAX handler for applying description
 */
function kwik_ai_description_ajax_apply()
{
  check_ajax_referer('kwik_ai_description_ajax', 'nonce');

  if (!current_user_can('edit_posts')) {
    wp_die(__('You do not have sufficient permissions.', KWIK_AI_DOMAIN));
  }

  $post_id = intval($_POST['post_id']);
  $description = sanitize_textarea_field($_POST['description']);

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
 * Get the AI-generated description for a post
 *
 * @param int $post_id
 * @return string
 */
function kwik_ai_get_description($post_id)
{
  $content = get_post_field('post_content', $post_id);

  if (empty($content)) {
    return '';
  }

  // Look for AI generated description between markers
  $description_marker = '<!-- AI Generated Description -->';
  $description_end_marker = '<!-- End AI Generated Description -->';

  $start_pos = strpos($content, $description_marker);
  if ($start_pos === false) {
    return '';
  }

  $start_pos += strlen($description_marker);
  $end_pos = strpos($content, $description_end_marker, $start_pos);

  if ($end_pos === false) {
    return '';
  }

  $description = substr($content, $start_pos, $end_pos - $start_pos);
  return trim($description);
}

/**
 * Generate tags for a specific post from both images and text content
 *
 * @param int $post_id
 * @return array|false
 */
function kwik_ai_tags_generate_for_post(int $post_id)
{
  error_log('Kwik AI: Starting tag generation for post ' . $post_id);

  $all_tags = [];

  /* ----------------------------------------------------- */
  /* 1. Try to get tags from images */
  /* ----------------------------------------------------- */

  // Get both attached images and images from block content
  $image_urls = [];

  // Traditional attached images
  $attachments = get_attached_media('image', $post_id);
  error_log('Kwik AI: Found ' . count($attachments) . ' attached images');

  foreach ($attachments as $attachment) {
    $url = wp_get_attachment_url($attachment->ID);
    if ($url) {
      $image_urls[] = $url;
    }
  }

  // Images from block content (modern WordPress)
  $post_content = get_post_field('post_content', $post_id);
  $block_images = kwik_ai_tags_extract_block_images($post_content, $post_id);
  error_log('Kwik AI: Found ' . count($block_images) . ' block images');

  $image_urls = array_merge($image_urls, $block_images);
  $image_urls = array_unique($image_urls); // Remove duplicates
  $image_urls = kwik_ai_tags_deduplicate_sized_images($image_urls); // Remove sized duplicates

  error_log('Kwik AI: Total unique images: ' . count($image_urls));

  if (!empty($image_urls)) {
    $image_tags = kwik_ai_tags_generate_from_image_urls($post_id, $image_urls);
    if ($image_tags) {
      $all_tags = array_merge($all_tags, $image_tags);
      error_log('Kwik AI: Generated ' . count($image_tags) . ' tags from images: ' . implode(', ', $image_tags));
    }
  }

  /* ----------------------------------------------------- */
  /* 2. Try to get tags from text content */
  /* ----------------------------------------------------- */
  $post_content = get_post_field('post_content', $post_id);
  $word_count = str_word_count(wp_strip_all_tags($post_content));

  error_log('Kwik AI: Post has ' . $word_count . ' words');

  if ($word_count >= KWIK_AI_MIN_WORDS) {
    $text_tags = kwik_ai_tags_generate_from_text($post_id, $post_content);
    if ($text_tags) {
      $all_tags = array_merge($all_tags, $text_tags);
      error_log('Kwik AI: Generated ' . count($text_tags) . ' tags from text: ' . implode(', ', $text_tags));
    }
  }

  /* ----------------------------------------------------- */
  /* 3. Combine, deduplicate, and limit tags */
  /* ----------------------------------------------------- */
  if (empty($all_tags)) {
    error_log('Kwik AI: No tags generated from any source');
    return false;
  }

  // Remove duplicates and limit to max tags
  $unique_tags = array_unique($all_tags);
  $final_tags = array_slice($unique_tags, 0, KWIK_AI_MAX_TAGS);

  error_log('Kwik AI: Final tags (' . count($final_tags) . '): ' . implode(', ', $final_tags));

  return $final_tags;
}

/**
 * Generate description for a specific post from images
 *
 * @param int $post_id
 * @return string|false
 */
function kwik_ai_description_generate_for_post(int $post_id)
{
  error_log('Kwik AI: Starting description generation for post ' . $post_id);

  /* ----------------------------------------------------- */
  /* 1. Get images from post */
  /* ----------------------------------------------------- */

  // Get both attached images and images from block content
  $image_urls = [];

  // Traditional attached images
  $attachments = get_attached_media('image', $post_id);
  error_log('Kwik AI: Found ' . count($attachments) . ' attached images');

  foreach ($attachments as $attachment) {
    $url = wp_get_attachment_url($attachment->ID);
    if ($url) {
      $image_urls[] = $url;
    }
  }

  // Images from block content (modern WordPress)
  $post_content = get_post_field('post_content', $post_id);
  $block_images = kwik_ai_tags_extract_block_images($post_content, $post_id);
  error_log('Kwik AI: Found ' . count($block_images) . ' block images');

  $image_urls = array_merge($image_urls, $block_images);
  $image_urls = array_unique($image_urls); // Remove duplicates
  $image_urls = kwik_ai_tags_deduplicate_sized_images($image_urls); // Remove sized duplicates

  error_log('Kwik AI: Total unique images: ' . count($image_urls));

  if (empty($image_urls)) {
    error_log('Kwik AI: No images found for description generation');
    return false;
  }

  /* ----------------------------------------------------- */
  /* 2. Generate description from images */
  /* ----------------------------------------------------- */
  $description = kwik_ai_description_generate_from_image_urls($post_id, $image_urls);

  if (!$description) {
    error_log('Kwik AI: Failed to generate description from images');
    return false;
  }

  // Limit description length
  if (strlen($description) > KWIK_AI_MAX_DESCRIPTION_LENGTH) {
    $description = substr($description, 0, KWIK_AI_MAX_DESCRIPTION_LENGTH - 3) . '...';
    error_log('Kwik AI: Truncated description to ' . KWIK_AI_MAX_DESCRIPTION_LENGTH . ' characters');
  }

  error_log('Kwik AI: Final description (' . strlen($description) . ' chars): ' . substr($description, 0, 100) . '...');

  return $description;
}

/**
 * Remove duplicate images that are the same image at different sizes
 * For example: image.jpg and image-700x500.jpg are the same image
 *
 * @param array $image_urls Array of image URLs
 * @return array Deduplicated array with only original images
 */
function kwik_ai_tags_deduplicate_sized_images(array $image_urls): array
{
  $unique_images = [];
  $base_names = [];

  foreach ($image_urls as $url) {
    // Extract the base name without size suffix
    $base_name = preg_replace('/-\d+x\d+(\.[a-zA-Z]+)$/', '$1', $url);

    // If we haven't seen this base image before, add it
    if (!in_array($base_name, $base_names)) {
      $base_names[] = $base_name;
      // Prefer the original (non-sized) version if available
      if ($base_name === $url) {
        // This is the original image
        $unique_images[] = $url;
      } else {
        // This is a sized version, but it's the first we've seen of this image
        $unique_images[] = $url;
      }
    } else {
      // We've seen this base image before
      $existing_index = array_search($base_name, $base_names);

      // If the current URL is the original (no size suffix) and the existing one is sized,
      // replace the sized version with the original
      if ($base_name === $url && $unique_images[$existing_index] !== $url) {
        $unique_images[$existing_index] = $url;
      }
    }
  }

  return array_values($unique_images);
}

/**
 * Extract image URLs from WordPress content (blocks and shortcodes)
 *
 * @param string $content Post content
 * @param int $post_id Post ID for fallback gallery handling
 * @return array Array of image URLs found in content
 */
function kwik_ai_tags_extract_block_images(string $content, int $post_id = 0): array
{
  $image_urls = [];

  // First, extract images from gallery shortcodes
  $shortcode_images = kwik_ai_tags_extract_gallery_shortcode_images($content, $post_id);
  $image_urls = array_merge($image_urls, $shortcode_images);

  // Parse blocks if the content contains block markup
  if (has_blocks($content)) {
    $blocks = parse_blocks($content);
    $block_images = kwik_ai_tags_extract_images_from_blocks($blocks);
    $image_urls = array_merge($image_urls, $block_images);
    
    // Enhanced debug logging for TRB belt gallery blocks
    if (WP_DEBUG) {
      $trb_belt_galleries = 0;
      foreach ($blocks as $block) {
        if ($block['blockName'] === 'trb/belt-gallery') {
          $trb_belt_galleries++;
          error_log('Kwik AI: Found TRB belt gallery block #' . $trb_belt_galleries);
          if (isset($block['attrs']['images']) && is_array($block['attrs']['images'])) {
            error_log('Kwik AI: TRB belt gallery has ' . count($block['attrs']['images']) . ' items');
            foreach ($block['attrs']['images'] as $index => $image) {
              $log_data = [];
              if (isset($image['id'])) $log_data[] = 'id=' . $image['id'];
              if (isset($image['url'])) $log_data[] = 'url=' . $image['url'];
              if (isset($image['title'])) $log_data[] = 'title=' . $image['title'];
              error_log('Kwik AI: TRB gallery item ' . $index . ': ' . implode(', ', $log_data));
            }
          }
        }
      }
    }
  } else {
    // Fallback: Extract images from HTML content using regex
    $html_images = kwik_ai_tags_extract_images_from_html($content);
    $image_urls = array_merge($image_urls, $html_images);
  }

  // Remove duplicates and deduplicate sized images
  $image_urls = array_unique($image_urls);
  $image_urls = kwik_ai_tags_deduplicate_sized_images($image_urls);

  error_log('Kwik AI: Extracted ' . count($image_urls) . ' unique images from content (including ' . count($shortcode_images) . ' from gallery shortcodes)');
  return $image_urls;
}

/**
 * Extract image URLs from gallery shortcodes in content
 *
 * @param string $content Post content
 * @param int $post_id Post ID for fallback when no IDs specified
 * @return array Array of image URLs from gallery shortcodes
 */
function kwik_ai_tags_extract_gallery_shortcode_images(string $content, int $post_id = 0): array
{
  $image_urls = [];

  // Use WordPress shortcode regex to find gallery shortcodes
  $shortcode_regex = get_shortcode_regex(['gallery']);
  error_log('Kwik AI: Extracted ' . strlen($shortcode_regex) . ' character shortcode regex');
  preg_match_all('/' . $shortcode_regex . '/s', $content, $matches);

  if (!empty($matches[0])) {
    foreach ($matches[0] as $shortcode_match) {
      // Extract shortcode attributes manually since shortcode_parse_atts expects just attributes
      $atts = [];

      // Extract attributes from the shortcode using regex
      if (preg_match('/\[gallery\s+([^]]+)\]/i', $shortcode_match, $attr_match)) {
        $attr_string = $attr_match[1];

        // Parse individual attributes
        if (preg_match_all('/(\w+)="([^"]*)"/', $attr_string, $attr_matches)) {
          foreach ($attr_matches[1] as $index => $key) {
            $atts[$key] = $attr_matches[2][$index];
          }
        }

        // Also handle attributes without quotes (like numbers)
        if (preg_match_all('/(\w+)=([^"\s]+)/', $attr_string, $attr_matches)) {
          foreach ($attr_matches[1] as $index => $key) {
            if (!isset($atts[$key])) { // Don't overwrite quoted attributes
              $atts[$key] = $attr_matches[2][$index];
            }
          }
        }
      }

      error_log('Kwik AI: Found gallery shortcode with attributes: ' . print_r($atts, true));

      if ($atts) {
        // Check if specific image IDs are provided
        if (isset($atts['ids']) && !empty($atts['ids'])) {
          // Parse comma-separated IDs
          $ids = array_map('intval', array_filter(explode(',', $atts['ids'])));
          error_log('Kwik AI: Gallery shortcode has ' . count($ids) . ' specified IDs');

          foreach ($ids as $attachment_id) {
            $url = wp_get_attachment_url($attachment_id);
            if ($url) {
              // Skip video files (galleries can contain both images and videos)
              if (!preg_match('/\.(mp4|webm|ogg|mov|avi)$/i', $url)) {
                $image_urls[] = $url;
              }
            }
          }

          error_log('Kwik AI: Extracted ' . count($ids) . ' images from gallery shortcode with IDs');
        }
        // If no IDs specified, get all image attachments for the post
        elseif ($post_id > 0) {
          $attachments = get_attached_media('image', $post_id);

          foreach ($attachments as $attachment) {
            $url = wp_get_attachment_url($attachment->ID);
            if ($url) {
              $image_urls[] = $url;
            }
          }

          error_log('Kwik AI: Extracted ' . count($attachments) . ' images from gallery shortcode (all post attachments)');
        }
      }
    }
  }

  return $image_urls;
}

/**
 * Recursively extract image URLs from parsed blocks
 *
 * @param array $blocks Parsed WordPress blocks
 * @return array Array of image URLs
 */
function kwik_ai_tags_extract_images_from_blocks(array $blocks): array
{
  $image_urls = [];

  foreach ($blocks as $block) {
    // Handle Image blocks
    if ($block['blockName'] === 'core/image') {
      if (isset($block['attrs']['id'])) {
        // Image block with attachment ID
        $url = wp_get_attachment_url($block['attrs']['id']);
        if ($url) {
          $image_urls[] = $url;
        }
      } elseif (isset($block['attrs']['url'])) {
        // Image block with direct URL
        $image_urls[] = $block['attrs']['url'];
      }
    }

    // Handle Gallery blocks
    elseif ($block['blockName'] === 'core/gallery') {
      if (isset($block['attrs']['ids']) && is_array($block['attrs']['ids'])) {
        foreach ($block['attrs']['ids'] as $id) {
          $url = wp_get_attachment_url($id);
          if ($url) {
            $image_urls[] = $url;
          }
        }
      }
    }

    // Handle TRB Belt Gallery blocks (custom block)
    elseif ($block['blockName'] === 'trb/belt-gallery') {
      if (isset($block['attrs']['images']) && is_array($block['attrs']['images'])) {
        foreach ($block['attrs']['images'] as $image) {
          // Try to get URL from attachment ID first (most reliable)
          if (isset($image['id']) && !empty($image['id'])) {
            $url = wp_get_attachment_url($image['id']);
            if ($url) {
              // Skip video files (belt gallery can contain both images and videos)
              if (!preg_match('/\.(mp4|webm|ogg|mov|avi)$/i', $url)) {
                $image_urls[] = $url;
              }
            }
          } 
          // Fallback to direct URL if available
          elseif (isset($image['url']) && !empty($image['url'])) {
            // Skip video files (belt gallery can contain both images and videos)
            if (!preg_match('/\.(mp4|webm|ogg|mov|avi)$/i', $image['url'])) {
              $image_urls[] = $image['url'];
            }
          }
        }
      }
    }

    // Handle Media & Text blocks
    elseif ($block['blockName'] === 'core/media-text') {
      if (isset($block['attrs']['mediaId'])) {
        $url = wp_get_attachment_url($block['attrs']['mediaId']);
        if ($url) {
          $image_urls[] = $url;
        }
      } elseif (isset($block['attrs']['mediaUrl'])) {
        $image_urls[] = $block['attrs']['mediaUrl'];
      }
    }

    // Handle Cover blocks
    elseif ($block['blockName'] === 'core/cover') {
      if (isset($block['attrs']['id'])) {
        $url = wp_get_attachment_url($block['attrs']['id']);
        if ($url) {
          $image_urls[] = $url;
        }
      } elseif (isset($block['attrs']['url'])) {
        $image_urls[] = $block['attrs']['url'];
      }
    }

    // Recursively check inner blocks
    if (isset($block['innerBlocks']) && is_array($block['innerBlocks'])) {
      $inner_images = kwik_ai_tags_extract_images_from_blocks($block['innerBlocks']);
      $image_urls = array_merge($image_urls, $inner_images);
    }

    // Fallback: extract images from rendered block content
    if (!empty($block['innerHTML'])) {
      $html_images = kwik_ai_tags_extract_images_from_html($block['innerHTML']);
      $image_urls = array_merge($image_urls, $html_images);
    }
  }

  return $image_urls;
}

/**
 * Extract image URLs from HTML content using regex
 *
 * @param string $html HTML content
 * @return array Array of image URLs
 */
function kwik_ai_tags_extract_images_from_html(string $html): array
{
  $image_urls = [];

  // Match img tags and extract src attributes
  preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches);

  if (!empty($matches[1])) {
    foreach ($matches[1] as $src) {
      // Convert relative URLs to absolute URLs
      if (strpos($src, 'http') !== 0) {
        if (strpos($src, '/') === 0) {
          $src = home_url($src);
        } else {
          $src = home_url('/' . $src);
        }
      }

      // Only include images from the same domain or uploads directory
      if (strpos($src, home_url()) === 0 || strpos($src, wp_upload_dir()['baseurl']) === 0) {
        $image_urls[] = $src;
      }
    }
  }

  return $image_urls;
}

/**
 * Generate tags from image URLs
 *
 * @param int $post_id
 * @param array $image_urls Array of image URLs
 * @return array|false
 */
function kwik_ai_tags_generate_from_image_urls(int $post_id, array $image_urls)
{
  error_log('Kwik AI: Generating tags from ' . count($image_urls) . ' image URLs');

  /* ----------------------------------------------------- */
  /* 1. Convert each image URL to a Base‑64 data URI */
  /* ----------------------------------------------------- */
  $data_uris = [];
  foreach ($image_urls as $image_url) {
    error_log('Kwik AI: Processing image URL: ' . $image_url);

    $data_uri = kwik_ai_tags_image_to_data_uri($image_url);
    if ($data_uri) {
      $data_uris[] = $data_uri;
      error_log('Kwik AI: Successfully converted image to data URI');
    } else {
      error_log('Kwik AI: Failed to convert image to data URI: ' . $image_url);
    }
  }

  error_log('Kwik AI: Converted ' . count($data_uris) . ' images to data URIs');

  if (empty($data_uris)) {
    error_log('Kwik AI: No data URIs generated from image URLs');
    return false;
  }

  /* ----------------------------------------------------- */
  /* 2. Build prompt for images */
  /* ----------------------------------------------------- */
  $prompt = 'Analyze these images and generate up to 5 concise, relevant tags that describe the main subjects, objects, activities, or themes shown. Focus on nouns and descriptive terms. Respond with a comma‑separated list only, no extra text.';
  error_log('Kwik AI: Using image prompt: ' . $prompt);

  /* ----------------------------------------------------- */
  /* 3. Send request to Ollama */
  /* ----------------------------------------------------- */
  error_log('Kwik AI: Sending image request to Ollama');
  $raw_response = kwik_ai_tags_query_ollama($prompt, $data_uris);
  error_log('Kwik AI: Ollama image response: ' . ($raw_response ?: 'NULL'));

  if (!$raw_response) {
    error_log('Kwik AI: No response from Ollama for images');
    return false;
  }

  /* ----------------------------------------------------- */
  /* 4. Parse tags */
  /* ----------------------------------------------------- */
  $tags = kwik_ai_tags_parse_tags($raw_response);
  error_log('Kwik AI: Parsed image tags: ' . print_r($tags, true));

  return $tags;
}

/**
 * Generate tags from post images (legacy function for backward compatibility)
 *
 * @param int $post_id
 * @param array $attachments
 * @return array|false
 */
function kwik_ai_tags_generate_from_images(int $post_id, array $attachments)
{
  error_log('Kwik AI: Generating tags from ' . count($attachments) . ' images (legacy method)');

  $image_urls = [];
  foreach ($attachments as $attachment) {
    $image_url = wp_get_attachment_url($attachment->ID);
    if ($image_url) {
      $image_urls[] = $image_url;
    }
  }

  return kwik_ai_tags_generate_from_image_urls($post_id, $image_urls);
}

/**
 * Generate tags from post text content
 *
 * @param int $post_id
 * @param string $content
 * @return array|false
 */
function kwik_ai_tags_generate_from_text(int $post_id, string $content)
{
  error_log('Kwik AI: Generating tags from text content');

  // Clean and prepare the text
  $clean_text = wp_strip_all_tags($content);
  $clean_text = preg_replace('/\s+/', ' ', $clean_text); // Normalize whitespace
  $clean_text = trim($clean_text);

  // Limit text length to avoid overwhelming the model
  if (strlen($clean_text) > 2000) {
    $clean_text = substr($clean_text, 0, 2000) . '...';
    error_log('Kwik AI: Truncated text to 2000 characters');
  }

  error_log('Kwik AI: Analyzing ' . str_word_count($clean_text) . ' words');

  /* ----------------------------------------------------- */
  /* 1. Build prompt for text analysis */
  /* ----------------------------------------------------- */
  $prompt = 'Analyze this article text and generate up to 5 relevant tags that capture the main topics, themes, subjects, or keywords. Focus on the most important concepts discussed. Respond with a comma‑separated list only, no extra text.

Article text:
' . $clean_text;

  error_log('Kwik AI: Using text prompt (first 200 chars): ' . substr($prompt, 0, 200) . '...');

  /* ----------------------------------------------------- */
  /* 2. Send request to Ollama (text-only, no images) */
  /* ----------------------------------------------------- */
  error_log('Kwik AI: Sending text request to Ollama');
  $raw_response = kwik_ai_tags_query_ollama($prompt, []); // Empty images array
  error_log('Kwik AI: Ollama text response: ' . ($raw_response ?: 'NULL'));

  if (!$raw_response) {
    error_log('Kwik AI: No response from Ollama for text');
    return false;
  }

  /* ----------------------------------------------------- */
  /* 3. Parse tags */
  /* ----------------------------------------------------- */
  $tags = kwik_ai_tags_parse_tags($raw_response);
  error_log('Kwik AI: Parsed text tags: ' . print_r($tags, true));

  return $tags;
}

/**
 * Convert an image URL to a Base‑64 data URI.
 * Multiple methods for better compatibility.
 *
 * @param string $url
 * @return string|null
 */
function kwik_ai_tags_image_to_data_uri(string $url): ?string
{
  error_log('Kwik AI: Converting image to data URI: ' . $url);

  // Method 1: Try WordPress HTTP API first (more reliable)
  $response = wp_remote_get($url, [
    'timeout' => 30,
    'user-agent' => 'WordPress/' . get_bloginfo('version'),
  ]);

  if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
    $image_data = wp_remote_retrieve_body($response);
    $content_type = wp_remote_retrieve_header($response, 'content-type');

    if ($image_data && $content_type) {
      error_log('Kwik AI: Successfully got image via wp_remote_get, type: ' . $content_type);
      // Return just base64 data for Ollama (not full data URI)
      return base64_encode($image_data);
    }
  }

  error_log('Kwik AI: wp_remote_get failed, trying file_get_contents');

  // Method 2: Try file_get_contents with context (original method improved)
  $context = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' => [
        'User-Agent: WordPress/' . get_bloginfo('version'),
        'Accept: image/*',
      ],
      'timeout' => 30,
    ]
  ]);

  $image_data = @file_get_contents($url, false, $context);
  if ($image_data !== false) {
    // Try to determine MIME type
    $mime_type = null;

    // Method 2a: Use finfo if available
    if (function_exists('finfo_open')) {
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      if ($finfo) {
        $mime_type = finfo_buffer($finfo, $image_data);
        finfo_close($finfo);
      }
    }

    // Method 2b: Use getimagesizefromstring if available
    if (!$mime_type && function_exists('getimagesizefromstring')) {
      $image_info = getimagesizefromstring($image_data);
      if ($image_info && isset($image_info['mime'])) {
        $mime_type = $image_info['mime'];
      }
    }

    // Method 2c: Guess from URL extension
    if (!$mime_type) {
      $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
      $mime_map = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        'svg' => 'image/svg+xml',
      ];
      $mime_type = $mime_map[$ext] ?? 'image/jpeg'; // Default to JPEG
    }

    error_log('Kwik AI: Successfully got image via file_get_contents, type: ' . $mime_type);
    // Return just base64 data for Ollama (not full data URI)
    return base64_encode($image_data);
  }

  error_log('Kwik AI: file_get_contents failed, trying cURL');

  // Method 3: Try cURL if available
  if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'WordPress/' . get_bloginfo('version'));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $image_data = curl_exec($ch);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($image_data !== false && $http_code === 200 && $content_type) {
      error_log('Kwik AI: Successfully got image via cURL, type: ' . $content_type);
      // Return just base64 data for Ollama (not full data URI)
      return base64_encode($image_data);
    }
  }

  error_log('Kwik AI: All methods failed for URL: ' . $url);

  // Method 4: If it's a local file path, try direct file access
  if (strpos($url, home_url()) === 0) {
    $file_path = str_replace(home_url(), ABSPATH, $url);
    $file_path = str_replace('//', '/', $file_path);

    error_log('Kwik AI: Trying local file path: ' . $file_path);

    if (file_exists($file_path) && is_readable($file_path)) {
      $image_data = file_get_contents($file_path);
      if ($image_data !== false) {
        $mime_type = mime_content_type($file_path) ?: 'image/jpeg';
        error_log('Kwik AI: Successfully got local file, type: ' . $mime_type);
        // Return just base64 data for Ollama (not full data URI)
        return base64_encode($image_data);
      }
    }
  }

  return null; // All methods failed
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
 * Generate description from image URLs
 *
 * @param int $post_id
 * @param array $image_urls Array of image URLs
 * @return string|false
 */
function kwik_ai_description_generate_from_image_urls(int $post_id, array $image_urls)
{
  error_log('Kwik AI: Generating description from ' . count($image_urls) . ' image URLs');

  /* ----------------------------------------------------- */
  /* 1. Convert each image URL to a Base‑64 data URI */
  /* ----------------------------------------------------- */
  $data_uris = [];
  foreach ($image_urls as $image_url) {
    error_log('Kwik AI: Processing image URL: ' . $image_url);

    $data_uri = kwik_ai_tags_image_to_data_uri($image_url);
    if ($data_uri) {
      $data_uris[] = $data_uri;
      error_log('Kwik AI: Successfully converted image to data URI');
    } else {
      error_log('Kwik AI: Failed to convert image to data URI: ' . $image_url);
    }
  }

  error_log('Kwik AI: Converted ' . count($data_uris) . ' images to data URIs');

  if (empty($data_uris)) {
    error_log('Kwik AI: No data URIs generated from image URLs');
    return false;
  }

  /* ----------------------------------------------------- */
  /* 2. Build prompt for description generation */
  /* ----------------------------------------------------- */
  $post_type = get_post_type($post_id);
  $post_type_obj = get_post_type_object($post_type);
  $post_type_name = $post_type_obj ? $post_type_obj->labels->singular_name : 'post';

  $prompt = 'Analyze these images and generate an engaging description for a ' . $post_type_name . '. Describe this work of art. Write in a natural, descriptive style without overusing adjectives. Avoid uncommon punctuation such as Em dash. Keep the description between 50-200 words. Respond with only the description text, no extra formatting or labels.';
  error_log('Kwik AI: Using description prompt: ' . $prompt);

  /* ----------------------------------------------------- */
  /* 3. Send request to Ollama */
  /* ----------------------------------------------------- */
  error_log('Kwik AI: Sending description request to Ollama');
  $raw_response = kwik_ai_tags_query_ollama($prompt, $data_uris);
  error_log('Kwik AI: Ollama description response: ' . substr($raw_response ?: 'NULL', 0, 200));

  if (!$raw_response) {
    error_log('Kwik AI: No response from Ollama for description');
    return false;
  }

  /* ----------------------------------------------------- */
  /* 4. Clean and return description */
  /* ----------------------------------------------------- */
  $description = trim($raw_response);

  // Remove any unwanted formatting or prefixes
  $description = preg_replace('/^(Description:|Summary:|About this|This image shows?)/i', '', $description);
  $description = trim($description);

  error_log('Kwik AI: Cleaned description: ' . substr($description, 0, 100) . '...');

  return $description;
}

/**
 * Test function to debug image conversion methods
 * Only available when WP_DEBUG is enabled
 */
function kwik_ai_tags_test_image_conversion($url = null)
{
  if (!WP_DEBUG) {
    return 'Debug mode not enabled';
  }

  if (!$url) {
    // Use a test image from the uploads directory
    $upload_dir = wp_upload_dir();
    $test_files = glob($upload_dir['basedir'] . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
    if (empty($test_files)) {
      return 'No test images found in uploads directory';
    }
    $url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $test_files[0]);
  }

  $result = kwik_ai_tags_image_to_data_uri($url);

  if ($result) {
    $data_size = strlen($result);
    return sprintf(
      'Success! Converted %s to base64 data (%s bytes)',
      basename($url),
      number_format($data_size)
    );
  } else {
    return 'Failed to convert: ' . $url;
  }
}

/**
 * Test function to debug gallery shortcode parsing
 * Only available when WP_DEBUG is enabled
 */
function kwik_ai_tags_test_gallery_parsing($content = null)
{
  if (!WP_DEBUG) {
    return 'Debug mode not enabled';
  }

  if (!$content) {
    // Use test content with gallery shortcodes
    $content = '[gallery columns="5" ids="8587,8586,8588,8589,8590,8591"] and [gallery columns="3"]';
  }

  $image_urls = kwik_ai_tags_extract_gallery_shortcode_images($content, 1); // Use post_id=1 for testing

  return sprintf(
    'Test content: %s<br>Extracted %d image URLs: %s',
    esc_html($content),
    count($image_urls),
    esc_html(implode(', ', $image_urls))
  );
}

/**
 * Add admin menu
 */
function kwik_ai_tags_add_admin_menu()
{
  $settings_hook = add_options_page(
    __('KWIK AI Settings', KWIK_AI_DOMAIN),
    __('KWIK AI', KWIK_AI_DOMAIN),
    'manage_options',
    'kwik-ai-tags-settings',
    'kwik_ai_tags_settings_page'
  );
  
  // Add action to enqueue styles only on our settings page
  add_action('admin_print_styles-' . $settings_hook, 'kwik_ai_tags_enqueue_settings_styles');
}

/**
 * Enqueue styles for settings page
 */
function kwik_ai_tags_enqueue_settings_styles()
{
  wp_enqueue_style(
    'kwik-ai-tags-settings',
    plugin_dir_url(__FILE__) . 'assets/settings.css',
    array(),
    '2.6'
  );
}

/**
 * Initialize settings
 */
function kwik_ai_tags_settings_init()
{
  register_setting(
    'kwik_ai_tags_settings',
    'kwik_ai_tags_enabled_post_types',
    array(
      'type' => 'array',
      'sanitize_callback' => 'kwik_ai_tags_sanitize_post_types',
      'default' => array('post', 'belt')
    )
  );

  add_settings_section(
    'kwik_ai_tags_main_section',
    __('Post Type Settings', KWIK_AI_DOMAIN),
    'kwik_ai_tags_settings_section_callback',
    'kwik_ai_tags_settings'
  );

  add_settings_field(
    'kwik_ai_tags_enabled_post_types',
    __('Enabled Post Types', KWIK_AI_DOMAIN),
    'kwik_ai_tags_enabled_post_types_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_main_section'
  );
}

/**
 * Sanitize post types setting
 */
function kwik_ai_tags_sanitize_post_types($input)
{
  if (!is_array($input)) {
    return array('post', 'belt');
  }

  $sanitized = array();
  foreach ($input as $post_type) {
    $post_type = sanitize_key($post_type);
    if (post_type_exists($post_type)) {
      $sanitized[] = $post_type;
    }
  }

  // Ensure at least one post type is selected
  if (empty($sanitized)) {
    $sanitized = array('post');
  }

  return $sanitized;
}

/**
 * Settings section callback
 */
function kwik_ai_tags_settings_section_callback()
{
  echo '<p>' . esc_html__('Choose which post types should have AI tag and description generation enabled.', KWIK_AI_DOMAIN) . '</p>';
}

/**
 * Enabled post types field callback
 */
function kwik_ai_tags_enabled_post_types_callback()
{
  $enabled_post_types = get_option('kwik_ai_tags_enabled_post_types', array('post'));
  $post_types = get_post_types(array('public' => true), 'objects');
  
  echo '<fieldset>';
  echo '<legend class="screen-reader-text">' . esc_html__('Enabled Post Types', KWIK_AI_DOMAIN) . '</legend>';
  
  foreach ($post_types as $post_type) {
    // Skip attachment post type
    if ($post_type->name === 'attachment') {
      continue;
    }
    
    $checked = in_array($post_type->name, $enabled_post_types) ? 'checked="checked"' : '';
    
    printf(
      '<label><input type="checkbox" name="kwik_ai_tags_enabled_post_types[]" value="%s" %s /> %s</label><br />',
      esc_attr($post_type->name),
      $checked,
      esc_html($post_type->label)
    );
  }
  
  echo '</fieldset>';
  echo '<p class="description">' . esc_html__('Select the post types where you want AI tag and description generation to be available. The AI Tags and AI Description meta boxes will appear in the editor for these post types.', KWIK_AI_DOMAIN) . '</p>';
}

/**
 * Settings page
 */
function kwik_ai_tags_settings_page()
{
  if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', KWIK_AI_DOMAIN));
  }
  
  ?>
  <div class="wrap kwik-ai-tags-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="card">
      <h2><?php esc_html_e('Plugin Information', KWIK_AI_DOMAIN); ?></h2>
      <p><?php esc_html_e('KWIK AI automatically generates relevant tags and descriptions for your content using AI image analysis. Descriptions are appended to the post content.', KWIK_AI_DOMAIN); ?></p>
      
      <h3><?php esc_html_e('Requirements', KWIK_AI_DOMAIN); ?></h3>
      <ul>
        <li><?php esc_html_e('Ollama running on http://localhost:11434', KWIK_AI_DOMAIN); ?></li>
        <li><?php esc_html_e('gemma3:27b model installed (run: ollama pull gemma3:27b)', KWIK_AI_DOMAIN); ?></li>
        <li><?php esc_html_e('Posts with images or at least 50 words of text content', KWIK_AI_DOMAIN); ?></li>
      </ul>
      
      <h3><?php esc_html_e('Current Status', KWIK_AI_DOMAIN); ?></h3>
      <p>
        <strong><?php esc_html_e('Ollama Host:', KWIK_AI_DOMAIN); ?></strong> 
        <code><?php echo esc_html(KWIK_AI_OLLAMA_HOST); ?></code>
        
        <?php
        // Test Ollama connection
        $ollama_status = kwik_ai_tags_test_ollama_connection();
        if ($ollama_status === true) {
          echo '<span class="kwik-ai-tags-status-connected"><span class="kwik-ai-tags-status-icon">✓</span>' . esc_html__('Connected', KWIK_AI_DOMAIN) . '</span>';
        } else {
          echo '<span class="kwik-ai-tags-status-error"><span class="kwik-ai-tags-status-icon">✗</span>' . esc_html($ollama_status) . '</span>';
        }
        ?>
      </p>
    </div>
    
    <form action="options.php" method="post">
      <?php
      settings_fields('kwik_ai_tags_settings');
      do_settings_sections('kwik_ai_tags_settings');
      submit_button();
      ?>
    </form>
  </div>
  <?php
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

// Add admin action to test image conversion
if (WP_DEBUG && is_admin()) {
  add_action('wp_ajax_kwik_ai_tags_test_conversion', function () {
    if (!current_user_can('manage_options')) {
      wp_die('Insufficient permissions');
    }

    $url = sanitize_url($_POST['test_url'] ?? '');
    $result = kwik_ai_tags_test_image_conversion($url);

    wp_send_json_success($result);
  });
}
