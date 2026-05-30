# KWIK AI Tags Plugin - Changelog

## Version 3.1 - FAL.AI Featured Image Generation (May 2026)

### New Features

#### **AI Featured Image Generation (FAL.AI)**
- New **"AI Featured Image"** meta box in the post editor with a single button to
  generate a featured image from the post's content.
- Your configured text AI provider (Ollama / OpenRouter / OpenAI) reads the whole
  post, distills it into its visual theme, and then crafts a creative image prompt
  illustrating that theme (two-step summarize-then-illustrate), which is sent to FAL.AI.
- Optional **guidance** field lets you steer style, subject, and mood.
- **Two-step workflow with a visible, editable prompt**: click "Generate Prompt" to
  see the prompt in an editable field (it reports whether it came from the AI or a
  fallback excerpt), tweak it as needed — e.g. to remove words an image content filter
  wrongly flags — then click "Generate Image". You can also type a prompt from scratch.
- Generated images are **previewed** before being sideloaded into the Media Library
  and set as the featured image. A **Regenerate** button lets you retry first.
- New **FAL.AI Image Generation** settings section: encrypted API key, image model
  selection, and default image size.
- The image-model dropdown is populated from FAL.AI's **live text-to-image catalog**
  (cached for 12 hours, with a "Refresh Models" link), so new models like Nano Banana 2
  appear automatically. Falls back to a built-in list if FAL.AI can't be reached.

### New Settings
- **Dedicated "Text Model" setting.** Text-only generation (featured-image prompts
  and URL-based descriptions) can now use a separate model from the main (vision)
  model. Leave it on "Use the main Model" to keep prior behavior, or pick a text/chat
  model when your main model is vision-only. The image-prompt step now uses this model.

### Bug Fixes
- **Custom (OpenAI-compatible) providers can now generate text.** Text generation
  for the `custom` provider previously only called Ollama's native `/api/generate`,
  which fails on OpenAI-compatible servers (Open WebUI, LM Studio, vLLM, LocalAI).
  It now tries `/chat/completions` first and falls back to `/api/generate`, matching
  how the model list is already fetched. This affected the AI image-prompt step
  (which silently fell back to a raw post excerpt).
- **Support newer OpenAI models that reject `max_tokens`.** Chat-completion requests
  now retry with `max_completion_tokens` when a model/proxy reports that `max_tokens`
  is unsupported (GPT-5-class models), instead of failing the request.
- **Support reasoning models.** Reasoning models spend tokens on hidden reasoning and
  can return empty content (`finish_reason: length`) when the token budget is small.
  Chat requests now retry once with a much larger budget in that case, and the
  image-prompt steps request a generous budget up front to avoid the extra round trip.

### Technical Notes
- Uses FAL.AI's asynchronous **queue API** with client-side polling, so slow image
  models do not block a single long-running request.
- FAL job tracking URLs and the resulting image URL are stored server-side
  (per-request transient with ownership checks); the browser only ever holds an
  opaque `request_id`.
- `kwik_ai_tags_query_ai_text_only()` now accepts optional system-prompt and
  max-token arguments (backward compatible) so the image-prompt step isn't biased
  by the tag-generation system prompt.

---

## Version 3.0 - Multi-Provider Support (March 2026)

### New Features

#### 1. **Multiple AI Provider Support**
- **OpenRouter**: Access a wide range of AI models through OpenRouter's unified API
- **OpenAI**: Direct integration with OpenAI's GPT models (GPT-4o, GPT-4o-mini, etc.)
- **Ollama**: Continue using local models with full backward compatibility

#### 2. **Configurable API Endpoint**
- Users can now set custom API endpoints for any provider
- Default endpoints are automatically set based on provider selection
- Supports both local and cloud-based AI services

#### 3. **Unified Settings Page**
- All AI provider configurations available in one place
- Connection status testing for all providers
- Model selection with vision capability indicators

### Technical Improvements

