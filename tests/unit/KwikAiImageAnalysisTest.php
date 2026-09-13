<?php
/**
 * Unit Tests for image selection and per-image tag merging.
 */

use PHPUnit\Framework\TestCase;

class KwikAiImageAnalysisTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['kwik_ai_test_attachments'] = array();
        $GLOBALS['kwik_ai_test_image_sizes'] = array();
    }

    private function registerAttachment(int $id, string $url, array $sizes): void
    {
        $GLOBALS['kwik_ai_test_attachments'][$url] = $id;
        $GLOBALS['kwik_ai_test_image_sizes'][$id] = $sizes;
    }

    /* ---------------------------------------------------------------- */
    /* kwik_ai_tags_get_analysis_image_url()                             */
    /* ---------------------------------------------------------------- */

    public function testPrefersLargestConfiguredIntermediateSize()
    {
        $this->registerAttachment(10, 'http://example.com/wp-content/uploads/photo.jpg', array(
            'full'      => array('http://example.com/wp-content/uploads/photo.jpg', 2560, 903, false),
            '1536x1536' => array('http://example.com/wp-content/uploads/photo-1536x542.jpg', 1536, 542, true),
            'large'     => array('http://example.com/wp-content/uploads/photo-800x282.jpg', 800, 282, true),
        ));

        $url = kwik_ai_tags_get_analysis_image_url('http://example.com/wp-content/uploads/photo.jpg');

        $this->assertEquals('http://example.com/wp-content/uploads/photo-1536x542.jpg', $url);
    }

    public function testFallsBackThroughSizeListWhenLargerSizesWereNotGenerated()
    {
        $this->registerAttachment(11, 'http://example.com/wp-content/uploads/small.jpg', array(
            'full'   => array('http://example.com/wp-content/uploads/small.jpg', 900, 600, false),
            'medium' => array('http://example.com/wp-content/uploads/small-300x200.jpg', 300, 200, true),
        ));

        $url = kwik_ai_tags_get_analysis_image_url('http://example.com/wp-content/uploads/small.jpg');

        $this->assertEquals('http://example.com/wp-content/uploads/small-300x200.jpg', $url);
    }

    public function testReturnsOriginalWhenNoIntermediateSizeExists()
    {
        $this->registerAttachment(12, 'http://example.com/wp-content/uploads/tiny.jpg', array(
            'full' => array('http://example.com/wp-content/uploads/tiny.jpg', 200, 100, false),
        ));

        $url = kwik_ai_tags_get_analysis_image_url('http://example.com/wp-content/uploads/tiny.jpg');

        $this->assertEquals('http://example.com/wp-content/uploads/tiny.jpg', $url);
    }

    public function testResolvesSizedVariantUrlToItsAttachment()
    {
        $this->registerAttachment(13, 'http://example.com/wp-content/uploads/photo.jpg', array(
            'full'  => array('http://example.com/wp-content/uploads/photo.jpg', 2560, 903, false),
            'large' => array('http://example.com/wp-content/uploads/photo-800x282.jpg', 800, 282, true),
        ));

        // A -WxH variant embedded in content should still map to the attachment.
        $url = kwik_ai_tags_get_analysis_image_url('http://example.com/wp-content/uploads/photo-2048x722.jpg');

        $this->assertEquals('http://example.com/wp-content/uploads/photo-800x282.jpg', $url);
    }

    public function testLeavesUnknownUrlsUntouched()
    {
        $url = kwik_ai_tags_get_analysis_image_url('https://cdn.example.org/external.png');

        $this->assertEquals('https://cdn.example.org/external.png', $url);
    }

    /* ---------------------------------------------------------------- */
    /* kwik_ai_tags_merge_image_tags()                                   */
    /* ---------------------------------------------------------------- */

    public function testMergeRanksTagsSeenInMoreImagesFirst()
    {
        $merged = kwik_ai_tags_merge_image_tags(array(
            array('leather', 'belt', 'buckle'),
            array('belt', 'gold', 'leather'),
            array('belt', 'wrestling'),
        ));

        $this->assertEquals(array('belt', 'leather', 'buckle', 'gold', 'wrestling'), $merged);
    }

    public function testMergeIsCaseInsensitiveAndKeepsFirstSpelling()
    {
        $merged = kwik_ai_tags_merge_image_tags(array(
            array('MVP', 'Belt'),
            array('mvp', 'belt'),
            array('trophy'),
        ));

        $this->assertEquals(array('MVP', 'Belt', 'trophy'), $merged);
    }

    public function testMergeCountsATagOncePerImage()
    {
        // 'belt' repeated inside one image must not outrank 'strap' seen in two.
        $merged = kwik_ai_tags_merge_image_tags(array(
            array('belt', 'Belt', 'BELT', 'strap'),
            array('strap'),
        ));

        $this->assertEquals(array('strap', 'belt'), $merged);
    }

    public function testMergeAppliesLimit()
    {
        $merged = kwik_ai_tags_merge_image_tags(array(
            array('one', 'two', 'three', 'four'),
        ), 2);

        $this->assertEquals(array('one', 'two'), $merged);
    }

    public function testMergeIgnoresEmptyInput()
    {
        $this->assertEquals(array(), kwik_ai_tags_merge_image_tags(array()));
        $this->assertEquals(array(), kwik_ai_tags_merge_image_tags(array(array(), array('', '  '))));
    }

    /* ---------------------------------------------------------------- */
    /* kwik_ai_tags_is_public_image_url()                                */
    /* ---------------------------------------------------------------- */

    public function testPublicHttpsUrlIsSendable()
    {
        $this->assertTrue(kwik_ai_tags_is_public_image_url('https://topropebelts.com/wp-content/uploads/a.jpg'));
        $this->assertTrue(kwik_ai_tags_is_public_image_url('http://cdn.example.org/a.png'));
    }

    public function testDevelopmentAndLoopbackHostsAreNotSendable()
    {
        $not_public = array(
            'http://topropebelts.test/wp-content/uploads/a.jpg',
            'http://localhost/a.jpg',
            'http://localhost:8081/a.jpg',
            'http://mysite.local/a.jpg',
            'http://intranet/a.jpg',
            'http://127.0.0.1/a.jpg',
            'http://192.168.1.193:8081/a.jpg',
            'http://10.0.0.5/a.jpg',
            'http://[::1]/a.jpg',
        );
        foreach ($not_public as $url) {
            $this->assertFalse(kwik_ai_tags_is_public_image_url($url), $url);
        }
    }

    public function testNonHttpValuesAreNotSendable()
    {
        $this->assertFalse(kwik_ai_tags_is_public_image_url('data:image/jpeg;base64,/9j/4AAQ'));
        $this->assertFalse(kwik_ai_tags_is_public_image_url('ftp://example.com/a.jpg'));
        $this->assertFalse(kwik_ai_tags_is_public_image_url('/wp-content/uploads/a.jpg'));
        $this->assertFalse(kwik_ai_tags_is_public_image_url(''));
    }

    /* ---------------------------------------------------------------- */
    /* kwik_ai_tags_build_vision_messages()                              */
    /* ---------------------------------------------------------------- */

    public function testVisionMessagesPassUrlsThroughAndPrefixBase64()
    {
        $messages = kwik_ai_tags_build_vision_messages('prompt', array(
            'https://example.com/a.jpg',
            '/9j/4AAQ',
            'data:image/png;base64,iVBORw0',
        ));

        $parts = $messages[1]['content'];
        $this->assertEquals('https://example.com/a.jpg', $parts[0]['image_url']['url']);
        $this->assertEquals('data:image/jpeg;base64,/9j/4AAQ', $parts[1]['image_url']['url']);
        $this->assertEquals('data:image/png;base64,iVBORw0', $parts[2]['image_url']['url']);
        $this->assertEquals('text', $parts[3]['type']);
        $this->assertEquals('prompt', $parts[3]['text']);
    }
}
