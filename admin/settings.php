<?php
/**
 * Settings page functionality
 *
 * @package KwikAI
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

// Load security utilities if available
if (file_exists(dirname(__DIR__) . '/includes/security-utilities.php')) {
  require_once dirname(__DIR__) . '/includes/security-utilities.php';
}

/**
 * Add admin menu
 */
function kwik_ai_tags_add_admin_menu()
{
  $settings_hook = add_options_page(
    __('KWIK AI Settings', KWIK_AI_DOMAIN),
    __('KWIK AI', KWIK_AI_DOMAIN),
    'manage_options',
    'kwik-ai-tags-settings',
    'kwik_ai_tags_settings_page'
  );
  
  // Add action to enqueue styles only on our settings page
  add_action('admin_print_styles-' . $settings_hook, 'kwik_ai_tags_enqueue_settings_styles');
}

/**
 * Enqueue styles for settings page
 */
function kwik_ai_tags_enqueue_settings_styles()
{
  wp_enqueue_style(
    'kwik-ai-tags-settings',
    plugin_dir_url(KWIK_AI_PLUGIN_FILE) . 'assets/css/settings.css',
    array(),
    '3.0'
  );

  // Enqueue settings JavaScript
  wp_enqueue_script(
    'kwik-ai-tags-settings',
    plugin_dir_url(KWIK_AI_PLUGIN_FILE) . 'assets/js/settings.js',
    array('jquery'),
    '3.0',
    true
  );

  wp_localize_script('kwik-ai-tags-settings', 'kwikAiSettings', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('kwik_ai_tags_ajax'),
    'strings' => [
      'loading' => __('Loading...', KWIK_AI_DOMAIN),
      'error' => __('Failed to fetch models. Please check your connection.', KWIK_AI_DOMAIN),
      'vision' => __('(Vision)', KWIK_AI_DOMAIN),
      'refresh' => __('Refresh Models', KWIK_AI_DOMAIN),
    ]
  ]);
}

/**
 * Initialize settings
 */
