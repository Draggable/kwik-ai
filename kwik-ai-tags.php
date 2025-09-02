<?php
/**
 * Plugin Name: KWIK‑AI‑TAGS
 * Description: Auto‑tag posts with the Gemma3:27b model via Ollama. Analyzes both images and text content (50+ words) with preview functionality.
 * Version: 2.2
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

// Include debug test if enabled
if (defined('WP_DEBUG') && WP_DEBUG) {
  include_once __DIR__ . '/debug-test.php';
}



/**
 * Constants
 */
define('KWIK_AI_TAGS_OLLAMA_HOST', 'http://localhost:11434'); // Change if Ollama runs elsewhere.
define('KWIK_AI_TAGS_MAX_TAGS', 8); // Max tags to apply per post (increased for text+image)
define('KWIK_AI_TAGS_MIN_WORDS', 50); // Minimum words required for text analysis

/**
 * Initialize the plugin
 */
add_action('init', 'kwik_ai_tags_init');
function kwik_ai_tags_init()
{
  error_log('KWIK AI Tags: Plugin initializing');
  
  // Add meta box for AI tags preview
  add_action('add_meta_boxes', 'kwik_ai_tags_add_meta_box');
  
  // Enqueue scripts and styles
  add_action('admin_enqueue_scripts', 'kwik_ai_tags_enqueue_scripts');
  
  // AJAX handlers
  add_action('wp_ajax_kwik_ai_tags_generate', 'kwik_ai_tags_ajax_generate');
  add_action('wp_ajax_kwik_ai_tags_apply', 'kwik_ai_tags_ajax_apply');
  
  error_log('KWIK AI Tags: Plugin initialized with hooks');
}

/**
 * Add meta box to post editor
 */
function kwik_ai_tags_add_meta_box()
{
  error_log('KWIK AI Tags: Adding meta box');
  
  add_meta_box(
    'kwik-ai-tags-preview-box',
    __('AI Tags', 'kwik-ai-tags'),
    'kwik_ai_tags_meta_box_callback',
    'post',
    'side',
    'high'
  );
  
  error_log('KWIK AI Tags: Meta box added');
}

/**
 * Meta box callback function
 */
function kwik_ai_tags_meta_box_callback($post)
{
  error_log('KWIK AI Tags: Meta box callback called for post ' . $post->ID);
  
  wp_nonce_field('kwik_ai_tags_meta_box', 'kwik_ai_tags_nonce');
  ?>
  <div id="kwik-ai-tags-container">
    <p>
      <button type="button" id="kwik-ai-tags-generate" class="button button-secondary">
        <?php esc_html_e('Generate AI Tags', 'kwik-ai-tags'); ?>
      </button>
    </p>
    
    <div id="kwik-ai-tags-loading" style="display: none;">
      <p><?php esc_html_e('Analyzing content...', 'kwik-ai-tags'); ?></p>
      <div class="kwik-ai-tags-spinner"></div>
    </div>
    
    <div id="kwik-ai-tags-preview" style="display: none;">
      <h4><?php esc_html_e('Suggested Tags:', 'kwik-ai-tags'); ?></h4>
      <div id="kwik-ai-tags-list"></div>
      <p>
        <button type="button" id="kwik-ai-tags-apply" class="button button-primary">
          <?php esc_html_e('Apply Tags', 'kwik-ai-tags'); ?>
        </button>
        <button type="button" id="kwik-ai-tags-regenerate" class="button button-secondary">
          <?php esc_html_e('Regenerate', 'kwik-ai-tags'); ?>
        </button>
      </p>
    </div>
    
    <div id="kwik-ai-tags-error" style="display: none;">
      <p class="error-message"></p>
    </div>
    
    <?php if (WP_DEBUG): ?>
    <div id="kwik-ai-tags-debug" style="margin-top: 10px; font-size: 11px; color: #666;">
      <strong>Debug Info:</strong><br>
      Post ID: <?php echo esc_html($post->ID); ?><br>
      Ajax URL: <?php echo esc_html(admin_url('admin-ajax.php')); ?><br>
      Ollama Host: <?php echo esc_html(KWIK_AI_TAGS_OLLAMA_HOST); ?><br>
      Attached Images: <?php 
        $attachments = get_attached_media('image', $post->ID);
        echo esc_html(count($attachments));
      ?><br>
      Word Count: <?php 
        $content = get_post_field('post_content', $post->ID);
        $word_count = str_word_count(wp_strip_all_tags($content));
        echo esc_html($word_count);
        echo $word_count >= KWIK_AI_TAGS_MIN_WORDS ? ' (✓ text analysis enabled)' : ' (text analysis disabled)';
      ?>
    </div>
    <?php endif; ?>
  </div>
  <?php
  
  error_log('KWIK AI Tags: Meta box HTML rendered');
}

