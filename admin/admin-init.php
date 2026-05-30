<?php
/**
 * Admin initialization
 *
 * @package KwikAI
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Enqueue scripts and styles for the admin
 */
function kwik_ai_tags_enqueue_scripts($hook)
{
  $enabled_post_types = kwik_ai_tags_get_enabled_post_types();
  
  // Debug logging
  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Hook = ' . $hook);
  }

  if ($hook !== 'post.php' && $hook !== 'post-new.php') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      kwik_ai_log('Kwik AI: Wrong hook, returning');
    }
    return;
  }

  $screen = get_current_screen();
  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Screen post_type = ' . $screen->post_type);
  }

  if (!in_array($screen->post_type, $enabled_post_types)) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      kwik_ai_log('Kwik AI: Post type not enabled, returning');
    }
    return;
  }

  $js_url = plugin_dir_url(KWIK_AI_PLUGIN_FILE) . 'assets/js/admin.js';
  $css_url = plugin_dir_url(KWIK_AI_PLUGIN_FILE) . 'assets/css/admin.css';

  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Enqueueing JS from: ' . $js_url);
    kwik_ai_log('Kwik AI: Enqueueing CSS from: ' . $css_url);
  }

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
  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Post ID = ' . $post_id);
  }

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

  $fal_js_path = plugin_dir_path(KWIK_AI_PLUGIN_FILE) . 'assets/js/featured-image.js';
  $fal_js_ver = file_exists($fal_js_path) ? filemtime($fal_js_path) : '3.0';

  wp_enqueue_script(
    'kwik-ai-featured-image',
    plugin_dir_url(KWIK_AI_PLUGIN_FILE) . 'assets/js/featured-image.js',
    ['jquery'],
    $fal_js_ver,
    true
  );

  wp_localize_script('kwik-ai-featured-image', 'kwikAiFeaturedImage', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('kwik_ai_featured_image_ajax'),
    'postId' => $post_id,
    'debug' => WP_DEBUG,
    // How often the browser polls for the queued image, and when to give up.
    'pollInterval' => 3000,
    'maxPollMs' => 300000,
    'strings' => [
      'generatePrompt' => __('Generate Prompt', KWIK_AI_DOMAIN),
      'generatingPrompt' => __('Writing prompt…', KWIK_AI_DOMAIN),
      'generate' => __('Generate Image', KWIK_AI_DOMAIN),
      'generating' => __('Generating image…', KWIK_AI_DOMAIN),
      'queued' => __('Queued…', KWIK_AI_DOMAIN),
      'inProgress' => __('Generating…', KWIK_AI_DOMAIN),
      'applying' => __('Setting featured image…', KWIK_AI_DOMAIN),
      'setFeatured' => __('Set as Featured Image', KWIK_AI_DOMAIN),
      'noPost' => __('No post ID found. Please save the post first.', KWIK_AI_DOMAIN),
      'noPrompt' => __('Generate or enter a prompt first.', KWIK_AI_DOMAIN),
      'timeout' => __('Image generation timed out. Please try again.', KWIK_AI_DOMAIN),
      'error' => __('An error occurred. Please try again.', KWIK_AI_DOMAIN),
      'success' => __('Featured image set!', KWIK_AI_DOMAIN),
      'sourceAi' => __('Prompt written by your AI provider. Review and edit it before generating — for example, remove any words an image filter might wrongly flag.', KWIK_AI_DOMAIN),
      'sourceFallback' => __('Heads up: this is a raw excerpt from the post because the text AI provider did not respond. Check your AI provider connection, or edit the prompt manually.', KWIK_AI_DOMAIN),
    ]
  ]);

  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Scripts and styles enqueued successfully');
  }
}
