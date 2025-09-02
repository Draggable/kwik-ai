# KWIK AI Tags Plugin

## Overview

The KWIK AI Tags plugin automatically generates relevant tags for WordPress posts by analyzing attached images using the Gemma3:27b model via Ollama. The plugin now includes a preview feature that allows you to review and modify suggested tags before applying them to your posts.

## Features

- **AI-Powered Tag Generation**: Uses the Gemma3:27b model to analyze images and suggest relevant tags
- **Preview Interface**: Review generated tags before applying them to your post
- **Individual Tag Management**: Remove unwanted tags from the preview
- **Seamless Integration**: Works directly in the WordPress post editor sidebar
- **AJAX-Powered**: Smooth, real-time interactions without page reloads

## Requirements

- WordPress 5.6+ (any recent LTS version)
- Ollama running on http://localhost:11434
- Gemma3:27b model installed (`ollama pull gemma3:27b`)
- Posts with attached images

## Installation

1. Copy the plugin folder to `wp-content/plugins/kwik-ai-tags/`
2. Activate the plugin in the WordPress admin
3. Ensure Ollama is running with the Gemma3:27b model

## Usage

### Generating Tags

1. Edit or create a new post
2. Add images to your post (via the media library)
3. Look for the "AI Generated Tags" meta box in the sidebar
4. Click "Generate AI Tags" to analyze your images
5. Review the suggested tags in the preview area
6. Remove any unwanted tags by clicking the "×" button
7. Click "Apply Tags" to add the tags to your post

### Regenerating Tags

If you're not satisfied with the generated tags, click "Regenerate" to get a new set of suggestions.

## Configuration

The plugin includes several configurable constants at the top of the main file:

- `KWIK_AI_TAGS_OLLAMA_HOST`: Ollama server URL (default: http://localhost:11434)
- `KWIK_AI_TAGS_MAX_TAGS`: Maximum number of tags to generate (default: 5)

## File Structure

```
kwik-ai-tags/
├── kwik-ai-tags.php          # Main plugin file
├── assets/
│   ├── admin.js              # Admin JavaScript functionality
│   └── admin.css             # Admin styling
└── README.md                 # This file
```

## Technical Details

### Security Features

- Nonce verification for all AJAX requests
- Capability checks for user permissions
- Input sanitization and validation
- XSS protection for all outputs

### Performance Optimizations

- Efficient image processing with data URIs
- Timeout handling for Ollama requests
- Error handling for network issues
- Minimal JavaScript footprint

### WordPress Standards

- Follows WordPress coding standards
- Uses WordPress APIs for all operations
- Proper enqueuing of scripts and styles
- Translation-ready with text domain

## Troubleshooting

### Common Issues

1. **No tags generated**: Ensure Ollama is running and the Gemma3:27b model is available
2. **No images found**: Make sure images are properly attached to the post
3. **Network errors**: Check that Ollama is accessible at the configured host URL

### Debug Steps

1. Verify Ollama is running: `curl http://localhost:11434/api/tags`
2. Check WordPress error logs for any PHP errors
3. Use browser developer tools to check for JavaScript errors
4. Ensure the post has attached images in the media library

## Changelog

### Version 2.0
- Added preview functionality
- Removed automatic tagging on save
- Added meta box in editor sidebar
- Added individual tag removal
- Improved error handling
- Enhanced security measures

### Version 1.0
- Initial release with automatic tagging

## Support

For issues or feature requests, please check the plugin code and configuration. Ensure all requirements are met before reporting problems.
