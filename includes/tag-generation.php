<?php
/**
 * Tag generation functions
 *
 * @package KwikAI
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Generate tags for a specific post from both images and text content
 *
 * @param int $post_id
 * @return array|false
 */
function kwik_ai_tags_generate_for_post(int $post_id)
{
  error_log('Kwik AI: Starting tag generation for post ' . $post_id);

  $all_tags = [];

  /* ----------------------------------------------------- */
  /* 1. Try to get tags from images */
  /* ----------------------------------------------------- */

  // Get both attached images and images from block content
  $image_urls = [];

  // Traditional attached images
  $attachments = get_attached_media('image', $post_id);
  error_log('Kwik AI: Found ' . count($attachments) . ' attached images');

  foreach ($attachments as $attachment) {
    $url = wp_get_attachment_url($attachment->ID);
    if ($url) {
      $image_urls[] = $url;
    }
  }

  // Images from block content (modern WordPress)
  $post_content = get_post_field('post_content', $post_id);
  $block_images = kwik_ai_tags_extract_block_images($post_content, $post_id);
  error_log('Kwik AI: Found ' . count($block_images) . ' block images');

  $image_urls = array_merge($image_urls, $block_images);
  $image_urls = array_unique($image_urls); // Remove duplicates
  $image_urls = kwik_ai_tags_deduplicate_sized_images($image_urls); // Remove sized duplicates

  error_log('Kwik AI: Total unique images: ' . count($image_urls));

  if (!empty($image_urls)) {
    $image_tags = kwik_ai_tags_generate_from_image_urls($post_id, $image_urls);
    if ($image_tags) {
      $all_tags = array_merge($all_tags, $image_tags);
      error_log('Kwik AI: Generated ' . count($image_tags) . ' tags from images: ' . implode(', ', $image_tags));
    }
  }

  /* ----------------------------------------------------- */
  /* 2. Try to get tags from text content */
  /* ----------------------------------------------------- */
  $post_content = get_post_field('post_content', $post_id);
  $word_count = str_word_count(wp_strip_all_tags($post_content));

  error_log('Kwik AI: Post has ' . $word_count . ' words');

  if ($word_count >= KWIK_AI_MIN_WORDS) {
    $text_tags = kwik_ai_tags_generate_from_text($post_id, $post_content);
    if ($text_tags) {
      $all_tags = array_merge($all_tags, $text_tags);
      error_log('Kwik AI: Generated ' . count($text_tags) . ' tags from text: ' . implode(', ', $text_tags));
    }
  }

  /* ----------------------------------------------------- */
  /* 3. Combine, deduplicate, and limit tags */
  /* ----------------------------------------------------- */
  if (empty($all_tags)) {
    error_log('Kwik AI: No tags generated from any source');
    return false;
  }

  // Remove duplicates and limit to max tags
  $unique_tags = array_unique($all_tags);
  $final_tags = array_slice($unique_tags, 0, KWIK_AI_MAX_TAGS);

  error_log('Kwik AI: Final tags (' . count($final_tags) . '): ' . implode(', ', $final_tags));

  return $final_tags;
}

/**
 * Generate tags from image URLs
 *
 * @param int $post_id
 * @param array $image_urls Array of image URLs
 * @return array|false
 */
function kwik_ai_tags_generate_from_image_urls(int $post_id, array $image_urls)
{
  error_log('Kwik AI: Generating tags from ' . count($image_urls) . ' image URLs');

  /* ----------------------------------------------------- */
  /* 1. Convert each image URL to a Base‑64 data URI */
  /* ----------------------------------------------------- */
  $data_uris = [];
  foreach ($image_urls as $image_url) {
    error_log('Kwik AI: Processing image URL: ' . $image_url);

    $data_uri = kwik_ai_tags_image_to_data_uri($image_url);
    if ($data_uri) {
      $data_uris[] = $data_uri;
      error_log('Kwik AI: Successfully converted image to data URI');
    } else {
      error_log('Kwik AI: Failed to convert image to data URI: ' . $image_url);
    }
  }

  error_log('Kwik AI: Converted ' . count($data_uris) . ' images to data URIs');

  if (empty($data_uris)) {
    error_log('Kwik AI: No data URIs generated from image URLs');
    return false;
  }

  /* ----------------------------------------------------- */
  /* 2. Build prompt for images */
  /* ----------------------------------------------------- */
  $prompt = 'Analyze these images and generate up to 5 concise, relevant tags that describe the main subjects, objects, activities, or themes shown. Focus on nouns and descriptive terms. Respond with a comma‑separated list only, no extra text.';
  error_log('Kwik AI: Using image prompt: ' . $prompt);

  /* ----------------------------------------------------- */
  /* 3. Send request to Ollama */
  /* ----------------------------------------------------- */
  error_log('Kwik AI: Sending image request to Ollama');
  $raw_response = kwik_ai_tags_query_ollama($prompt, $data_uris);
  error_log('Kwik AI: Ollama image response: ' . ($raw_response ?: 'NULL'));

  if (!$raw_response) {
    error_log('Kwik AI: No response from Ollama for images');
    return false;
  }

  /* ----------------------------------------------------- */
  /* 4. Parse tags */
  /* ----------------------------------------------------- */
  $tags = kwik_ai_tags_parse_tags($raw_response);
  error_log('Kwik AI: Parsed image tags: ' . print_r($tags, true));

  return $tags;
}

