<?php
/**
 * Security Tests for Kwik AI Plugin
 *
 * Static analysis tests that verify security patterns exist in source code.
 * Each test asserts the presence of specific security functions/patterns
 * that address identified vulnerabilities.
 */

use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    private $plugin_dir;

    protected function setUp(): void
    {
        // __DIR__ = tests/unit/, so go up two levels to plugin root
        $this->plugin_dir = dirname(dirname(__DIR__));
    }

    /**
     * Helper: read file content
     */
    private function getFileContent(string $relative_path): string
    {
        $path = $this->plugin_dir . '/' . $relative_path;
        $this->assertFileExists($path, "File $relative_path should exist");
        return file_get_contents($path);
    }

    // =========================================================================
    // HH-1: Stored XSS in description block render callback
    // =========================================================================

    public function testDescriptionBlockEscapesOutput()
    {
        $content = $this->getFileContent('blocks/description-block.php');

        // Must use esc_html() or wp_kses_post() on the description before output
        $this->assertMatchesRegularExpression(
            '/esc_html\s*\(/',
            $content,
            'description-block.php must use esc_html() to escape output'
        );

        // Must sanitize the description variable before output
        $this->assertMatchesRegularExpression(
            '/wp_kses_post\s*\(\s*\$description\s*\)/',
            $content,
            'description-block.php must sanitize description with wp_kses_post()'
        );

        // Must NOT output raw $description in sprintf/echo
        $this->assertDoesNotMatchRegularExpression(
            '/sprintf\s*\([^)]*%s[^)]*\)\s*;[^)]*\n\s*\$description\s*\)/',
            $content,
            'description-block.php must not pass raw $description to output'
        );
    }

    // =========================================================================
    // HH-2: API key cross-provider overwrite bug
    // =========================================================================

    public function testApiKeySanitizationDoesNotCrossOverwrite()
    {
        $content = $this->getFileContent('admin/settings.php');

        // The sanitize_api_key function should NOT store to both keys
        // Check that there are separate sanitization callbacks or the function
        // uses the correct option name contextually
        $has_separate_callbacks = preg_match(
            '/kwik_ai_tags_sanitize_openrouter_api_key|kwik_ai_tags_sanitize_openai_api_key/',
            $content
        );

        $has_contextual_callback = preg_match(
            '/\$option_name\s*=/',
            $content
        );

        $this->assertTrue(
            $has_separate_callbacks || $has_contextual_callback,
            'API key sanitization must not cross-overwrite different provider keys. ' .
            'Use separate callbacks or capture the option name contextually.'
        );
    }

    // =========================================================================
    // HH-3: SSL verification disabled on cURL
    // =========================================================================

    public function testCurlDoesNotDisableSslVerification()
    {
        $content = $this->getFileContent('includes/image-processing.php');

        $this->assertDoesNotMatchRegularExpression(
            '/CURLOPT_SSL_VERIFYPEER\s*,\s*false/',
            $content,
            'image-processing.php must not disable SSL peer verification'
        );
    }

    // =========================================================================
    // HH-4: Fatal error -- function name mismatches
    // =========================================================================

    public function testWebScrapingUsesCorrectFunctionNames()
    {
        $content = $this->getFileContent('includes/web-scraping.php');

        // Must use kwik_ai_tags_query_ai_text_only (not kwik_ai_tags_query_text_only)
        $this->assertMatchesRegularExpression(
            '/kwik_ai_tags_query_ai_text_only\s*\(/',
            $content,
            'web-scraping.php must call kwik_ai_tags_query_ai_text_only()'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/kwik_ai_tags_query_text_only\s*\(/',
            $content,
            'web-scraping.php must not call non-existent kwik_ai_tags_query_text_only()'
        );
    }

    public function testDescriptionGenerationUsesCorrectFunctionNames()
    {
        $content = $this->getFileContent('includes/description-generation.php');

        // Must use kwik_ai_tags_query_ai (not kwik_ai_tags_query_ollama)
        $this->assertMatchesRegularExpression(
            '/kwik_ai_tags_query_ai\s*\(/',
            $content,
            'description-generation.php must call kwik_ai_tags_query_ai()'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/kwik_ai_tags_query_ollama\s*\(/',
            $content,
            'description-generation.php must not call non-existent kwik_ai_tags_query_ollama()'
        );

        // Must use kwik_ai_tags_query_ai_text_only (not kwik_ai_tags_query_ollama_text_only)
        $this->assertMatchesRegularExpression(
            '/kwik_ai_tags_query_ai_text_only\s*\(/',
            $content,
            'description-generation.php must call kwik_ai_tags_query_ai_text_only()'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/kwik_ai_tags_query_ollama_text_only\s*\(/',
            $content,
            'description-generation.php must not call non-existent kwik_ai_tags_query_ollama_text_only()'
        );
    }

    // =========================================================================
    // MM-1: Unescaped debug output in meta box
    // =========================================================================

    public function testMetaBoxDebugOutputIsEscaped()
    {
        $content = $this->getFileContent('admin/meta-boxes.php');

        // The ternary word count output should be escaped
        // Check that there's no bare echo of the ternary without esc_html
        $this->assertDoesNotMatchRegularExpression(
            '/echo\s+\$word_count\s*>=\s*KWIK_AI_MIN_WORDS/',
            $content,
            'meta-boxes.php must escape the word count ternary output'
        );
    }

    // =========================================================================
    // MM-2: Custom API key not encrypted
    // =========================================================================

    public function testCustomApiKeyUsesSecureStorage()
    {
        $content = $this->getFileContent('admin/settings.php');

        // The custom API key setting should use secure sanitization
        $this->assertMatchesRegularExpression(
            '/kwik_ai_custom_api_key.*kwik_ai.*sanitize.*api.*key|kwik_ai.*store_credential.*kwik_ai_custom_api_key/i',
            $content,
            'Custom API key should use secure credential storage'
        );
    }

    // =========================================================================
    // MM-3: Excessive error logging in production
    // =========================================================================

    public function testErrorLoggingGatedByWpDebug()
    {
        $files_to_check = [
            'admin/meta-boxes.php',
            'admin/admin-init.php',
            'includes/tag-generation.php',
            'includes/description-generation.php',
            'includes/image-processing.php',
        ];

        foreach ($files_to_check as $file) {
            $content = $this->getFileContent($file);

            // Find all error_log() calls
            preg_match_all('/error_log\s*\(/', $content, $matches, PREG_OFFSET_CAPTURE);

            if (empty($matches[0])) {
                continue; // No error_log calls -- that's fine
            }

            // Each error_log should be preceded by a WP_DEBUG check within ~200 chars
            foreach ($matches[0] as $match) {
                $pos = $match[1];
                // Look backwards for WP_DEBUG gate
                $before = substr($content, max(0, $pos - 300), 300);

                // Check if there's a WP_DEBUG conditional wrapping this call
                $has_debug_gate = strpos($before, "WP_DEBUG") !== false ||
                                  strpos($before, 'KWIK_AI_TEST_MODE') !== false;

                $this->assertTrue(
                    $has_debug_gate,
                    "$file: error_log() at position $pos is not gated by WP_DEBUG check"
                );
            }
        }
    }

    // =========================================================================
    // LL-5: Missing image MIME type validation
    // =========================================================================

    public function testImageFetchingValidatesMimeType()
    {
        $content = $this->getFileContent('includes/image-processing.php');

        // Should validate content-type starts with 'image/'
        $this->assertMatchesRegularExpression(
            '/strpos\s*\(\s*\$content_type\s*,\s*[\'"]image\/[\'"]\s*\)/',
            $content,
            'image-processing.php should validate that fetched content has image MIME type'
        );
    }
}
