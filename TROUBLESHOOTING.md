# KWIK AI Tags - Troubleshooting Guide

## Common Issues and Solutions

### 1. Meta Box Doesn't Appear
**Symptoms:** The "AI Generated Tags" box is not visible in the post editor sidebar.

**Solutions:**
- Ensure you're editing a **post** (not page or other post type)
- Check that the plugin is activated
- Look for PHP errors in WordPress debug log
- Try deactivating and reactivating the plugin

### 2. Button Clicks Don't Work / Box Disappears
**Symptoms:** Clicking "Generate AI Tags" makes the box disappear or nothing happens.

**Solutions:**
- Check browser console for JavaScript errors (F12 → Console)
- Ensure WordPress jQuery is loaded
- Check if there are plugin conflicts by deactivating other plugins temporarily
- Verify the AJAX URL is correct in the debug info

### 3. "No Images Found" Error
**Symptoms:** Error message says no images found even when images are attached.

**Solutions:**
- Ensure images are **attached** to the post (not just inserted)
- Re-upload images and make sure they're attached to this specific post
- Check that images are valid formats (JPEG, PNG, GIF, WebP)

### 4. Ollama Connection Issues
**Symptoms:** Timeout errors, "Failed to generate tags" messages.

**Solutions:**
- Verify Ollama is running: `curl http://localhost:11434/api/tags`
- Check if gemma3:27b model is available: `ollama list`
- If Ollama is on a different host, update `KWIK_AI_TAGS_OLLAMA_HOST` constant
- Increase timeout if images are large

### 5. Tags Not Applied to Post
**Symptoms:** Success message appears but tags don't show in WordPress.

**Solutions:**
- Refresh the post editor page
- Check the Tags meta box in the editor
- Verify user has permission to edit posts and assign tags

## Debug Mode

To enable detailed logging:

1. Add to `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

2. Check logs in `/wp-content/debug.log`

3. Look for entries starting with "KWIK AI Tags:"

## Browser Console Debugging

1. Open browser developer tools (F12)
2. Go to Console tab
3. Look for "KWIK AI Tags:" messages
4. Check for any red error messages

## Manual Testing

### Test Ollama Connection
```bash
curl -X POST http://localhost:11434/api/generate \
  -H "Content-Type: application/json" \
  -d '{
    "model": "gemma3:27b",
    "prompt": "Generate 3 tags: cat, dog, animal",
    "stream": false
  }'
```

### Check WordPress AJAX
In browser console on post edit page:
```javascript
jQuery.post(ajaxurl, {
  action: 'kwik_ai_tags_generate',
  nonce: kwikAiTags.nonce,
  post_id: kwikAiTags.postId
}, function(response) {
  console.log(response);
});
```

## Common Error Messages

- **"Invalid post ID"**: Save the post first, then try again
- **"You do not have sufficient permissions"**: User lacks edit_posts capability
- **"No images found"**: No images attached to the post
- **"Failed to generate tags"**: Ollama connection or processing issue
- **"Request timed out"**: Ollama taking too long, try smaller/fewer images

## Plugin Files Check

Ensure these files exist:
- `/wp-content/plugins/kwik-ai-tags/kwik-ai-tags.php`
- `/wp-content/plugins/kwik-ai-tags/assets/admin.js`
- `/wp-content/plugins/kwik-ai-tags/assets/admin.css`

## Support Information

When reporting issues, please include:
- WordPress version
- PHP version  
- Plugin version
- Browser and version
- Error messages from debug log
- Steps to reproduce the issue
