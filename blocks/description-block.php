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
  error_log('KWIK AI: kwik_ai_description_block_render() called'. print_r($attributes, true));
  $description = isset($attributes['description']) ? $attributes['description'] : '';
  error_log('KWIK AI: Description content: ' . $description);
  
  if (empty($description)) {
    return '';
  }

  // The description already contains HTML (paragraph tags from RichText.Content)
  // so we don't need to wrap it in additional paragraph tags or escape it
  return sprintf(
    '<div class="wp-block-kwik-ai-description">%s</div>',
    $description
  );
}
