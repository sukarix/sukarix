<?php

declare(strict_types=1);

namespace Sukarix\Security;

/**
 * Authenticated encryption for credentials at rest, on libsodium's
 * secretbox (XSalsa20-Poly1305).
 *
 * The nonce is generated per message and prefixed to the ciphertext, so
 * re-sealing the same plaintext produces a different blob and a database
 * dump reveals nothing by comparison. Decryption is authenticated: a
 * tampered row fails rather than yielding attacker-chosen bytes.
 */
final class SecretBox
{
    private function __construct(private string $key) {}

    /**
     * @throws \RuntimeException when the material is not a 32-byte key
     */
    public static function fromKeyMaterial(string $raw): self
    {
        $raw = trim($raw);
        $key = null;

        if (64 === \strlen($raw) && 1 === preg_match('/^[0-9a-fA-F]+$/', $raw)) {
            $key = hex2bin($raw) ?: null;
        }
        if (null === $key) {
            $decoded = base64_decode($raw, true);
            if (\is_string($decoded) && SODIUM_CRYPTO_SECRETBOX_KEYBYTES === \strlen($decoded)) {
                $key = $decoded;
            }
        }
        if (null === $key && SODIUM_CRYPTO_SECRETBOX_KEYBYTES === \strlen($raw)) {
            $key = $raw;
        }
        if (null === $key) {
            throw new \RuntimeException('The key material must be a 32-byte key as 64 hex characters or base64');
        }

        return new self($key);
    }

    /**
     * A fresh key, for provisioning tools and deployment documentation.
     */
    public static function generateKey(): string
    {
        return bin2hex(sodium_crypto_secretbox_keygen());
    }

    /**
     * @param array<string, mixed> $values
     */
    public function encryptArray(array $values): string
    {
        return $this->encrypt(json_encode($values, JSON_THROW_ON_ERROR));
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plaintext, $nonce, $this->key));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \RuntimeException when the blob is corrupt or was sealed with another key
     */
    public function decryptArray(string $blob): array
    {
        $decoded = json_decode($this->decrypt($blob), true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @throws \RuntimeException when the blob is corrupt or was sealed with another key
     */
    public function decrypt(string $blob): string
    {
        $raw = base64_decode($blob, true);
        if (!\is_string($raw) || \strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('The stored secret is not a valid sealed blob');
        }

        $nonce      = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext  = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        if (!\is_string($plaintext)) {
            throw new \RuntimeException('The stored secret could not be decrypted with the configured key');
        }

        return $plaintext;
    }
}
