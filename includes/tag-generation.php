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
  kwik_ai_log('Kwik AI: Starting tag generation for post ' . $post_id);

  $all_tags = [];

  $post_content = get_post_field('post_content', $post_id);

  /* ----------------------------------------------------- */
  /* 1. Try to get tags from images */
  /* ----------------------------------------------------- */

  // Get both attached images and images from block content
  $image_urls = [];

  // Traditional attached images. get_attached_media() returns every image
  // whose post_parent is this post — including old uploads that have since been
  // removed from the content. Send only the ones actually used by the post so
  // detached images aren't analyzed (and don't fail an OpenAI-compatible
  // endpoint that the orphaned request would otherwise hit).
  $attachments = get_attached_media('image', $post_id);
  $image_urls = kwik_ai_tags_filter_referenced_attachments($attachments, $post_content, $post_id);
  kwik_ai_log('Kwik AI: Using ' . count($image_urls) . ' of ' . count($attachments) . ' attached images referenced by the post');

  // Images from block content (modern WordPress)
  $block_images = kwik_ai_tags_extract_block_images($post_content, $post_id);
  kwik_ai_log('Kwik AI: Found ' . count($block_images) . ' block images');

  $image_urls = array_merge($image_urls, $block_images);
  $image_urls = array_unique($image_urls); // Remove duplicates
  $image_urls = kwik_ai_tags_deduplicate_sized_images($image_urls); // Remove sized duplicates

  kwik_ai_log('Kwik AI: Total unique images: ' . count($image_urls));

  if (!empty($image_urls)) {
    $image_tags = kwik_ai_tags_generate_from_image_urls($post_id, $image_urls);
    if ($image_tags) {
      $all_tags = array_merge($all_tags, $image_tags);
      kwik_ai_log('Kwik AI: Generated ' . count($image_tags) . ' tags from images: ' . implode(', ', $image_tags));
    }
  }

  /* ----------------------------------------------------- */
  /* 2. Try to get tags from text content */
  /* ----------------------------------------------------- */
  $word_count = str_word_count(wp_strip_all_tags($post_content));

  kwik_ai_log('Kwik AI: Post has ' . $word_count . ' words');

  // Always try text when there's enough of it. When the images produced no tags
  // (none found, all detached, or the provider failed), drop to a lower floor
  // so the post can still be tagged from its words rather than relying solely
  // on images.
  $text_floor = empty($all_tags) ? KWIK_AI_MIN_WORDS_FALLBACK : KWIK_AI_MIN_WORDS;

  if ($word_count >= $text_floor) {
    $text_tags = kwik_ai_tags_generate_from_text($post_id, $post_content);
    if ($text_tags) {
      $all_tags = array_merge($all_tags, $text_tags);
      kwik_ai_log('Kwik AI: Generated ' . count($text_tags) . ' tags from text: ' . implode(', ', $text_tags));
    }
  }

  /* ----------------------------------------------------- */
  /* 3. Combine, deduplicate, and limit tags */
  /* ----------------------------------------------------- */
  if (empty($all_tags)) {
    kwik_ai_log('Kwik AI: No tags generated from any source');
    return false;
  }

  // Remove duplicates and limit to max tags
  $unique_tags = array_unique($all_tags);
  $final_tags = array_slice($unique_tags, 0, KWIK_AI_MAX_TAGS);

  kwik_ai_log('Kwik AI: Final tags (' . count($final_tags) . '): ' . implode(', ', $final_tags));

  return $final_tags;
}

/**
 * Generate tags from image URLs.
 *
 * Each image is analyzed in its own request, using an already-generated
 * intermediate size rather than the original upload. That keeps memory and
 * payload size small (an original photo can be 1MB+; the "large" size is a
 * fraction of that) and works with providers that accept only one image per
 * prompt. The per-image tag lists are then merged, ranking tags that appear
 * across several images first.
 *
 * When the image lives on a public host, the URL itself is sent and the
 * provider fetches it, so WordPress never downloads or encodes the file. If
 * the provider cannot use URLs (native Ollama, a private site, hotlink
 * protection) the image is fetched and sent as base64 instead, and the
 * remaining images skip straight to base64.
 *
 * @param int   $post_id
 * @param array $image_urls Array of image URLs
 * @return array|false
 */
