<?php
/**
 * Test script for URL scraping functionality
 * 
 * To use this script:
 * 1. Access it via browser: https://yoursite.com/wp-content/plugins/kwik-ai/test-web-scraping.php
 * 2. Or run it via command line: php test-web-scraping.php
 * 
 * Note: For web access, you must be logged in as an administrator
 */

// Check if running from command line or web
if (php_sapi_name() !== 'cli') {
    // Load WordPress environment
    require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');
    
    // Check if user is logged in and is administrator
    if (!is_user_logged_in() || !current_user_can('administrator')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    echo '<pre>';
    echo "=== Web Scraping Test ===\n\n";
} else {
    echo "=== Web Scraping Test ===\n\n";
}

// Include the plugin files to access the functions
require_once(dirname(__FILE__) . '/includes/web-scraping.php');
require_once(dirname(__FILE__) . '/includes/ollama-api.php');

// Test the web scraping function
echo "Testing URL scraping functionality...\n\n";

// Test with a simple URL
$url = 'https://example.com';
echo "Testing URL: " . $url . "\n";

$summary = kwik_ai_scrape_and_summarize_url($url);
if ($summary) {
    echo "SUCCESS! Summary: " . $summary . "\n";
} else {
    echo "FAILED to scrape and summarize URL\n";
}

echo "\n=== Test Complete ===\n";

if (php_sapi_name() !== 'cli') {
    echo '</pre>';
    echo '<p><a href="' . admin_url() . '">Return to WordPress Admin</a></p>';
}