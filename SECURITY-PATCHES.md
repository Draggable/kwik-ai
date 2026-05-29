# Security Patches for kwik-ai-tags

This document describes the security patches applied to the kwik-ai-tags WordPress plugin.

## Summary of Changes

### Critical Fixes
1. **Credential Encryption** - Added secure storage for API keys
2. **SSRF Protection** - Prevented internal network access from URL scraping

### High Priority Fixes
3. **Reduced Logging of Sensitive Data** - Logs no longer expose full API responses
4. **Secure Credential Retrieval** - API key functions now use encryption when available

### Medium Priority Fixes
5. **Enhanced Input Sanitization** - Added secure API key sanitization
6. **Security Notices** - Added warnings about credential storage

---

## File Changes

### 1. New File: `includes/security-utilities.php`
**Purpose:** Provides credential encryption/decryption utilities

**Functions Added:**
- `kwik_ai_encrypt()` - Encrypts values using WordPress encryption
- `kwik_ai_decrypt()` - Decrypts encrypted values
- `kwik_ai_has_encryption()` - Checks if encryption is available
- `kwik_ai_store_credential()` - Stores credentials securely
- `kwik_ai_retrieve_credential()` - Retrieves encrypted credentials

**Fallback:** Falls back to base64 encoding if WordPress encryption is unavailable

---

### 2. Modified: `includes/web-scraping.php`
**Changes:**
- Added `kwik_ai_validate_url_safety()` function
- Modified `kwik_ai_scrape_and_summarize_url()` to check URL safety before fetching
- Blocks access to private/internal IP addresses (RFC 1918)
- Blocks IPv6 loopback and link-local addresses
- Blocks cloud metadata service endpoints

**Security Impact:** Prevents Server-Side Request Forgery (SSRF) attacks

---

### 3. Modified: `includes/ollama-api.php`
**Changes:**
- Added include for `security-utilities.php`
- Modified `kwik_ai_tags_get_api_key()` to use secure credential retrieval
- Modified `kwik_ai_tags_test_openrouter_connection()` to use secure key retrieval
- Modified `kwik_ai_tags_test_openai_connection()` to use secure key retrieval
- Reduced log verbosity - no longer logs full response bodies
- Fixed variable parsing issues in original code

**Security Impact:** Credentials now stored encrypted; reduced data exposure in logs

---

### 4. Modified: `admin/settings.php`
**Changes:**
- Added include for `security-utilities.php`
- Modified `kwik_ai_tags_auth_section_callback()` to show encryption status
- Added `kwik_ai_tags_sanitize_api_key()` function
- Modified API key callbacks to use secure retrieval
- Added security notice when encryption is unavailable

**Security Impact:** Users are informed about credential storage; keys stored securely

---

### 5. Modified: `kwik-ai.php`
**Changes:**
- Added include for `includes/security-utilities.php`

**Security Impact:** Security utilities loaded before other includes that need them

---

## Integration Instructions

### Step 1: Deploy Files
Copy the modified/new files to your WordPress installation:
```
wp-content/plugins/kwik-ai-tags/includes/security-utilities.php
wp-content/plugins/kwik-ai-tags/includes/web-scraping.php
wp-content/plugins/kwik-ai-tags/includes/ollama-api.php
wp-content/plugins/kwik-ai-tags/admin/settings.php
```

### Step 2: Update Credentials (If Using Encryption)
If WordPress encryption is available, existing credentials will be automatically re-encrypted on next save.

To manually migrate existing credentials:
1. Go to Settings > KWIK AI
2. Re-enter your API keys/passwords
3. Save the settings

### Step 3: Verify Encryption
After saving settings, check if encryption is working:
```php
// In a custom plugin or wp-cli
var_dump(function_exists('wp_encrypt')); // Should be true
var_dump(function_exists('wp_decrypt'));  // Should be true
```

---

## Testing Checklist

### SSRF Protection
- [ ] Try to fetch content from `http://192.168.1.1/` - should fail
- [ ] Try to fetch content from `http://169.254.169.254/latest/meta-data/` - should fail
- [ ] Try to fetch content from `http://localhost:11434/` - should fail
- [ ] Try to fetch content from a public URL - should succeed

### Credential Encryption
- [ ] Save an API key in settings
- [ ] Check `wp_options` table - encrypted values should be longer than plain text
- [ ] Verify keys can still be used for API requests

### Logging
- [ ] Enable WP_DEBUG
- [ ] Generate tags/descriptions
- [ ] Check error logs - should NOT see full API responses
- [ ] Should see response sizes instead of content

---

## Rollback Instructions

If you need to rollback:

1. Restore original files from version control
2. Clear any cached credentials (they may still be encrypted)
3. Re-enter API keys in settings

---

## Known Limitations

1. **No Encryption Available:** If WordPress doesn't have encryption functions, credentials will be base64 encoded (not truly encrypted, but obfuscated)

2. **Backward Compatibility:** Existing credentials stored in plain text will remain that way until re-saved

3. **Ollama Basic Auth:** Currently not encrypted (stored in plain text) - future enhancement

---

## Future Enhancements

1. Add encryption for Ollama basic auth credentials
2. Consider using environment variables via WP-Config-Security plugin
3. Add option to disable URL scraping entirely
4. Add rate limiting for API calls
5. Add audit logging for credential access
