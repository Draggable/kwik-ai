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
  error_log('Kwik AI: Adding meta box');

  $enabled_post_types = kwik_ai_tags_get_enabled_post_types();
  
  foreach ($enabled_post_types as $post_type) {
    add_meta_box(
      'kwik-ai-tags-preview-box',
      __('AI Tags', KWIK_AI_DOMAIN),
      'kwik_ai_tags_meta_box_callback',
      $post_type,
      'side',
      'high'
    );
  }

  error_log('Kwik AI: Meta box added for post types: ' . implode(', ', $enabled_post_types));
}

/**
 * Add meta box for AI description generation
 */
function kwik_ai_description_add_meta_box()
{
  error_log('Kwik AI: Adding description meta box');

  $enabled_post_types = kwik_ai_tags_get_enabled_post_types();
  
  foreach ($enabled_post_types as $post_type) {
    add_meta_box(
      'kwik-ai-description-preview-box',
      __('AI Description', KWIK_AI_DOMAIN),
      'kwik_ai_description_meta_box_callback',
      $post_type,
      'side',
      'high'
    );
  }

  error_log('Kwik AI: Description meta box added for post types: ' . implode(', ', $enabled_post_types));
}

/**
 * Meta box callback function
 */
function kwik_ai_tags_meta_box_callback($post)
{
  error_log('Kwik AI: Meta box callback called for post ' . $post->ID);

  wp_nonce_field('kwik_ai_tags_meta_box', 'kwik_ai_tags_nonce');
  ?>
  <div id="kwik-ai-tags-container">
    <p>
      <button type="button" id="kwik-ai-tags-generate" class="button button-secondary">
        <?php esc_html_e('Generate AI Tags', KWIK_AI_DOMAIN); ?>
      </button>
    </p>

    <div id="kwik-ai-tags-loading" style="display: none;">
      <p><?php esc_html_e('Analyzing content...', KWIK_AI_DOMAIN); ?></p>
      <div class="kwik-ai-tags-spinner"></div>
    </div>

    <div id="kwik-ai-tags-preview" style="display: none;">
      <h4><?php esc_html_e('Suggested Tags:', KWIK_AI_DOMAIN); ?></h4>
      <div id="kwik-ai-tags-list"></div>
      <p>
        <button type="button" id="kwik-ai-tags-apply" class="button button-primary">
          <?php esc_html_e('Apply Tags', KWIK_AI_DOMAIN); ?>
        </button>
        <button type="button" id="kwik-ai-tags-regenerate" class="button button-secondary">
          <?php esc_html_e('Regenerate', KWIK_AI_DOMAIN); ?>
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
        Ollama Host: <?php echo esc_html(kwik_ai_tags_get_ollama_url()); ?><br>
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
        echo $word_count >= KWIK_AI_MIN_WORDS ? ' (✓ text analysis enabled)' : ' (text analysis disabled)';
        ?>
      </div>
    <?php endif; ?>
  </div>
  <?php

  error_log('Kwik AI: Meta box HTML rendered');
}

/**
 * Description meta box callback function
 */
function kwik_ai_description_meta_box_callback($post)
{
  error_log('Kwik AI: Description meta box callback called for post ' . $post->ID);

  wp_nonce_field('kwik_ai_description_meta_box', 'kwik_ai_description_nonce');
  
  // Get existing description
  $existing_description = kwik_ai_get_description($post->ID);
  ?>
  <div id="kwik-ai-description-container">
    <?php if (!empty($existing_description)): ?>
      <div id="kwik-ai-description-current" style="margin-bottom: 15px; padding: 10px; background: #e8f4fd; border: 1px solid #4f94d4; border-radius: 3px;">
        <h4 style="margin: 0 0 8px 0; color: #1d2327;"><?php esc_html_e('Current AI Description:', KWIK_AI_DOMAIN); ?></h4>
        <div style="font-size: 13px; line-height: 1.4; white-space: pre-wrap;"><?php echo esc_html($existing_description); ?></div>
      </div>
    <?php endif; ?>
    
    <p>
      <button type="button" id="kwik-ai-description-generate" class="button button-secondary">
        <?php esc_html_e('Generate AI Description', KWIK_AI_DOMAIN); ?>
      </button>
    </p>

    <div id="kwik-ai-description-loading" style="display: none;">
      <p><?php esc_html_e('Analyzing content...', KWIK_AI_DOMAIN); ?></p>
      <div class="kwik-ai-tags-spinner"></div>
    </div>

    <div id="kwik-ai-description-preview" style="display: none;">
      <h4><?php esc_html_e('Generated Description:', KWIK_AI_DOMAIN); ?></h4>
      <div id="kwik-ai-description-text" style="margin-bottom: 15px; padding: 10px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 3px; font-size: 13px; line-height: 1.4;"></div>
      <p>
        <button type="button" id="kwik-ai-description-apply" class="button button-primary">
          <?php esc_html_e('Apply', KWIK_AI_DOMAIN); ?>
        </button>
        <button type="button" id="kwik-ai-description-regenerate" class="button button-secondary">
          <?php esc_html_e('Regenerate', KWIK_AI_DOMAIN); ?>
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

  error_log('Kwik AI: Description meta box HTML rendered');
}