function kwik_ai_tags_settings_init()
{
  // Post types setting
  register_setting(
    'kwik_ai_tags_settings',
    'kwik_ai_tags_enabled_post_types',
    array(
      'type' => 'array',
      'sanitize_callback' => 'kwik_ai_tags_sanitize_post_types',
      'default' => array('post', 'belt')
    )
  );

  // AI Provider setting
  register_setting(
    'kwik_ai_tags_settings',
    'kwik_ai_ai_provider',
    array(
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => 'custom'
    )
  );

  // API Endpoint (used by all providers)
  register_setting(
    'kwik_ai_tags_settings',
    'kwik_ai_api_endpoint',
    array(
      'type' => 'string',
      'sanitize_callback' => 'esc_url_raw',
      'default' => 'http://localhost:11434'
    )
  );

  // API Key (generic - can be used for OpenRouter/OpenAI)
  register_setting(
    'kwik_ai_tags_settings',
    'kwik_ai_api_key',
    array(
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => ''
    )
  );

  // Model setting
  register_setting(
    'kwik_ai_tags_settings',
    'kwik_ai_model',
    array(
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => 'gemma3:27b'
    )
  );

  // OpenRouter-specific API key
  register_setting(
    'kwik_ai_tags_settings',
    'kwik_ai_openrouter_api_key',
    array(
      'type' => 'string',
      'sanitize_callback' => 'kwik_ai_tags_sanitize_openrouter_api_key',
      'default' => ''
    )
  );

  // OpenAI-specific API key
  register_setting(
    'kwik_ai_tags_settings',
    'kwik_ai_openai_api_key',
    array(
      'type' => 'string',
      'sanitize_callback' => 'kwik_ai_tags_sanitize_openai_api_key',
      'default' => ''
    )
  );

  // Custom/Ollama API key
  register_setting(
    'kwik_ai_tags_settings',
    'kwik_ai_custom_api_key',
    array(
      'type' => 'string',
      'sanitize_callback' => 'kwik_ai_tags_sanitize_custom_api_key',
      'default' => ''
    )
  );

  add_settings_section(
    'kwik_ai_tags_main_section',
    __('Post Type Settings', KWIK_AI_DOMAIN),
    'kwik_ai_tags_settings_section_callback',
    'kwik_ai_tags_settings'
  );

  add_settings_section(
    'kwik_ai_tags_provider_section',
    __('AI Provider Settings', KWIK_AI_DOMAIN),
    'kwik_ai_tags_provider_section_callback',
    'kwik_ai_tags_settings'
  );

  add_settings_section(
    'kwik_ai_tags_auth_section',
    __('Authentication', KWIK_AI_DOMAIN),
    'kwik_ai_tags_auth_section_callback',
    'kwik_ai_tags_settings'
  );

  add_settings_field(
    'kwik_ai_tags_enabled_post_types',
    __('Enabled Post Types', KWIK_AI_DOMAIN),
    'kwik_ai_tags_enabled_post_types_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_main_section'
  );

  add_settings_field(
    'kwik_ai_ai_provider',
    __('AI Provider', KWIK_AI_DOMAIN),
    'kwik_ai_ai_provider_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_provider_section'
  );

  add_settings_field(
    'kwik_ai_api_endpoint',
    __('API Endpoint', KWIK_AI_DOMAIN),
    'kwik_ai_api_endpoint_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_provider_section'
  );

  add_settings_field(
    'kwik_ai_model',
    __('Model', KWIK_AI_DOMAIN),
    'kwik_ai_model_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_provider_section'
  );

  add_settings_field(
    'kwik_ai_custom_api_key',
    __('Custom API Key', KWIK_AI_DOMAIN),
    'kwik_ai_custom_api_key_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_auth_section'
  );

  add_settings_field(
    'kwik_ai_openrouter_api_key',
    __('OpenRouter API Key', KWIK_AI_DOMAIN),
    'kwik_ai_openrouter_api_key_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_auth_section'
  );

  add_settings_field(
    'kwik_ai_openai_api_key',
    __('OpenAI API Key', KWIK_AI_DOMAIN),
    'kwik_ai_openai_api_key_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_auth_section'
  );
}

/**
 * Sanitize post types setting
 */
function kwik_ai_tags_sanitize_post_types($input)
{
  if (!is_array($input)) {
    return array('post', 'belt');
  }

  $sanitized = array();
  foreach ($input as $post_type) {
    $post_type = sanitize_key($post_type);
    if (post_type_exists($post_type)) {
      $sanitized[] = $post_type;
    }
  }

  // Ensure at least one post type is selected
  if (empty($sanitized)) {
    $sanitized = array('post');
  }

  return $sanitized;
}

/**
 * Sanitize password setting
 */
function kwik_ai_tags_sanitize_password($input)
{
  return trim($input);
}

/**
 * Sanitize OpenRouter API key setting
 * Uses secure storage if available
 *
 * @param string $input
 * @return string
 */
function kwik_ai_tags_sanitize_openrouter_api_key($input)
{
  $key = trim($input);

  // Store securely if available
  if (function_exists('kwik_ai_store_credential')) {
    kwik_ai_store_credential('kwik_ai_openrouter_api_key', $key);
  }

  return $key;
}

/**
 * Sanitize OpenAI API key setting
 * Uses secure storage if available
 *
 * @param string $input
 * @return string
 */
function kwik_ai_tags_sanitize_openai_api_key($input)
{
  $key = trim($input);

  // Store securely if available
  if (function_exists('kwik_ai_store_credential')) {
    kwik_ai_store_credential('kwik_ai_openai_api_key', $key);
  }

  return $key;
}

/**
 * Sanitize Custom/Ollama API key setting
 * Uses secure storage if available
 *
 * @param string $input
 * @return string
 */
function kwik_ai_tags_sanitize_custom_api_key($input)
{
  $key = trim($input);

  // Store securely if available
  if (function_exists('kwik_ai_store_credential')) {
    kwik_ai_store_credential('kwik_ai_custom_api_key', $key);
  }

  return $key;
}