/**
 * Enqueue scripts and styles for the admin
 */
function kwik_ai_tags_enqueue_scripts($hook)
{
  // Debug logging
  error_log('KWIK AI Tags: Hook = ' . $hook);
  
  if ($hook !== 'post.php' && $hook !== 'post-new.php') {
    error_log('KWIK AI Tags: Wrong hook, returning');
    return;
  }
  
  $screen = get_current_screen();
  error_log('KWIK AI Tags: Screen post_type = ' . $screen->post_type);
  
  if ($screen->post_type !== 'post') {
    error_log('KWIK AI Tags: Wrong post type, returning');
    return;
  }
  
  $js_url = plugin_dir_url(__FILE__) . 'assets/admin.js';
  $css_url = plugin_dir_url(__FILE__) . 'assets/admin.css';
  
  error_log('KWIK AI Tags: Enqueueing JS from: ' . $js_url);
  error_log('KWIK AI Tags: Enqueueing CSS from: ' . $css_url);
  
  wp_enqueue_script(
    'kwik-ai-tags-admin',
    $js_url,
    ['jquery'],
    '2.2.0',
    true
  );
  
  wp_enqueue_style(
    'kwik-ai-tags-admin',
    $css_url,
    [],
    '2.2.0'
  );
  
  $post_id = get_the_ID();
  error_log('KWIK AI Tags: Post ID = ' . $post_id);
  
  wp_localize_script('kwik-ai-tags-admin', 'kwikAiTags', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('kwik_ai_tags_ajax'),
    'postId' => $post_id,
    'debug' => WP_DEBUG,
    'strings' => [
      'error' => __('An error occurred. Please try again.', 'kwik-ai-tags'),
      'noContent' => __('No images or insufficient text found. Please add images or write at least 50 words.', 'kwik-ai-tags'),
      'success' => __('Tags applied successfully!', 'kwik-ai-tags'),
    ]
  ]);
  
  error_log('KWIK AI Tags: Scripts and styles enqueued successfully');
}

/**
 * AJAX handler for generating tags
 */
function kwik_ai_tags_ajax_generate()
{
  error_log('KWIK AI Tags: Generate AJAX called');
  error_log('KWIK AI Tags: POST data: ' . print_r($_POST, true));
  
  check_ajax_referer('kwik_ai_tags_ajax', 'nonce');
  
  if (!current_user_can('edit_posts')) {
    error_log('KWIK AI Tags: User does not have edit_posts capability');
    wp_die(__('You do not have sufficient permissions.', 'kwik-ai-tags'));
  }
  
  $post_id = intval($_POST['post_id']);
  error_log('KWIK AI Tags: Post ID: ' . $post_id);
  
  if (!$post_id || get_post_status($post_id) === false) {
    error_log('KWIK AI Tags: Invalid post ID');
    wp_send_json_error(__('Invalid post ID.', 'kwik-ai-tags'));
  }
  
  // Check if post has images or sufficient text content
  $attachments = get_attached_media('image', $post_id);
  $post_content = get_post_field('post_content', $post_id);
  $word_count = str_word_count(wp_strip_all_tags($post_content));
  
  error_log('KWIK AI Tags: Found ' . count($attachments) . ' images and ' . $word_count . ' words');
  
  if (empty($attachments) && $word_count < KWIK_AI_TAGS_MIN_WORDS) {
    error_log('KWIK AI Tags: Insufficient content for analysis');
    wp_send_json_error(__('Please add images or write at least 50 words to generate AI tags.', 'kwik-ai-tags'));
  }
  
  error_log('KWIK AI Tags: Calling kwik_ai_tags_generate_for_post');
  $tags = kwik_ai_tags_generate_for_post($post_id);
  error_log('KWIK AI Tags: Generated tags: ' . print_r($tags, true));
  
  if ($tags === false) {
    error_log('KWIK AI Tags: Tag generation failed');
    wp_send_json_error(__('Failed to generate tags. Please check if Ollama is running and try again.', 'kwik-ai-tags'));
  }
  
  if (empty($tags)) {
    error_log('KWIK AI Tags: No tags generated');
    wp_send_json_error(__('No tags were generated. Try adding more descriptive images.', 'kwik-ai-tags'));
  }
  
  error_log('KWIK AI Tags: Sending success response');
  wp_send_json_success(['tags' => $tags]);
}

