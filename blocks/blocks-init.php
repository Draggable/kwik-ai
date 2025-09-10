<?php
/**
 * Block initialization
 *
 * @package KwikAI
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Register Gutenberg blocks
 */
function kwik_ai_register_blocks()
{
  // Check if the function exists (WordPress 5.0+)
  if (!function_exists('register_block_type')) {
    return;
  }
  error_log('KWIK AI: kwik_ai_register_blocks() called');

  // Register the AI Description block
  register_block_type('kwik-ai/description', array(
    'editor_script' => 'kwik-ai-description-block',
    'editor_style' => 'kwik-ai-description-block-editor',
    'style' => 'kwik-ai-description-block-frontend',
    'render_callback' => 'kwik_ai_description_block_render',
    'attributes' => array(
      'description' => array(
        'type' => 'string',
        'default' => '',
      ),
      'postId' => array(
        'type' => 'number',
        'default' => 0,
      ),
      'isGenerating' => array(
        'type' => 'boolean',
        'default' => false,
      ),
    ),
  ));
}

/**
 * Enqueue block editor assets
 */
function kwik_ai_description_block_editor_assets()
{
  // Debug logging
  error_log('KWIK AI: kwik_ai_description_block_editor_assets() called');
  
  // The enqueue_block_editor_assets hook only fires in admin, so no need to check is_admin()
  error_log('KWIK AI: Enqueueing block assets');

  // Enqueue block editor script
  wp_enqueue_script(
    'kwik-ai-description-block',
    plugin_dir_url(KWIK_AI_PLUGIN_FILE) . 'assets/js/description-block.build.js',
    array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data'),
    '2.8.0', // Increment version to force cache refresh
    true
  );

  // Enqueue block editor styles
  wp_enqueue_style(
    'kwik-ai-description-block-editor',
    plugin_dir_url(KWIK_AI_PLUGIN_FILE) . 'assets/css/description-block-editor.css',
    array('wp-edit-blocks'),
    '2.8.0'
  );

  // Enqueue frontend styles
  wp_enqueue_style(
    'kwik-ai-description-block-frontend',
    plugin_dir_url(KWIK_AI_PLUGIN_FILE) . 'assets/css/description-block-frontend.css',
    array(),
    '2.8.0'
  );

  // Localize script with AJAX data
  wp_localize_script('kwik-ai-description-block', 'kwikAiDescriptionBlock', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('kwik_ai_description_ajax'),
    'enabledPostTypes' => kwik_ai_tags_get_enabled_post_types(),
    'strings' => array(
      'title' => __('AI Description', KWIK_AI_DOMAIN),
      'description' => __('Generate an AI-powered description based on post images or URLs', KWIK_AI_DOMAIN),
      'generateButton' => __('Generate Description', KWIK_AI_DOMAIN),
      'regenerateButton' => __('Regenerate', KWIK_AI_DOMAIN),
      'generating' => __('Generating...', KWIK_AI_DOMAIN),
      'placeholder' => __('Click "Generate Description" to create an AI-powered description based on the images in this post or content from URLs.', KWIK_AI_DOMAIN),
      'error' => __('Failed to generate description. Please ensure Ollama is running and try again.', KWIK_AI_DOMAIN),
      'noImages' => __('No images found. Please add images to the post or provide URLs to generate a description.', KWIK_AI_DOMAIN),
    ),
  ));

  error_log('KWIK AI: Script enqueued: ' . plugin_dir_url(KWIK_AI_PLUGIN_FILE) . 'assets/js/description-block.js');
}