/**
 * Settings section callback
 */
function kwik_ai_tags_settings_section_callback()
{
  echo '<p>' . esc_html__('Choose which post types should have AI tag and description generation enabled.', KWIK_AI_DOMAIN) . '</p>';
}

/**
 * Enabled Post Types field callback
 * Renders checkboxes for all public post types
 */
function kwik_ai_tags_enabled_post_types_callback()
{
  $enabled = get_option('kwik_ai_tags_enabled_post_types', array('post', 'belt'));
  if (!is_array($enabled)) {
    $enabled = array('post', 'belt');
  }

  $post_types = get_post_types(array('public' => true), 'objects');

  echo '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ccc; padding: 10px;">';
  foreach ($post_types as $pt) {
    $checked = in_array($pt->name, $enabled) ? 'checked' : '';
    printf(
      '<label style="display: block; margin: 4px 0;"><input type="checkbox" name="kwik_ai_tags_enabled_post_types[]" value="%s" %s /> %s</label>',
      esc_attr($pt->name),
      $checked,
      esc_html($pt->labels->singular_name)
    );
  }
  echo '</div>';
  echo '<p class="description">' . esc_html__('Select the post types where AI tag and description generation should be enabled.', KWIK_AI_DOMAIN) . '</p>';
}

/**
 * Provider settings section callback
 */
function kwik_ai_tags_provider_section_callback()
{
  echo '<p>' . esc_html__('Select your AI provider and configure the connection details.', KWIK_AI_DOMAIN) . '</p>';
}

/**
 * Authentication section callback
 */
function kwik_ai_tags_auth_section_callback()
{
  $has_encryption = function_exists('kwik_ai_has_encryption') && kwik_ai_has_encryption();
  
  echo '<p>' . esc_html__('Enter credentials for your AI provider.', KWIK_AI_DOMAIN) . '</p>';
  
  if ($has_encryption) {
    echo '<div class="notice notice-success inline" style="margin: 5px 0 0;"><p>' . 
      esc_html__('Credentials will be encrypted before storage.', KWIK_AI_DOMAIN) . '</p></div>';
  } else {
    echo '<div class="notice notice-warning inline" style="margin: 5px 0 0;"><p>' . 
      esc_html__('Credentials are stored without encryption. Ensure your database is properly secured.', KWIK_AI_DOMAIN) . '</p></div>';
  }
}

/**
 * AI Provider field callback
 */
function kwik_ai_ai_provider_callback()
{
  $provider = get_option('kwik_ai_ai_provider', 'custom');
  
  echo '<select name="kwik_ai_ai_provider" id="kwik-ai-provider-select" class="regular-text">';
  
  $providers = array(
    "custom" => "Custom",
    'openrouter' => 'OpenRouter',
    'openai' => 'OpenAI',
  );
  
  foreach ($providers as $value => $label) {
    $selected = selected($provider, $value, false);
    printf(
      '<option value="%s" %s>%s</option>',
      esc_attr($value),
      $selected,
      esc_html($label)
    );
  }
  
  echo '</select>';
  echo '<p class="description">';
  echo esc_html__('Custom requires a configured endpoint URL. OpenRouter and OpenAI require API keys.');
  echo '</p>';
}

/**
 * API Endpoint field callback
 */
function kwik_ai_api_endpoint_callback()
{
  $endpoint = get_option('kwik_ai_api_endpoint', 'http://localhost:11434');
  $provider = get_option('kwik_ai_ai_provider', 'custom');
  
  // Set default endpoint based on provider
  if ($provider === 'openrouter' && empty($endpoint)) {
    $endpoint = 'https://openrouter.ai/api/v1';
  } elseif ($provider === 'openai' && empty($endpoint)) {
    $endpoint = 'https://api.openai.com/v1';
  }
  
  printf(
    '<input type="url" name="kwik_ai_api_endpoint" value="%s" class="regular-text" placeholder="http://localhost:11434" />',
    esc_attr($endpoint)
  );
  echo '<p class="description">';
  echo esc_html__('For Custom: your API server URL (e.g., http://localhost:11434).');
  echo ' <strong>OpenRouter:</strong> https://openrouter.ai/api/v1';
  echo ' <strong>OpenAI:</strong> https://api.openai.com/v1';
  echo '</p>';
}