/**
 * AJAX handler for applying tags
 */
function kwik_ai_tags_ajax_apply()
{
  check_ajax_referer('kwik_ai_tags_ajax', 'nonce');
  
  if (!current_user_can('edit_posts')) {
    wp_die(__('You do not have sufficient permissions.', 'kwik-ai-tags'));
  }
  
  $post_id = intval($_POST['post_id']);
  $tags = sanitize_text_field($_POST['tags']);
  
  if (!$post_id || get_post_status($post_id) === false) {
    wp_send_json_error(__('Invalid post ID.', 'kwik-ai-tags'));
  }
  
  if (!$tags) {
    wp_send_json_error(__('No tags provided.', 'kwik-ai-tags'));
  }
  
  $tag_array = array_filter(array_map('trim', explode(',', $tags)));
  
  if (empty($tag_array)) {
    wp_send_json_error(__('No valid tags to apply.', 'kwik-ai-tags'));
  }
  
  // Sanitize each tag
  $tag_array = array_map('sanitize_text_field', $tag_array);
  
  $result = wp_set_post_terms($post_id, $tag_array, 'post_tag', true);
  
  if (is_wp_error($result)) {
    wp_send_json_error($result->get_error_message());
  }
  
  wp_send_json_success(sprintf(__('Successfully applied %d tags to the post!', 'kwik-ai-tags'), count($tag_array)));
}

/**
 * Generate tags for a specific post from both images and text content
 *
 * @param int $post_id
 * @return array|false
 */
function kwik_ai_tags_generate_for_post(int $post_id)
{
  error_log('KWIK AI Tags: Starting tag generation for post ' . $post_id);
  
  $all_tags = [];
  
  /* ----------------------------------------------------- */
  /* 1. Try to get tags from images */
  /* ----------------------------------------------------- */
  $attachments = get_attached_media('image', $post_id);
  error_log('KWIK AI Tags: Found ' . count($attachments) . ' attachments');
  
  if (!empty($attachments)) {
    $image_tags = kwik_ai_tags_generate_from_images($post_id, $attachments);
    if ($image_tags) {
      $all_tags = array_merge($all_tags, $image_tags);
      error_log('KWIK AI Tags: Generated ' . count($image_tags) . ' tags from images: ' . implode(', ', $image_tags));
    }
  }
  
  /* ----------------------------------------------------- */
  /* 2. Try to get tags from text content */
  /* ----------------------------------------------------- */
  $post_content = get_post_field('post_content', $post_id);
  $word_count = str_word_count(wp_strip_all_tags($post_content));
  
  error_log('KWIK AI Tags: Post has ' . $word_count . ' words');
  
  if ($word_count >= KWIK_AI_TAGS_MIN_WORDS) {
    $text_tags = kwik_ai_tags_generate_from_text($post_id, $post_content);
    if ($text_tags) {
      $all_tags = array_merge($all_tags, $text_tags);
      error_log('KWIK AI Tags: Generated ' . count($text_tags) . ' tags from text: ' . implode(', ', $text_tags));
    }
  }
  
  /* ----------------------------------------------------- */
  /* 3. Combine, deduplicate, and limit tags */
  /* ----------------------------------------------------- */
  if (empty($all_tags)) {
    error_log('KWIK AI Tags: No tags generated from any source');
    return false;
  }
  
  // Remove duplicates and limit to max tags
  $unique_tags = array_unique($all_tags);
  $final_tags = array_slice($unique_tags, 0, KWIK_AI_TAGS_MAX_TAGS);
  
  error_log('KWIK AI Tags: Final tags (' . count($final_tags) . '): ' . implode(', ', $final_tags));
  
  return $final_tags;
}

/**
 * Generate tags from post images
 *
 * @param int $post_id
 * @param array $attachments
 * @return array|false
 */
