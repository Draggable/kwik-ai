<?php
/**
 * Plugin constants
 *
 * @package KwikAI
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

// Define plugin constants
define('KWIK_AI_OLLAMA_HOST', 'http://localhost:11434'); // Change if Ollama runs elsewhere.
define('KWIK_AI_MAX_TAGS', 8); // Max tags to apply per post (increased for text+image)
define('KWIK_AI_MIN_WORDS', 50); // Minimum words required for text analysis
define('KWIK_AI_MAX_DESCRIPTION_LENGTH', 2000); // Maximum length for generated descriptions
define('KWIK_AI_DOMAIN', 'kwik-ai');