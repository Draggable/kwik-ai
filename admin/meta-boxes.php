<?php
/**
 * Meta box functionality
 *
 * @package KwikAI
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Add meta box to post editor
 */
function kwik_ai_tags_add_meta_box()
{
  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Adding meta box');
  }

  $enabled_post_types = kwik_ai_tags_get_enabled_post_types();

  foreach ($enabled_post_types as $post_type) {
    add_meta_box(
      'kwik-ai-preview-box',
      __('AI Tags', 'kwik-ai'),
      'kwik_ai_tags_meta_box_callback',
      $post_type,
      'side',
      'high'
    );
  }

  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Meta box added for post types: ' . implode(', ', $enabled_post_types));
  }
}

/**
 * Add meta box for AI description generation
 */
function kwik_ai_description_add_meta_box()
{
  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Adding description meta box');
  }

  $enabled_post_types = kwik_ai_tags_get_enabled_post_types();

  foreach ($enabled_post_types as $post_type) {
    add_meta_box(
      'kwik-ai-description-preview-box',
      __('AI Excerpt', 'kwik-ai'),
      'kwik_ai_description_meta_box_callback',
      $post_type,
      'side',
      'high'
    );
  }

  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Description meta box added for post types: ' . implode(', ', $enabled_post_types));
  }
}

/**
 * Meta box callback function
 */
function kwik_ai_tags_meta_box_callback($post)
{
  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Meta box callback called for post ' . $post->ID);
  }

  // Note: This meta box is AJAX-driven; nonce verification happens
  // in admin/ajax-handlers.php via check_ajax_referer().
  ?>
  <div id="kwik-ai-container">
    <p>
      <button type="button" id="kwik-ai-generate" class="button button-secondary">
        <?php esc_html_e('Generate AI Tags', 'kwik-ai'); ?>
      </button>
    </p>

    <div id="kwik-ai-loading" style="display: none;">
      <p><?php esc_html_e('Analyzing content...', 'kwik-ai'); ?></p>
      <div class="kwik-ai-spinner"></div>
    </div>

    <div id="kwik-ai-preview" style="display: none;">
      <h4><?php esc_html_e('Suggested Tags:', 'kwik-ai'); ?></h4>
      <div id="kwik-ai-list"></div>
      <p>
        <button type="button" id="kwik-ai-apply" class="button button-primary">
          <?php esc_html_e('Apply Tags', 'kwik-ai'); ?>
        </button>
        <button type="button" id="kwik-ai-regenerate" class="button button-secondary">
          <?php esc_html_e('Regenerate', 'kwik-ai'); ?>
        </button>
      </p>
    </div>

    <div id="kwik-ai-error" style="display: none;">
      <p class="error-message"></p>
    </div>

    <?php if (current_user_can('manage_options') && defined('WP_DEBUG') && WP_DEBUG): ?>
      <div id="kwik-ai-debug" style="margin-top: 10px; font-size: 11px; color: #666;">
        <strong>Debug Info:</strong><br>
        Post ID: <?php echo esc_html($post->ID); ?><br>
        Ajax URL: <?php echo esc_html(admin_url('admin-ajax.php')); ?><br>
        Ollama Host: <?php echo esc_html(get_option('kwik_ai_api_endpoint', 'http://localhost:11434')); ?><br>
        Attached Images: <?php
        $attachments = get_attached_media('image', $post->ID);
        echo esc_html(count($attachments));
        ?><br>
        Block Images: <?php
        $content = get_post_field('post_content', $post->ID);
        $block_images = kwik_ai_tags_extract_block_images($content, $post->ID);
        echo esc_html(count($block_images));

        // Check for TRB belt gallery blocks specifically
        $trb_belt_gallery_count = 0;
        $trb_belt_gallery_images = 0;
        if (has_blocks($content)) {
          $blocks = parse_blocks($content);
          foreach ($blocks as $block) {
            if ($block['blockName'] === 'trb/belt-gallery') {
              $trb_belt_gallery_count++;
              if (isset($block['attrs']['images']) && is_array($block['attrs']['images'])) {
                foreach ($block['attrs']['images'] as $image) {
                  if (isset($image['id']) || (isset($image['url']) && !preg_match('/\.(mp4|webm|ogg|mov|avi)$/i', $image['url']))) {
                    $trb_belt_gallery_images++;
                  }
                }
              }
            }
          }
        }

        if ($trb_belt_gallery_count > 0) {
          echo '<br>TRB Belt Galleries: ' . esc_html($trb_belt_gallery_count) . ' (with ' . esc_html($trb_belt_gallery_images) . ' images)';
        }
        ?><br>
        Total Images: <?php
        $image_urls = [];
        foreach ($attachments as $attachment) {
          $url = wp_get_attachment_url($attachment->ID);
          if ($url)
            $image_urls[] = $url;
        }
        $image_urls = array_merge($image_urls, $block_images);
        $image_urls = array_unique($image_urls);
        $image_urls = kwik_ai_tags_deduplicate_sized_images($image_urls);
        echo esc_html(count($image_urls));
        ?><br>
       Word Count: <?php
        $word_count = str_word_count(wp_strip_all_tags($content));
        echo esc_html($word_count);
        $text_analysis_status = $word_count >= KWIK_AI_MIN_WORDS
          ? esc_html__('text analysis enabled', 'kwik-ai')
          : esc_html__('text analysis disabled', 'kwik-ai');
        echo ' (' . esc_html($text_analysis_status) . ')';
        ?>
      </div>
    <?php endif; ?>
  </div>
  <?php

  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Meta box HTML rendered');
  }
}

