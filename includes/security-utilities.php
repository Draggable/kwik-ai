<?php
/**
 * Security utilities for credential handling
 *
 * @package KwikAI
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Encrypt a value using WordPress's built-in encryption
 * Falls back to base64 if encryption is unavailable
 *
 * @param string $value Value to encrypt
 * @return string Encrypted value or base64 encoded fallback
 */
function kwik_ai_encrypt($value) {
  if (function_exists('wp_encrypt')) {
    return wp_encrypt($value, wp_hash_salt('kwik-ai-encryption-key'));
  }
  
  // Fallback: base64 encode (not encryption, but better than plain text)
  return base64_encode($value);
}

/**
 * Decrypt a value using WordPress's built-in decryption
 * Falls back to base64 decode if decryption is unavailable
 *
 * @param string $value Encrypted value
 * @return string Decrypted value or empty string on failure
 */
function kwik_ai_decrypt($value) {
  if (function_exists('wp_decrypt')) {
    $decrypted = wp_decrypt($value, wp_hash_salt('kwik-ai-encryption-key'));
    if ($decrypted !== false) {
      return $decrypted;
    }
  }
  
  // Fallback: base64 decode
  $decoded = base64_decode($value);
  if ($decoded !== false) {
    return $decoded;
  }
  
  return '';
}

/**
 * Check if encryption is available
 *
 * @return bool
 */
function kwik_ai_has_encryption() {
  return function_exists('wp_encrypt') && function_exists('wp_decrypt');
}

/**
 * Store a credential securely
 *
 * @param string $option_name WordPress option name
 * @param string $value Credential value
 */
function kwik_ai_store_credential($option_name, $value) {
  if (empty($value)) {
    delete_option($option_name);
    return;
  }
  
  $encrypted = kwik_ai_encrypt($value);
  update_option($option_name, $encrypted);
}

/**
 * Retrieve a credential securely
 *
 * @param string $option_name WordPress option name
 * @param string $default Default value if not found
 * @return string Decrypted credential value
 */
function kwik_ai_retrieve_credential($option_name, $default = '') {
  $encrypted = get_option($option_name);
  
  if (empty($encrypted)) {
    return $default;
  }
  
  return kwik_ai_decrypt($encrypted);
}