/**
 * Model field callback
 */
function kwik_ai_model_callback()
{
  $provider = get_option('kwik_ai_ai_provider', 'custom');
  $selected_model = get_option('kwik_ai_model', 'gemma3:27b');
  $models = kwik_ai_tags_fetch_models();

  echo '<div class="kwik-ai-model-selector-wrapper">';

  if ($models === false) {
    // Could not fetch models, show text input
    printf(
      '<input type="text" name="kwik_ai_model" value="%s" class="regular-text" id="kwik-ai-model-input" />',
      esc_attr($selected_model)
    );
    echo '<p class="description">' . esc_html__('Enter the model name manually.', KWIK_AI_DOMAIN) . '</p>';
  } else {
    // Show dropdown with models
    echo '<select name="kwik_ai_model" id="kwik-ai-model-select" class="regular-text">';

    foreach ($models as $model) {
      $selected = selected($selected_model, $model['name'], false);
      $vision_indicator = isset($model['has_vision']) && $model['has_vision'] ? ' ' . __('(Vision)', KWIK_AI_DOMAIN) : '';
      $vision_class = isset($model['has_vision']) && $model['has_vision'] ? 'vision-model' : '';

      printf(
        '<option value="%s" %s class="%s">%s%s</option>',
        esc_attr($model['name']),
        $selected,
        esc_attr($vision_class),
        esc_html($model['name']),
        esc_html($vision_indicator)
      );
    }

    echo '</select>';
    echo '<button type="button" class="button" id="kwik-ai-refresh-models" style="margin-left: 10px;">' . esc_html__('Refresh Models', KWIK_AI_DOMAIN) . '</button>';
    echo '<span id="kwik-ai-model-loading" style="display: none; margin-left: 10px;">' . esc_html__('Loading...', KWIK_AI_DOMAIN) . '</span>';

    echo '<p class="description">';
    echo esc_html__('Select the model to use for AI tag and description generation.');
    echo ' <strong>' . esc_html__('Models marked with "(Vision)" support image analysis.', KWIK_AI_DOMAIN) . '</strong>';
    echo '</p>';

    // Show warning if selected model doesn't have vision
    $selected_has_vision = false;
    foreach ($models as $model) {
      if ($model['name'] === $selected_model && isset($model['has_vision']) && $model['has_vision']) {
        $selected_has_vision = true;
        break;
      }
    }

    if (!$selected_has_vision && $provider === 'custom') {
      echo '<div class="notice notice-warning inline" style="margin-top: 10px;">';
      echo '<p>' . esc_html__('Warning: The selected model may not support image analysis. For best results with this plugin, select a vision-capable model.', KWIK_AI_DOMAIN) . '</p>';
      echo '</div>';
    }
  }

  echo '</div>';
}

/**
 * Custom API key field callback
 */
function kwik_ai_custom_api_key_callback()
{
  // Use secure retrieval if available
  if (function_exists('kwik_ai_retrieve_credential')) {
    $api_key = kwik_ai_retrieve_credential('kwik_ai_custom_api_key', '');
  } else {
    $api_key = get_option('kwik_ai_custom_api_key', '');
  }

  printf(
    '<input type="password" name="kwik_ai_custom_api_key" value="%s" class="regular-text" id="kwik-ai-custom-api-key" />',
    esc_attr($api_key)
  );
  echo '<p class="description">' . esc_html__('Enter the API key for your custom/Ollama endpoint (leave blank if no authentication is required)', KWIK_AI_DOMAIN) . '</p>';
}

/**
 * OpenRouter API key field callback
 */
