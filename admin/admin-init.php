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

  $plugin_dir = plugin_dir_path(KWIK_AI_PLUGIN_FILE);
  $js_url = plugin_dir_url(KWIK_AI_PLUGIN_FILE) . 'assets/js/admin.js';
  $css_url = plugin_dir_url(KWIK_AI_PLUGIN_FILE) . 'assets/css/admin.css';

  // Use file modification time for cache busting so asset changes (e.g. the
  // spinner styles) are picked up on upgrade without manually bumping a version
  // string — a stale cached admin.css is what made the loading spinner render
  // as a rotating square instead of a circle on existing installs.
  $js_path = $plugin_dir . 'assets/js/admin.js';
  $css_path = $plugin_dir . 'assets/css/admin.css';
  $js_ver = file_exists($js_path) ? filemtime($js_path) : '3.1';
  $css_ver = file_exists($css_path) ? filemtime($css_path) : '3.1';

  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Enqueueing JS from: ' . $js_url);
    kwik_ai_log('Kwik AI: Enqueueing CSS from: ' . $css_url);
  }

  wp_enqueue_script(
    'kwik-ai-admin',
    $js_url,
    ['jquery'],
    $js_ver,
    true
  );

  wp_enqueue_style(
    'kwik-ai-admin',
    $css_url,
    [],
    $css_ver
  );

  $post_id = get_the_ID();
  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Post ID = ' . $post_id);
  }

  wp_localize_script('kwik-ai-admin', 'kwikAiTags', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('kwik_ai_tags_ajax'),
    'postId' => $post_id,
    'debug' => WP_DEBUG,
    'strings' => [
      'error' => __('An error occurred. Please try again.', 'kwik-ai'),
      'noContent' => __('No images or insufficient text found. Please add images or write at least 50 words.', 'kwik-ai'),
      'success' => __('Tags applied successfully!', 'kwik-ai'),
    ]
  ]);

  wp_localize_script('kwik-ai-admin', 'kwikAiDescription', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('kwik_ai_description_ajax'),
    'postId' => $post_id,
    'debug' => WP_DEBUG,
    'strings' => [
      'error' => __('An error occurred. Please try again.', 'kwik-ai'),
      'noContent' => __('No images found. Please add images to generate a description.', 'kwik-ai'),
      'success' => __('Description applied successfully!', 'kwik-ai'),
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
      'generatePrompt' => __('Generate Prompt', 'kwik-ai'),
      'generatingPrompt' => __('Writing prompt…', 'kwik-ai'),
      'generate' => __('Generate Image', 'kwik-ai'),
      'generating' => __('Generating image…', 'kwik-ai'),
      'queued' => __('Queued…', 'kwik-ai'),
      'inProgress' => __('Generating…', 'kwik-ai'),
      'applying' => __('Setting featured image…', 'kwik-ai'),
      'setFeatured' => __('Set as Featured Image', 'kwik-ai'),
      'noPost' => __('No post ID found. Please save the post first.', 'kwik-ai'),
      'noPrompt' => __('Generate or enter a prompt first.', 'kwik-ai'),
      'timeout' => __('Image generation timed out. Please try again.', 'kwik-ai'),
      'error' => __('An error occurred. Please try again.', 'kwik-ai'),
      'success' => __('Featured image set!', 'kwik-ai'),
      'sourceAi' => __('Prompt written by your AI provider. Review and edit it before generating — for example, remove any words an image filter might wrongly flag.', 'kwik-ai'),
      'sourceFallback' => __('Heads up: this is a raw excerpt from the post because the text AI provider did not respond. Check your AI provider connection, or edit the prompt manually.', 'kwik-ai'),
    ]
  ]);

  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Scripts and styles enqueued successfully');
  }
}
