<?php
/**
 * Unit Tests for Kwik AI API Functions
 *
 * Tests for the AI API integration functions.
 */

use PHPUnit\Framework\TestCase;

class KwikAiApiTest extends TestCase
{
    /**
     * Test that model is retrieved correctly
     */
    public function testGetAiModel() {
        $model = kwik_ai_tags_get_ai_model();
        $this->assertEquals('gemma3:27b', $model);
        $this->assertNotEmpty($model);
    }

    /**
     * Test that provider is retrieved correctly
     */
    public function testGetAiProvider() {
        $provider = kwik_ai_tags_get_ai_provider();
        $this->assertEquals('custom', $provider);
        $this->assertContains($provider, array('custom', 'openrouter', 'openai'));
    }

    /**
     * Test API endpoint retrieval
     */
    public function testGetApiEndpoint() {
        $endpoint = kwik_ai_tags_get_api_endpoint();
        $this->assertEquals('http://localhost:11434', $endpoint);
        $this->assertStringStartsWith('http', $endpoint);
    }

    /**
     * Test that API key functions return empty by default
     */
    public function testApiKeysEmptyByDefault() {
        $this->assertEquals('', kwik_ai_tags_get_api_key('custom'));
        $this->assertEquals('', kwik_ai_tags_get_api_key('openrouter'));
        $this->assertEquals('', kwik_ai_tags_get_api_key('openai'));
    }

    /**
     * Test custom endpoint config retrieval
     */
    public function testGetOllamaConfig() {
        $config = kwik_ai_tags_get_ollama_config();
        
        $this->assertArrayHasKey('url', $config);
        $this->assertArrayHasKey('api_key', $config);
        $this->assertArrayHasKey('has_auth', $config);
        
        $this->assertEquals('http://localhost:11434', $config['url']);
        $this->assertEquals('', $config['api_key']);
        $this->assertFalse($config['has_auth']);
    }

    /**
     * Test Ollama auth header generation
     */
    public function testGetOllamaAuthHeaderNoAuth() {
        $headers = kwik_ai_tags_get_ollama_auth_header();
        $this->assertEmpty($headers);
    }

    /**
     * Test that API headers function returns correct structure
     */
    public function testGetApiHeaders() {
        $headers = kwik_ai_tags_get_api_headers('custom');
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertEquals('application/json', $headers['Content-Type']);
    }
}
