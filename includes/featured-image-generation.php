<?php
/**
 * Featured image generation orchestration.
 *
 * Scans a post, uses the configured text AI to craft a text-to-image prompt,
 * submits the job to FAL.AI's queue, and sideloads the result as the post's
 * featured image once the user confirms.
 *
 * @package KwikAI
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

// How long a submitted FAL job's tracking data is kept (seconds).
define('KWIK_AI_FAL_REQUEST_TTL', HOUR_IN_SECONDS);

/**
 * Generate a text-to-image prompt for a post, reporting how it was produced.
 *
 * Uses a two-step approach with the configured text AI provider: first the
 * whole post is distilled into its visual theme, then that theme is turned into
 * a creative image prompt (blending in any user-supplied guidance). This means
 * the prompt reflects the overall theme of the post rather than a verbatim
 * excerpt of its opening. Falls back to a title/excerpt-based prompt if the
 * text AI is unavailable.
 *
 * @param int    $post_id
 * @param string $guidance Optional user guidance to prioritize.
 * @return array{prompt:string,source:string} 'source' is 'ai' or 'fallback'.
 */
function kwik_ai_featured_image_generate_prompt(int $post_id, string $guidance = ''): array
{
  $title = get_the_title($post_id);
  $content = wp_strip_all_tags(get_post_field('post_content', $post_id));
  $content = trim(preg_replace('/\s+/', ' ', $content));
  $guidance = trim($guidance);

  $prompt = '';
  $source = 'fallback';

  $provider = function_exists('kwik_ai_tags_get_ai_provider') ? kwik_ai_tags_get_ai_provider() : '(unknown)';
  $endpoint = function_exists('kwik_ai_tags_get_api_endpoint') ? kwik_ai_tags_get_api_endpoint() : '(unknown)';
  $text_model = function_exists('kwik_ai_tags_get_text_model') ? kwik_ai_tags_get_text_model() : '(unknown)';
  kwik_ai_log(sprintf(
    'Kwik AI FAL: Building prompt for post %d — text provider=%s, endpoint=%s, text model=%s, content words=%d',
    $post_id,
    $provider,
    $endpoint,
    $text_model,
    str_word_count($content)
  ));

  if (!function_exists('kwik_ai_tags_query_ai_text_only')) {
    kwik_ai_log('Kwik AI FAL: kwik_ai_tags_query_ai_text_only() is unavailable — using fallback.');
  } elseif ($content === '') {
    kwik_ai_log('Kwik AI FAL: Post has no text content — using fallback (title/guidance only).');
  } else {
    // Step 1: read the whole post and distill it into a visual theme.
    $theme = kwik_ai_featured_image_summarize_theme($title, $content);
    kwik_ai_log('Kwik AI FAL: Theme step ' . ($theme !== '' ? 'returned ' . strlen($theme) . ' chars' : 'returned NOTHING (AI call failed or empty)'));

    // Step 2: turn the theme into a creative image prompt. If summarizing
    // failed, fall back to a trimmed excerpt as the basis so we still try.
    $basis = $theme !== '' ? $theme : wp_trim_words($content, 300, '');
    $crafted = kwik_ai_featured_image_craft_prompt($title, $basis, $guidance);
    kwik_ai_log('Kwik AI FAL: Craft step ' . ($crafted !== '' ? 'returned ' . strlen($crafted) . ' chars' : 'returned NOTHING (AI call failed or empty)'));

    if ($crafted !== '') {
      $prompt = $crafted;
      $source = 'ai';
    }
  }

  // Fallback: build a prompt directly from the post when the text AI is
  // unreachable or returns nothing. This is the verbatim-excerpt behavior;
  // surfacing source = 'fallback' tells the editor the AI step didn't run.
  if ($prompt === '') {
    kwik_ai_log('Kwik AI FAL: Text AI unavailable, using fallback prompt.');
    $parts = array();
    if ($title !== '') {
      $parts[] = $title;
    }
    if ($guidance !== '') {
      $parts[] = $guidance;
    }
    $short = wp_trim_words($content, 40, '');
    if ($short !== '') {
      $parts[] = $short;
    }
    $prompt = implode('. ', $parts);
    $source = 'fallback';
  }

  kwik_ai_log('Kwik AI FAL: Final prompt source=' . $source);

  /**
   * Filter the generated FAL image prompt before it is shown/submitted.
   *
   * @param string $prompt   The prompt text.
   * @param int    $post_id  Post being illustrated.
   * @param string $guidance User-supplied guidance.
   */
  $prompt = apply_filters('kwik_ai_featured_image_prompt', $prompt, $post_id, $guidance);

  return array(
    'prompt'   => $prompt,
    'source'   => $source,
    'provider' => $provider,
  );
}