function kwik_ai_tags_generate_from_images(int $post_id, array $attachments)
{
  error_log('KWIK AI Tags: Generating tags from ' . count($attachments) . ' images');
  
  /* ----------------------------------------------------- */
  /* 1. Convert each image URL to a Base‑64 data URI */
  /* ----------------------------------------------------- */
  $data_uris = [];
  foreach ($attachments as $attachment) {
    $image_url = wp_get_attachment_url($attachment->ID);
    error_log('KWIK AI Tags: Processing image: ' . $image_url);
    
    if (!$image_url) {
      error_log('KWIK AI Tags: Could not get URL for attachment ' . $attachment->ID);
      continue;
    }

    $data_uri = kwik_ai_tags_image_to_data_uri($image_url);
    if ($data_uri) {
      $data_uris[] = $data_uri;
      error_log('KWIK AI Tags: Successfully converted image to data URI');
    } else {
      error_log('KWIK AI Tags: Failed to convert image to data URI: ' . $image_url);
    }
  }

  error_log('KWIK AI Tags: Converted ' . count($data_uris) . ' images to data URIs');

  if (empty($data_uris)) {
    error_log('KWIK AI Tags: No data URIs generated from images');
    return false;
  }

  /* ----------------------------------------------------- */
  /* 2. Build prompt for images */
  /* ----------------------------------------------------- */
  $prompt = 'Analyze these images and generate up to 5 concise, relevant tags that describe the main subjects, objects, activities, or themes shown. Focus on nouns and descriptive terms. Respond with a comma‑separated list only, no extra text.';
  error_log('KWIK AI Tags: Using image prompt: ' . $prompt);

  /* ----------------------------------------------------- */
  /* 3. Send request to Ollama */
  /* ----------------------------------------------------- */
  error_log('KWIK AI Tags: Sending image request to Ollama');
  $raw_response = kwik_ai_tags_query_ollama($prompt, $data_uris);
  error_log('KWIK AI Tags: Ollama image response: ' . ($raw_response ?: 'NULL'));
  
  if (!$raw_response) {
    error_log('KWIK AI Tags: No response from Ollama for images');
    return false;
  }

  /* ----------------------------------------------------- */
  /* 4. Parse tags */
  /* ----------------------------------------------------- */
  $tags = kwik_ai_tags_parse_tags($raw_response);
  error_log('KWIK AI Tags: Parsed image tags: ' . print_r($tags, true));
  
  return $tags;
}

/**
 * Generate tags from post text content
 *
 * @param int $post_id
 * @param string $content
 * @return array|false
 */
function kwik_ai_tags_generate_from_text(int $post_id, string $content)
{
  error_log('KWIK AI Tags: Generating tags from text content');
  
  // Clean and prepare the text
  $clean_text = wp_strip_all_tags($content);
  $clean_text = preg_replace('/\s+/', ' ', $clean_text); // Normalize whitespace
  $clean_text = trim($clean_text);
  
  // Limit text length to avoid overwhelming the model
  if (strlen($clean_text) > 2000) {
    $clean_text = substr($clean_text, 0, 2000) . '...';
    error_log('KWIK AI Tags: Truncated text to 2000 characters');
  }
  
  error_log('KWIK AI Tags: Analyzing ' . str_word_count($clean_text) . ' words');
  
  /* ----------------------------------------------------- */
  /* 1. Build prompt for text analysis */
  /* ----------------------------------------------------- */
  $prompt = 'Analyze this article text and generate up to 5 relevant tags that capture the main topics, themes, subjects, or keywords. Focus on the most important concepts discussed. Respond with a comma‑separated list only, no extra text.

Article text:
' . $clean_text;
  
  error_log('KWIK AI Tags: Using text prompt (first 200 chars): ' . substr($prompt, 0, 200) . '...');

  /* ----------------------------------------------------- */
  /* 2. Send request to Ollama (text-only, no images) */
  /* ----------------------------------------------------- */
  error_log('KWIK AI Tags: Sending text request to Ollama');
  $raw_response = kwik_ai_tags_query_ollama($prompt, []); // Empty images array
  error_log('KWIK AI Tags: Ollama text response: ' . ($raw_response ?: 'NULL'));
  
  if (!$raw_response) {
    error_log('KWIK AI Tags: No response from Ollama for text');
    return false;
  }

  /* ----------------------------------------------------- */
  /* 3. Parse tags */
  /* ----------------------------------------------------- */
  $tags = kwik_ai_tags_parse_tags($raw_response);
  error_log('KWIK AI Tags: Parsed text tags: ' . print_r($tags, true));
  
  return $tags;
}

/**
 * Convert an image URL to a Base‑64 data URI.
 * Multiple methods for better compatibility.
 *
 * @param string $url
 * @return string|null
 */