function kwik_ai_tags_generate_from_image_urls(int $post_id, array $image_urls)
{
  kwik_ai_log('Kwik AI: Generating tags from ' . count($image_urls) . ' image URLs');

  /**
   * Filter the maximum number of images analyzed per post.
   *
   * @param int $max_images Default KWIK_AI_MAX_IMAGES.
   * @param int $post_id    Post being tagged.
   */
  $max_images = (int) apply_filters('kwik_ai_max_images_per_post', KWIK_AI_MAX_IMAGES, $post_id);
  if ($max_images > 0 && count($image_urls) > $max_images) {
    kwik_ai_log('Kwik AI: Limiting analysis to the first ' . $max_images . ' images');
    $image_urls = array_slice($image_urls, 0, $max_images);
  }

  $prompt = 'Analyze this image and generate up to 5 concise, relevant tags that describe the main subjects, objects, activities, or themes shown. Focus on nouns and descriptive terms. Respond with a comma‑separated list only, no extra text.';

  /**
   * Filter whether public image URLs may be sent for the provider to fetch,
   * instead of always downloading and base64-encoding the image.
   *
   * @param bool $send_urls Default true.
   * @param int  $post_id   Post being tagged.
   */
  $send_urls = (bool) apply_filters('kwik_ai_send_image_urls', true, $post_id);
  $urls_accepted = null; // Unknown until the first URL attempt.

  $per_image_tags = [];
  $attempted = 0;

  foreach ($image_urls as $image_url) {
    $analysis_url = kwik_ai_tags_get_analysis_image_url($image_url);
    kwik_ai_log('Kwik AI: Processing image URL: ' . $analysis_url);

    $raw_response = null;

    // Preferred: let the provider fetch the image.
    if ($send_urls && $urls_accepted !== false && kwik_ai_tags_is_public_image_url($analysis_url)) {
      $attempted++;
      kwik_ai_log('Kwik AI: Sending image URL to AI provider');
      $raw_response = kwik_ai_tags_query_ai($prompt, [$analysis_url]);
      kwik_ai_log('Kwik AI: Image response: ' . ($raw_response ?: 'NULL'));

      if ($raw_response) {
        $urls_accepted = true;
      } else {
        $urls_accepted = false;
        kwik_ai_log('Kwik AI: Provider did not return a result for the image URL; using base64 for this and the remaining images');
      }
    }

    // Fallback: download the image and send its data.
    if (!$raw_response) {
      $data_uri = kwik_ai_tags_image_to_data_uri($analysis_url);
      if (!$data_uri) {
        kwik_ai_log('Kwik AI: Failed to convert image to data URI: ' . $analysis_url);
        continue;
      }
      $attempted++;

      kwik_ai_log('Kwik AI: Sending image data to AI provider');
      $raw_response = kwik_ai_tags_query_ai($prompt, [$data_uri]);
      unset($data_uri); // Release the base64 payload before the next image.
      kwik_ai_log('Kwik AI: Image response: ' . ($raw_response ?: 'NULL'));
    }

    if (!$raw_response) {
      continue;
    }

    $tags = kwik_ai_tags_parse_tags($raw_response);
    kwik_ai_log('Kwik AI: Parsed image tags: ' . wp_json_encode($tags));
    if (!empty($tags)) {
      $per_image_tags[] = $tags;
    }
  }

  if ($attempted === 0) {
    kwik_ai_log('Kwik AI: No images could be sent for analysis');
    return false;
  }

  if (empty($per_image_tags)) {
    kwik_ai_log('Kwik AI: No tags generated from any image');
    return false;
  }

  $merged = kwik_ai_tags_merge_image_tags($per_image_tags, KWIK_AI_MAX_IMAGE_TAGS);
  kwik_ai_log('Kwik AI: Merged image tags from ' . count($per_image_tags) . ' images: ' . wp_json_encode($merged));

  return $merged;
}

