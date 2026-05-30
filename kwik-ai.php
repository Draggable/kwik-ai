<?php
/**
 * Plugin Name: Kwik AI
 * Description: Auto‑tag posts using AI models. Supports vision-capable models for image analysis (e.g., gemma3, llava, gpt-4o) and text analysis (50+ words) with preview functionality. Also generates AI descriptions for posts based on attached images or content scraped from URLs, and AI featured images via FAL.AI. Supports Ollama, OpenRouter, and OpenAI. Configure model selection and post types via Settings > KWIK AI.
 * Version: 3.1
 * Author: Kevin Chappell
 * Text Domain: kwik-ai-tags
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