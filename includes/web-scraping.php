<?php
/**
 * Web scraping functions for URL content extraction
 *
 * @package KwikAI
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Scrape and summarize content from a URL
 *
 * @param string $url
 * @return string|false
 */
function kwik_ai_scrape_and_summarize_url(string $url)
{
  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Kwik AI: Scraping content from URL: ' . $url);
  }

  // Validate URL
  if (!filter_var($url, FILTER_VALIDATE_URL)) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Kwik AI: Invalid URL provided: ' . $url);
    }
    return false;
  }

  // Get content from URL
  $content = kwik_ai_fetch_url_content($url);
  if (!$content) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Kwik AI: Failed to fetch content from URL: ' . $url);
    }
    return false;
  }

  // Extract main content from HTML
  $main_content = kwik_ai_extract_main_content($content);
  if (!$main_content) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Kwik AI: Failed to extract main content from URL: ' . $url);
    }
    return false;
  }

  // Summarize the content using Ollama
  $summary = kwik_ai_summarize_content($main_content);
  if (!$summary) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Kwik AI: Failed to summarize content from URL: ' . $url);
    }
    return false;
  }

  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Kwik AI: Successfully scraped and summarized content from URL: ' . $url);
  }
  return $summary;
}

/**
 * Fetch content from a URL
 *
 * @param string $url
 * @return string|false
 */
function kwik_ai_fetch_url_content(string $url): ?string
{
  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Kwik AI: Fetching content from URL: ' . $url);
  }

  // Try WordPress HTTP API first
  $response = wp_remote_get($url, [
    'timeout' => 30,
    'user-agent' => 'Mozilla/5.0 (compatible; KwikAI/1.0; +https://kwik-ai.com)',
    'headers' => [
      'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    ],
  ]);

  if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
    $content = wp_remote_retrieve_body($response);
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Kwik AI: Successfully fetched content via wp_remote_get (' . strlen($content) . ' chars)');
    }
    return $content;
  }

  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Kwik AI: wp_remote_get failed, trying file_get_contents');
  }

  // Try file_get_contents with context
  $context = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' => [
        'User-Agent: Mozilla/5.0 (compatible; KwikAI/1.0; +https://kwik-ai.com)',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
      ],
      'timeout' => 30,
    ]
  ]);

  $content = @file_get_contents($url, false, $context);
  if ($content !== false) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Kwik AI: Successfully fetched content via file_get_contents (' . strlen($content) . ' chars)');
    }
    return $content;
  }

  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Kwik AI: file_get_contents failed');
  }
  return false;
}

/**
 * Extract main content from HTML
 *
 * @param string $html
 * @return string|false
 */
function kwik_ai_extract_main_content(string $html): ?string
{
  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Kwik AI: Extracting main content from HTML (' . strlen($html) . ' chars)');
  }

  // Load HTML into DOMDocument
  $dom = new DOMDocument();
  
  // Suppress errors due to malformed HTML
  libxml_use_internal_errors(true);
  
  // Convert to UTF-8 if needed
  if (function_exists('mb_convert_encoding')) {
    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
  }
  
  $dom->loadHTML($html);
  libxml_clear_errors();

  // Try to find main content containers
  $content_selectors = [
    'article',
    '.content',
    '.post-content',
    '.entry-content',
    '.article-content',
    '.main-content',
    'main',
    '#content',
    '.post',
    '.entry'
  ];

  $main_content = '';

  foreach ($content_selectors as $selector) {
    $elements = [];
    
    if (strpos($selector, '#') === 0) {
      // ID selector
      $element = $dom->getElementById(substr($selector, 1));
      if ($element) {
        $elements = [$element];
      }
    } elseif (strpos($selector, '.') === 0) {
      // Class selector
      $elements = $dom->getElementsByTagName('*');
      $class_elements = [];
      foreach ($elements as $element) {
        if ($element->hasAttribute('class') && 
            strpos($element->getAttribute('class'), substr($selector, 1)) !== false) {
          $class_elements[] = $element;
        }
      }
      $elements = $class_elements;
    } else {
      // Tag selector
      $elements = $dom->getElementsByTagName($selector);
    }

    if (!empty($elements)) {
      foreach ($elements as $element) {
        $text = trim($element->textContent);
        if (strlen($text) > strlen($main_content)) {
          $main_content = $text;
        }
      }
      
      // If we found substantial content, break
      if (strlen($main_content) > 500) {
        break;
      }
    }
  }

  // If no main content found, extract from body
  if (empty($main_content)) {
    $body_elements = $dom->getElementsByTagName('body');
    if ($body_elements->length > 0) {
      $main_content = trim($body_elements->item(0)->textContent);
    }
  }

  // Clean up the content
  if (!empty($main_content)) {
    // Remove extra whitespace
    $main_content = preg_replace('/\s+/', ' ', $main_content);
    
    // Limit content length to prevent overwhelming the AI model
    if (strlen($main_content) > 5000) {
      $main_content = substr($main_content, 0, 5000) . '...';
    }
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Kwik AI: Extracted main content (' . strlen($main_content) . ' chars)');
    }
    return $main_content;
  }

  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Kwik AI: Failed to extract main content');
  }
  return false;
}

/**
 * Summarize content using Ollama
 *
 * @param string $content
 * @return string|false
 */
function kwik_ai_summarize_content(string $content): ?string
{
  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Kwik AI: Summarizing content (' . strlen($content) . ' chars)');
  }

  // Create a prompt for summarization
  $prompt = 'Summarize the following content in a clear and concise way. Focus on the main points and key information. Keep the summary to 2-3 sentences. Respond with only the summary text, no extra formatting or labels.' . "\n\n" . $content;

  // Query Ollama for summary
  $summary = kwik_ai_tags_query_ollama_text_only($prompt);

  if ($summary) {
    $summary = trim($summary);
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Kwik AI: Generated summary (' . strlen($summary) . ' chars): ' . substr($summary, 0, 100) . '...');
    }
    return $summary;
  }

  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Kwik AI: Failed to generate summary');
  }
  return false;
}
