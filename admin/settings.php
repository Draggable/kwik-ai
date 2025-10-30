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
        <li><?php esc_html_e('gemma3:27b model installed (run: ollama pull gemma3:27b)', KWIK_AI_DOMAIN); ?></li>
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
          echo '<span class="kwik-ai-tags-status-connected"><span class="kwik-ai-tags-status-icon">✓</span>' . esc_html__('Connected', KWIK_AI_DOMAIN) . '</span>';
        } else {
          echo '<span class="kwik-ai-tags-status-error"><span class="kwik-ai-tags-status-icon">✗</span>' . esc_html($ollama_status) . '</span>';
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