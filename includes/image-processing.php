<?php
/**
 * Image processing functions
 *
 * @package KwikAI
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Remove duplicate images that are the same image at different sizes
 * For example: image.jpg and image-700x500.jpg are the same image
 *
 * @param array $image_urls Array of image URLs
 * @return array Deduplicated array with only original images
 */
function kwik_ai_tags_deduplicate_sized_images(array $image_urls): array
{
  $unique_images = [];
  $base_names = [];

  foreach ($image_urls as $url) {
    // Extract the base name without size suffix
    $base_name = preg_replace('/-\d+x\d+(\.[a-zA-Z]+)$/', '$1', $url);

    // If we haven't seen this base image before, add it
    if (!in_array($base_name, $base_names)) {
      $base_names[] = $base_name;
      // Prefer the original (non-sized) version if available
      if ($base_name === $url) {
        // This is the original image
        $unique_images[] = $url;
      } else {
        // This is a sized version, but it's the first we've seen of this image
        $unique_images[] = $url;
      }
    } else {
      // We've seen this base image before
      $existing_index = array_search($base_name, $base_names);

      // If the current URL is the original (no size suffix) and the existing one is sized,
      // replace the sized version with the original
      if ($base_name === $url && $unique_images[$existing_index] !== $url) {
        $unique_images[$existing_index] = $url;
      }
    }
  }

  return array_values($unique_images);
}

/**
 * Extract image URLs from WordPress content (blocks and shortcodes)
 *
 * @param string $content Post content
 * @param int $post_id Post ID for fallback gallery handling
 * @return array Array of image URLs found in content
 */
function kwik_ai_tags_extract_block_images(string $content, int $post_id = 0): array
{
  $image_urls = [];

  // First, extract images from gallery shortcodes
  $shortcode_images = kwik_ai_tags_extract_gallery_shortcode_images($content, $post_id);
  $image_urls = array_merge($image_urls, $shortcode_images);

  // Parse blocks if the content contains block markup
  if (has_blocks($content)) {
    $blocks = parse_blocks($content);
    $block_images = kwik_ai_tags_extract_images_from_blocks($blocks);
    $image_urls = array_merge($image_urls, $block_images);
    
    // Enhanced debug logging for TRB belt gallery blocks
    if (WP_DEBUG) {
      $trb_belt_galleries = 0;
      foreach ($blocks as $block) {
        if ($block['blockName'] === 'trb/belt-gallery') {
          $trb_belt_galleries++;
          kwik_ai_log('Kwik AI: Found TRB belt gallery block #' . $trb_belt_galleries);
          if (isset($block['attrs']['images']) && is_array($block['attrs']['images'])) {
            kwik_ai_log('Kwik AI: TRB belt gallery has ' . count($block['attrs']['images']) . ' items');
            foreach ($block['attrs']['images'] as $index => $image) {
              $log_data = [];
              if (isset($image['id'])) $log_data[] = 'id=' . $image['id'];
              if (isset($image['url'])) $log_data[] = 'url=' . $image['url'];
              if (isset($image['title'])) $log_data[] = 'title=' . $image['title'];
              kwik_ai_log('Kwik AI: TRB gallery item ' . $index . ': ' . implode(', ', $log_data));
            }
          }
        }
      }
    }
  } else {
    // Fallback: Extract images from HTML content using regex
    $html_images = kwik_ai_tags_extract_images_from_html($content);
    $image_urls = array_merge($image_urls, $html_images);
  }

  // Remove duplicates and deduplicate sized images
  $image_urls = array_unique($image_urls);
  $image_urls = kwik_ai_tags_deduplicate_sized_images($image_urls);

  kwik_ai_log('Kwik AI: Extracted ' . count($image_urls) . ' unique images from content (including ' . count($shortcode_images) . ' from gallery shortcodes)');
  return $image_urls;
}

/**
 * Extract image URLs from gallery shortcodes in content
 *
 * @param string $content Post content
 * @param int $post_id Post ID for fallback when no IDs specified
 * @return array Array of image URLs from gallery shortcodes
 */