function kwik_ai_tags_image_to_data_uri(string $url): ?string
{
  error_log('KWIK AI Tags: Converting image to data URI: ' . $url);
  
  // Method 1: Try WordPress HTTP API first (more reliable)
  $response = wp_remote_get($url, [
    'timeout' => 30,
    'user-agent' => 'WordPress/' . get_bloginfo('version'),
  ]);
  
  if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
    $image_data = wp_remote_retrieve_body($response);
    $content_type = wp_remote_retrieve_header($response, 'content-type');
    
    if ($image_data && $content_type) {
      error_log('KWIK AI Tags: Successfully got image via wp_remote_get, type: ' . $content_type);
      // Return just base64 data for Ollama (not full data URI)
      return base64_encode($image_data);
    }
  }
  
  error_log('KWIK AI Tags: wp_remote_get failed, trying file_get_contents');
  
  // Method 2: Try file_get_contents with context (original method improved)
  $context = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' => [
        'User-Agent: WordPress/' . get_bloginfo('version'),
        'Accept: image/*',
      ],
      'timeout' => 30,
    ]
  ]);
  
  $image_data = @file_get_contents($url, false, $context);
  if ($image_data !== false) {
    // Try to determine MIME type
    $mime_type = null;
    
    // Method 2a: Use finfo if available
    if (function_exists('finfo_open')) {
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      if ($finfo) {
        $mime_type = finfo_buffer($finfo, $image_data);
        finfo_close($finfo);
      }
    }
    
    // Method 2b: Use getimagesizefromstring if available
    if (!$mime_type && function_exists('getimagesizefromstring')) {
      $image_info = getimagesizefromstring($image_data);
      if ($image_info && isset($image_info['mime'])) {
        $mime_type = $image_info['mime'];
      }
    }
    
    // Method 2c: Guess from URL extension
    if (!$mime_type) {
      $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
      $mime_map = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        'svg' => 'image/svg+xml',
      ];
      $mime_type = $mime_map[$ext] ?? 'image/jpeg'; // Default to JPEG
    }
    
    error_log('KWIK AI Tags: Successfully got image via file_get_contents, type: ' . $mime_type);
    // Return just base64 data for Ollama (not full data URI)
    return base64_encode($image_data);
  }
  
  error_log('KWIK AI Tags: file_get_contents failed, trying cURL');
  
  // Method 3: Try cURL if available
  if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'WordPress/' . get_bloginfo('version'));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $image_data = curl_exec($ch);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($image_data !== false && $http_code === 200 && $content_type) {
      error_log('KWIK AI Tags: Successfully got image via cURL, type: ' . $content_type);
      // Return just base64 data for Ollama (not full data URI)
      return base64_encode($image_data);
    }
  }
  
  error_log('KWIK AI Tags: All methods failed for URL: ' . $url);
  
  // Method 4: If it's a local file path, try direct file access
  if (strpos($url, home_url()) === 0) {
    $file_path = str_replace(home_url(), ABSPATH, $url);
    $file_path = str_replace('//', '/', $file_path);
    
    error_log('KWIK AI Tags: Trying local file path: ' . $file_path);
    
    if (file_exists($file_path) && is_readable($file_path)) {
      $image_data = file_get_contents($file_path);
      if ($image_data !== false) {
        $mime_type = mime_content_type($file_path) ?: 'image/jpeg';
        error_log('KWIK AI Tags: Successfully got local file, type: ' . $mime_type);
        // Return just base64 data for Ollama (not full data URI)
        return base64_encode($image_data);
      }
    }
  }
  
  return null; // All methods failed
}

/**
 * Send a request to Ollama's /api/generate endpoint.
 *
 * @param string $prompt
 * @param array  $images   Array of data‑URI strings
 * @return string|null     Raw response string (e.g., "cat, outdoor")
 */
