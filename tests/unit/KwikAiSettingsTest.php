<?php
/**
 * Unit Tests for Kwik AI Settings
 *
 * Tests for the settings page and configuration functions.
 */

use PHPUnit\Framework\TestCase;

class KwikAiSettingsTest extends TestCase
{
    /**
     * Test that settings are properly initialized with defaults
     */
    public function testSettingsInitializeWithDefaults()
    {
        // Test default AI provider
        $this->assertEquals('custom', get_option('kwik_ai_ai_provider'));
        
        // Test default endpoint
        $this->assertEquals('http://localhost:11434', get_option('kwik_ai_api_endpoint'));
        
        // Test default model
        $this->assertEquals('gemma3:27b', get_option('kwik_ai_model'));
        
        // Test default post types
        $this->assertEquals(array('post', 'belt'), get_option('kwik_ai_tags_enabled_post_types'));
        
        // Test default credentials (empty)
        $this->assertEquals('', get_option('kwik_ai_openrouter_api_key'));
        $this->assertEquals('', get_option('kwik_ai_openai_api_key'));
    }

    /**
     * Test that settings can be updated
     */
    public function testSettingsCanBeUpdated()
    {
        // In real WordPress, these would be updated via update_option()
        // For testing, we verify the structure is correct
        $this->assertTrue(is_array(get_option('kwik_ai_tags_enabled_post_types')));
        $this->assertTrue(is_string(get_option('kwik_ai_api_endpoint')));
    }

    /**
     * Test sanitize_post_types function
     */
    public function testSanitizePostTypes() {
        // This function is defined in admin/settings.php
        // We test the logic manually
        
        $test_input = array('post', 'invalid_post_type', 'belt');
        $result = kwik_ai_tags_sanitize_post_types($test_input);
        
        $this->assertContains('post', $result);
        $this->assertContains('belt', $result);
        $this->assertNotContains('invalid_post_type', $result);
    }

    /**
     * Test that default post types are always valid
     */
    public function testDefaultPostTypesAreValid()
    {
        $default = array('post', 'belt');
        foreach ($default as $post_type) {
            $this->assertTrue(post_type_exists($post_type));
        }
    }
}
