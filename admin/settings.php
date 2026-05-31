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
    __('KWIK AI Settings', 'kwik-ai-tags'),
    __('KWIK AI', 'kwik-ai-tags'),
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
  $plugin_dir = plugin_dir_path(KWIK_AI_PLUGIN_FILE);
  $css_path = $plugin_dir . 'assets/css/settings.css';
  $js_path = $plugin_dir . 'assets/js/settings.js';

  // Use file modification time for cache busting so asset changes are picked
  // up without manually bumping a version string.
  $css_ver = file_exists($css_path) ? filemtime($css_path) : '3.0';
  $js_ver = file_exists($js_path) ? filemtime($js_path) : '3.0';

  wp_enqueue_style(
    'kwik-ai-tags-settings',
    plugin_dir_url(KWIK_AI_PLUGIN_FILE) . 'assets/css/settings.css',
    array(),
    $css_ver
  );

  // Enqueue settings JavaScript
  wp_enqueue_script(
    'kwik-ai-tags-settings',
    plugin_dir_url(KWIK_AI_PLUGIN_FILE) . 'assets/js/settings.js',
    array('jquery'),
    $js_ver,
    true
  );

  wp_localize_script('kwik-ai-tags-settings', 'kwikAiSettings', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('kwik_ai_tags_ajax'),
    'strings' => [
      'loading' => __('Loading...', 'kwik-ai-tags'),
      'error' => __('Failed to fetch models. Please check your connection.', 'kwik-ai-tags'),
      'vision' => __('(Vision)', 'kwik-ai-tags'),
      'refresh' => __('Refresh Models', 'kwik-ai-tags'),
      'testing' => __('Testing connection...', 'kwik-ai-tags'),
      'testError' => __('Connection failed. Please check your endpoint URL and API key.', 'kwik-ai-tags'),
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

  // Text model setting (used for text-only generation; empty = use main model)
  register_setting(
    'kwik_ai_tags_settings',
    'kwik_ai_text_model',
    array(
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => ''
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

  // FAL.AI API key (encrypted for secure storage)
  register_setting(
    'kwik_ai_tags_settings',
    'kwik_ai_fal_api_key',
    array(
      'type' => 'string',
      'sanitize_callback' => 'kwik_ai_tags_sanitize_fal_api_key',
      'default' => ''
    )
  );

  // FAL.AI image model
  register_setting(
    'kwik_ai_tags_settings',
    'kwik_ai_fal_model',
    array(
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => KWIK_AI_FAL_DEFAULT_MODEL
    )
  );

  // FAL.AI image size
  register_setting(
    'kwik_ai_tags_settings',
    'kwik_ai_fal_image_size',
    array(
      'type' => 'string',
      'sanitize_callback' => 'kwik_ai_tags_sanitize_fal_image_size',
      'default' => KWIK_AI_FAL_DEFAULT_IMAGE_SIZE
    )
  );

  add_settings_section(
    'kwik_ai_tags_main_section',
    __('Post Type Settings', 'kwik-ai-tags'),
    'kwik_ai_tags_settings_section_callback',
    'kwik_ai_tags_settings'
  );

  add_settings_section(
    'kwik_ai_tags_provider_section',
    __('AI Provider Settings', 'kwik-ai-tags'),
    'kwik_ai_tags_provider_section_callback',
    'kwik_ai_tags_settings'
  );

  add_settings_section(
    'kwik_ai_tags_auth_section',
    __('Authentication', 'kwik-ai-tags'),
    'kwik_ai_tags_auth_section_callback',
    'kwik_ai_tags_settings'
  );

  add_settings_section(
    'kwik_ai_tags_fal_section',
    __('FAL.AI Image Generation', 'kwik-ai-tags'),
    'kwik_ai_tags_fal_section_callback',
    'kwik_ai_tags_settings'
  );

  add_settings_field(
    'kwik_ai_tags_enabled_post_types',
    __('Enabled Post Types', 'kwik-ai-tags'),
    'kwik_ai_tags_enabled_post_types_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_main_section'
  );

  add_settings_field(
    'kwik_ai_ai_provider',
    __('AI Provider', 'kwik-ai-tags'),
    'kwik_ai_ai_provider_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_provider_section'
  );

  add_settings_field(
    'kwik_ai_api_endpoint',
    __('API Endpoint', 'kwik-ai-tags'),
    'kwik_ai_api_endpoint_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_provider_section'
  );

  add_settings_field(
    'kwik_ai_model',
    __('Model', 'kwik-ai-tags'),
    'kwik_ai_model_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_provider_section'
  );

  add_settings_field(
    'kwik_ai_text_model',
    __('Text Model', 'kwik-ai-tags'),
    'kwik_ai_text_model_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_provider_section'
  );

  add_settings_field(
    'kwik_ai_custom_api_key',
    __('Custom API Key', 'kwik-ai-tags'),
    'kwik_ai_custom_api_key_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_auth_section'
  );

  add_settings_field(
    'kwik_ai_openrouter_api_key',
    __('OpenRouter API Key', 'kwik-ai-tags'),
    'kwik_ai_openrouter_api_key_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_auth_section'
  );

  add_settings_field(
    'kwik_ai_openai_api_key',
    __('OpenAI API Key', 'kwik-ai-tags'),
    'kwik_ai_openai_api_key_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_auth_section'
  );

  add_settings_field(
    'kwik_ai_fal_api_key',
    __('FAL.AI API Key', 'kwik-ai-tags'),
    'kwik_ai_fal_api_key_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_fal_section'
  );

  add_settings_field(
    'kwik_ai_fal_model',
    __('Image Model', 'kwik-ai-tags'),
    'kwik_ai_fal_model_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_fal_section'
  );

  add_settings_field(
    'kwik_ai_fal_image_size',
    __('Image Size', 'kwik-ai-tags'),
    'kwik_ai_fal_image_size_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_fal_section'
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
 * Encrypt an API key for storage by the Settings API.
 *
 * This is a sanitize_callback helper: it MUST return the value to be stored
 * and MUST NOT call update_option() itself. Calling update_option() on the
 * same option here re-triggers the sanitize_option_{$option} filter and causes
 * infinite recursion (and memory exhaustion). The encrypted value is returned
 * so the Settings API stores it; kwik_ai_retrieve_credential() decrypts on read.
 *
 * @param string $input Raw key submitted from the form
 * @return string Encrypted value to store (or plaintext if encryption unavailable)
 */
function kwik_ai_tags_encrypt_api_key_for_storage($input)
{
  $key = trim((string) $input);

  if ($key === '') {
    return '';
  }

  // Encrypt before storage if encryption utilities are available.
  if (function_exists('kwik_ai_encrypt')) {
    return kwik_ai_encrypt($key);
  }

  return $key;
}

/**
 * Sanitize OpenRouter API key setting (encrypts for secure storage).
 *
 * @param string $input
 * @return string
 */
function kwik_ai_tags_sanitize_openrouter_api_key($input)
{
  return kwik_ai_tags_encrypt_api_key_for_storage($input);
}

/**
 * Sanitize OpenAI API key setting (encrypts for secure storage).
 *
 * @param string $input
 * @return string
 */
function kwik_ai_tags_sanitize_openai_api_key($input)
{
  return kwik_ai_tags_encrypt_api_key_for_storage($input);
}

/**
 * Sanitize Custom/Ollama API key setting (encrypts for secure storage).
 *
 * @param string $input
 * @return string
 */
function kwik_ai_tags_sanitize_custom_api_key($input)
{
  return kwik_ai_tags_encrypt_api_key_for_storage($input);
}

/**
 * Sanitize FAL.AI API key setting (encrypts for secure storage).
 *
 * @param string $input
 * @return string
 */
function kwik_ai_tags_sanitize_fal_api_key($input)
{
  return kwik_ai_tags_encrypt_api_key_for_storage($input);
}

/**
 * Sanitize FAL.AI image size, restricting to supported enum values.
 *
 * @param string $input
 * @return string
 */
function kwik_ai_tags_sanitize_fal_image_size($input)
{
  $input = sanitize_text_field($input);
  $allowed = function_exists('kwik_ai_fal_get_image_sizes') ? kwik_ai_fal_get_image_sizes() : array();
  return isset($allowed[$input]) ? $input : KWIK_AI_FAL_DEFAULT_IMAGE_SIZE;
}

/**
 * Settings section callback
 */
function kwik_ai_tags_settings_section_callback()
{
  echo '<p>' . esc_html__('Choose which post types should have AI tag and description generation enabled.', 'kwik-ai-tags') . '</p>';
}

/**
 * Get the post types that should be offered on the settings page.
 *
 * Includes any post type with an admin editing UI (show_ui), so custom post
 * types registered by other plugins/themes appear even when they aren't
 * flagged "public". WordPress internal types that aren't user content
 * (media, block/template/navigation types) are excluded.
 *
 * @return WP_Post_Type[] Keyed by post type name.
 */
function kwik_ai_tags_get_selectable_post_types()
{
  $ui_types = get_post_types(array('show_ui' => true), 'objects');

  // WordPress internal post types that aren't editorial content.
  $excluded = array(
    'attachment',
    'wp_block',
    'wp_template',
    'wp_template_part',
    'wp_navigation',
    'wp_global_styles',
    'wp_font_family',
    'wp_font_face',
  );

  $post_types = array();
  foreach ($ui_types as $name => $object) {
    if (in_array($name, $excluded, true)) {
      continue;
    }
    $post_types[$name] = $object;
  }

  /**
   * Filter the list of post types offered for AI tag/description generation.
   *
   * @param WP_Post_Type[] $post_types Post types keyed by name.
   */
  return apply_filters('kwik_ai_tags_selectable_post_types', $post_types);
}

/**
 * Enabled Post Types field callback
 * Renders checkboxes for all selectable post types (built-in and custom).
 */
function kwik_ai_tags_enabled_post_types_callback()
{
  $enabled = get_option('kwik_ai_tags_enabled_post_types', array('post', 'belt'));
  if (!is_array($enabled)) {
    $enabled = array('post', 'belt');
  }

  $post_types = kwik_ai_tags_get_selectable_post_types();

  echo '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ccc; padding: 10px;">';
  foreach ($post_types as $pt) {
    $checked = in_array($pt->name, $enabled) ? 'checked' : '';
    $label = isset($pt->labels->singular_name) && $pt->labels->singular_name !== ''
      ? $pt->labels->singular_name
      : $pt->name;
    printf(
      '<label style="display: block; margin: 4px 0;"><input type="checkbox" name="kwik_ai_tags_enabled_post_types[]" value="%s" %s /> %s <code>%s</code></label>',
      esc_attr($pt->name),
      esc_attr($checked),
      esc_html($label),
      esc_html($pt->name)
    );
  }
  echo '</div>';
  echo '<p class="description">' . esc_html__('Select the post types where AI tag and description generation should be enabled. Custom post types added by other plugins or your theme are included here.', 'kwik-ai-tags') . '</p>';
}

/**
 * Provider settings section callback
 */
function kwik_ai_tags_provider_section_callback()
{
  echo '<p>' . esc_html__('Select your AI provider and configure the connection details.', 'kwik-ai-tags') . '</p>';
}

/**
 * Authentication section callback
 */
function kwik_ai_tags_auth_section_callback()
{
  $has_encryption = function_exists('kwik_ai_has_encryption') && kwik_ai_has_encryption();
  
  echo '<p>' . esc_html__('Enter credentials for your AI provider.', 'kwik-ai-tags') . '</p>';
  
  if ($has_encryption) {
    echo '<div class="notice notice-success inline" style="margin: 5px 0 0;"><p>' . 
      esc_html__('Credentials will be encrypted before storage.', 'kwik-ai-tags') . '</p></div>';
  } else {
    echo '<div class="notice notice-warning inline" style="margin: 5px 0 0;"><p>' . 
      esc_html__('Credentials are stored without encryption. Ensure your database is properly secured.', 'kwik-ai-tags') . '</p></div>';
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
    echo '<option value="' . esc_attr($value) . '" ' . selected($provider, $value, false) . '>' . esc_html($label) . '</option>';
  }
  
  echo '</select>';
  echo '<p class="description">';
  echo esc_html__('Custom requires a configured endpoint URL. OpenRouter and OpenAI require API keys.', 'kwik-ai-tags');
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
  echo esc_html__('For Custom: your API server URL (e.g., http://localhost:11434).', 'kwik-ai-tags');
  echo ' <strong>OpenRouter:</strong> https://openrouter.ai/api/v1';
  echo ' <strong>OpenAI:</strong> https://api.openai.com/v1';
  echo '</p>';
}

/**
 * Model field callback
 */
function kwik_ai_model_callback()
{
  $selected_model = get_option('kwik_ai_model', 'gemma3:27b');
  $models = kwik_ai_tags_fetch_models();

  if (!is_array($models)) {
    $models = array();
  }

  // Always keep the currently saved model selectable, even when the provider
  // is unreachable or the model isn't in the fetched list. This preserves the
  // saved value on submit and lets the user re-populate via "Refresh Models".
  $has_selected = false;
  foreach ($models as $model) {
    if ($model['name'] === $selected_model) {
      $has_selected = true;
      break;
    }
  }
  if (!$has_selected && $selected_model !== '') {
    array_unshift($models, array(
      'name' => $selected_model,
      'has_vision' => kwik_ai_tags_model_has_vision($selected_model),
    ));
  }

  echo '<div class="kwik-ai-model-selector-wrapper">';

  echo '<select name="kwik_ai_model" id="kwik-ai-model-select" class="regular-text">';
  foreach ($models as $model) {
    $has_vision = !empty($model['has_vision']);
    $vision_indicator = $has_vision ? ' ' . __('(Vision)', 'kwik-ai-tags') : '';
    $vision_class = $has_vision ? 'vision-model' : '';

    echo '<option value="' . esc_attr($model['name']) . '" ' . selected($selected_model, $model['name'], false) . ' class="' . esc_attr($vision_class) . '">' . esc_html($model['name'] . $vision_indicator) . '</option>';
  }
  echo '</select>';

  echo '<button type="button" class="button" id="kwik-ai-refresh-models" style="margin-left: 10px;">' . esc_html__('Refresh Models', 'kwik-ai-tags') . '</button>';
  echo '<button type="button" class="button" id="kwik-ai-test-connection" style="margin-left: 6px;">' . esc_html__('Test Connection', 'kwik-ai-tags') . '</button>';
  echo '<span id="kwik-ai-model-loading" style="display: none; margin-left: 10px;">' . esc_html__('Loading...', 'kwik-ai-tags') . '</span>';

  echo '<div id="kwik-ai-connection-result" class="kwik-ai-connection-result" style="display: none; margin-top: 10px;"></div>';

  echo '<p class="description">';
  echo esc_html__('Select the model to use for AI tag and description generation. Use "Refresh Models" to load the list from your provider, or "Test Connection" to verify your endpoint and API key without saving.', 'kwik-ai-tags');
  echo ' <strong>' . esc_html__('Models marked with "(Vision)" support image analysis.', 'kwik-ai-tags') . '</strong>';
  echo '</p>';

  echo '</div>';
}

/**
 * Text Model field callback
 *
 * Optional model used for text-only generation (featured-image prompts and
 * URL-based descriptions). Leaving it on "Use the main Model" reuses the model
 * above; pick a text/chat model here when the main model is vision-only.
 */
function kwik_ai_text_model_callback()
{
  $selected = get_option('kwik_ai_text_model', '');
  $models = kwik_ai_tags_fetch_models();

  if (!is_array($models)) {
    $models = array();
  }

  echo '<select name="kwik_ai_text_model" id="kwik-ai-text-model-select" class="regular-text">';
  echo '<option value="" ' . selected($selected, '', false) . '>' . esc_html__('Use the main Model (above)', 'kwik-ai-tags') . '</option>';

  // Keep the saved text model selectable even if it isn't in the fetched list.
  $has_selected = false;
  foreach ($models as $model) {
    if ($model['name'] === $selected) {
      $has_selected = true;
      break;
    }
  }
  if (!$has_selected && $selected !== '') {
    echo '<option value="' . esc_attr($selected) . '" selected>' . esc_html($selected) . '</option>';
  }

  foreach ($models as $model) {
    $has_vision = !empty($model['has_vision']);
    $vision_indicator = $has_vision ? ' ' . __('(Vision)', 'kwik-ai-tags') : '';
    echo '<option value="' . esc_attr($model['name']) . '" ' . selected($selected, $model['name'], false) . '>' . esc_html($model['name'] . $vision_indicator) . '</option>';
  }
  echo '</select>';

  echo '<p class="description">';
  echo esc_html__('Model used for text-only generation: featured-image prompts and URL-based descriptions. Leave on "Use the main Model" to reuse the model above. Choose a text/chat model here if your main Model is a vision model that cannot handle plain text.', 'kwik-ai-tags');
  echo '</p>';
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
  echo '<p class="description">' . esc_html__('Enter the API key for your custom provider endpoint (leave blank if no authentication is required)', 'kwik-ai-tags') . '</p>';
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
  echo esc_html__('Get your API key from ', 'kwik-ai-tags');
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
  echo esc_html__('Get your API key from ', 'kwik-ai-tags');
  echo '<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">OpenAI</a>.';
  echo '</p>';
}

/**
 * FAL.AI section callback
 */
function kwik_ai_tags_fal_section_callback()
{
  echo '<p>' . esc_html__('Generate featured images from your post content using FAL.AI. The post is scanned, an image prompt is crafted with your selected AI provider above, and the image is created by the FAL model below.', 'kwik-ai-tags') . '</p>';

  $configured = function_exists('kwik_ai_fal_has_api_key') && kwik_ai_fal_has_api_key();
  echo '<p><strong>' . esc_html__('FAL.AI Status:', 'kwik-ai-tags') . '</strong> ';
  if ($configured) {
    echo '<span class="kwik-ai-tags-status-connected"><span class="kwik-ai-tags-status-icon">✓</span>' . esc_html__('API key configured', 'kwik-ai-tags') . '</span>';
  } else {
    echo '<span class="kwik-ai-tags-status-error"><span class="kwik-ai-tags-status-icon">✗</span>' . esc_html__('No API key configured', 'kwik-ai-tags') . '</span>';
  }
  echo '</p>';
}

/**
 * FAL.AI API key field callback
 */
function kwik_ai_fal_api_key_callback()
{
  if (function_exists('kwik_ai_retrieve_credential')) {
    $api_key = kwik_ai_retrieve_credential('kwik_ai_fal_api_key', '');
  } else {
    $api_key = get_option('kwik_ai_fal_api_key', '');
  }

  printf(
    '<input type="password" name="kwik_ai_fal_api_key" value="%s" class="regular-text" autocomplete="new-password" />',
    esc_attr($api_key)
  );
  echo '<p class="description">';
  echo esc_html__('Get your API key from ', 'kwik-ai-tags');
  echo '<a href="https://fal.ai/dashboard/keys" target="_blank" rel="noopener">FAL.AI</a>.';
  echo '</p>';
}

/**
 * FAL.AI model field callback
 *
 * Populates the dropdown from FAL's live text-to-image catalog (cached), with a
 * nonce-protected link to refresh that list. Falls back to the curated list if
 * the catalog can't be fetched.
 */
function kwik_ai_fal_model_callback()
{
  // A nonce-protected "Refresh" link forces a refetch of the live model list.
  $force = false;
  if (isset($_GET['kwik_ai_fal_refresh_models'])) {
    check_admin_referer('kwik_ai_fal_refresh_models');
    $force = true;
  }

  $selected = kwik_ai_fal_get_model();
  $models = kwik_ai_fal_get_available_models($force);
  $is_live = !empty(kwik_ai_fal_fetch_models(false));

  echo '<select name="kwik_ai_fal_model" class="regular-text">';

  // Keep a custom/saved model selectable even if it isn't in the fetched list.
  if (!isset($models[$selected])) {
    echo '<option value="' . esc_attr($selected) . '" selected>' . esc_html($selected) . '</option>';
  }

  foreach ($models as $value => $label) {
    echo '<option value="' . esc_attr($value) . '" ' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
  }
  echo '</select>';

  $refresh_url = wp_nonce_url(
    add_query_arg(
      'kwik_ai_fal_refresh_models',
      '1',
      admin_url('options-general.php?page=kwik-ai-tags-settings')
    ),
    'kwik_ai_fal_refresh_models'
  );
  echo ' <a href="' . esc_url($refresh_url) . '" class="button">' . esc_html__('Refresh Models', 'kwik-ai-tags') . '</a>';

  echo '<p class="description">';
  echo esc_html__('The FAL.AI text-to-image model used to create featured images.', 'kwik-ai-tags');
  if ($is_live) {
    echo ' ' . sprintf(
      /* translators: %d: number of models */
      esc_html__('Showing %d models from FAL.AI.', 'kwik-ai-tags'),
      count($models)
    );
  } else {
    echo ' ' . esc_html__('Showing a built-in list (could not reach FAL.AI). Click "Refresh Models" to try again.', 'kwik-ai-tags');
  }
  echo '</p>';
}

/**
 * FAL.AI image size field callback
 */
function kwik_ai_fal_image_size_callback()
{
  $selected = kwik_ai_fal_get_image_size();
  $sizes = kwik_ai_fal_get_image_sizes();

  echo '<select name="kwik_ai_fal_image_size" class="regular-text">';
  foreach ($sizes as $value => $label) {
    echo '<option value="' . esc_attr($value) . '" ' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
  }
  echo '</select>';
  echo '<p class="description">' . esc_html__('Aspect ratio for generated featured images. Landscape works best for most themes.', 'kwik-ai-tags') . '</p>';
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
  $endpoint = get_option('kwik_ai_api_endpoint', '');
  $api_key = kwik_ai_tags_get_provider_api_key($provider);

  // Try to fetch the live model list from the provider's /models endpoint
  // (or Ollama's /api/tags for custom providers that use it).
  $models = kwik_ai_tags_fetch_models_live($provider, $endpoint, $api_key);
  if (is_array($models) && !empty($models)) {
    return $models;
  }

  // Fall back to a curated list for hosted providers when the live list
  // can't be retrieved (e.g. no API key entered yet).
  if ($provider === 'openrouter' || $provider === 'openai') {
    return kwik_ai_tags_get_provider_models($provider);
  }

  return false;
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
  $models = kwik_ai_tags_request_ollama_models($config['url'], $config['api_key']);

  return $models === false ? false : $models;
}

/**
 * Get the stored API key for a provider, using secure retrieval if available.
 *
 * @param string $provider
 * @return string
 */
function kwik_ai_tags_get_provider_api_key($provider)
{
  if ($provider === 'openrouter') {
    $option = 'kwik_ai_openrouter_api_key';
  } elseif ($provider === 'openai') {
    $option = 'kwik_ai_openai_api_key';
  } else {
    $option = 'kwik_ai_custom_api_key';
  }

  if (function_exists('kwik_ai_retrieve_credential')) {
    return kwik_ai_retrieve_credential($option, '');
  }

  return get_option($option, '');
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
    wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'kwik-ai-tags'));
  }
  
  ?>
  <div class="wrap kwik-ai-tags-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="card">
      <h2><?php esc_html_e('Plugin Information', 'kwik-ai-tags'); ?></h2>
      <p><?php esc_html_e('KWIK AI automatically generates relevant tags and descriptions for your content using AI image analysis.', 'kwik-ai-tags'); ?></p>
      
      <h3><?php esc_html_e('Requirements', 'kwik-ai-tags'); ?></h3>
      <ul>
        <li><?php esc_html_e('AI provider configured (Custom, OpenRouter, or OpenAI)', 'kwik-ai-tags'); ?></li>
        <li><?php esc_html_e('Vision-capable model for image analysis (e.g., gemma3:27b, llava:13b, gpt-4o)', 'kwik-ai-tags'); ?></li>
        <li><?php esc_html_e('Posts with images or at least 50 words of text content', 'kwik-ai-tags'); ?></li>
      </ul>
      
      <h3><?php esc_html_e('Current Status', 'kwik-ai-tags'); ?></h3>
      <p>
        <strong><?php esc_html_e('AI Provider:', 'kwik-ai-tags'); ?></strong>
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
        <strong><?php esc_html_e('API Endpoint:', 'kwik-ai-tags'); ?></strong>
        <code><?php
          $endpoint = get_option('kwik_ai_api_endpoint', 'http://localhost:11434');
          echo esc_html($endpoint);
        ?></code>
      </p>
      <p>
        <strong><?php esc_html_e('Selected Model:', 'kwik-ai-tags'); ?></strong>
        <code><?php
          $selected_model = get_option('kwik_ai_model', 'gemma3:27b');
          $has_vision = kwik_ai_tags_model_has_vision($selected_model);
          echo esc_html($selected_model);
          if ($has_vision) {
            echo ' <span class="kwik-ai-tags-status-connected">(' . esc_html__('Vision Capable', 'kwik-ai-tags') . ')</span>';
          } else {
            echo ' <span class="kwik-ai-tags-status-error">(' . esc_html__('No Vision Support', 'kwik-ai-tags') . ')</span>';
          }
        ?></code>
      </p>
      <p>
        <strong><?php esc_html_e('Connection Status:', 'kwik-ai-tags'); ?></strong>
        <?php
        $connection_status = kwik_ai_tags_test_provider_connection();
        if ($connection_status === true) {
          echo '<span class="kwik-ai-tags-status-connected"><span class="kwik-ai-tags-status-icon">✓</span>' . esc_html__('Connected', 'kwik-ai-tags') . '</span>';
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
    esc_html__('Settings', 'kwik-ai-tags')
  );
  
  array_unshift($links, $settings_link);
  return $links;
}
