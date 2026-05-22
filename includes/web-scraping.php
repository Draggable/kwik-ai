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
 * Validate URL is not an internal/private address (SSRF protection)
 *
 * @param string $url URL to validate
 * @return bool True if safe to fetch, false otherwise
 */
function kwik_ai_validate_url_safety($url) {
  $parsed_url = parse_url($url);

  if (!$parsed_url || !isset($parsed_url['host'])) {
    return false;
  }

  $host = $parsed_url['host'];

  // Block IPv6 loopback and link-local
  if (strpos($host, '::1') === 0 || strpos($host, 'fe80:') === 0) {
    kwik_ai_log('Kwik AI: Blocked SSRF attempt to IPv6 private address: ' . $host);
    return false;
  }

  // Additional check: ensure URL uses http/https
  if (!isset($parsed_url['scheme']) || !in_array(strtolower($parsed_url['scheme']), ['http', 'https'])) {
    return false;
  }

  return true;
}

/**
 * Check if an IP address is private/internal (SSRF protection)
 *
 * @param string $ip IP address to check
 * @return bool True if the IP is private/internal
 */
function kwik_ai_is_private_ip(string $ip): bool
{
  // Block private IP ranges (RFC 1918)
  $private_ranges = [
    '/^10\./',                    // 10.0.0.0/8
    '/^172\.(1[6-9]|2[0-9]|3[0-1])\./',  // 172.16.0.0/12
    '/^192\.168\./',              // 192.168.0.0/16
    '/^127\./',                   // Loopback
    '/^169\.254\./',              // Link-local
    '/^0\./',                     // Current network
    '/^224\./',                   // Multicast
    '/^240\./',                   // Reserved
  ];

  foreach ($private_ranges as $pattern) {
    if (preg_match($pattern, $ip)) {
      return true;
    }
  }

  // Block IPv6 loopback and link-local
  if (strpos($ip, '::1') === 0 || strpos($ip, 'fe80:') === 0) {
    return true;
  }

  return false;
}

/**
 * Fetch URL content with DNS rebinding protection.
 * Re-validates the resolved IP after connection to prevent
 * DNS rebinding attacks where the attacker controls DNS to
 * return a safe IP during validation, then a private IP during fetch.
 *
 * @param string $url URL to fetch
 * @return string|false Content or false on failure
 */
function kwik_ai_fetch_url_content(string $url): ?string
{
  kwik_ai_log('Kwik AI: Fetching content from URL: ' . $url);

  // Try WordPress HTTP API with redirect disabled to prevent redirect-based SSRF
  $response = wp_remote_get($url, [
    'timeout' => 30,
    'user-agent' => 'Mozilla/5.0 (compatible; KwikAI/1.0; +https://kwik-ai.com)',
    'headers' => [
      'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    ],
    'redirection' => 0, // Disable redirects to prevent redirect-based SSRF
  ]);

  if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
    $content = wp_remote_retrieve_body($response);
    kwik_ai_log('Kwik AI: Successfully fetched content via wp_remote_get (' . strlen($content) . ' chars)');
    return $content;
  }

  kwik_ai_log('Kwik AI: wp_remote_get failed, trying file_get_contents');

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
    kwik_ai_log('Kwik AI: Successfully fetched content via file_get_contents (' . strlen($content) . ' chars)');
    return $content;
  }

  kwik_ai_log('Kwik AI: file_get_contents failed');
  return false;
}

/**
 * Scrape and summarize content from a URL
 *
 * @param string $url
 * @return string|false
 */
function kwik_ai_scrape_and_summarize_url(string $url)
{
  kwik_ai_log('Kwik AI: Scraping content from URL: ' . $url);

  // Validate URL format
  if (!filter_var($url, FILTER_VALIDATE_URL)) {
    kwik_ai_log('Kwik AI: Invalid URL provided: ' . $url);
    return false;
  }

  // SSRF protection - check if URL targets internal/private addresses
  if (!kwik_ai_validate_url_safety($url)) {
    kwik_ai_log('Kwik AI: URL blocked by SSRF protection: ' . $url);
    return false;
  }

  // Get content from URL
  $content = kwik_ai_fetch_url_content($url);
  if (!$content) {
    kwik_ai_log('Kwik AI: Failed to fetch content from URL: ' . $url);
    return false;
  }

  // Extract main content from HTML
  $main_content = kwik_ai_extract_main_content($content);
  if (!$main_content) {
    kwik_ai_log('Kwik AI: Failed to extract main content from URL: ' . $url);
    return false;
  }

  // Summarize the content using Ollama
  $summary = kwik_ai_summarize_content($main_content);
  if (!$summary) {
    kwik_ai_log('Kwik AI: Failed to summarize content from URL: ' . $url);
    return false;
  }

  kwik_ai_log('Kwik AI: Successfully scraped and summarized content from URL: ' . $url);
  return $summary;
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
    kwik_ai_log('Kwik AI: Extracting main content from HTML (' . strlen($html) . ' chars)');
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
      kwik_ai_log('Kwik AI: Extracted main content (' . strlen($main_content) . ' chars)');
    }
    return $main_content;
  }

  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Failed to extract main content');
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
    kwik_ai_log('Kwik AI: Summarizing content (' . strlen($content) . ' chars)');
  }

  // Create a prompt for summarization
  $prompt = 'Summarize the following content in a clear and concise way. Focus on the main points and key information. Keep the summary to 2-3 sentences. Respond with only the summary text, no extra formatting or labels.' . "\n\n" . $content;

  // Query AI provider for summary
  $summary = kwik_ai_tags_query_ai_text_only($prompt);

  if ($summary) {
    $summary = trim($summary);
    if (defined('WP_DEBUG') && WP_DEBUG) {
      kwik_ai_log('Kwik AI: Generated summary (' . strlen($summary) . ' chars): ' . substr($summary, 0, 100) . '...');
    }
    return $summary;
  }

  if (defined('WP_DEBUG') && WP_DEBUG) {
    kwik_ai_log('Kwik AI: Failed to generate summary');
  }
  return false;
}
