/**
 * KWIK AI Tags - Admin JavaScript
 */
jQuery(document).ready(function($) {
    'use strict';
    
    // Debug logging function
    function debugLog(message, data) {
        if (kwikAiTags.debug) {
            console.log('KWIK AI Tags: ' + message, data || '');
        }
    }
    
    debugLog('Script loaded', kwikAiTags);
    
    const $container = $('#kwik-ai-tags-container');
    const $generateBtn = $('#kwik-ai-tags-generate');
    const $regenerateBtn = $('#kwik-ai-tags-regenerate');
    const $applyBtn = $('#kwik-ai-tags-apply');
    const $loading = $('#kwik-ai-tags-loading');
    const $preview = $('#kwik-ai-tags-preview');
    const $tagsList = $('#kwik-ai-tags-list');
    const $error = $('#kwik-ai-tags-error');
    
    // Description elements
    const $descContainer = $('#kwik-ai-description-container');
    const $descGenerateBtn = $('#kwik-ai-description-generate');
    const $descRegenerateBtn = $('#kwik-ai-description-regenerate');
    const $descApplyBtn = $('#kwik-ai-description-apply');
    const $descLoading = $('#kwik-ai-description-loading');
    const $descPreview = $('#kwik-ai-description-preview');
    const $descText = $('#kwik-ai-description-text');
    const $descError = $('#kwik-ai-description-error');
    const $descLength = $('#kwik-ai-description-length');
    const $descLengthValue = $('#kwik-ai-description-length-value');
    const $descTone = $('#kwik-ai-description-tone');
    
    debugLog('Elements found', {
        container: $container.length,
        generateBtn: $generateBtn.length,
        loading: $loading.length,
        preview: $preview.length
    });
    
    let currentTags = [];
    
    /**
     * Show loading state
     */
    function showLoading() {
        debugLog('Showing loading state');
        $generateBtn.prop('disabled', true).text('Analyzing...');
        $regenerateBtn.prop('disabled', true);
        $loading.show();
        $preview.hide();
        $error.hide();
    }
    
    /**
     * Hide loading state
     */
    function hideLoading() {
        debugLog('Hiding loading state');
        $generateBtn.prop('disabled', false).text('Generate AI Tags');
        $regenerateBtn.prop('disabled', false);
        $loading.hide();
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        debugLog('Showing error', message);
        hideLoading();
        $error.find('.error-message').text(message);
        $error.show();
        $preview.hide();
    }
    
    /**
     * Display tags in the preview
     */
    function displayTags(tags) {
        debugLog('Displaying tags', tags);
        currentTags = tags;
        $tagsList.empty();
        
        tags.forEach(function(tag, index) {
            const $tagItem = $('<div class="kwik-ai-tag-item"></div>');
            const $tagSpan = $('<span class="kwik-ai-tag"></span>').text(tag);
            const $removeBtn = $('<button type="button" class="kwik-ai-tag-remove" title="Remove tag">×</button>');
            
            $removeBtn.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                removeTag(index);
            });
            
            $tagItem.append($tagSpan).append($removeBtn);
            $tagsList.append($tagItem);
        });
        
        hideLoading();
        $preview.show();
        $error.hide();
    }
    
    /**
     * Remove a tag from the preview
     */
    function removeTag(index) {
        debugLog('Removing tag at index', index);
        currentTags.splice(index, 1);
        displayTags(currentTags);
    }
    
    /**
     * Generate tags via AJAX
     */
    function generateTags(e) {
        debugLog('Starting tag generation');
        
        // Prevent default if this is triggered by a button
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        if (!kwikAiTags.postId) {
            showError('No post ID found. Please save the post first.');
            return;
        }
        
        showLoading();
        
        const ajaxData = {
            action: 'kwik_ai_tags_generate',
            nonce: kwikAiTags.nonce,
            post_id: kwikAiTags.postId
        };
        
        debugLog('AJAX data', ajaxData);
        
        $.ajax({
            url: kwikAiTags.ajaxUrl,
            type: 'POST',
            data: ajaxData,
            timeout: 120000, // 2 minutes
            success: function(response) {
                debugLog('AJAX success', response);
                if (response.success) {
                    displayTags(response.data.tags);
                } else {
                    showError(response.data || kwikAiTags.strings.error);
                }
            },
            error: function(xhr, status, error) {
                debugLog('AJAX error', {xhr: xhr, status: status, error: error});
                let errorMessage = kwikAiTags.strings.error;
                
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Ollama might be processing - try again.';
                } else if (xhr.responseText) {
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        errorMessage = errorData.data || errorMessage;
                    } catch (e) {
                        errorMessage = 'Server error: ' + xhr.status;
                    }
                }
                
                showError(errorMessage);
            }
        });
    }
    
    /**
     * Apply tags to the post
     */
    function applyTags(e) {
        debugLog('Applying tags', currentTags);
        
        // Prevent default if this is triggered by a button
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        if (currentTags.length === 0) {
            showError('No tags to apply.');
            return;
        }
        
        $applyBtn.prop('disabled', true).text('Applying...');
        
        $.ajax({
            url: kwikAiTags.ajaxUrl,
            type: 'POST',
            data: {
                action: 'kwik_ai_tags_apply',
                nonce: kwikAiTags.nonce,
                post_id: kwikAiTags.postId,
                tags: currentTags.join(',')
            },
            success: function(response) {
                debugLog('Apply success', response);
                if (response.success) {
                    // Show success message briefly
                    const $success = $('<div class="kwik-ai-tags-success"></div>').text(response.data);
                    $container.prepend($success);
                    
                    setTimeout(function() {
                        $success.fadeOut(function() {
                            $success.remove();
                        });
                    }, 3000);
                    
                    // Refresh the WordPress tags meta box if it exists
                    if (typeof tagBox !== 'undefined') {
                        tagBox.get('post_tag');
                    }
                    
                } else {
                    showError(response.data || kwikAiTags.strings.error);
                }
            },
            error: function(xhr, status, error) {
                debugLog('Apply error', {xhr: xhr, status: status, error: error});
                showError(kwikAiTags.strings.error);
            },
            complete: function() {
                $applyBtn.prop('disabled', false).text('Apply Tags');
            }
        });
    }
    
    // Event handlers - use event delegation and prevent default
    $container.on('click', '#kwik-ai-tags-generate', function(e) {
        e.preventDefault();
        e.stopPropagation();
        generateTags();
    });
    
    $container.on('click', '#kwik-ai-tags-regenerate', function(e) {
        e.preventDefault();
        e.stopPropagation();
        generateTags();
    });
    
    $container.on('click', '#kwik-ai-tags-apply', function(e) {
        e.preventDefault();
        e.stopPropagation();
        applyTags();
    });
    
    debugLog('Event handlers attached');
    
    // Description-specific functions
    let currentDescription = '';
    
    /**
     * Show description loading state
     */
    function showDescLoading() {
        debugLog('Showing description loading state');
        $descGenerateBtn.prop('disabled', true).text('Analyzing...');
        $descRegenerateBtn.prop('disabled', true);
        $descLoading.show();
        $descPreview.hide();
        $descError.hide();
    }
    
    /**
     * Hide description loading state
     */
    function hideDescLoading() {
        debugLog('Hiding description loading state');
        $descGenerateBtn.prop('disabled', false).text('Generate AI Excerpt');
        $descRegenerateBtn.prop('disabled', false);
        $descLoading.hide();
    }
    
    /**
     * Show description error message
     */
    function showDescError(message) {
        debugLog('Showing description error', message);
        hideDescLoading();
        $descError.find('.error-message').text(message);
        $descError.show();
        $descPreview.hide();
    }
    
    /**
     * Display description in the preview
     */
    function displayDescription(description) {
        debugLog('Displaying description', description.substring(0, 100) + '...');
        currentDescription = description;
        $descText.text(description);
        hideDescLoading();
        $descPreview.show();
        $descError.hide();
    }
    
    /**
     * Generate description via AJAX
     */
    function generateDescription(e) {
        debugLog('Starting description generation');
        
        // Prevent default if this is triggered by a button
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        if (!kwikAiDescription.postId) {
            showDescError('No post ID found. Please save the post first.');
            return;
        }
        
        showDescLoading();
        
        const ajaxData = {
            action: 'kwik_ai_description_generate',
            nonce: kwikAiDescription.nonce,
            post_id: kwikAiDescription.postId,
            max_words: $descLength.length ? $descLength.val() : 35,
            tone: $descTone.length ? $descTone.val() : 'straight'
        };
        
        debugLog('Description AJAX data', ajaxData);
        
        $.ajax({
            url: kwikAiDescription.ajaxUrl,
            type: 'POST',
            data: ajaxData,
            timeout: 120000, // 2 minutes
            success: function(response) {
                debugLog('Description AJAX success', response);
                if (response.success) {
                    displayDescription(response.data.description);
                } else {
                    showDescError(response.data || kwikAiDescription.strings.error);
                }
            },
            error: function(xhr, status, error) {
                debugLog('Description AJAX error', {xhr: xhr, status: status, error: error});
                let errorMessage = kwikAiDescription.strings.error;
                
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Ollama might be processing - try again.';
                } else if (xhr.responseText) {
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        errorMessage = errorData.data || errorMessage;
                    } catch (e) {
                        errorMessage = 'Server error: ' + xhr.status;
                    }
                }
                
                showDescError(errorMessage);
            }
        });
    }

    function updateDescriptionLengthLabel() {
        if ($descLength.length && $descLengthValue.length) {
            $descLengthValue.text($descLength.val());
        }
    }
    
    /**
     * Apply the generated excerpt to the post.
     */
    function applyExcerptToEditor(excerpt) {
        debugLog('Setting post excerpt');

        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch && wp.data.dispatch('core/editor')) {
            // Block editor: stage the excerpt; it is saved with the post.
            wp.data.dispatch('core/editor').editPost({ excerpt: excerpt });
            debugLog('Set excerpt via block editor');
        } else if ($('#excerpt').length) {
            // Classic editor excerpt field.
            $('#excerpt').val(excerpt);
            debugLog('Set excerpt via classic editor field');
        } else {
            debugLog('No excerpt field found');
        }
    }
    
    /**
     * Apply description to the post
     */
    function applyDescription(e) {
        debugLog('Applying description', currentDescription.substring(0, 100) + '...');
        
        // Prevent default if this is triggered by a button
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        if (currentDescription.length === 0) {
            showDescError('No description to apply.');
            return;
        }
        
        $descApplyBtn.prop('disabled', true).text('Applying...');
        
        $.ajax({
            url: kwikAiDescription.ajaxUrl,
            type: 'POST',
            data: {
                action: 'kwik_ai_description_apply',
                nonce: kwikAiDescription.nonce,
                post_id: kwikAiDescription.postId,
                description: currentDescription
            },
            success: function(response) {
                debugLog('Description apply success', response);
                if (response.success) {
                    // Apply the generated excerpt to the post
                    applyExcerptToEditor(currentDescription);
                    
                    // Show success message briefly
                    const $success = $('<div class="kwik-ai-tags-success"></div>').text(response.data);
                    $descContainer.prepend($success);
                    
                    setTimeout(function() {
                        $success.fadeOut(function() {
                            $success.remove();
                        });
                    }, 3000);
                } else {
                    showDescError(response.data || kwikAiDescription.strings.error);
                }
            },
            error: function(xhr, status, error) {
                debugLog('Description apply error', {xhr: xhr, status: status, error: error});
                showDescError(kwikAiDescription.strings.error);
            },
            complete: function() {
                $descApplyBtn.prop('disabled', false).text('Apply');
            }
        });
    }
    
    // Description event handlers
    $descContainer.on('input change', '#kwik-ai-description-length', updateDescriptionLengthLabel);
    updateDescriptionLengthLabel();

    $descContainer.on('click', '#kwik-ai-description-generate', function(e) {
        e.preventDefault();
        e.stopPropagation();
        generateDescription();
    });
    
    $descContainer.on('click', '#kwik-ai-description-regenerate', function(e) {
        e.preventDefault();
        e.stopPropagation();
        generateDescription();
    });
    
    $descContainer.on('click', '#kwik-ai-description-apply', function(e) {
        e.preventDefault();
        e.stopPropagation();
        applyDescription();
    });
});
