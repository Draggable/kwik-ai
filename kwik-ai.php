<?php
/**
 * Plugin Name: Kwik AI
 * Description: Auto‑tag posts with the Gemma3:27b model via Ollama. Analyzes both images (including block images and custom TRB belt gallery blocks) and text content (50+ words) with preview functionality. Also generates AI descriptions for belt posts based on attached images or content scraped from URLs. Configure which post types to enable via Settings > KWIK AI.
 * Version: 2.8
 * Author: Your Name
 * Text Domain: kwik-ai-tags
 *
 * This plugin requires:
 *   • WordPress 5.6+ (any recent LTS)
 *   • Ollama running on http://localhost:11434
 *   • gemma3:27b model pulled (`ollama pull gemma3:27b`)
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
require_once plugin_dir_path(__FILE__) . 'includes/image-processing.php';
require_once plugin_dir_path(__FILE__) . 'includes/ollama-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/tag-generation.php';
require_once plugin_dir_path(__FILE__) . 'includes/description-generation.php';
require_once plugin_dir_path(__FILE__) . 'includes/web-scraping.php';

// Include admin functionality
require_once plugin_dir_path(__FILE__) . 'admin/admin-init.php';
require_once plugin_dir_path(__FILE__) . 'admin/meta-boxes.php';
require_once plugin_dir_path(__FILE__) . 'admin/settings.php';
require_once plugin_dir_path(__FILE__) . 'admin/ajax-handlers.php';

// Include block functionality
require_once plugin_dir_path(__FILE__) . 'blocks/blocks-init.php';
require_once plugin_dir_path(__FILE__) . 'blocks/description-block.php';

// Include admin functionality
require_once plugin_dir_path(__FILE__) . 'admin/admin-init.php';
require_once plugin_dir_path(__FILE__) . 'admin/meta-boxes.php';
require_once plugin_dir_path(__FILE__) . 'admin/settings.php';
require_once plugin_dir_path(__FILE__) . 'admin/ajax-handlers.php';

// Include block functionality
require_once plugin_dir_path(__FILE__) . 'blocks/blocks-init.php';
require_once plugin_dir_path(__FILE__) . 'blocks/description-block.php';