<?php
/**
 * Plugin initialization
 *
 * @package KwikAI
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Initialize the plugin
 */
add_action('init', 'kwik_ai_tags_init');
add_action('enqueue_block_editor_assets', 'kwik_ai_description_block_editor_assets');

function kwik_ai_tags_init()
{
  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Kwik AI: Plugin initializing');
  }

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
  add_action('wp_ajax_kwik_ai_tags_fetch_models', 'kwik_ai_tags_ajax_fetch_models');
  add_action('wp_ajax_kwik_ai_tags_test_connection', 'kwik_ai_tags_ajax_test_connection');

  // Register blocks
  kwik_ai_register_blocks();

  // Add admin menu
  add_action('admin_menu', 'kwik_ai_tags_add_admin_menu');

  // Initialize settings
  add_action('admin_init', 'kwik_ai_tags_settings_init');

  // Add settings link to plugin page
  add_filter('plugin_action_links_' . plugin_basename(KWIK_AI_PLUGIN_FILE), 'kwik_ai_tags_add_settings_link');

  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Kwik AI: Plugin initialized with hooks');
  }
}
