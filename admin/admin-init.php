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

  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Scripts and styles enqueued successfully');
  }
}