function kwik_ai_tags_extract_gallery_shortcode_images(string $content, int $post_id = 0): array
{
  $image_urls = [];

  // Use WordPress shortcode regex to find gallery shortcodes
  $shortcode_regex = get_shortcode_regex(['gallery']);
  kwik_ai_log('Kwik AI: Extracted ' . strlen($shortcode_regex) . ' character shortcode regex');
  preg_match_all('/' . $shortcode_regex . '/s', $content, $matches);

  if (!empty($matches[0])) {
    foreach ($matches[0] as $shortcode_match) {
      // Extract shortcode attributes manually since shortcode_parse_atts expects just attributes
      $atts = [];

      // Extract attributes from the shortcode using regex
      if (preg_match('/\[gallery\s+([^]]+)\]/i', $shortcode_match, $attr_match)) {
        $attr_string = $attr_match[1];

        // Parse individual attributes
        if (preg_match_all('/(\w+)="([^"]*)"/', $attr_string, $attr_matches)) {
          foreach ($attr_matches[1] as $index => $key) {
            $atts[$key] = $attr_matches[2][$index];
          }
        }

        // Also handle attributes without quotes (like numbers)
        if (preg_match_all('/(\w+)=([^"\s]+)/', $attr_string, $attr_matches)) {
          foreach ($attr_matches[1] as $index => $key) {
            if (!isset($atts[$key])) { // Don't overwrite quoted attributes
              $atts[$key] = $attr_matches[2][$index];
            }
          }
        }
      }

      kwik_ai_log('Kwik AI: Found gallery shortcode with attributes: ' . wp_json_encode($atts));

      if ($atts) {
        // Check if specific image IDs are provided
        if (isset($atts['ids']) && !empty($atts['ids'])) {
          // Parse comma-separated IDs
          $ids = array_map('intval', array_filter(explode(',', $atts['ids'])));
          kwik_ai_log('Kwik AI: Gallery shortcode has ' . count($ids) . ' specified IDs');

          foreach ($ids as $attachment_id) {
            $url = wp_get_attachment_url($attachment_id);
            if ($url) {
              // Skip video files (galleries can contain both images and videos)
              if (!preg_match('/\.(mp4|webm|ogg|mov|avi)$/i', $url)) {
                $image_urls[] = $url;
              }
            }
          }

          kwik_ai_log('Kwik AI: Extracted ' . count($ids) . ' images from gallery shortcode with IDs');
        }
        // If no IDs specified, get all image attachments for the post
        elseif ($post_id > 0) {
          $attachments = get_attached_media('image', $post_id);

          foreach ($attachments as $attachment) {
            $url = wp_get_attachment_url($attachment->ID);
            if ($url) {
              $image_urls[] = $url;
            }
          }

          kwik_ai_log('Kwik AI: Extracted ' . count($attachments) . ' images from gallery shortcode (all post attachments)');
        }
      }
    }
  }

  return $image_urls;
}

/**
 * Recursively extract image URLs from parsed blocks
 *
 * @param array $blocks Parsed WordPress blocks
 * @return array Array of image URLs
 */
function kwik_ai_tags_extract_images_from_blocks(array $blocks): array
{
  $image_urls = [];

  foreach ($blocks as $block) {
    // Handle Image blocks
    if ($block['blockName'] === 'core/image') {
      if (isset($block['attrs']['id'])) {
        // Image block with attachment ID
        $url = wp_get_attachment_url($block['attrs']['id']);
        if ($url) {
          $image_urls[] = $url;
        }
      } elseif (isset($block['attrs']['url'])) {
        // Image block with direct URL
        $image_urls[] = $block['attrs']['url'];
      }
    }

    // Handle Gallery blocks
    elseif ($block['blockName'] === 'core/gallery') {
      if (isset($block['attrs']['ids']) && is_array($block['attrs']['ids'])) {
        foreach ($block['attrs']['ids'] as $id) {
          $url = wp_get_attachment_url($id);
          if ($url) {
            $image_urls[] = $url;
          }
        }
      }
    }

    // Handle TRB Belt Gallery blocks (custom block)
    elseif ($block['blockName'] === 'trb/belt-gallery') {
      if (isset($block['attrs']['images']) && is_array($block['attrs']['images'])) {
        foreach ($block['attrs']['images'] as $image) {
          // Try to get URL from attachment ID first (most reliable)
          if (isset($image['id']) && !empty($image['id'])) {
            $url = wp_get_attachment_url($image['id']);
            if ($url) {
              // Skip video files (belt gallery can contain both images and videos)
              if (!preg_match('/\.(mp4|webm|ogg|mov|avi)$/i', $url)) {
                $image_urls[] = $url;
              }
            }
          } 
          // Fallback to direct URL if available
          elseif (isset($image['url']) && !empty($image['url'])) {
            // Skip video files (belt gallery can contain both images and videos)
            if (!preg_match('/\.(mp4|webm|ogg|mov|avi)$/i', $image['url'])) {
              $image_urls[] = $image['url'];
            }
          }
        }
      }
    }

    // Handle Media & Text blocks
    elseif ($block['blockName'] === 'core/media-text') {
      if (isset($block['attrs']['mediaId'])) {
        $url = wp_get_attachment_url($block['attrs']['mediaId']);
        if ($url) {
          $image_urls[] = $url;
        }
      } elseif (isset($block['attrs']['mediaUrl'])) {
        $image_urls[] = $block['attrs']['mediaUrl'];
      }
    }

    // Handle Cover blocks
    elseif ($block['blockName'] === 'core/cover') {
      if (isset($block['attrs']['id'])) {
        $url = wp_get_attachment_url($block['attrs']['id']);
        if ($url) {
          $image_urls[] = $url;
        }
      } elseif (isset($block['attrs']['url'])) {
        $image_urls[] = $block['attrs']['url'];
      }
    }

    // Recursively check inner blocks
    if (isset($block['innerBlocks']) && is_array($block['innerBlocks'])) {
      $inner_images = kwik_ai_tags_extract_images_from_blocks($block['innerBlocks']);
      $image_urls = array_merge($image_urls, $inner_images);
    }

    // Fallback: extract images from rendered block content
    if (!empty($block['innerHTML'])) {
      $html_images = kwik_ai_tags_extract_images_from_html($block['innerHTML']);
      $image_urls = array_merge($image_urls, $html_images);
    }
  }

  return $image_urls;
}