/**
 * Merge per-image tag lists into one ranked list.
 *
 * Tags are compared case-insensitively and counted once per image. A tag seen
 * in more images ranks higher; ties keep first-seen order, so the earliest
 * image's tags lead. The first spelling encountered is kept.
 *
 * @param array $per_image_tags Array of tag arrays, one per image.
 * @param int   $limit          Maximum tags to return (0 = no limit).
 * @return array
 */
function kwik_ai_tags_merge_image_tags(array $per_image_tags, int $limit = 0): array
{
  $counts = [];
  $labels = [];
  $order = [];

  foreach ($per_image_tags as $tags) {
    $seen_in_image = [];
    foreach ((array) $tags as $tag) {
      $tag = trim((string) $tag);
      if ($tag === '') {
        continue;
      }
      $key = strtolower($tag);
      if (isset($seen_in_image[$key])) {
        continue;
      }
      $seen_in_image[$key] = true;

      if (!isset($counts[$key])) {
        $counts[$key] = 0;
        $labels[$key] = $tag;
        $order[$key] = count($order);
      }
      $counts[$key]++;
    }
  }

  uksort($counts, function ($a, $b) use ($counts, $order) {
    if ($counts[$a] !== $counts[$b]) {
      return $counts[$b] <=> $counts[$a];
    }
    return $order[$a] <=> $order[$b];
  });

  $merged = [];
  foreach (array_keys($counts) as $key) {
    $merged[] = $labels[$key];
  }

  if ($limit > 0) {
    $merged = array_slice($merged, 0, $limit);
  }

  return $merged;
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
  kwik_ai_log('Kwik AI: Generating tags from ' . count($attachments) . ' images (legacy method)');

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
  kwik_ai_log('Kwik AI: Generating tags from text content');

  // Clean and prepare the text
  $clean_text = wp_strip_all_tags($content);
  $clean_text = preg_replace('/\s+/', ' ', $clean_text); // Normalize whitespace
  $clean_text = trim($clean_text);

  // Limit text length to avoid overwhelming the model
  if (strlen($clean_text) > 2000) {
    $clean_text = substr($clean_text, 0, 2000) . '...';
    kwik_ai_log('Kwik AI: Truncated text to 2000 characters');
  }

  kwik_ai_log('Kwik AI: Analyzing ' . str_word_count($clean_text) . ' words');

  /* ----------------------------------------------------- */
  /* 1. Build prompt for text analysis */
  /* ----------------------------------------------------- */
  $prompt = 'Analyze this article text and generate up to 5 relevant tags that capture the main topics, themes, subjects, or keywords. Focus on the most important concepts discussed. Respond with a comma‑separated list only, no extra text.

Article text:
' . $clean_text;

  kwik_ai_log('Kwik AI: Using text prompt (first 200 chars): ' . substr($prompt, 0, 200) . '...');

  /* ----------------------------------------------------- */
  /* 2. Send request to AI provider (text-only, no images) */
  /* ----------------------------------------------------- */
  kwik_ai_log('Kwik AI: Sending text request to AI provider');
  $raw_response = kwik_ai_tags_query_ai_text_only($prompt);
  kwik_ai_log('Kwik AI: Ollama text response: ' . ($raw_response ?: 'NULL'));

  if (!$raw_response) {
    kwik_ai_log('Kwik AI: No response from Ollama for text');
    return false;
  }

  /* ----------------------------------------------------- */
  /* 3. Parse tags */
  /* ----------------------------------------------------- */
  $tags = kwik_ai_tags_parse_tags($raw_response);
  kwik_ai_log('Kwik AI: Parsed text tags: ' . wp_json_encode($tags));

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