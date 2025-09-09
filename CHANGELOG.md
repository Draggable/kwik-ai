# KWIK AI Tags Plugin - Changelog

## Version 2.7 - Code Restructuring (September 9, 2025)

### New Features
- **Code Restructuring**: Plugin code has been reorganized into a more maintainable structure with separate directories for core, includes, admin, and blocks functionality
- **Improved Maintainability**: Code is now organized into logical modules making it easier to understand and modify
- **Better Performance**: More efficient file loading and organization

### Technical Improvements
- **New Directory Structure**:
  - `/core/` - Core plugin functionality (init, constants, functions)
  - `/includes/` - Helper functions (image processing, Ollama API, tag/description generation)
  - `/admin/` - Admin-specific functionality (meta boxes, settings, AJAX handlers)
  - `/blocks/` - Gutenberg block functionality
  - `/assets/` - Frontend assets (CSS, JS) organized into subdirectories
- **Modular Loading**: Plugin now uses a modular approach with clear separation of concerns
- **Backward Compatibility**: All existing functionality remains intact with no breaking changes

### Migration
- No changes required for existing installations
- Plugin works immediately after update
- All settings and data are preserved

---

## Version 2.6 - AI Description Block (September 5, 2025)

### New Features
- **AI Description Block**: Added a new Gutenberg block for generating AI descriptions
- **Block Editor Integration**: Full integration with WordPress block editor
- **Editable Descriptions**: Generated descriptions can be edited directly in the block
- **Multiple Blocks**: Multiple AI description blocks can be added to a single post

### Technical Improvements
- **New Functions**:
  - `kwik_ai_register_blocks()` - Registers Gutenberg blocks
  - `kwik_ai_description_block_editor_assets()` - Enqueues block assets
  - `kwik_ai_description_block_render()` - Renders block on frontend
- **Enhanced JavaScript**: Modern React-based block implementation
- **Improved User Experience**: Better controls and feedback in the block editor

### User Interface
- **Block Controls**: Regeneration and editing controls built into the block
- **Inspector Panel**: Additional settings in the block inspector
- **Loading States**: Visual feedback during AI generation
- **Error Handling**: Clear error messages for troubleshooting

### Migration
- Existing meta box functionality remains unchanged
- No data loss or configuration required
- Plugin works immediately after update

---

## Version 2.4 - Post Type Settings (September 2, 2025)

### New Features
- **Settings Page**: Added comprehensive settings page at Settings > KWIK AI Tags
- **Post Type Selection**: Choose which post types should have AI tag generation enabled
- **Connection Status**: Real-time Ollama connection and model availability checking
- **Settings Link**: Quick access to settings from the plugins page

### Settings Features
- **Post Type Configuration**: Enable/disable AI tags for any public post type
- **Visual Status Indicators**: Shows Ollama connection status and gemma3:27b model availability
- **Requirement Information**: Clear documentation of system requirements
- **Default Fallback**: Always falls back to 'post' type if no valid types selected

### Technical Improvements
- **New Functions**:
  - `kwik_ai_tags_get_enabled_post_types()` - Centralized post type management
  - `kwik_ai_tags_add_admin_menu()` - Settings page integration
  - `kwik_ai_tags_settings_init()` - WordPress Settings API integration
  - `kwik_ai_tags_test_ollama_connection()` - Real-time status checking
  - `kwik_ai_tags_sanitize_post_types()` - Input validation and sanitization

### User Interface
- **Custom CSS**: Professional styling for settings page
- **Responsive Design**: Works on all screen sizes
- **Clear Instructions**: Help text and descriptions throughout
- **Status Indicators**: Visual feedback for system status

### Code Refactoring
- Removed hardcoded post types (`'post', 'belt'`)
- Centralized post type checking through settings
- Dynamic meta box registration based on settings
- Backward compatibility maintained

### Migration
- Existing installations automatically use 'post' as default
- No data loss or configuration required
- Plugin works immediately after update

---