/**
 * Description meta box callback function
 */
function kwik_ai_description_meta_box_callback($post)
{
  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Description meta box callback called for post ' . $post->ID);
  }

  // Note: This meta box is AJAX-driven; nonce verification happens
  // in admin/ajax-handlers.php via check_ajax_referer().

  // Get the post's current excerpt
  $existing_description = get_post_field('post_excerpt', $post->ID);
  ?>
  <div id="kwik-ai-description-container">
    <?php if (!empty($existing_description)): ?>
      <div id="kwik-ai-description-current" style="margin-bottom: 15px; padding: 10px; background: #e8f4fd; border: 1px solid #4f94d4; border-radius: 3px;">
        <h4 style="margin: 0 0 8px 0; color: #1d2327;"><?php esc_html_e('Current Excerpt:', 'kwik-ai'); ?></h4>
        <div style="font-size: 13px; line-height: 1.4; white-space: pre-wrap;"><?php echo esc_html($existing_description); ?></div>
      </div>
    <?php endif; ?>

    <div class="kwik-ai-description-controls">
      <label for="kwik-ai-description-length">
        <?php esc_html_e('Length', 'kwik-ai'); ?>
        <span id="kwik-ai-description-length-value">35</span>
        <?php esc_html_e('words', 'kwik-ai'); ?>
      </label>
      <input type="range" id="kwik-ai-description-length" min="20" max="80" step="5" value="35">

      <label for="kwik-ai-description-tone">
        <?php esc_html_e('Tone', 'kwik-ai'); ?>
      </label>
      <select id="kwik-ai-description-tone">
        <option value="straight"><?php esc_html_e('Straight', 'kwik-ai'); ?></option>
        <option value="technical"><?php esc_html_e('Technical', 'kwik-ai'); ?></option>
        <option value="excerpt"><?php esc_html_e('Excerpt', 'kwik-ai'); ?></option>
        <option value="pithy"><?php esc_html_e('Pithy', 'kwik-ai'); ?></option>
      </select>
    </div>

    <p>
      <button type="button" id="kwik-ai-description-generate" class="button button-secondary">
        <?php esc_html_e('Generate AI Excerpt', 'kwik-ai'); ?>
      </button>
    </p>

    <div id="kwik-ai-description-loading" style="display: none;">
      <p><?php esc_html_e('Analyzing content...', 'kwik-ai'); ?></p>
      <div class="kwik-ai-spinner"></div>
    </div>

    <div id="kwik-ai-description-preview" style="display: none;">
      <h4><?php esc_html_e('Generated Excerpt:', 'kwik-ai'); ?></h4>
      <div id="kwik-ai-description-text" style="margin-bottom: 15px; padding: 10px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 3px; font-size: 13px; line-height: 1.4;"></div>
      <p>
        <button type="button" id="kwik-ai-description-apply" class="button button-primary">
          <?php esc_html_e('Apply', 'kwik-ai'); ?>
        </button>
        <button type="button" id="kwik-ai-description-regenerate" class="button button-secondary">
          <?php esc_html_e('Regenerate', 'kwik-ai'); ?>
        </button>
      </p>
    </div>

    <div id="kwik-ai-description-error" style="display: none;">
      <p class="error-message"></p>
    </div>

    <?php if (WP_DEBUG): ?>
      <div id="kwik-ai-description-debug" style="margin-top: 10px; font-size: 11px; color: #666;">
        <strong>Description Debug Info:</strong><br>
        Post ID: <?php echo esc_html($post->ID); ?><br>
        Ajax URL: <?php echo esc_html(admin_url('admin-ajax.php')); ?><br>
        Current Description: <?php
        $current_desc = kwik_ai_get_description($post->ID);
        echo $current_desc ? esc_html('Yes (' . strlen($current_desc) . ' chars)') : 'No';
        ?><br>
        Attached Images: <?php
        $attachments = get_attached_media('image', $post->ID);
        echo esc_html(count($attachments));
        ?><br>
        Block Images: <?php
        $content = get_post_field('post_content', $post->ID);
        $block_images = kwik_ai_tags_extract_block_images($content, $post->ID);
        echo esc_html(count($block_images));
        ?><br>
        Total Images: <?php
        $image_urls = [];
        foreach ($attachments as $attachment) {
          $url = wp_get_attachment_url($attachment->ID);
          if ($url)
            $image_urls[] = $url;
        }
        $image_urls = array_merge($image_urls, $block_images);
        $image_urls = array_unique($image_urls);
        $image_urls = kwik_ai_tags_deduplicate_sized_images($image_urls);
        echo esc_html(count($image_urls));
        ?>
      </div>
    <?php endif; ?>
  </div>
  <?php

  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Description meta box HTML rendered');
  }
}

