(function() {
    'use strict';

    const { __ } = wp.i18n;
    const { registerBlockType } = wp.blocks;
    const { useState, useEffect } = wp.element;
    const { 
        PanelBody,
        Button,
        Placeholder,
        Spinner,
        Notice,
        TextControl
    } = wp.components;
    const { 
        InspectorControls,
        RichText,
        useBlockProps
    } = wp.blockEditor;
    const { useSelect } = wp.data;

    /**
     * Generate description via AJAX
     */
    function generateDescription(postId, urls, onSuccess, onError) {
        if (!postId) {
            onError(kwikAiDescriptionBlock.strings.error);
            return;
        }

        jQuery.ajax({
            url: kwikAiDescriptionBlock.ajaxUrl,
            type: 'POST',
            data: {
                action: 'kwik_ai_description_generate',
                nonce: kwikAiDescriptionBlock.nonce,
                post_id: postId,
                urls: urls
            },
            timeout: 120000, // 2 minutes
            success: function(response) {
                if (response.success && response.data.description) {
                    onSuccess(response.data.description);
                } else {
                    onError(response.data || kwikAiDescriptionBlock.strings.error);
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = kwikAiDescriptionBlock.strings.error;
                
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
                
                onError(errorMessage);
            }
        });
    }

    /**
     * AI Description Block Component
     */
    function AIDescriptionEdit({ attributes, setAttributes }) {
        const { description, urls = [] } = attributes;
        const [isGenerating, setIsGenerating] = useState(false);
        const [error, setError] = useState('');
        const [urlInputs, setUrlInputs] = useState(urls.length > 0 ? [...urls, ''] : ['']);
        
        const blockProps = useBlockProps({
            className: 'kwik-ai-description-block'
        });

        // Get current post ID
        const postId = useSelect(select => {
            return select('core/editor').getCurrentPostId();
        }, []);

        // Update postId in attributes if it changes
        useEffect(() => {
            if (postId && postId !== attributes.postId) {
                setAttributes({ postId });
            }
        }, [postId]);

        const handleGenerate = () => {
            setIsGenerating(true);
            setError('');

            // Filter out empty URLs
            const validUrls = urlInputs.filter(url => url.trim() !== '');

            generateDescription(
                postId,
                validUrls,
                (generatedDescription) => {
                    setAttributes({ description: generatedDescription, urls: validUrls });
                    setIsGenerating(false);
                },
                (errorMessage) => {
                    setError(errorMessage);
                    setIsGenerating(false);
                }
            );
        };

        const handleDescriptionChange = (newDescription) => {
            setAttributes({ description: newDescription });
        };

        const handleUrlChange = (index, value) => {
            const newUrls = [...urlInputs];
            newUrls[index] = value;
            setUrlInputs(newUrls);
        };

        const addUrlField = () => {
            setUrlInputs([...urlInputs, '']);
        };

        const removeUrlField = (index) => {
            if (urlInputs.length > 1) {
                const newUrls = [...urlInputs];
                newUrls.splice(index, 1);
                setUrlInputs(newUrls);
            }
        };

        // If no description yet, show placeholder with generate button
        if (!description && !isGenerating) {
            return (
                <div {...blockProps}>
                    <Placeholder
                        icon="text-page"
                        label={kwikAiDescriptionBlock.strings.title}
                        instructions={kwikAiDescriptionBlock.strings.placeholder}
                    >
                        {error && (
                            <Notice status="error" isDismissible={false}>
                                {error}
                            </Notice>
                        )}
                        {urlInputs.map((url, index) => (
                            <div key={index} style={{ marginBottom: '10px', display: 'flex', alignItems: 'center' }}>
                                <TextControl
                                    placeholder="https://example.com/article"
                                    value={url}
                                    onChange={(value) => handleUrlChange(index, value)}
                                    style={{ flex: 1 }}
                                />
                                {urlInputs.length > 1 && (
                                    <Button 
                                        isSecondary 
                                        isSmall 
                                        onClick={() => removeUrlField(index)}
                                        style={{ marginLeft: '10px' }}
                                    >
                                        Remove
                                    </Button>
                                )}
                            </div>
                        ))}
                        <Button 
                            isSecondary 
                            isSmall 
                            onClick={addUrlField}
                            style={{ marginBottom: '10px' }}
                        >
                            Add URL
                        </Button>
                        <Button
                            isPrimary
                            onClick={handleGenerate}
                            disabled={isGenerating || !postId}
                        >
                            {kwikAiDescriptionBlock.strings.generateButton}
                        </Button>
                    </Placeholder>
                </div>
            );
        }

        // If generating, show loading state
        if (isGenerating) {
            return (
                <div {...blockProps}>
                    <Placeholder
                        icon="text-page"
                        label={kwikAiDescriptionBlock.strings.title}
                        instructions={kwikAiDescriptionBlock.strings.generating}
                    >
                        <Spinner />
                    </Placeholder>
                </div>
            );
        }

        // Show the editable description with controls
        return (
            <>
                <InspectorControls>
                    <PanelBody title={kwikAiDescriptionBlock.strings.title}>
                        <p>{kwikAiDescriptionBlock.strings.description}</p>
                        {error && (
                            <Notice status="error" isDismissible={true} onRemove={() => setError('')}>
                                {error}
                            </Notice>
                        )}
                        {urlInputs.map((url, index) => (
                            <div key={index} style={{ marginBottom: '10px', display: 'flex', alignItems: 'center' }}>
                                <TextControl
                                    placeholder="https://example.com/article"
                                    value={url}
                                    onChange={(value) => handleUrlChange(index, value)}
                                    style={{ flex: 1 }}
                                />
                                {urlInputs.length > 1 && (
                                    <Button 
                                        isSecondary 
                                        isSmall 
                                        onClick={() => removeUrlField(index)}
                                        style={{ marginLeft: '10px' }}
                                    >
                                        Remove
                                    </Button>
                                )}
                            </div>
                        ))}
                        <Button 
                            isSecondary 
                            isSmall 
                            onClick={addUrlField}
                            style={{ marginBottom: '10px' }}
                        >
                            Add URL
                        </Button>
                        <Button
                            isSecondary
                            onClick={handleGenerate}
                            disabled={isGenerating || !postId}
                            style={{ marginBottom: '10px', width: '100%' }}
                        >
                            {isGenerating 
                                ? kwikAiDescriptionBlock.strings.generating 
                                : kwikAiDescriptionBlock.strings.regenerateButton
                            }
                        </Button>
                    </PanelBody>
                </InspectorControls>
                
                <div {...blockProps}>
                    <div className="kwik-ai-description-content">
                        <RichText
                            tagName="p"
                            value={description}
                            onChange={handleDescriptionChange}
                            placeholder={kwikAiDescriptionBlock.strings.placeholder}
                            allowedFormats={['core/bold', 'core/italic']}
                        />
                    </div>
                    <div className="kwik-ai-description-controls">
                        <Button
                            isSmall
                            isSecondary
                            onClick={handleGenerate}
                            disabled={isGenerating || !postId}
                        >
                            {isGenerating 
                                ? kwikAiDescriptionBlock.strings.generating 
                                : kwikAiDescriptionBlock.strings.regenerateButton
                            }
                        </Button>
                    </div>
                </div>
            </>
        );
    }

    /**
     * Save function - just return the RichText content
     */
    function AIDescriptionSave({ attributes }) {
        const { description } = attributes;
        const blockProps = useBlockProps.save({
            className: 'wp-block-kwik-ai-description'
        });

        if (!description) {
            return null;
        }

        return (
            <div {...blockProps}>
                <RichText.Content tagName="p" value={description} />
            </div>
        );
    }

    /**
     * Register the block
     */
    registerBlockType('kwik-ai/description', {
        title: kwikAiDescriptionBlock.strings.title,
        description: kwikAiDescriptionBlock.strings.description,
        icon: 'text-page',
        category: 'common',
        attributes: {
            description: {
                type: 'string',
                source: 'html',
                selector: 'p',
                default: '',
            },
            urls: {
                type: 'array',
                default: [],
            },
            postId: {
                type: 'number',
                default: 0,
            }
        },
        supports: {
            html: false,
            multiple: true,
            reusable: true,
        },
        edit: AIDescriptionEdit,
        save: AIDescriptionSave,
    });

})();