## Version 2.3 - Block Image Support (September 2, 2025)

### New Features
- **Block Image Detection**: Now extracts images from WordPress block content (Gutenberg editor)
- **Comprehensive Image Analysis**: Supports images from:
  - Traditional attached media
  - Image blocks (`core/image`)
  - Gallery blocks (`core/gallery`)
  - Media & Text blocks (`core/media-text`)
  - Cover blocks (`core/cover`)
  - Nested blocks and custom blocks with images

### Technical Improvements
- **New Functions**:
  - `kwik_ai_tags_extract_block_images()` - Main block parser
  - `kwik_ai_tags_extract_images_from_blocks()` - Recursive block analyzer
  - `kwik_ai_tags_extract_images_from_html()` - HTML fallback parser
  - `kwik_ai_tags_generate_from_image_urls()` - URL-based tag generation
- **Enhanced Debug Info**: Shows attached, block, and total image counts
- **Improved Compatibility**: Works with modern WordPress block editor and classic editor

### Code Refactoring
- Unified image processing through URL-based approach
- Better separation of concerns between attachment and block image handling
- Backward compatibility maintained for existing functionality

---

## Version 2.2 - Previous Updates

### Fixed Issues

#### 1. Meta Box Disappearing
- **Root Cause**: JavaScript event handling issues and potential form submission
- **Fix**: Improved event delegation with proper preventDefault() and stopPropagation()
- **Added**: Event parameter passing to prevent conflicts

#### 2. Ollama Streaming Response Handling
- **Root Cause**: Ollama returns streaming JSON responses by default
- **Fix**: Added `stream: false` to request payload for single JSON response
- **Added**: Fallback parsing for streaming responses if needed

#### 3. Error Handling and Debugging
- **Added**: Comprehensive logging throughout the plugin
- **Added**: Browser console debugging with debug mode
- **Added**: Better error messages and timeout handling
- **Added**: Debug info panel (visible when WP_DEBUG is enabled)

#### 4. AJAX Improvements
- **Added**: Request validation and sanitization
- **Added**: Better timeout handling (120 seconds for vision processing)
- **Added**: Detailed error reporting
- **Added**: Response validation

#### 5. User Experience Enhancements
- **Added**: Loading state with visual feedback
- **Added**: Button state management (disabled during processing)
- **Added**: Success/error message display
- **Added**: Individual tag removal functionality

### Technical Improvements

#### Security
- Nonce verification for all AJAX requests
- Capability checks (`edit_posts`)
- Input sanitization and validation
- XSS protection with proper escaping

#### Performance
- Increased timeout for image processing
- Efficient data URI generation
- Minimal JavaScript footprint
- Proper script/style enqueuing

#### Compatibility
- WordPress coding standards compliance
- jQuery dependency management
- Translation-ready text domain
- Responsive CSS design

### New Files
- `assets/admin.js` - Enhanced JavaScript with debugging
- `assets/admin.css` - Improved styling with animations
- `TROUBLESHOOTING.md` - Comprehensive debugging guide

### Key Configuration
```php
define('KWIK_AI_TAGS_OLLAMA_HOST', 'http://localhost:11434');
define('KWIK_AI_TAGS_MAX_TAGS', 8); // Increased for text+image analysis
define('KWIK_AI_TAGS_MIN_WORDS', 50); // Minimum words for text analysis
```

### Debug Mode
Enable by adding to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Usage Flow
1. Edit/create post with images (attached or in blocks) and/or text content
2. Look for "AI Tags" meta box in sidebar
3. Click "Generate AI Tags" button
4. Review suggested tags (remove unwanted ones)
5. Click "Apply Tags" to add to post
6. Use "Regenerate" for different suggestions

### Browser Console Debugging
When `WP_DEBUG` is enabled, detailed logging appears in browser console with "KWIK AI Tags:" prefix.

### Server-side Logging
All plugin operations are logged to WordPress debug log with "KWIK AI Tags:" prefix when WP_DEBUG is enabled.