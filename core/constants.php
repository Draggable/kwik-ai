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
// Note: These can be overridden by settings in the admin panel

// Max tags to apply per post (increased for text+image)
define('KWIK_AI_MAX_TAGS', 8);

// Minimum words required for text analysis
define('KWIK_AI_MIN_WORDS', 50);

// Maximum length for generated descriptions
define('KWIK_AI_MAX_DESCRIPTION_LENGTH', 2000);

// Plugin domain
define('KWIK_AI_DOMAIN', 'kwik-ai');

// Default values - used if settings are not configured
define('KWIK_AI_DEFAULT_OLLAMA_HOST', 'http://localhost:11434');
define('KWIK_AI_DEFAULT_OPENROUTER_ENDPOINT', 'https://openrouter.ai/api/v1');
define('KWIK_AI_DEFAULT_OPENAI_ENDPOINT', 'https://api.openai.com/v1');

// NOTE: Do NOT define dynamic settings (API keys, endpoints, models) as constants
// here. Constants are frozen on first load and won't reflect settings changes.
// Use getter functions (kwik_ai_tags_get_api_key(), etc.) instead.
