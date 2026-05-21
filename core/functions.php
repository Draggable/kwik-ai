<?php
/**
 * Core plugin functions
 *
 * @package KwikAI
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Log debug messages (gated by WP_DEBUG)
 *
 * Centralized logging helper that prevents excessive error_log() calls
 * in production where WP_DEBUG is disabled.
 *
 * @param string $message Message to log
 */
function kwik_ai_log(string $message): void
{
  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log($message);
  }
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