- **Updated API Integration**: `ollama-api.php` now supports Ollama, OpenRouter, and OpenAI
- **Provider-Specific Headers**: Automatic header configuration based on provider
- **Enhanced Error Handling**: Better error messages for each provider type
- **Model List Caching**: Improved model fetching with provider-specific lists

### Migration

- Existing Ollama installations continue to work without any changes
- No data migration required
- Settings are automatically configured with sensible defaults

---

## Version 2.9 - Customizable Description Length (September 10, 2025)

### New Features
- **Customizable Description Length**: Added slider controls to set minimum and maximum word count for generated descriptions
- **Word Count Control**: Users can now specify exact length requirements for AI-generated descriptions
- **Minimum Length Enforcement**: Ensures all descriptions are at least 50 words long
- **Flexible Range Selection**: Supports word count ranges from 50-500 words

### Technical Improvements
- **New Parameters**:
  - `min_words` - Minimum word count for descriptions
  - `max_words` - Maximum word count for descriptions
- **Enhanced Block Editor**: Slider controls with input fields for precise word count selection
- **Updated Prompts**: Dynamic Ollama prompts that include word count constraints
- **Better Validation**: Ensures min/max values are properly constrained

### User Interface
- **Range Sliders**: Intuitive slider controls for setting description length
- **Input Fields**: Direct numeric input for precise word count settings
- **Visual Feedback**: Clear display of current min/max word count settings
- **Real-time Updates**: Sliders automatically adjust to maintain valid ranges

### Migration
- Existing installations automatically gain description length customization
- No data loss or configuration required
- Plugin works immediately after update
- Backward compatibility maintained with default 50-200 word range

---

## Version 2.8 - URL Content Generation (September 10, 2025)

### New Features
- **URL Content Generation**: AI Description Block now supports generating descriptions from external URLs
- **Web Scraping**: Automatically extracts and summarizes content from provided URLs
- **Two-Step Process**: First summarizes content from URLs, then generates a description based on the summary
- **Flexible Input**: Works with both images and URLs, or either independently
- **Multiple URLs**: Support for adding multiple URLs to generate comprehensive descriptions

### Technical Improvements
- **New Functions**:
  - `kwik_ai_scrape_and_summarize_url()` - Main URL scraping and summarization function
  - `kwik_ai_fetch_url_content()` - Fetches content from URLs
  - `kwik_ai_extract_main_content()` - Extracts main content from HTML
  - `kwik_ai_summarize_content()` - Summarizes content using Ollama
  - `kwik_ai_tags_query_ollama_text_only()` - Text-only Ollama queries
  - `kwik_ai_description_generate_from_urls()` - Generates descriptions from URLs
- **Enhanced Block Editor**: URL input fields with add/remove functionality
- **Improved AJAX Handling**: Supports both image and URL-based generation
- **Better Error Handling**: Clear error messages for URL-related issues

### User Interface
- **URL Input Fields**: Easy-to-use input fields for adding external URLs
- **Add/Remove Buttons**: Dynamic management of multiple URLs
- **Updated Prompts**: Clear instructions for using URL functionality
- **Enhanced Validation**: Better URL validation and error feedback

### Migration
- Existing installations automatically gain URL functionality
- No data loss or configuration required
- Plugin works immediately after update
- Backward compatibility maintained for image-only generation

---

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
- **Gutenberg Block Registration**: Proper WordPress block registration with editor scripts
- **React-Based Block**: Modern React implementation for the block editor
- **State Management**: Block state stored in block attributes for persistence
- **Asset Management**: Proper enqueueing of block assets

### User Interface
- **Block Selection**: Appears in the block inserter under "KWIK AI" category
- **Inline Editing**: Generated descriptions are editable directly in the block
- **Regenerate Button**: Easy way to create new descriptions
- **Loading States**: Visual feedback during generation

### Migration
- Existing installations automatically gain the new block
- Meta box functionality remains available for backward compatibility
- No data loss or configuration required
