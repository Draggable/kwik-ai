## [1.0.7](https://github.com/Draggable/kwik-ai/compare/v1.0.6...v1.0.7) (2026-06-12)


### Bug Fixes

* use WordPress HTTP and Filesystem APIs instead of file_get_contents ([3fa65b7](https://github.com/Draggable/kwik-ai/commit/3fa65b7ac48f11aaff9f065391e361da7fcf33ed))

## [1.0.6](https://github.com/Draggable/kwik-ai/compare/v1.0.5...v1.0.6) (2026-06-05)


### Bug Fixes

* validate private ip ranges forweb scraper, fix url description handler ([9fa8747](https://github.com/Draggable/kwik-ai/commit/9fa874761192185936d2dea1fadd9400994410d5))

## [1.0.5](https://github.com/Draggable/kwik-ai/compare/v1.0.4...v1.0.5) (2026-06-05)


### Bug Fixes

* generate tags when images are removed from post ([24b1738](https://github.com/Draggable/kwik-ai/commit/24b1738ba91eec0df054139978651810839ded05))
* resolve local image paths via attachment/uploads APIs ([8041ff1](https://github.com/Draggable/kwik-ai/commit/8041ff1d4699c8dc32362e5bb249e42628d6a17b))

## [1.0.4](https://github.com/Draggable/kwik-ai/compare/v1.0.3...v1.0.4) (2026-06-01)


### Bug Fixes

* description controls style, test time constants ([0df93ab](https://github.com/Draggable/kwik-ai/commit/0df93ab337d6b64ad27674a7c0bc27e27320dd3d))

## [1.0.3](https://github.com/Draggable/kwik-ai/compare/v1.0.2...v1.0.3) (2026-06-01)


### Bug Fixes

* text-domain and scraper reported url ([5eedc56](https://github.com/Draggable/kwik-ai/commit/5eedc56dffa82eb579a9206214a9bac1c10b4ff2))

## [1.0.2](https://github.com/Draggable/kwik-ai/compare/v1.0.1...v1.0.2) (2026-05-31)


### Bug Fixes

* readme short description length ([24dca12](https://github.com/Draggable/kwik-ai/commit/24dca125728bce547b4911e7266c8278d490d77d))

## [1.0.1](https://github.com/Draggable/kwik-ai/compare/v1.0.0...v1.0.1) (2026-05-31)


### Bug Fixes

* plugin name ([4a60c09](https://github.com/Draggable/kwik-ai/commit/4a60c099c7f2a488f4eadb637149f6777caeabb0))

# 1.0.0 (2026-05-31)


### Bug Fixes

* add guard clauses ([3c1f0a6](https://github.com/Draggable/kwik-ai/commit/3c1f0a655aa120b06d694ae1f5f4e33c397cedb9))
* **ci:** repair phpcs.xml ruleset and fix security sniff violations ([5c1416a](https://github.com/Draggable/kwik-ai/commit/5c1416a74424e58e4d6dec5212a310079062f56e))
* description block, exclude react from build ([cd8c3d2](https://github.com/Draggable/kwik-ai/commit/cd8c3d26ac1710e02e9e5f9b99db80909ebb5569))
* **HH-2:** separate API key sanitization callbacks per provider ([769762a](https://github.com/Draggable/kwik-ai/commit/769762add2bf304483df46168e88f5b97c6b4811))
* **HH-3:** enable SSL verification on cURL image fetching ([7fddd75](https://github.com/Draggable/kwik-ai/commit/7fddd75b7d93c100d7c2c87cca099f43ab88d2a7))
* **HH-4:** correct function name mismatches causing fatal errors ([4702162](https://github.com/Draggable/kwik-ai/commit/470216204c22ee8bff8455ec9a1958f1913edccf))
* **LL-2:** sanitize AI-generated content before storing in block attributes ([7520af5](https://github.com/Draggable/kwik-ai/commit/7520af51c09ceec1f963c3c9783655b32f6ca457))
* **LL-3:** add rate limiting to AJAX endpoints ([78aad37](https://github.com/Draggable/kwik-ai/commit/78aad373ceaf8d549c1517ccb7dbd49d4bc4db2d))
* **MM-1,MM-3:** escape debug output + gate all error_log calls ([ecd0c5a](https://github.com/Draggable/kwik-ai/commit/ecd0c5a0cd3447026ca36a4fbb095dbe03e57145))
* **MM-4,LL-4:** remove unused nonce fields + gate debug section ([6adac32](https://github.com/Draggable/kwik-ai/commit/6adac3285aba4c1d04d8a70d0bc624c32ef46a86))
* **MM-5,MM-6,LL-1:** strengthen encryption + remove stale constants ([5c892ab](https://github.com/Draggable/kwik-ai/commit/5c892ab42ab04e9cedadbf5e2923d3ae4f00d6a8))
* **MM-7:** harden SSRF protection against DNS rebinding ([b74469d](https://github.com/Draggable/kwik-ai/commit/b74469da65135e7ae6f83c4eab79c48167b76e37))
* node v24 required for semantic release ([af534f6](https://github.com/Draggable/kwik-ai/commit/af534f649202e03b543f04525344b598cf5958fa))
* permissions ([1890855](https://github.com/Draggable/kwik-ai/commit/189085546cd5358f6e7ce547a683eaf1bfc0f3c0))
* plugin check issues, prepare for submission to repository ([58794eb](https://github.com/Draggable/kwik-ai/commit/58794eb2de048aa57c12d25b7043d60b68c26411))


### Features

* add release flow ([a0fe375](https://github.com/Draggable/kwik-ai/commit/a0fe37542522a54e8889a5c0f768e8d9655e0730))
* AI excerpt generation + recover minified JS and fix build ([1895c88](https://github.com/Draggable/kwik-ai/commit/1895c88104c5c76a3424699d8a2531f01c5cdf2b))
* image generation ([29d18ee](https://github.com/Draggable/kwik-ai/commit/29d18ee4f3049508239af1ac2d04411b26848fae))
* merge multi-ai-providers branch ([1371a9a](https://github.com/Draggable/kwik-ai/commit/1371a9ad243f5f6f6c9cd65e409a86ff7078b88c))
* model selector ([7077ccc](https://github.com/Draggable/kwik-ai/commit/7077ccc5f9aca4f1ded228c734709cb5150fdc59))
* test connection flow ([f77fd43](https://github.com/Draggable/kwik-ai/commit/f77fd43e2d74706d705a6d9ade482bece6a30671))

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
