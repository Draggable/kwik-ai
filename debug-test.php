<?php
/**
 * Test AJAX manually to debug the plugin
 */

// Add debug AJAX endpoint
add_action('wp_ajax_kwik_ai_debug_test', 'kwik_ai_debug_test');

function kwik_ai_debug_test() {
    error_log('KWIK AI Debug: Test AJAX endpoint called');
    error_log('KWIK AI Debug: POST data: ' . print_r($_POST, true));
    
    // Test Ollama connection
    $ollama_test = wp_remote_post('http://localhost:11434/api/generate', [
        'body' => json_encode([
            'model' => 'gemma3:27b',
            'prompt' => 'Hello test',
            'stream' => false
        ]),
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 30
    ]);
    
    if (is_wp_error($ollama_test)) {
        error_log('KWIK AI Debug: Ollama error: ' . $ollama_test->get_error_message());
        wp_send_json_error('Ollama connection failed: ' . $ollama_test->get_error_message());
    } else {
        $response_code = wp_remote_retrieve_response_code($ollama_test);
        $body = wp_remote_retrieve_body($ollama_test);
        error_log('KWIK AI Debug: Ollama response code: ' . $response_code);
        error_log('KWIK AI Debug: Ollama response: ' . substr($body, 0, 200));
        
        wp_send_json_success([
            'message' => 'Test successful',
            'ollama_response_code' => $response_code,
            'ollama_response' => substr($body, 0, 200)
        ]);
    }
}

// Add admin notice with test button on post edit pages
add_action('admin_notices', function() {
    $screen = get_current_screen();
    if ($screen && ($screen->id === 'post' || $screen->base === 'post')) {
        ?>
        <div class="notice notice-info">
            <p>
                <strong>KWIK AI Debug Mode:</strong>
                <button type="button" id="kwik-ai-debug-test" class="button">Test AJAX & Ollama</button>
                <button type="button" id="kwik-ai-js-test" class="button">Test JS Loading</button>
                <span id="kwik-ai-debug-result"></span>
            </p>
            <script>
            jQuery(document).ready(function($) {
                console.log('KWIK AI Debug: jQuery loaded, setting up debug buttons');
                
                $('#kwik-ai-debug-test').click(function() {
                    console.log('KWIK AI Debug: Test button clicked');
                    $('#kwik-ai-debug-result').text('Testing AJAX & Ollama...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'kwik_ai_debug_test',
                            test: 'debug'
                        },
                        success: function(response) {
                            console.log('KWIK AI Debug: AJAX success:', response);
                            $('#kwik-ai-debug-result').html('✓ AJAX Test passed: ' + JSON.stringify(response.data).substring(0, 100));
                        },
                        error: function(xhr, status, error) {
                            console.log('KWIK AI Debug: AJAX error:', xhr, status, error);
                            $('#kwik-ai-debug-result').html('✗ AJAX Test failed: ' + error);
                        }
                    });
                });
                
                $('#kwik-ai-js-test').click(function() {
                    console.log('KWIK AI Debug: JS test button clicked');
                    var jsLoaded = typeof kwikAiTags !== 'undefined';
                    var elementsFound = {
                        container: $('#kwik-ai-tags-container').length,
                        generateBtn: $('#kwik-ai-tags-generate').length
                    };
                    
                    $('#kwik-ai-debug-result').html('JS Loaded: ' + jsLoaded + ', Elements: ' + JSON.stringify(elementsFound));
                    console.log('KWIK AI Debug: kwikAiTags object:', typeof kwikAiTags !== 'undefined' ? kwikAiTags : 'undefined');
                    console.log('KWIK AI Debug: Elements found:', elementsFound);
                });
            });
            </script>
        </div>
        <?php
    }
});
?>
