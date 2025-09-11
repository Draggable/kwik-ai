# KWIK AI

Auto-tag posts with the Gemma3:27b model via Ollama. Analyzes both images (including block images and custom TRB belt gallery blocks) and text content (50+ words) with preview functionality. Also generates AI descriptions for belt posts based on attached images or content scraped from URLs, with customizable description length. Configure which post types to enable via Settings > KWIK AI.

## Features

### 1. AI Tags Generation
- Generates relevant tags from images and text content
- Available as a meta box in the post editor
- Supports multiple image sources (attachments, blocks, galleries)
- Preview and edit tags before applying

### 2. AI Description Generation (Meta Box)
- Generates descriptions from post images using AI
- Available as a meta box in the post editor sidebar
- Inserts description into post content as HTML comments

### 3. AI Description Block (NEW!)
- **Gutenberg block** for generating AI descriptions
- Self-contained with generation controls built into the block
- Editable content directly in the block editor
- Can generate descriptions from images or content scraped from URLs
- **Customizable description length** with slider controls (50-500 words)
- No insertion into post content - the block contains the description

## Requirements

- WordPress 5.6+ (any recent LTS)
- Ollama running on http://localhost:11434
- gemma3:27b model pulled (`ollama pull gemma3:27b`)
- Posts with images or at least 50 words of text content

## Installation

1. Upload the plugin to your WordPress site
2. Activate the plugin
3. Configure post types in Settings > KWIK AI
4. Ensure Ollama is running with the gemma3:27b model

## Usage

### AI Tags Meta Box
1. Create or edit a post with images and/or sufficient text
2. Find the "AI Tags" meta box in the sidebar
3. Click "Generate AI Tags" to analyze content
4. Review and remove unwanted tags
5. Click "Apply Tags" to add them to the post

### AI Description Meta Box
1. Create or edit a post with images
2. Find the "AI Description" meta box in the sidebar
3. Click "Generate AI Description" to analyze images
4. Review the generated description
5. Click "Apply" to insert into post content

### AI Description Block (Recommended)
1. Add a new block and search for "AI Description"
2. Insert the block where you want the description
3. Add URLs to external content in the input fields (optional)
4. Set your desired description length using the sliders (50-500 words)
5. Click "Generate Description" to create content from images or URLs
6. Edit the description directly in the block if needed
7. Use the "Regenerate" button to create new content

## Block vs Meta Box

### AI Description Block (Recommended)
- ✅ Content stays within the block (no HTML comments)
- ✅ Editable directly in block editor
- ✅ Better content management
- ✅ Can place anywhere in content
- ✅ Multiple blocks per post possible
- ✅ Better user experience

### AI Description Meta Box (Legacy)
- ⚠️ Inserts content with HTML comment markers
- ⚠️ Appends to beginning of post content
- ⚠️ Less flexible positioning
- ✅ Still functional for existing workflows

## Configuration

Navigate to **Settings > KWIK AI** to:
- Enable/disable specific post types
- View Ollama connection status
- Check requirements

## Supported Post Types

By default, the plugin works with:
- Posts (`post`)
- Belts (`belt`)

Additional post types can be enabled in the settings.

## Technical Details

### Image Sources Supported
- Featured images
- Media library attachments
- Gutenberg image blocks
- Gallery blocks
- TRB belt gallery blocks (custom)
- Media & text blocks
- Cover blocks

### AI Model
- Uses Gemma3:27b via Ollama
- Vision model for image analysis
- Text analysis for content with 50+ words
- Generates concise, relevant tags and descriptions

### Performance
- 2-minute timeout for AI generation
- Automatic image deduplication
- Supports multiple image formats
- Optimized for WordPress block editor

## Project Structure

The plugin has been restructured for better maintainability:

```
/kwik-ai/
├── kwik-ai.php (main plugin file)
├── /core/ (core plugin functionality)
│   ├── init.php
│   ├── constants.php
│   └── functions.php
├── /includes/ (helper functions)
│   ├── image-processing.php
│   ├── ollama-api.php
│   ├── tag-generation.php
│   └── description-generation.php
├── /admin/ (admin-specific functionality)
│   ├── admin-init.php
│   ├── meta-boxes.php
│   ├── settings.php
│   └── ajax-handlers.php
├── /blocks/ (Gutenberg block functionality)
│   ├── blocks-init.php
│   └── description-block.php
├── /assets/ (frontend assets)
│   ├── /js/
│   │   ├── admin.js
│   │   └── description-block.js
│   └── /css/
│       ├── admin.css
│       ├── settings.css
│       ├── description-block-editor.css
│       └── description-block-frontend.css
├── debug-test.php
├── README.md
├── CHANGELOG.md
├── TROUBLESHOOTING.md
└── BLOCK-IMPLEMENTATION.md
```

## Troubleshooting

### Common Issues
1. **No tags/descriptions generated**: Ensure Ollama is running and gemma3:27b is installed
2. **Timeout errors**: Ollama might be processing, try again
3. **No images found**: Add images to the post or check image permissions
4. **Block not appearing**: Check if post type is enabled in settings

### Debug Mode
Enable `WP_DEBUG` to see detailed logging and status information in the meta boxes.