/**
 * Generate tags from post images (legacy function for backward compatibility)
 *
 * @param int $post_id
 * @param array $attachments
 * @return array|false
 */
function kwik_ai_tags_generate_from_images(int $post_id, array $attachments)
{
  error_log('Kwik AI: Generating tags from ' . count($attachments) . ' images (legacy method)');

  $image_urls = [];
  foreach ($attachments as $attachment) {
    $image_url = wp_get_attachment_url($attachment->ID);
    if ($image_url) {
      $image_urls[] = $image_url;
    }
  }

  return kwik_ai_tags_generate_from_image_urls($post_id, $image_urls);
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
  error_log('Kwik AI: Generating tags from text content');

  // Clean and prepare the text
  $clean_text = wp_strip_all_tags($content);
  $clean_text = preg_replace('/\s+/', ' ', $clean_text); // Normalize whitespace
  $clean_text = trim($clean_text);

  // Limit text length to avoid overwhelming the model
  if (strlen($clean_text) > 2000) {
    $clean_text = substr($clean_text, 0, 2000) . '...';
    error_log('Kwik AI: Truncated text to 2000 characters');
  }

  error_log('Kwik AI: Analyzing ' . str_word_count($clean_text) . ' words');

  /* ----------------------------------------------------- */
  /* 1. Build prompt for text analysis */
  /* ----------------------------------------------------- */
  $prompt = 'Analyze this article text and generate up to 5 relevant tags that capture the main topics, themes, subjects, or keywords. Focus on the most important concepts discussed. Respond with a comma‑separated list only, no extra text.

Article text:
' . $clean_text;

  error_log('Kwik AI: Using text prompt (first 200 chars): ' . substr($prompt, 0, 200) . '...');

  /* ----------------------------------------------------- */
  /* 2. Send request to Ollama (text-only, no images) */
  /* ----------------------------------------------------- */
  error_log('Kwik AI: Sending text request to Ollama');
  $raw_response = kwik_ai_tags_query_ollama($prompt, []); // Empty images array
  error_log('Kwik AI: Ollama text response: ' . ($raw_response ?: 'NULL'));

  if (!$raw_response) {
    error_log('Kwik AI: No response from Ollama for text');
    return false;
  }

  /* ----------------------------------------------------- */
  /* 3. Parse tags */
  /* ----------------------------------------------------- */
  $tags = kwik_ai_tags_parse_tags($raw_response);
  error_log('Kwik AI: Parsed text tags: ' . print_r($tags, true));

  return $tags;
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
    return sprintf(
      'Success! Converted %s to base64 data (%s bytes)',
      basename($url),
      number_format($data_size)
    );
  } else {
    return 'Failed to convert: ' . $url;
  }
}

/**
 * Test function to debug gallery shortcode parsing
 * Only available when WP_DEBUG is enabled
 */
function kwik_ai_tags_test_gallery_parsing($content = null)
{
  if (!WP_DEBUG) {
    return 'Debug mode not enabled';
  }

  if (!$content) {
    // Use test content with gallery shortcodes
    $content = '[gallery columns="5" ids="8587,8586,8588,8589,8590,8591"] and [gallery columns="3"]';
  }

  $image_urls = kwik_ai_tags_extract_gallery_shortcode_images($content, 1); // Use post_id=1 for testing

  return sprintf(
    'Test content: %s<br>Extracted %d image URLs: %s',
    esc_html($content),
    count($image_urls),
    esc_html(implode(', ', $image_urls))
  );
}