/**
 * Build a text-to-image prompt for a post (prompt text only).
 *
 * Thin wrapper around kwik_ai_featured_image_generate_prompt() for callers that
 * only need the prompt string.
 *
 * @param int    $post_id
 * @param string $guidance Optional user guidance to prioritize.
 * @return string
 */
function kwik_ai_featured_image_build_prompt(int $post_id, string $guidance = ''): string
{
  $result = kwik_ai_featured_image_generate_prompt($post_id, $guidance);
  return $result['prompt'];
}

/**
 * Step 1 of prompt building: distill an entire post into its visual theme.
 *
 * Sends (almost) the whole post to the text AI and asks for the central theme,
 * mood, key subjects, and setting — the visual essence to illustrate — rather
 * than an image prompt. A generous word cap protects against very long posts
 * blowing the model's context window.
 *
 * @param string $title
 * @param string $content Plain-text post content.
 * @return string Theme summary, or empty string on failure.
 */
function kwik_ai_featured_image_summarize_theme(string $title, string $content): string
{
  // Cap extremely long posts but still capture the whole article for the vast
  // majority of content.
  $body = wp_trim_words($content, 4000, '');

  $system  = "You analyze an article and distill it into the visual essence needed to illustrate it. ";
  $system .= "Read the entire article, then identify its central theme, overall mood, the most important subjects, and the setting. ";
  $system .= "Respond with a concise summary of 2 to 4 sentences describing what an illustration of this article should convey. ";
  $system .= "Do not write an image prompt yet and do not list tags; just capture the theme in prose.";

  $user  = "Title: " . $title . "\n\n";
  $user .= "Article:\n" . $body;

  // Generous budget so reasoning models (which spend tokens thinking before
  // answering) have room to return content in a single request.
  $response = kwik_ai_tags_query_ai_text_only($user, $system, 2000);

  return is_string($response) ? trim($response) : '';
}

/**
 * Step 2 of prompt building: turn a theme summary into a creative image prompt.
 *
 * @param string $title
 * @param string $basis    Theme summary (or excerpt fallback) to illustrate.
 * @param string $guidance Optional user guidance to prioritize.
 * @return string Cleaned image prompt, or empty string on failure.
 */
function kwik_ai_featured_image_craft_prompt(string $title, string $basis, string $guidance = ''): string
{
  $system  = "You are crafting a single text-to-image prompt for an AI image generator that will create a featured image for an article. ";
  $system .= "Using the article's theme below, invent one vivid, creative image that illustrates that theme as a single-paragraph prompt. ";
  $system .= "Be imaginative rather than literal: evoke the theme through concrete visual subjects, setting, composition, style, lighting, and mood. ";
  $system .= "Do not include any text, words, captions, or logos in the image. ";
  $system .= "Respond with only the prompt text, no preamble, labels, or quotation marks.";

  $user  = "Article title: " . $title . "\n\n";
  $user .= "Theme to illustrate: " . $basis;

  if ($guidance !== '') {
    $user .= "\n\nPrioritize this creative direction from the author: " . $guidance;
  }

  // Generous budget so reasoning models have room to think and still return the
  // full prompt in a single request.
  $response = kwik_ai_tags_query_ai_text_only($user, $system, 2000);

  return is_string($response) ? kwik_ai_featured_image_clean_prompt($response) : '';
}