/**
 * Extract image URLs from HTML content using regex
 *
 * @param string $html HTML content
 * @return array Array of image URLs
 */
function kwik_ai_tags_extract_images_from_html(string $html): array
{
  $image_urls = [];

  // Match img tags and extract src attributes
  preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches);

  if (!empty($matches[1])) {
    foreach ($matches[1] as $src) {
      // Convert relative URLs to absolute URLs
      if (strpos($src, 'http') !== 0) {
        if (strpos($src, '/') === 0) {
          $src = home_url($src);
        } else {
          $src = home_url('/' . $src);
        }
      }

      // Only include images from the same domain or uploads directory
      if (strpos($src, home_url()) === 0 || strpos($src, wp_upload_dir()['baseurl']) === 0) {
        $image_urls[] = $src;
      }
    }
  }

  return $image_urls;
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
  kwik_ai_log('Kwik AI: Converting image to data URI: ' . $url);

  // Method 1: Fetch via the WordPress HTTP API.
  $response = wp_remote_get($url, [
    'timeout' => 30,
    'redirection' => 5,
    'user-agent' => 'WordPress/' . get_bloginfo('version'),
  ]);

  if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
    $image_data = wp_remote_retrieve_body($response);
    $content_type = wp_remote_retrieve_header($response, 'content-type');

    if ($image_data && $content_type) {
      // Validate content is actually an image
      if (strpos($content_type, 'image/') === 0) {
        kwik_ai_log('Kwik AI: Successfully got image via wp_remote_get, type: ' . $content_type);
        return base64_encode($image_data);
      }
    }
  }

  kwik_ai_log('Kwik AI: wp_remote_get failed, trying local file path');

  // Method 2: If it's a local upload, try direct file access.
  $file_path = kwik_ai_tags_url_to_local_path($url);
  if ($file_path) {
    kwik_ai_log('Kwik AI: Trying local file path: ' . $file_path);

    if (file_exists($file_path) && is_readable($file_path)) {
      $image_data = file_get_contents($file_path);
      if ($image_data !== false) {
        $mime_type = mime_content_type($file_path) ?: 'image/jpeg';
        kwik_ai_log('Kwik AI: Successfully got local file, type: ' . $mime_type);
        // Return just base64 data for Ollama (not full data URI)
        return base64_encode($image_data);
      }
    }
  }

  return null; // All methods failed
}

/**
 * Resolve the local filesystem path for an image URL.
 *
 * Uses the attachment APIs and the configured uploads directory rather than
 * assuming the site URL maps directly onto ABSPATH, so it works on
 * subdirectory, multisite, and offloaded/custom uploads setups.
 *
 * @param string $url
 * @return string|null Absolute path, or null if the URL is not a local upload.
 */
function kwik_ai_tags_url_to_local_path(string $url): ?string
{
  // Prefer the attachment APIs when the URL maps to a known media item.
  $attachment_id = attachment_url_to_postid($url);
  if ($attachment_id) {
    $path = get_attached_file($attachment_id);
    if ($path) {
      return $path;
    }
  }

  // Fall back to mapping the uploads URL onto its filesystem directory. This
  // also covers resized/cropped variants that are not attachments themselves.
  $uploads = wp_upload_dir();
  if (
    empty($uploads['error'])
    && !empty($uploads['baseurl'])
    && strpos($url, $uploads['baseurl']) === 0
  ) {
    return $uploads['basedir'] . substr($url, strlen($uploads['baseurl']));
  }

  return null;
}