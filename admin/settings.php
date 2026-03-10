<?php
/**
 * Settings page functionality
 *
 * @package KwikAI
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
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
    '2.6'
  );

  // Enqueue settings JavaScript
  wp_enqueue_script(
    'kwik-ai-tags-settings',
    plugin_dir_url(KWIK_AI_PLUGIN_FILE) . 'assets/js/settings.js',
    array('jquery'),
    '2.6',
    true
  );

  wp_localize_script('kwik-ai-tags-settings', 'kwikAiSettings', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('kwik_ai_tags_ajax'),
    'strings' => [
      'loading' => __('Loading...', KWIK_AI_DOMAIN),
      'error' => __('Failed to fetch models. Please check your Ollama connection.', KWIK_AI_DOMAIN),
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
  register_setting(
    'kwik_ai_tags_settings',
    'kwik_ai_tags_enabled_post_types',
    array(
      'type' => 'array',
      'sanitize_callback' => 'kwik_ai_tags_sanitize_post_types',
      'default' => array('post', 'belt')
    )
  );

  register_setting(
    'kwik_ai_tags_settings',
    'kwik_ai_tags_ollama_url',
    array(
      'type' => 'string',
      'sanitize_callback' => 'esc_url_raw',
      'default' => 'http://localhost:11434'
    )
  );

  register_setting(
    'kwik_ai_tags_settings',
    'kwik_ai_tags_ollama_username',
    array(
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => ''
    )
  );

  register_setting(
    'kwik_ai_tags_settings',
    'kwik_ai_tags_ollama_password',
    array(
      'type' => 'string',
      'sanitize_callback' => 'kwik_ai_tags_sanitize_password',
      'default' => ''
    )
  );

  register_setting(
    'kwik_ai_tags_settings',
    'kwik_ai_tags_ollama_model',
    array(
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => 'gemma3:27b'
    )
  );

  add_settings_section(
    'kwik_ai_tags_main_section',
    __('Post Type Settings', KWIK_AI_DOMAIN),
    'kwik_ai_tags_settings_section_callback',
    'kwik_ai_tags_settings'
  );

  add_settings_section(
    'kwik_ai_tags_ollama_section',
    __('Ollama Settings', KWIK_AI_DOMAIN),
    'kwik_ai_tags_ollama_section_callback',
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
    'kwik_ai_tags_ollama_url',
    __('Ollama URL', KWIK_AI_DOMAIN),
    'kwik_ai_tags_ollama_url_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_ollama_section'
  );

  add_settings_field(
    'kwik_ai_tags_ollama_username',
    __('Username', KWIK_AI_DOMAIN),
    'kwik_ai_tags_ollama_username_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_ollama_section'
  );

  add_settings_field(
    'kwik_ai_tags_ollama_password',
    __('Password', KWIK_AI_DOMAIN),
    'kwik_ai_tags_ollama_password_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_ollama_section'
  );

  add_settings_field(
    'kwik_ai_tags_ollama_model',
    __('Model', KWIK_AI_DOMAIN),
    'kwik_ai_tags_ollama_model_callback',
    'kwik_ai_tags_settings',
    'kwik_ai_tags_ollama_section'
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
  // Store password as-is but trim whitespace
  return trim($input);
}

/**
 * Settings section callback
 */
function kwik_ai_tags_settings_section_callback()
{
  echo '<p>' . esc_html__('Choose which post types should have AI tag and description generation enabled.', KWIK_AI_DOMAIN) . '</p>';
}

/**
 * Ollama settings section callback
 */
function kwik_ai_tags_ollama_section_callback()
{
  echo '<p>' . esc_html__('Configure your Ollama instance connection details.', KWIK_AI_DOMAIN) . '</p>';
}

/**
 * Enabled post types field callback
 */
