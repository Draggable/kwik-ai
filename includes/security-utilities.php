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
 * Get the encryption key for credential storage.
 * Uses a random key stored in wp_options instead of deriving from
 * wp_hash_salt() which is deterministic and compromises all credentials
 * if wp-config.php is leaked.
 *
 * @return string Encryption key
 */
function kwik_ai_get_encryption_key(): string
{
  $key_option = 'kwik_ai_encryption_key';
  $key = get_option($key_option, '');

  if (empty($key)) {
    // Generate a cryptographically secure random key
    $key = bin2hex(random_bytes(32));
    add_option($key_option, $key, '', 'no');
  }

  return $key;
}

/**
 * Encrypt a value using OpenSSL with a stored random key.
 * Falls back to HMAC-based one-way storage if OpenSSL is unavailable.
 *
 * @param string $value Value to encrypt
 * @return string Encrypted value or HMAC hash
 */
function kwik_ai_encrypt(string $value): string
{
  $key = kwik_ai_get_encryption_key();

  // Primary: OpenSSL AES-256-CBC encryption
  if (function_exists('openssl_encrypt')) {
    $iv_length = openssl_cipher_iv_length('AES-256-CBC');
    $iv = random_bytes($iv_length);
    $encrypted = openssl_encrypt(
      $value,
      'AES-256-CBC',
      hex2bin(substr($key, 0, 64)), // Ensure 32-byte key
      0,
      $iv
    );

    if ($encrypted !== false) {
      // Prepend IV to ciphertext for decryption
      return base64_encode($iv . $encrypted);
    }
  }

  // Fallback: HMAC-based one-way storage (not reversible, but secure)
  // Use hash_hmac instead of plain base64 which provides zero security
  return 'hmac:' . hash_hmac('sha256', $value, $key);
}

/**
 * Decrypt a value using OpenSSL with a stored random key.
 * Returns empty string for HMAC-stored values (one-way).
 *
 * @param string $value Encrypted value
 * @return string Decrypted value or empty string on failure
 */
function kwik_ai_decrypt(string $value): string
{
  // HMAC-stored values are one-way and cannot be decrypted
  if (strpos($value, 'hmac:') === 0) {
    return ''; // Cannot decrypt one-way hashes
  }

  $key = kwik_ai_get_encryption_key();

  // Primary: OpenSSL AES-256-CBC decryption
  if (function_exists('openssl_encrypt')) {
    $data = base64_decode($value);
    if ($data === false) {
      return '';
    }

    $iv_length = openssl_cipher_iv_length('AES-256-CBC');
    $iv = substr($data, 0, $iv_length);
    $ciphertext = substr($data, $iv_length);

    $decrypted = openssl_decrypt(
      $ciphertext,
      'AES-256-CBC',
      hex2bin(substr($key, 0, 64)),
      0,
      $iv
    );

    if ($decrypted !== false) {
      return $decrypted;
    }
  }

  return '';
}

/**
 * Check if full encryption/decryption is available.
 * Returns true only if two-way encryption is supported.
 *
 * @return bool
 */
function kwik_ai_has_encryption(): bool
{
  return function_exists('openssl_encrypt') && function_exists('openssl_decrypt');
}

/**
 * Store a credential securely.
 *
 * @param string $option_name WordPress option name
 * @param string $value Credential value
 */
function kwik_ai_store_credential(string $option_name, string $value): void
{
  if (empty($value)) {
    delete_option($option_name);
    return;
  }

  $encrypted = kwik_ai_encrypt($value);
  update_option($option_name, $encrypted);
}

/**
 * Retrieve a credential securely.
 *
 * @param string $option_name WordPress option name
 * @param string $default Default value if not found
 * @return string Decrypted credential value
 */
function kwik_ai_retrieve_credential(string $option_name, string $default = ''): string
{
  $encrypted = get_option($option_name, '');

  if (empty($encrypted)) {
    return $default;
  }

  return kwik_ai_decrypt($encrypted);
}
