<?php
/**
 * Description generation functions
 *
 * @package KwikAI
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Generate description for a specific post from images
 *
 * @param int $post_id
 * @return string|false
 */
function kwik_ai_description_generate_for_post(int $post_id)
{
  error_log('Kwik AI: Starting description generation for post ' . $post_id);

  /* ----------------------------------------------------- */
  /* 1. Get images from post */
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

  if (empty($image_urls)) {
    error_log('Kwik AI: No images found for description generation');
    return false;
  }

  /* ----------------------------------------------------- */
  /* 2. Generate description from images */
  /* ----------------------------------------------------- */
  $description = kwik_ai_description_generate_from_image_urls($post_id, $image_urls);

  if (!$description) {
    error_log('Kwik AI: Failed to generate description from images');
    return false;
  }

  // Limit description length
  if (strlen($description) > KWIK_AI_MAX_DESCRIPTION_LENGTH) {
    $description = substr($description, 0, KWIK_AI_MAX_DESCRIPTION_LENGTH - 3) . '...';
    error_log('Kwik AI: Truncated description to ' . KWIK_AI_MAX_DESCRIPTION_LENGTH . ' characters');
  }

  error_log('Kwik AI: Final description (' . strlen($description) . ' chars): ' . substr($description, 0, 100) . '...');

  return $description;
}

/**
 * Generate description from image URLs
 *
 * @param int $post_id
 * @param array $image_urls Array of image URLs
 * @return string|false
 */
function kwik_ai_description_generate_from_image_urls(int $post_id, array $image_urls)
{
  error_log('Kwik AI: Generating description from ' . count($image_urls) . ' image URLs');

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
  /* 2. Build prompt for description generation */
  /* ----------------------------------------------------- */
  $post_type = get_post_type($post_id);
  $post_type_obj = get_post_type_object($post_type);
  $post_type_name = $post_type_obj ? $post_type_obj->labels->singular_name : 'post';

  $prompt = 'Analyze these images and generate an engaging description for a ' . $post_type_name . '. Describe this work of art. Write in a natural, descriptive style without overusing adjectives. Avoid uncommon punctuation such as Em dash. Keep the description between 50-200 words. Respond with only the description text, no extra formatting or labels.';
  error_log('Kwik AI: Using description prompt: ' . $prompt);

  /* ----------------------------------------------------- */
  /* 3. Send request to Ollama */
  /* ----------------------------------------------------- */
  error_log('Kwik AI: Sending description request to Ollama');
  $raw_response = kwik_ai_tags_query_ollama($prompt, $data_uris);
  error_log('Kwik AI: Ollama description response: ' . substr($raw_response ?: 'NULL', 0, 200));

  if (!$raw_response) {
    error_log('Kwik AI: No response from Ollama for description');
    return false;
  }

  /* ----------------------------------------------------- */
  /* 4. Clean and return description */
  /* ----------------------------------------------------- */
  $description = trim($raw_response);

  // Remove any unwanted formatting or prefixes
  $description = preg_replace('/^(Description:|Summary:|About this|This image shows?)/i', '', $description);
  $description = trim($description);

  error_log('Kwik AI: Cleaned description: ' . substr($description, 0, 100) . '...');

  return $description;
}

/**
 * Generate description from URLs by first summarizing content and then creating a description
 *
 * @param int $post_id
 * * @param array $urls Array of URLs to scrape content from
 * @return string|false
 */
function kwik_ai_description_generate_from_urls(int $post_id, array $urls)
{
  error_log('Kwik AI: Generating description from ' . count($urls) . ' URLs');

  /* ----------------------------------------------------- */
  /* 1. Scrape and summarize content from each URL */
  /* ----------------------------------------------------- */
  $summaries = [];
  foreach ($urls as $url) {
    error_log('Kwik AI: Processing URL: ' . $url);

    $summary = kwik_ai_scrape_and_summarize_url($url);
    if ($summary) {
      $summaries[] = $summary;
      error_log('Kwik AI: Successfully summarized content from URL');
    } else {
      error_log('Kwik AI: Failed to summarize content from URL: ' . $url);
    }
  }

  error_log('Kwik AI: Summarized content from ' . count($summaries) . ' URLs');

  if (empty($summaries)) {
    error_log('Kwik AI: No summaries generated from URLs');
    return false;
  }

  /* ----------------------------------------------------- */
  /* 2. Build prompt for description generation */
  /* ----------------------------------------------------- */
  $post_type = get_post_type($post_id);
  $post_type_obj = get_post_type_object($post_type);
  $post_type_name = $post_type_obj ? $post_type_obj->labels->singular_name : 'post';

  // Combine all summaries into one text
  $combined_summaries = implode("\n\n", $summaries);

  // Create a two-step prompt: first summarize all content, then generate description
  $summary_prompt = 'Summarize the following content in a clear and concise way. Focus on the main points and key information. Respond with only the summary text, no extra formatting or labels.' . "\n\n" . $combined_summaries;
  
  error_log('Kwik AI: Using summary prompt: ' . $summary_prompt);

  // Get a summary of all the content
  $summary_response = kwik_ai_tags_query_ollama_text_only($summary_prompt);
  if (!$summary_response) {
    error_log('Kwik AI: Failed to get summary of combined content');
    return false;
  }

  $final_summary = trim($summary_response);
  error_log('Kwik AI: Generated combined summary: ' . substr($final_summary, 0, 100) . '...');

  // Now generate the final description based on the summary
  $description_prompt = 'Based on the following summary, generate an engaging description for a ' . $post_type_name . '. Write in a natural, descriptive style without overusing adjectives. Avoid uncommon punctuation such as Em dash. Keep the description between 50-200 words. Respond with only the description text, no extra formatting or labels.' . "\n\n" . $final_summary;
  error_log('Kwik AI: Using description prompt: ' . $description_prompt);

  /* ----------------------------------------------------- */
  /* 3. Send request to Ollama */
  /* ----------------------------------------------------- */
  error_log('Kwik AI: Sending description request to Ollama');
  $raw_response = kwik_ai_tags_query_ollama_text_only($description_prompt);
  error_log('Kwik AI: Ollama description response: ' . substr($raw_response ?: 'NULL', 0, 200));

  if (!$raw_response) {
    error_log('Kwik AI: No response from Ollama for description');
    return false;
  }

  /* ----------------------------------------------------- */
  /* 4. Clean and return description */
  /* ----------------------------------------------------- */
  $description = trim($raw_response);

  // Remove any unwanted formatting or prefixes
  $description = preg_replace('/^(Description:|Summary:|About this|This content shows?)/i', '', $description);
  $description = trim($description);

  error_log('Kwik AI: Cleaned description: ' . substr($description, 0, 100) . '...');

  return $description;
}