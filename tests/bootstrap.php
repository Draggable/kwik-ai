<?php
/**
 * PHPUnit Bootstrap File
 *
 * This file is loaded before any tests are run.
 * It sets up the test environment including WordPress mocks.
 */

if (!defined('KWIK_AI_TEST_MODE')) {
    define('KWIK_AI_TEST_MODE', true);
}

// Define ABSPATH if not already defined
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/wordpress/');
}

// Create necessary WordPress directories
if (!is_dir(ABSPATH)) {
    mkdir(ABSPATH, 0755, true);
}

// Mock WordPress functions and classes
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        // Return test defaults
        $defaults = array(
            'kwik_ai_ai_provider' => 'ollama',
            'kwik_ai_api_endpoint' => 'http://localhost:11434',
            'kwik_ai_model' => 'gemma3:27b',
            'kwik_ai_tags_enabled_post_types' => array('post', 'belt'),
            'kwik_ai_openrouter_api_key' => '',
            'kwik_ai_openai_api_key' => '',
            'kwik_ai_tags_ollama_username' => '',
            'kwik_ai_tags_ollama_password' => '',
            'kwik_ai_api_key' => '',
        );
        return isset($defaults[$option]) ? $defaults[$option] : $default;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '') {
        switch ($show) {
            case 'name':
                return 'Test Blog';
            case 'url':
                return 'http://example.com';
            default:
                return '';
        }
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'http://example.com' . $path;
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return $url;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim($str);
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        echo $text;
    }
}

if (!function_exists('_x')) {
    function _x($text, $context, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('sprintf')) {
    // WordPress may override sprintf in some cases
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $flags = 0, $depth = 512) {
        return json_encode($data, $flags, $depth);
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return true;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return false;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct($code = '', $message = '', $data = '') {}
        public function get_error_message() {
            return '';
        }
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = array()) {
        // Mock response
        return new class {
            public function get_error_message() { return 'Network error'; }
            public function get_error_code() { return 'http_request_failed'; }
        };
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = array()) {
        return new class {
            public function get_error_message() { return 'Network error'; }
            public function get_error_code() { return 'http_request_failed'; }
        };
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return 200;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return json_encode(array(
            'models' => array(
                array('name' => 'gemma3:27b'),
                array('name' => 'llava:13b'),
            )
        ));
    }
}

if (!function_exists('wp_remote_retrieve_response_message')) {
    function wp_remote_retrieve_response_message($response) {
        return 'OK';
    }
}

if (!function_exists('post_type_exists')) {
    function post_type_exists($post_type) {
        return in_array($post_type, array('post', 'page', 'belt'));
    }
}

if (!function_exists('get_post_types')) {
    function get_post_types($args = array(), $output = 'objects') {
        $post_types = array(
            'post' => (object) array('name' => 'post', 'label' => 'Posts'),
            'page' => (object) array('name' => 'page', 'label' => 'Pages'),
            'belt' => (object) array('name' => 'belt', 'label' => 'Belts'),
        );
        return $post_types;
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://example.com/wp-content/plugins/kwik-ai/';
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'http://example.com/wp-admin/' . $path;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('add_options_page')) {
    function add_options_page($page_title, $menu_title, $capability, $menu_slug, $callback = '') {
        return 'settings-page-hook';
    }
}

if (!function_exists('add_settings_section')) {
    function add_settings_section($id, $title, $callback, $page) {
        return true;
    }
}

if (!function_exists('add_settings_field')) {
    function add_settings_field($id, $title, $callback, $page, $section = '', $args = array()) {
        return true;
    }
}

if (!function_exists('register_setting')) {
    function register_setting($option_group, $option_name, $args = array()) {
        return true;
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n) {
        return true;
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all') {
        return true;
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false) {
        return true;
    }
}

if (!function_exists('add_meta_box')) {
    function add_meta_box($id, $title, $callback, $screen, $context = 'normal', $priority = 'default', $callback_args = null) {
        return true;
    }
}

if (!function_exists('register_block_type')) {
    function register_block_type($block_name, $args = array()) {
        return true;
    }
}

if (!function_exists('enqueue_block_editor_assets')) {
    function enqueue_block_editor_assets() {}
}

if (!function_exists('add_menu_page')) {
    function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null) {
        return 'menu-page-hook';
    }
}

if (!function_exists('add_submenu_page')) {
    function add_submenu_page($parent_slug, $page_title, $menu_title, $capability = 'manage_options', $menu_slug = '', $callback = '') {
        return 'submenu-page-hook';
    }
}

if (!function_exists('load_plugin_textdomain')) {
    function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = '') {
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action($tag, ...$args) {
        return null;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('did_action')) {
    function did_action($action) {
        return 0;
    }
}

// Load plugin files
require_once __DIR__ . '/../kwik-ai.php';