function kwik_ai_openrouter_api_key_callback()
{
  // Use secure retrieval if available
  if (function_exists('kwik_ai_retrieve_credential')) {
    $api_key = kwik_ai_retrieve_credential('kwik_ai_openrouter_api_key', '');
  } else {
    $api_key = get_option('kwik_ai_openrouter_api_key', '');
  }
  
  printf(
    '<input type="password" name="kwik_ai_openrouter_api_key" value="%s" class="regular-text" />',
    esc_attr($api_key)
  );
  echo '<p class="description">';
  echo esc_html__('Get your API key from ');
  echo '<a href="https://openrouter.ai/keys" target="_blank" rel="noopener">OpenRouter</a>.';
  echo '</p>';
}

/**
 * OpenAI API key field callback
 */
function kwik_ai_openai_api_key_callback()
{
  // Use secure retrieval if available
  if (function_exists('kwik_ai_retrieve_credential')) {
    $api_key = kwik_ai_retrieve_credential('kwik_ai_openai_api_key', '');
  } else {
    $api_key = get_option('kwik_ai_openai_api_key', '');
  }
  
  printf(
    '<input type="password" name="kwik_ai_openai_api_key" value="%s" class="regular-text" />',
    esc_attr($api_key)
  );
  echo '<p class="description">';
  echo esc_html__('Get your API key from ');
  echo '<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">OpenAI</a>.';
  echo '</p>';
}

/**
 * Get list of known vision-capable models
 *
 * @return array
 */
function kwik_ai_tags_get_vision_models()
{
  return array(
    'gemma3' => true,
    'gemma3:1b' => true,
    'gemma3:4b' => true,
    'gemma3:12b' => true,
    'gemma3:27b' => true,
    'llava' => true,
    'llava:7b' => true,
    'llava:13b' => true,
    'llava:34b' => true,
    'llava-phi3' => true,
    'llava-llama3' => true,
    'llava-gemma' => true,
    'bakllava' => true,
    'moondream' => true,
    'moondream:1.8b' => true,
  );
}

/**
 * Check if a model has vision capabilities
 *
 * @param string $model_name
 * @return bool
 */
function kwik_ai_tags_model_has_vision($model_name)
{
  $vision_models = kwik_ai_tags_get_vision_models();

  // Check exact match
  if (isset($vision_models[$model_name])) {
    return true;
  }

  // Check if model name starts with any vision model prefix
  foreach ($vision_models as $vision_model => $has_vision) {
    if ($has_vision && (strpos($model_name, $vision_model) === 0 || strpos($model_name, $vision_model . ':') === 0)) {
      return true;
    }
  }

  // Check for common vision-related tags in model name
  $vision_keywords = array('vision', 'vl', 'multimodal', 'image', 'llava');
  $model_lower = strtolower($model_name);
  foreach ($vision_keywords as $keyword) {
    if (strpos($model_lower, $keyword) !== false) {
      return true;
    }
  }

  return false;
}

/**
 * Fetch available models from configured provider
 *
 * @return array|false Array of model info or false on error
 */
function kwik_ai_tags_fetch_models()
{
  $provider = get_option('kwik_ai_ai_provider', 'custom');
  
  if ($provider === 'custom') {
    return kwik_ai_tags_fetch_ollama_models();
  }
  
  // For OpenRouter/OpenAI, return a list of commonly used models
  // since they don't have a simple /api/tags endpoint
  return kwik_ai_tags_get_provider_models($provider);
}

/**
 * Get models for OpenRouter or OpenAI
 *
 * @param string $provider
 * @return array
 */
