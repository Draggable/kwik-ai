# KWIK AI

**Multi-Provider AI Tagging and Description Generation for WordPress**

KWIK AI is a WordPress plugin that automatically generates relevant tags and descriptions for your content using AI. It supports multiple AI providers including **Ollama** (local models), **OpenRouter** (cloud models), and **OpenAI** (GPT models).

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

### 4. AI Featured Image Generation (FAL.AI)
- Generates a **featured image** from your post content with a single button
- Scans the post and uses your text AI provider to craft an image prompt
- Optional guidance field to steer style, subject, and mood
- Preview the result before it's saved, with a Regenerate option
- Uses FAL.AI's queue API so slow image models don't time out

### 5. Multiple AI Provider Support
- **Ollama**: Run models locally on your server
- **OpenRouter**: Access hundreds of AI models through a unified API
- **OpenAI**: Use GPT models directly

## Requirements

- WordPress 5.6+ (any recent LTS)
- One of the following AI providers:
  - **Ollama** running on `http://localhost:11434` (configurable)
  - **OpenRouter** API key
  - **OpenAI** API key
- Vision-capable model for image analysis:
  - **Ollama**: `gemma3:27b`, `llava:13b`, etc.
  - **OpenRouter**: `anthropic/claude-3-5-sonnet-latest`, `openai/gpt-4o`, etc.
  - **OpenAI**: `gpt-4o`, `gpt-4o-mini`, etc.
- Posts with images or at least 50 words of text content

## Installation

1. Upload the plugin to your WordPress site
2. Activate the plugin
3. Configure your AI provider in **Settings > KWIK AI**
4. Ensure your AI provider is running with a vision-capable model

## Configuration

Navigate to **Settings > KWIK AI** to:
- Select your AI provider (Ollama, OpenRouter, or OpenAI)
- Configure the API endpoint
- Select the model to use
- (Optional) Set a separate **Text Model** for text-only generation (image prompts,
  URL-based descriptions) when your main model is vision-only
- Enter API credentials
- Enable/disable specific post types
- View connection status
- (Optional) Add a FAL.AI API key and pick an image model/size for AI featured images

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

### AI Featured Image Meta Box (FAL.AI)
1. Add a FAL.AI API key in **Settings > KWIK AI** and choose an image model and size
2. Create or edit a post with a title and some content
3. Find the "AI Featured Image" meta box in the sidebar
4. (Optional) Enter guidance such as "watercolor style, warm lighting, no people"
5. Click "Generate Prompt" to draft an image prompt from the post, then review and
   edit it in the prompt field (or type your own). This is also where you can remove
   any wording an image content filter might wrongly flag
6. Click "Generate Image" and wait while the image is created
7. Review the preview, then click "Set as Featured Image" (or "Regenerate" for a new
   image from the same prompt)

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

## AI Provider Comparison

### Ollama (Local)
**Pros:**
- Free - no API costs
- Privacy - all processing done locally
- No rate limits
- Works offline after models are downloaded

**Cons:**
- Requires local installation
- Needs sufficient hardware resources
- Model download time
- Limited to available models

**Best for:** Users with local hardware, privacy concerns, or limited budgets

### OpenRouter (Cloud)
**Pros:**
- Access to hundreds of models
- Pay-per-use pricing
- No local setup required
- High-quality models available

**Cons:**
- Costs money
- Requires internet connection
- Rate limits on free tier

**Best for:** Users who want access to multiple models without local setup

### OpenAI (Cloud)
**Pros:**
- Best-in-class models (GPT-4o)
- Simple setup
- Reliable service

**Cons:**
- Costs money
- Requires internet connection
- Limited to OpenAI models

**Best for:** Users who want the best quality and don't mind paying

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

### AI Models

**Ollama Models:**
- Uses Gemma3, LLaVA, or other vision-capable models
- Vision model for image analysis
- Text analysis for content with 50+ words
- Generates concise, relevant tags and descriptions

**OpenRouter Models:**
- Anthropic Claude series
- OpenAI GPT series
- Meta Llama series
- And many more...

**OpenAI Models:**
- GPT-4o
- GPT-4o-mini
- GPT-4
- And more...

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
│   ├── ollama-api.php (supports Ollama, OpenRouter, OpenAI)
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
├── tests/ (test suite)
│   ├── bootstrap.php
│   ├── unit/ (unit tests)
│   │   ├── TestSettings.php
│   │   ├── TestApiFunctions.php
│   │   └── TestTagGeneration.php
│   └── integration/ (integration tests)
│       └── TestPluginIntegration.php
├── debug-test.php
├── README.md
├── CHANGELOG.md
├── BRANCH-GUIDE.md
└── TROUBLESHOOTING.md
```

## Testing

The plugin includes a comprehensive test suite using PHPUnit.

### Running Tests

```bash
# Install dependencies
composer install

# Run all tests
composer test

# Run with coverage report
composer test:coverage

# Run only unit tests
composer test:unit

# Run only integration tests
composer test:integration

# Run code quality checks
composer lint

# Fix code style issues
composer lint:fix
```

### Test Coverage

- **Unit Tests**: Tests individual functions and methods
- **Integration Tests**: Tests plugin functionality end-to-end
- **Code Quality**: PHP_CodeSniffer with WordPress coding standards

### Adding Tests

See `tests/README.md` for detailed instructions on writing tests.

## Troubleshooting

### Common Issues

1. **No tags/descriptions generated**: 
   - Ensure your AI provider is running/configured
   - Check that you have a vision-capable model installed

2. **Timeout errors**: 
   - The AI provider might be processing, try again
   - Increase the timeout in the code if needed

3. **No images found**: 
   - Add images to the post or check image permissions

4. **Block not appearing**: 
   - Check if post type is enabled in settings

5. **Connection errors**: 
   - Test the connection in Settings > KWIK AI
   - Verify API credentials are correct
   - Check firewall settings

### Debug Mode

Enable `WP_DEBUG` to see detailed logging and status information in the meta boxes.

## License

GPL-2.0-or-later
