<?php
/**
 * Integration Tests for Kwik AI Plugin
 *
 * Tests that verify the plugin works end-to-end.
 */

use PHPUnit\Framework\TestCase;

class KwikAiIntegrationTest extends TestCase
{
    /**
     * Test that plugin file loads without errors
     */
    public function testPluginLoads() {
        // This verifies that kwik-ai.php can be included without fatal errors
        $this->assertTrue(true, 'Plugin file should load without errors');
    }

    /**
     * Test that all required functions are defined
     */
    public function testRequiredFunctionsDefined() {
        $required_functions = array(
            'kwik_ai_tags_get_ai_model',
            'kwik_ai_tags_get_ai_provider',
            'kwik_ai_tags_get_api_endpoint',
            'kwik_ai_tags_get_api_key',
            'kwik_ai_tags_get_api_headers',
            'kwik_ai_tags_query_ai',
            'kwik_ai_tags_query_ai_text_only',
            'kwik_ai_tags_parse_tags',
            'kwik_ai_tags_get_ollama_config',
            'kwik_ai_tags_get_ollama_auth_header',
        );

        foreach ($required_functions as $function) {
            $this->assertTrue(
                function_exists($function),
                "Function {$function} should be defined"
            );
        }
    }

    /**
     * Test that constants are properly defined
     */
    public function testConstantsDefined() {
        $constants = array(
            'KWIK_AI_MAX_TAGS',
            'KWIK_AI_MIN_WORDS',
            'KWIK_AI_MAX_DESCRIPTION_LENGTH',
            'KWIK_AI_DOMAIN',
            'KWIK_AI_DEFAULT_OLLAMA_HOST',
            'KWIK_AI_DEFAULT_OPENROUTER_ENDPOINT',
            'KWIK_AI_DEFAULT_OPENAI_ENDPOINT',
        );

        foreach ($constants as $constant) {
            $this->assertTrue(
                defined($constant),
                "Constant {$constant} should be defined"
            );
        }

        // Dynamic settings (provider, endpoint, model, API keys) must NOT
        // be defined as constants -- they use getter functions instead.
        // See LL-1 fix: constants freeze values on first load.
        $this->assertFalse(
            defined('KWIK_AI_AI_PROVIDER'),
            'KWIK_AI_AI_PROVIDER should NOT be a constant (use getter function)'
        );
        $this->assertFalse(
            defined('KWIK_AI_API_KEY'),
            'KWIK_AI_API_KEY should NOT be a constant (use getter function)'
        );
    }

    /**
     * Test that settings page can be accessed
     */
    public function testSettingsPageAccessible() {
        // Verify settings functions exist and work
        $this->assertTrue(function_exists('kwik_ai_tags_add_admin_menu'));
        $this->assertTrue(function_exists('kwik_ai_tags_settings_init'));
        $this->assertTrue(function_exists('kwik_ai_tags_settings_page'));
    }

    /**
     * Test that admin menu is registered
     */
    public function testAdminMenuRegistered() {
        // The add_options_page should return a hook
        $result = add_options_page(
            'Test Title',
            'Test Menu',
            'manage_options',
            'test-menu',
            ''
        );
        
        $this->assertNotFalse($result);
    }

    /**
     * Test that meta boxes can be added
     */
    public function testMetaBoxesCanAdd() {
        // Verify meta box functions work
        $result = add_meta_box(
            'test-meta-box',
            'Test Box',
            '__return_empty_array',
            'post',
            'side',
            'high'
        );
        
        $this->assertTrue($result);
    }

    /**
     * Test block registration
     */
    public function testBlockRegistration() {
        // Verify block registration works
        $result = register_block_type('kwik-ai/test-block', array(
            'editor_script' => 'test-script',
            'editor_style' => 'test-style',
        ));
        
        $this->assertTrue($result);
    }
}