/**
 * Add meta box for AI featured image generation (FAL.AI).
 */
function kwik_ai_featured_image_add_meta_box()
{
  $enabled_post_types = kwik_ai_tags_get_enabled_post_types();

  foreach ($enabled_post_types as $post_type) {
    // Only offer this where the post type actually supports featured images.
    if (!post_type_supports($post_type, 'thumbnail')) {
      continue;
    }

    add_meta_box(
      'kwik-ai-featured-image-box',
      __('AI Featured Image', 'kwik-ai'),
      'kwik_ai_featured_image_meta_box_callback',
      $post_type,
      'side',
      'high'
    );
  }
}

/**
 * Featured image meta box callback function.
 */
function kwik_ai_featured_image_meta_box_callback($post)
{
  // Note: This meta box is AJAX-driven; nonce verification happens
  // in admin/ajax-handlers.php via check_ajax_referer().
  $has_key = function_exists('kwik_ai_fal_has_api_key') && kwik_ai_fal_has_api_key();
  ?>
  <div id="kwik-ai-featured-image-container">
    <?php if (!$has_key): ?>
      <div class="notice notice-warning inline" style="margin: 0 0 10px;">
        <p>
          <?php
          printf(
            wp_kses(
              /* translators: %s: settings page URL */
              __('Add a FAL.AI API key in <a href="%s">KWIK AI settings</a> to enable image generation.', 'kwik-ai'),
              array('a' => array('href' => array()))
            ),
            esc_url(admin_url('options-general.php?page=kwik-ai-settings'))
          );
          ?>
        </p>
      </div>
    <?php endif; ?>

    <p>
      <label for="kwik-ai-featured-image-guidance">
        <?php esc_html_e('Optional guidance (style, subject, mood):', 'kwik-ai'); ?>
      </label>
      <textarea id="kwik-ai-featured-image-guidance" rows="2" style="width: 100%;"
        placeholder="<?php esc_attr_e('e.g. watercolor style, warm lighting, no people', 'kwik-ai'); ?>"></textarea>
    </p>

    <p>
      <button type="button" id="kwik-ai-featured-image-prompt-generate" class="button button-secondary" <?php disabled(!$has_key); ?>>
        <?php esc_html_e('Generate Prompt', 'kwik-ai'); ?>
      </button>
      <span id="kwik-ai-featured-image-prompt-loading" style="display: none;">
        <span class="kwik-ai-spinner" style="display: inline-block; vertical-align: middle;"></span>
        <?php esc_html_e('Writing prompt…', 'kwik-ai'); ?>
      </span>
    </p>

    <p>
      <label for="kwik-ai-featured-image-prompt-input">
        <?php esc_html_e('Image prompt (review and edit before generating):', 'kwik-ai'); ?>
      </label>
      <textarea id="kwik-ai-featured-image-prompt-input" rows="5" style="width: 100%;"
        placeholder="<?php esc_attr_e('Click "Generate Prompt", or type your own prompt here.', 'kwik-ai'); ?>"></textarea>
      <span id="kwik-ai-featured-image-prompt-source" class="description" style="display: block;"></span>
    </p>

    <p>
      <button type="button" id="kwik-ai-featured-image-generate" class="button button-primary" <?php disabled(!$has_key); ?>>
        <?php esc_html_e('Generate Image', 'kwik-ai'); ?>
      </button>
    </p>

    <div id="kwik-ai-featured-image-loading" style="display: none;">
      <p>
        <span id="kwik-ai-featured-image-status"><?php esc_html_e('Generating image…', 'kwik-ai'); ?></span>
        <span id="kwik-ai-featured-image-elapsed"></span>
      </p>
      <div class="kwik-ai-spinner"></div>
    </div>

    <div id="kwik-ai-featured-image-preview" style="display: none;">
      <img id="kwik-ai-featured-image-img" src="" alt="" style="max-width: 100%; height: auto; border: 1px solid #dcdcde; border-radius: 3px;" />
      <p>
        <button type="button" id="kwik-ai-featured-image-apply" class="button button-primary">
          <?php esc_html_e('Set as Featured Image', 'kwik-ai'); ?>
        </button>
        <button type="button" id="kwik-ai-featured-image-regenerate" class="button button-secondary">
          <?php esc_html_e('Regenerate', 'kwik-ai'); ?>
        </button>
      </p>
      <p class="description"><?php esc_html_e('“Regenerate” creates a new image from the same prompt above. Edit the prompt and click “Generate Image” to change it.', 'kwik-ai'); ?></p>
    </div>

    <div id="kwik-ai-featured-image-error" style="display: none;">
      <p class="error-message"></p>
    </div>
  </div>
  <?php
}