function kwik_ai_tags_get_provider_models($provider)
{
  $models = array();
  
  if ($provider === 'openrouter') {
    $models = array(
      'meta-llama/llama-3.1-8b-instruct:free' => true,
      'meta-llama/llama-3.1-70b-instruct' => true,
      'meta-llama/llama-3.3-70b-instruct' => true,
      'anthropic/claude-3.5-sonnet:free' => true,
      'anthropic/claude-3.5-haiku:free' => true,
      'anthropic/claude-3-5-sonnet-latest' => true,
      'anthropic/claude-3-5-sonnet-20241022' => true,
      'anthropic/claude-3-opus-latest' => true,
      'anthropic/claude-3-haiku-20240307' => true,
      'openai/gpt-4o' => true,
      'openai/gpt-4o-mini' => true,
      'openai/o1' => true,
      'openai/o1-mini' => true,
      'deepseek/deepseek-chat' => true,
      'mistralai/mistral-nemo:free' => true,
      'microsoft/phi-3.5-mini-instruct:free' => true,
      'google/gemma-2-9b-it:free' => true,
      'google/gemma-2-2b-it:free' => true,
      'google/gemma-2-27b-it' => true,
      'google/gemma-2-9b-it' => true,
      'mistralai/mistral-small-24b-instruct-2501:free' => true,
      'mistralai/mistral-medium' => true,
      'mistralai/mistral-large' => true,
      'cohere/command-r-plus-08-2024' => true,
      'cohere/command-r-08-2024' => true,
    );
  } elseif ($provider === 'openai') {
    $models = array(
      'gpt-4o' => true,
      'gpt-4o-mini' => true,
      'o1' => true,
      'o1-mini' => true,
      'o3-mini' => true,
      'gpt-4-turbo' => true,
      'gpt-4' => true,
      'gpt-4-turbo-preview' => true,
      'gpt-4-0125-preview' => true,
      'gpt-4-1106-preview' => true,
      'gpt-4-vision-preview' => true,
      'gpt-4-1106-vision-preview' => true,
      'gpt-3.5-turbo' => true,
      'gpt-3.5-turbo-0125' => true,
      'gpt-3.5-turbo-1106' => true,
    );
  }
  
  // Process and sort models
  $processed_models = array();
  foreach ($models as $model_name => $has_vision) {
    $processed_models[] = array(
      'name' => $model_name,
      'has_vision' => $has_vision,
    );
  }
  
  // Sort models: vision models first, then alphabetically
  usort($processed_models, function ($a, $b) {
    if ($a['has_vision'] !== $b['has_vision']) {
      return $b['has_vision'] ? 1 : -1;
    }
    return strcasecmp($a['name'], $b['name']);
  });
  
  return $processed_models;
}

/**
 * Fetch available models from Ollama server
 *
 * @return array|false Array of model info or false on error
 */
function kwik_ai_tags_fetch_ollama_models()
{
  $config = kwik_ai_tags_get_ollama_config();
  $url = $config['url'] . '/api/tags';
  $auth_headers = kwik_ai_tags_get_ollama_auth_header();

  $headers = array_merge(
    array('Content-Type' => 'application/json'),
    $auth_headers
  );

  $response = wp_remote_get($url, array(
    'timeout' => 10,
    'headers' => $headers
  ));

  if (is_wp_error($response)) {
    return false;
  }

  $response_code = wp_remote_retrieve_response_code($response);
  if ($response_code !== 200) {
    return false;
  }

  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body, true);

  if (!$data || !isset($data['models'])) {
    return false;
  }

  // Process and sort models
  $models = array();
  foreach ($data['models'] as $model) {
    if (isset($model['name'])) {
      $models[] = array(
        'name' => $model['name'],
        'has_vision' => kwik_ai_tags_model_has_vision($model['name']),
        'size' => isset($model['size']) ? $model['size'] : null,
        'modified_at' => isset($model['modified_at']) ? $model['modified_at'] : null,
      );
    }
  }

  // Sort models: vision models first, then alphabetically
  usort($models, function ($a, $b) {
    if ($a['has_vision'] !== $b['has_vision']) {
      return $b['has_vision'] ? 1 : -1;
    }
    return strcasecmp($a['name'], $b['name']);
  });

  return $models;
}

/**
 * Test connection to the configured AI provider
 *
 * @return bool|string True if connected, error message on failure
 */
function kwik_ai_tags_test_provider_connection()
{
  $provider = get_option('kwik_ai_ai_provider', 'custom');
  
  if ($provider === 'custom') {
    return kwik_ai_tags_test_ollama_connection();
  } elseif ($provider === 'openrouter') {
    return kwik_ai_tags_test_openrouter_connection();
  } elseif ($provider === 'openai') {
    return kwik_ai_tags_test_openai_connection();
  }
  
  return 'Unknown provider';
}