/**
 * Strip common preambles, labels, and wrapping quotes from an AI prompt response.
 *
 * @param string $response
 * @return string
 */
function kwik_ai_featured_image_clean_prompt(string $response): string
{
  $prompt = trim($response);
  $prompt = preg_replace('/^(Prompt|Image prompt|Here(?:\'s| is)[^:]*)\s*:\s*/i', '', $prompt);
  $prompt = trim($prompt, " \t\n\r\0\x0B\"'");
  return $prompt;
}

/**
 * Submit a FAL image job for a post using a specific prompt.
 *
 * The prompt is supplied by the caller (typically the reviewed/edited prompt
 * from the meta box) so the user can see and adjust it before it is sent — for
 * example to remove words a content filter wrongly flags. If an empty prompt is
 * passed, one is built automatically as a convenience.
 *
 * Stores the job's tracking URLs in a transient keyed by request_id so the
 * status/apply handlers can look them up without trusting client-supplied URLs.
 *
 * @param int    $post_id
 * @param string $prompt   The image prompt to send to FAL.
 * @return array|WP_Error ['request_id','prompt'] or error.
 */
function kwik_ai_featured_image_submit(int $post_id, string $prompt = '')
{
  $prompt = trim($prompt);

  // Convenience: build a prompt if the caller didn't supply one.
  if ($prompt === '') {
    $prompt = kwik_ai_featured_image_build_prompt($post_id);
  }

  if (trim($prompt) === '') {
    return new WP_Error('kwik_ai_fal_no_prompt', __('Could not build an image prompt. Add a title or content to the post, or enter a prompt.', 'kwik-ai'));
  }

  $submission = kwik_ai_fal_submit_request($prompt);
  if (is_wp_error($submission)) {
    return $submission;
  }

  set_transient(
    kwik_ai_featured_image_transient_key($submission['request_id']),
    array(
      'status_url'   => $submission['status_url'],
      'response_url' => $submission['response_url'],
      'prompt'       => $prompt,
      'post_id'      => $post_id,
      'user_id'      => get_current_user_id(),
      'image_url'    => '',
    ),
    KWIK_AI_FAL_REQUEST_TTL
  );

  return array(
    'request_id' => $submission['request_id'],
    'prompt'     => $prompt,
  );
}

/**
 * Build the transient key for a FAL request.
 *
 * @param string $request_id
 * @return string
 */
function kwik_ai_featured_image_transient_key(string $request_id): string
{
  return 'kwik_ai_fal_' . md5($request_id);
}

/**
 * Sideload a generated image into the media library and set it as the post's
 * featured image.
 *
 * @param int    $post_id
 * @param string $image_url Image URL returned by FAL.
 * @return int|WP_Error Attachment ID or error.
 */
function kwik_ai_featured_image_set_as_thumbnail(int $post_id, string $image_url)
{
  // Only fetch images from FAL's media hosts.
  $host = wp_parse_url($image_url, PHP_URL_HOST);
  if (!is_string($host) || !preg_match('/(^|\.)fal\.media$/', $host)) {
    return new WP_Error('kwik_ai_fal_bad_image_host', __('Refusing to download an image from an unexpected host.', 'kwik-ai'));
  }

  require_once ABSPATH . 'wp-admin/includes/media.php';
  require_once ABSPATH . 'wp-admin/includes/file.php';
  require_once ABSPATH . 'wp-admin/includes/image.php';

  $description = sprintf(
    /* translators: %s: post title */
    __('AI-generated featured image for "%s"', 'kwik-ai'),
    get_the_title($post_id)
  );

  $attachment_id = media_sideload_image($image_url, $post_id, $description, 'id');

  if (is_wp_error($attachment_id)) {
    kwik_ai_log('Kwik AI FAL: Sideload failed: ' . $attachment_id->get_error_message());
    return $attachment_id;
  }

  set_post_thumbnail($post_id, $attachment_id);

  return (int) $attachment_id;
}