function kwik_ai_tags_enabled_post_types_callback()
{
  $enabled_post_types = get_option('kwik_ai_tags_enabled_post_types', array('post'));
  $post_types = get_post_types(array('public' => true), 'objects');
  
  echo '<fieldset>';
  echo '<legend class="screen-reader-text">' . esc_html__('Enabled Post Types', KWIK_AI_DOMAIN) . '</legend>';
  
  foreach ($post_types as $post_type) {
    // Skip attachment post type
    if ($post_type->name === 'attachment') {
      continue;
    }
    
    $checked = in_array($post_type->name, $enabled_post_types) ? 'checked="checked"' : '';
    
    printf(
      '<label><input type="checkbox" name="kwik_ai_tags_enabled_post_types[]" value="%s" %s /> %s</label><br />',
      esc_attr($post_type->name),
      $checked,
      esc_html($post_type->label)
    );
  }
  
  echo '</fieldset>';
  echo '<p class="description">' . esc_html__('Select the post types where you want AI tag and description generation to be available. The AI Tags and AI Description meta boxes will appear in the editor for these post types.', KWIK_AI_DOMAIN) . '</p>';
}

/**
 * Ollama URL field callback
 */
function kwik_ai_tags_ollama_url_callback()
{
  $url = get_option('kwik_ai_tags_ollama_url', 'http://localhost:11434');
  
  printf(
    '<input type="url" name="kwik_ai_tags_ollama_url" value="%s" class="regular-text" placeholder="http://localhost:11434" />',
    esc_attr($url)
  );
  echo '<p class="description">' . esc_html__('Enter the full URL of your Ollama instance (e.g., http://localhost:11434 or https://ollama.kevv.in)', KWIK_AI_DOMAIN) . '</p>';
}

/**
 * Ollama username field callback
 */
function kwik_ai_tags_ollama_username_callback()
{
  $username = get_option('kwik_ai_tags_ollama_username', '');
  
  printf(
    '<input type="text" name="kwik_ai_tags_ollama_username" value="%s" class="regular-text" />',
    esc_attr($username)
  );
  echo '<p class="description">' . esc_html__('Enter the username for basic authentication (leave blank if no authentication is required)', KWIK_AI_DOMAIN) . '</p>';
}

/**
 * Ollama password field callback
 */
