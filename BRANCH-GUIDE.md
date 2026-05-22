# KWIK AI - Multi-Provider AI Support

## Overview

This branch adds support for multiple AI providers to the KWIK AI WordPress plugin:
- **Ollama** (local models) - existing functionality preserved
- **OpenRouter** (cloud models) - new
- **OpenAI** (GPT models) - new

## Changes Made

### 1. Settings Page (`admin/settings.php`)

#### New Settings Added
- **AI Provider** (`kwik_ai_ai_provider`): Dropdown to select Ollama, OpenRouter, or OpenAI
- **API Endpoint** (`kwik_ai_api_endpoint`): Configurable endpoint for all providers
- **Model** (`kwik_ai_model`): Configurable model name
- **OpenRouter API Key** (`kwik_ai_openrouter_api_key`): Separate key field
- **OpenAI API Key** (`kwik_ai_openai_api_key`): Separate key field
- **Custom API Key** (`kwik_ai_custom_api_key`): Bearer token for custom/Ollama endpoints

#### Updated Settings
- **Ollama Authentication**: Replaced username/password with API key (Bearer token)

### 2. API Integration (`includes/ollama-api.php`)

#### New Functions
- `kwik_ai_tags_get_ai_model()`: Gets configured model
- `kwik_ai_tags_get_ai_provider()`: Gets configured provider
- `kwik_ai_tags_get_api_endpoint()`: Gets configured endpoint based on provider
- `kwik_ai_tags_get_api_key()`: Gets API key based on provider
- `kwik_ai_tags_get_api_headers()`: Gets appropriate headers for provider

#### Updated Functions
- `kwik_ai_tags_query_ai()`: Unified function that works with all providers
- `kwik_ai_tags_query_ai_text_only()`: Text-only version for all providers
- `kwik_ai_tags_get_ollama_config()`: Updated to use new settings structure
- `kwik_ai_tags_test_ai_connection()`: Tests connection based on provider

### 3. Constants (`core/constants.php`)

All constants now read from settings instead of hardcoded values:
- `KWIK_AI_AI_PROVIDER`
- `KWIK_AI_API_ENDPOINT`
- `KWIK_AI_API_KEY`
- `KWIK_AI_MODEL`
- `KWIK_AI_OPENROUTER_API_KEY`
- `KWIK_AI_OPENAI_API_KEY`

## Provider-Specific Details

### Ollama
- **Endpoint**: `http://localhost:11434` (default)
- **Authentication**: Bearer token (API key)
- **Models**: gemma3, llava, etc.
- **API Endpoint**: `/api/generate`

### OpenRouter
- **Endpoint**: `https://openrouter.ai/api/v1` (default)
- **Authentication**: Bearer token
- **Models**: Hundreds available (Claude, GPT, Llama, etc.)
- **API Endpoint**: `/chat/completions`

### OpenAI
- **Endpoint**: `https://api.openai.com/v1` (default)
- **Authentication**: Bearer token
- **Models**: GPT-4o, GPT-4, etc.
- **API Endpoint**: `/chat/completions`

## Testing Setup

The plugin now includes a comprehensive test suite:

### Run Tests
```bash
# Install dependencies first
composer install

# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run code quality checks
composer lint
```

### Test Coverage
- Unit tests for settings, API functions, and tag generation
- Integration tests for overall plugin functionality
- Code quality checks with PHP_CodeSniffer

## Backward Compatibility

All existing Ollama configurations continue to work:
- Default endpoint matches previous behavior
- Settings are migrated automatically
- No breaking changes to existing functionality

## Configuration Migration

If you have existing settings, they will be automatically migrated:
- `kwik_ai_tags_ollama_url` → `kwik_ai_api_endpoint`
- `kwik_ai_tags_ollama_model` → `kwik_ai_model`
- Credentials remain in place

## Usage

### For Existing Users (Ollama)
No changes needed. The plugin continues to work exactly as before.

### For New Users (OpenRouter/OpenAI)
1. Go to **Settings > KWIK AI**
2. Select your preferred provider
3. Configure the API endpoint
4. Enter your API key
5. Select a model
6. Save settings