function kwik_ai_tags_query_ollama(string $prompt, array $images): ?string
{
  $url = KWIK_AI_TAGS_OLLAMA_HOST . '/api/generate';
  error_log('KWIK AI Tags: Ollama URL: ' . $url);
  error_log('KWIK AI Tags: Number of images: ' . count($images));
  
  $payload = [
    'model' => 'gemma3:27b',
    'prompt' => $prompt,
    'images' => $images,
    'stream' => false, // Important: disable streaming for easier parsing
  ];

  error_log('KWIK AI Tags: Payload (without images): ' . json_encode(array_merge($payload, ['images' => '[' . count($images) . ' images]'])));

  $response = wp_remote_post(
    $url,
    [
      'body' => wp_json_encode($payload),
      'headers' => ['Content-Type' => 'application/json'],
      'timeout' => 120, // Increased timeout for vision processing
    ]
  );

  if (is_wp_error($response)) {
    error_log('KWIK AI Tags: WP Error: ' . $response->get_error_message());
    return null; // Network or server error.
  }

  $response_code = wp_remote_retrieve_response_code($response);
  error_log('KWIK AI Tags: Response code: ' . $response_code);

  if ($response_code !== 200) {
    $body = wp_remote_retrieve_body($response);
    error_log('KWIK AI Tags: HTTP error: ' . $response_code . ' - Response body: ' . $body);
    return null;
  }

  $body = wp_remote_retrieve_body($response);
  error_log('KWIK AI Tags: Response body: ' . substr($body, 0, 500)); // Log first 500 chars
  
  $data = json_decode($body, true);
  
  if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('KWIK AI Tags: JSON decode error: ' . json_last_error_msg());
    
    // Try to handle streaming response manually
    $lines = explode("\n", trim($body));
    $full_response = '';
    
    foreach ($lines as $line) {
      $line = trim($line);
      if (empty($line)) continue;
      
      $json = json_decode($line, true);
      if ($json && isset($json['response'])) {
        $full_response .= $json['response'];
        
        // If this is the final chunk
        if (isset($json['done']) && $json['done'] === true) {
          break;
        }
      }
    }
    
    if (!empty($full_response)) {
      error_log('KWIK AI Tags: Reconstructed response: ' . $full_response);
      return trim($full_response);
    }
    
    return null;
  }

  if (!isset($data['response'])) {
    error_log('KWIK AI Tags: No response field in data: ' . print_r($data, true));
    return null;
  }

  return trim($data['response']);
}

/**
 * Parse the comma‑separated tag list from the model response.
 *
 * @param string $raw_response
 * @return array
 */
function kwik_ai_tags_parse_tags(string $raw_response): array
{
  // Clean up the response - remove quotes, extra whitespace, etc.
  $clean_response = trim($raw_response, '"\'');
  $clean_response = preg_replace('/\s*,\s*/', ',', $clean_response);
  
  $parts = array_filter(array_map('trim', explode(',', $clean_response)));
  
  // Clean each tag - remove quotes, excessive whitespace, and invalid characters
  $cleaned_parts = [];
  foreach ($parts as $part) {
    $tag = trim($part, '"\'');
    $tag = preg_replace('/[^\w\s-]/', '', $tag); // Keep only letters, numbers, spaces, hyphens
    $tag = preg_replace('/\s+/', ' ', $tag); // Normalize whitespace
    $tag = trim($tag);
    
    // Skip empty tags or tags that are too short/long
    if (!empty($tag) && strlen($tag) >= 2 && strlen($tag) <= 50) {
      $cleaned_parts[] = $tag;
    }
  }

  // Deduplicate (case-insensitive)
  $unique_tags = [];
  $lower_tags = [];
  foreach ($cleaned_parts as $tag) {
    $lower = strtolower($tag);
    if (!in_array($lower, $lower_tags)) {
      $unique_tags[] = $tag;
      $lower_tags[] = $lower;
    }
  }
  
  return $unique_tags;
}

/**
 * Test function to debug image conversion methods
 * Only available when WP_DEBUG is enabled
 */
function kwik_ai_tags_test_image_conversion($url = null)
{
  if (!WP_DEBUG) {
    return 'Debug mode not enabled';
  }
  
  if (!$url) {
    // Use a test image from the uploads directory
    $upload_dir = wp_upload_dir();
    $test_files = glob($upload_dir['basedir'] . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
    if (empty($test_files)) {
      return 'No test images found in uploads directory';
    }
    $url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $test_files[0]);
  }
  
  $result = kwik_ai_tags_image_to_data_uri($url);
  
  if ($result) {
    $data_size = strlen($result);
    $mime_start = strpos($result, 'data:') + 5;
    $mime_end = strpos($result, ';', $mime_start);
    $mime_type = substr($result, $mime_start, $mime_end - $mime_start);
    
    return sprintf(
      'Success! Converted %s to %s data URI (%s bytes)',
      basename($url),
      $mime_type,
      number_format($data_size)
    );
  } else {
    return 'Failed to convert: ' . $url;
  }
}

// Add admin action to test image conversion
if (WP_DEBUG && is_admin()) {
  add_action('wp_ajax_kwik_ai_tags_test_conversion', function() {
    if (!current_user_can('manage_options')) {
      wp_die('Insufficient permissions');
    }
    
    $url = sanitize_url($_POST['test_url'] ?? '');
    $result = kwik_ai_tags_test_image_conversion($url);
    
    wp_send_json_success($result);
  });
}