function kwik_ai_tags_ollama_password_callback()
{
  $password = get_option('kwik_ai_tags_ollama_password', '');

  printf(
    '<input type="password" name="kwik_ai_tags_ollama_password" value="%s" class="regular-text" />',
    esc_attr($password)
  );
  echo '<p class="description">' . esc_html__('Enter the password for basic authentication (leave blank if no authentication is required)', KWIK_AI_DOMAIN) . '</p>';
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
 * Ollama model field callback
 */
function kwik_ai_tags_ollama_model_callback()
{
  $selected_model = get_option('kwik_ai_tags_ollama_model', 'gemma3:27b');
  $models = kwik_ai_tags_fetch_ollama_models();

  echo '<div class="kwik-ai-model-selector-wrapper">';

  if ($models === false) {
    // Ollama not reachable, show text input
    printf(
      '<input type="text" name="kwik_ai_tags_ollama_model" value="%s" class="regular-text" id="kwik-ai-model-input" />',
      esc_attr($selected_model)
    );
    echo '<p class="description">' . esc_html__('Enter the model name manually (e.g., gemma3:27b, llava:13b). Could not connect to Ollama to fetch available models.', KWIK_AI_DOMAIN) . '</p>';
  } else {
    // Show dropdown with models
    echo '<select name="kwik_ai_tags_ollama_model" id="kwik-ai-model-select" class="regular-text">';

    foreach ($models as $model) {
      $selected = selected($selected_model, $model['name'], false);
      $vision_indicator = $model['has_vision'] ? ' ' . __('(Vision)', KWIK_AI_DOMAIN) : '';
      $vision_class = $model['has_vision'] ? 'vision-model' : '';

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
    echo esc_html__('Select the model to use for AI tag and description generation.', KWIK_AI_DOMAIN);
    echo ' <strong>' . esc_html__('Models marked with "(Vision)" support image analysis.', KWIK_AI_DOMAIN) . '</strong>';
    echo '</p>';

    // Show warning if selected model doesn't have vision
    $selected_has_vision = false;
    foreach ($models as $model) {
      if ($model['name'] === $selected_model && $model['has_vision']) {
        $selected_has_vision = true;
        break;
      }
    }

    if (!$selected_has_vision) {
      echo '<div class="notice notice-warning inline" style="margin-top: 10px;">';
      echo '<p>' . esc_html__('Warning: The selected model may not support image analysis. For best results with this plugin, select a vision-capable model.', KWIK_AI_DOMAIN) . '</p>';
      echo '</div>';
    }
  }

  echo '</div>';
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
      <p><?php esc_html_e('KWIK AI automatically generates relevant tags and descriptions for your content using AI image analysis. Descriptions are appended to the post content.', KWIK_AI_DOMAIN); ?></p>
      
      <h3><?php esc_html_e('Requirements', KWIK_AI_DOMAIN); ?></h3>
      <ul>
        <li><?php esc_html_e('Ollama running with configurable URL (default: http://localhost:11434)', KWIK_AI_DOMAIN); ?></li>
        <li><?php esc_html_e('Vision-capable model installed (e.g., gemma3:27b, llava:13b, etc.)', KWIK_AI_DOMAIN); ?></li>
        <li><?php esc_html_e('Posts with images or at least 50 words of text content', KWIK_AI_DOMAIN); ?></li>
        <li><?php esc_html_e('Basic authentication credentials if your Ollama instance requires it', KWIK_AI_DOMAIN); ?></li>
      </ul>
      
      <h3><?php esc_html_e('Current Status', KWIK_AI_DOMAIN); ?></h3>
      <p>
        <strong><?php esc_html_e('Ollama Host:', KWIK_AI_DOMAIN); ?></strong>
        <code><?php
          $url = get_option('kwik_ai_tags_ollama_url', 'http://localhost:11434');
          $username = get_option('kwik_ai_tags_ollama_username', '');
          if (!empty($username)) {
            echo esc_html($username . '@' . $url);
          } else {
            echo esc_html($url);
          }
        ?></code>

        <?php
        // Test Ollama connection
        $ollama_status = kwik_ai_tags_test_ollama_connection();
        if ($ollama_status === true) {
          echo '<span class="kwik-ai-tags-status-connected"><span class="kwik-ai-tags-status-icon">?</span>' . esc_html__('Connected', KWIK_AI_DOMAIN) . '</span>';
        } else {
          echo '<span class="kwik-ai-tags-status-error"><span class="kwik-ai-tags-status-icon">?</span>' . esc_html($ollama_status) . '</span>';
        }
        ?>
      </p>
      <p>
        <strong><?php esc_html_e('Selected Model:', KWIK_AI_DOMAIN); ?></strong>
        <code><?php
          $selected_model = get_option('kwik_ai_tags_ollama_model', 'gemma3:27b');
          $has_vision = kwik_ai_tags_model_has_vision($selected_model);
          echo esc_html($selected_model);
          if ($has_vision) {
            echo ' <span class="kwik-ai-tags-status-connected">(' . esc_html__('Vision Capable', KWIK_AI_DOMAIN) . ')</span>';
          } else {
            echo ' <span class="kwik-ai-tags-status-error">(' . esc_html__('No Vision Support', KWIK_AI_DOMAIN) . ')</span>';
          }
        ?></code>
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

/**
 * Get current Ollama configuration
 */
function kwik_ai_tags_get_ollama_config()
{
  $url = get_option('kwik_ai_tags_ollama_url', 'http://localhost:11434');
  $username = get_option('kwik_ai_tags_ollama_username', '');
  $password = get_option('kwik_ai_tags_ollama_password', '');

  // Ensure URL doesn't have trailing slash
  $url = rtrim($url, '/');

  return array(
    'url' => $url,
    'username' => $username,
    'password' => $password,
    'has_auth' => !empty($username) && !empty($password)
  );
}

/**
 * Get configured Ollama URL
 */
function kwik_ai_tags_get_ollama_url()
{
  $config = kwik_ai_tags_get_ollama_config();
  return $config['url'];
}

/**
 * Get basic auth header for Ollama requests
 */
function kwik_ai_tags_get_ollama_auth_header()
{
  $config = kwik_ai_tags_get_ollama_config();
  
  if (!$config['has_auth']) {
    return array();
  }
  
  $credentials = $config['username'] . ':' . $config['password'];
  $encoded_credentials = base64_encode($credentials);
  
  return array('Authorization' => 'Basic ' . $encoded_credentials);
}