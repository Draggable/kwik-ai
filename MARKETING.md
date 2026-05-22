# Kwik AI

**AI-Powered Tags and Descriptions for WordPress**

## Overview

Kwik AI automatically generates relevant tags and descriptions for your WordPress posts using artificial intelligence. It analyzes both the text content and attached images of your posts to produce accurate, context-aware tags that improve content organization and discoverability.

The plugin supports multiple AI providers, giving you the flexibility to run models locally with Ollama for full data privacy or connect to cloud services like OpenRouter and OpenAI for maximum convenience. Vision-capable models can analyze images directly, extracting subjects, activities, and themes that might not be obvious from text alone.

Kwik AI integrates into the WordPress editing workflow with a dedicated meta box, letting you generate tags on demand and preview results before applying them. It also includes a Gutenberg block for displaying AI-generated descriptions directly on the front end.

## Key Features

- **Multi-source tag generation** -- analyzes both post text and attached images to produce comprehensive tags
- **Vision model support** -- models like GPT-4o, LLaVA, and Gemma 3 can see and interpret images
- **Three AI providers** -- connect to Ollama (local), OpenRouter, or OpenAI
- **Model picker with live detection** -- fetches available models from your provider and highlights vision-capable ones
- **Per-post-type configuration** -- enable AI tagging for posts, pages, custom post types, or any combination
- **Description generation** -- generate written descriptions from images or scraped URL content
- **Gutenberg description block** -- display AI-generated descriptions on your site with a native WordPress block
- **Web scraping integration** -- pull and analyze content from external URLs to generate descriptions
- **Secure credential storage** -- API keys and passwords are encrypted before database storage
- **Custom post type support** -- works with any registered post type in your WordPress installation

## Use Cases

- **Content-heavy websites** -- automate tagging for blogs, news sites, and magazines with high post volume
- **E-commerce catalogs** -- generate product tags from product images and descriptions to improve internal search
- **Photo and portfolio sites** -- use vision models to tag images automatically, saving hours of manual work
- **Multilingual content** -- AI models understand content in multiple languages and generate appropriate tags
- **SEO optimization** -- rich, relevant tags improve WordPress taxonomy-based navigation and search engine indexing
- **Digital asset management** -- tag and organize large media libraries with AI-powered metadata

## Value Proposition

Kwik AI turns minutes of manual tagging into seconds of automated generation. It reduces the friction of content organization, ensures consistent tagging across your site, and leverages cutting-edge AI models without requiring any machine learning expertise. Run it locally for privacy or connect to cloud providers for ease of use.

## Technical Highlights

- **WordPress plugin** -- install via the WordPress plugin directory or upload directly
- **WordPress 5.6+ compatible** -- works with all recent WordPress versions
- **PHP 7.4+ required** -- standard modern PHP requirement
- **Ollama, OpenRouter, and OpenAI support** -- choose the provider that fits your needs
- **Gutenberg block included** -- native WordPress block editor integration
- **AJAX-powered** -- tag generation happens asynchronously without page reloads
- **Secure by design** -- nonce verification, input sanitization, and encrypted credential storage
- **Extensible** -- hook into the tag generation pipeline with WordPress filters and actions
