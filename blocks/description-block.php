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
  $description = isset($attributes['description']) ? $attributes['description'] : '';
  
  if (empty($description)) {
    return '';
  }

  return sprintf(
    '<div class="wp-block-kwik-ai-description"><p>%s</p></div>',
    esc_html($description)
  );
}