/**
 * Settings page
 */
function kwik_ai_tags_settings_page()
{
  if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', KWIK_AI_DOMAIN));
  }
  
  ?>
  <div class="wrap kwik-ai-tags-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="card">
      <h2><?php esc_html_e('Plugin Information', KWIK_AI_DOMAIN); ?></h2>
      <p><?php esc_html_e('KWIK AI automatically generates relevant tags and descriptions for your content using AI image analysis.', KWIK_AI_DOMAIN); ?></p>
      
      <h3><?php esc_html_e('Requirements', KWIK_AI_DOMAIN); ?></h3>
      <ul>
        <li><?php esc_html_e('AI provider configured (Custom, OpenRouter, or OpenAI)', KWIK_AI_DOMAIN); ?></li>
        <li><?php esc_html_e('Vision-capable model for image analysis (e.g., gemma3:27b, llava:13b, gpt-4o)', KWIK_AI_DOMAIN); ?></li>
        <li><?php esc_html_e('Posts with images or at least 50 words of text content', KWIK_AI_DOMAIN); ?></li>
      </ul>
      
      <h3><?php esc_html_e('Current Status', KWIK_AI_DOMAIN); ?></h3>
      <p>
        <strong><?php esc_html_e('AI Provider:', KWIK_AI_DOMAIN); ?></strong>
        <code><?php
          $provider = get_option('kwik_ai_ai_provider', 'custom');
          $provider_labels = array(
            'custom' => 'Custom',
            'openrouter' => 'OpenRouter',
            'openai' => 'OpenAI',
          );
          echo esc_html(isset($provider_labels[$provider]) ? $provider_labels[$provider] : $provider);
        ?></code>
      </p>
      <p>
        <strong><?php esc_html_e('API Endpoint:', KWIK_AI_DOMAIN); ?></strong>
        <code><?php
          $endpoint = get_option('kwik_ai_api_endpoint', 'http://localhost:11434');
          echo esc_html($endpoint);
        ?></code>
      </p>
      <p>
        <strong><?php esc_html_e('Selected Model:', KWIK_AI_DOMAIN); ?></strong>
        <code><?php
          $selected_model = get_option('kwik_ai_model', 'gemma3:27b');
          $has_vision = kwik_ai_tags_model_has_vision($selected_model);
          echo esc_html($selected_model);
          if ($has_vision) {
            echo ' <span class="kwik-ai-tags-status-connected">(' . esc_html__('Vision Capable', KWIK_AI_DOMAIN) . ')</span>';
          } else {
            echo ' <span class="kwik-ai-tags-status-error">(' . esc_html__('No Vision Support', KWIK_AI_DOMAIN) . ')</span>';
          }
        ?></code>
      </p>
      <p>
        <strong><?php esc_html_e('Connection Status:', KWIK_AI_DOMAIN); ?></strong>
        <?php
        $connection_status = kwik_ai_tags_test_provider_connection();
        if ($connection_status === true) {
          echo '<span class="kwik-ai-tags-status-connected"><span class="kwik-ai-tags-status-icon">✓</span>' . esc_html__('Connected', KWIK_AI_DOMAIN) . '</span>';
        } else {
          echo '<span class="kwik-ai-tags-status-error"><span class="kwik-ai-tags-status-icon">✗</span>' . esc_html($connection_status) . '</span>';
        }
        ?>
      </p>
    </div>
    
    <form action="options.php" method="post">
      <?php
      settings_fields('kwik_ai_tags_settings');
      do_settings_sections('kwik_ai_tags_settings');
      submit_button();
      ?>
    </form>
  </div>
  <?php
}

/**
 * Add settings link to plugin page
 */
function kwik_ai_tags_add_settings_link($links)
{
  $settings_link = sprintf(
    '<a href="%s">%s</a>',
    esc_url(admin_url('options-general.php?page=kwik-ai-tags-settings')),
    esc_html__('Settings', KWIK_AI_DOMAIN)
  );
  
  array_unshift($links, $settings_link);
  return $links;
}
