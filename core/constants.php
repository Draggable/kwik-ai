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

// AI Provider constants - these will be set from settings if available
if (!defined('KWIK_AI_AI_PROVIDER')) {
  define('KWIK_AI_AI_PROVIDER', get_option('kwik_ai_ai_provider', 'ollama'));
}

if (!defined('KWIK_AI_API_ENDPOINT')) {
  define('KWIK_AI_API_ENDPOINT', get_option('kwik_ai_api_endpoint', 'http://localhost:11434'));
}

if (!defined('KWIK_AI_API_KEY')) {
  define('KWIK_AI_API_KEY', get_option('kwik_ai_api_key', ''));
}

if (!defined('KWIK_AI_API_USERNAME')) {
  define('KWIK_AI_API_USERNAME', get_option('kwik_ai_api_username', ''));
}

if (!defined('KWIK_AI_API_PASSWORD')) {
  define('KWIK_AI_API_PASSWORD', get_option('kwik_ai_api_password', ''));
}

if (!defined('KWIK_AI_MODEL')) {
  define('KWIK_AI_MODEL', get_option('kwik_ai_model', 'gemma3:27b'));
}

// Provider-specific constants
if (!defined('KWIK_AI_OPENROUTER_API_KEY')) {
  define('KWIK_AI_OPENROUTER_API_KEY', get_option('kwik_ai_openrouter_api_key', ''));
}

if (!defined('KWIK_AI_OPENAI_API_KEY')) {
  define('KWIK_AI_OPENAI_API_KEY', get_option('kwik_ai_openai_api_key', ''));
}

// Default values - used if settings are not configured
define('KWIK_AI_DEFAULT_OLLAMA_HOST', 'http://localhost:11434');
define('KWIK_AI_DEFAULT_OPENROUTER_ENDPOINT', 'https://openrouter.ai/api/v1');
define('KWIK_AI_DEFAULT_OPENAI_ENDPOINT', 'https://api.openai.com/v1');