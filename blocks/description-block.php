<?php
/**
 * Description block functionality
 *
 * @package KwikAI
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Render callback for the AI Description block
 */
function kwik_ai_description_block_render($attributes)
{
  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('KWIK AI: kwik_ai_description_block_render() called'. print_r($attributes, true));
  }
  $description = isset($attributes['description']) ? $attributes['description'] : '';
  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('KWIK AI: Description content: ' . $description);
  }
  
  if (empty($description)) {
    return '';
  }

  // Sanitize the description to prevent stored XSS
  // AI-generated content may contain HTML; allow safe markup only
  $safe_description = wp_kses_post($description);

  return sprintf(
    '<div class="wp-block-kwik-ai-description">%s</div>',
    esc_html($safe_description)
  );
}
