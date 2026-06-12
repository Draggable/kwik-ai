<?php
/**
 * Plugin Name: Kwik AI
 * Plugin URI: https://draggable.io/products/kwik-ai
 * Description: Auto-tag posts with AI, generate post descriptions from images or scraped URLs, and create AI featured images. Supports Ollama, OpenRouter, and OpenAI.
 * Version: 1.1.0
 * Author: Draggable
 * Author URI: https://draggable.io
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Text Domain: kwik-ai
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * This plugin requires:
 *   • WordPress 5.6+ (any recent LTS)
 *   • AI provider configured (Ollama, OpenRouter, or OpenAI)
 *   • A vision-capable model (e.g., gemma3:27b, llava:13b for Ollama; gpt-4o for OpenAI)
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

// Define plugin file constant
if (!defined('KWIK_AI_PLUGIN_FILE')) {
  define('KWIK_AI_PLUGIN_FILE', __FILE__);
}

// Include core files
require_once plugin_dir_path(__FILE__) . 'core/constants.php';
require_once plugin_dir_path(__FILE__) . 'core/functions.php';
require_once plugin_dir_path(__FILE__) . 'core/init.php';

// Include helper functions
require_once plugin_dir_path(__FILE__) . 'includes/security-utilities.php';
require_once plugin_dir_path(__FILE__) . 'includes/image-processing.php';
require_once plugin_dir_path(__FILE__) . 'includes/ollama-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/tag-generation.php';
require_once plugin_dir_path(__FILE__) . 'includes/description-generation.php';
require_once plugin_dir_path(__FILE__) . 'includes/web-scraping.php';
require_once plugin_dir_path(__FILE__) . 'includes/fal-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/featured-image-generation.php';

// Include admin functionality
require_once plugin_dir_path(__FILE__) . 'admin/admin-init.php';
require_once plugin_dir_path(__FILE__) . 'admin/meta-boxes.php';
require_once plugin_dir_path(__FILE__) . 'admin/settings.php';
require_once plugin_dir_path(__FILE__) . 'admin/ajax-handlers.php';

// Include block functionality
require_once plugin_dir_path(__FILE__) . 'blocks/blocks-init.php';
require_once plugin_dir_path(__FILE__) . 'blocks/description-block.php';
