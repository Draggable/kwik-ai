<?php
/**
 * Unit Tests for Kwik AI Tag Generation
 *
 * Tests for the tag parsing and generation functions.
 */

use PHPUnit\Framework\TestCase;

class KwikAiTagGenerationTest extends TestCase
{
    /**
     * Test tag parsing with simple comma-separated list
     */
    public function testParseTagsSimpleList() {
        $raw_response = 'tag1,tag2,tag3';
        $tags = kwik_ai_tags_parse_tags($raw_response);
        
        $this->assertCount(3, $tags);
        $this->assertEquals('tag1', $tags[0]);
        $this->assertEquals('tag2', $tags[1]);
        $this->assertEquals('tag3', $tags[2]);
    }

    /**
     * Test tag parsing with extra whitespace
     */
    public function testParseTagsWithWhitespace() {
        $raw_response = '  tag1 , tag2 , tag3  ';
        $tags = kwik_ai_tags_parse_tags($raw_response);
        
        $this->assertCount(3, $tags);
        $this->assertEquals('tag1', $tags[0]);
        $this->assertEquals('tag2', $tags[1]);
        $this->assertEquals('tag3', $tags[2]);
    }

    /**
     * Test tag parsing with quotes
     */
    public function testParseTagsWithQuotes() {
        $raw_response = '"tag1","tag2","tag3"';
        $tags = kwik_ai_tags_parse_tags($raw_response);
        
        $this->assertCount(3, $tags);
        $this->assertEquals('tag1', $tags[0]);
        $this->assertEquals('tag2', $tags[1]);
        $this->assertEquals('tag3', $tags[2]);
    }

    /**
     * Test tag parsing removes duplicates (case-insensitive)
     */
    public function testParseTagsRemovesDuplicates() {
        $raw_response = 'tag1,Tag1,TAG1,tag2';
        $tags = kwik_ai_tags_parse_tags($raw_response);
        
        $this->assertCount(2, $tags);
        $this->assertEquals('tag1', $tags[0]);
        $this->assertEquals('tag2', $tags[1]);
    }

    /**
     * Test tag parsing filters out very short tags
     */
    public function testParseTagsFiltersShortTags() {
        $raw_response = 'ab,c,d12,valid_tag';
        $tags = kwik_ai_tags_parse_tags($raw_response);
        
        // 'ab' is exactly 2 chars so it passes, 'c' is too short
        $this->assertContains('valid_tag', $tags);
        $this->assertNotContains('c', $tags);
    }

    /**
     * Test tag parsing filters out very long tags
     */
    public function testParseTagsFiltersLongTags() {
        $raw_response = 'short,verylongtagname_that_exceeds_fifty_characters';
        $tags = kwik_ai_tags_parse_tags($raw_response);
        
        $this->assertContains('short', $tags);
        $this->assertNotContains('verylongtagname_that_exceeds_fifty_characters', $tags);
    }

    /**
     * Test tag parsing removes invalid characters
     */
    public function testParseTagsRemovesInvalidCharacters() {
        $raw_response = 'tag#1,tag$2,tag%3,tag4';
        $tags = kwik_ai_tags_parse_tags($raw_response);
        
        $this->assertContains('tag1', $tags);
        $this->assertContains('tag2', $tags);
        $this->assertContains('tag3', $tags);
        $this->assertContains('tag4', $tags);
        $this->assertNotContains('#', $tags[0]);
        $this->assertNotContains('$', $tags[1]);
        $this->assertNotContains('%', $tags[2]);
    }

    /**
     * Test empty response
     */
    public function testParseTagsEmptyResponse() {
        $raw_response = '';
        $tags = kwik_ai_tags_parse_tags($raw_response);
        
        $this->assertEmpty($tags);
    }

    /**
     * Test single tag
     */
    public function testParseTagsSingleTag() {
        $raw_response = 'single';
        $tags = kwik_ai_tags_parse_tags($raw_response);
        
        $this->assertCount(1, $tags);
        $this->assertEquals('single', $tags[0]);
    }

    /**
     * Test tag with spaces converted to hyphens
     */
    public function testParseTagsSpacesInTags() {
        $raw_response = 'machine learning,web development,data science';
        $tags = kwik_ai_tags_parse_tags($raw_response);
        
        $this->assertCount(3, $tags);
        $this->assertEquals('machine learning', $tags[0]);
        $this->assertEquals('web development', $tags[1]);
        $this->assertEquals('data science', $tags[2]);
    }
}
