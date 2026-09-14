<?php

declare(strict_types=1);

namespace Sukarix\Security;

/**
 * HMAC-signed capability tokens for machine callers: a base64url payload
 * carrying iss/aud/exp claims plus a signature, small enough for query
 * strings and remote data sources.
 */
final class CapabilityToken
{
    private const ALGO = 'sha256';

    /**
     * @param string $secret shared signing secret
     * @param array<string, mixed> $claims token claims, at least iss/aud
     * @param int $ttl lifetime in seconds
     */
    public static function issue(string $secret, array $claims, int $ttl): string
    {
        $claims['iat'] = time();
        $claims['exp'] = time() + $ttl;

        $payload = self::encode(json_encode($claims, JSON_THROW_ON_ERROR));

        return $payload . '.' . self::sign($secret, $payload);
    }

    /**
     * @param string $secret shared signing secret
     * @param string $token token as issued
     * @param string|null $audience required audience, when the caller scopes it
     *
     * @return array<string, mixed>|null the claims, or null when the token is
     *                                   malformed, forged, expired or foreign
     */
    public static function verify(string $secret, string $token, ?string $audience = null): ?array
    {
        $parts = explode('.', $token);
        if (2 !== \count($parts)) {
            return null;
        }

        [$payload, $signature] = $parts;
        if (!hash_equals(self::sign($secret, $payload), $signature)) {
            return null;
        }

        try {
            $json = self::decode($payload);
            if ('' === $json) {
                return null;
            }
            $claims = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!\is_array($claims) || ($claims['exp'] ?? 0) < time()) {
            return null;
        }

        if (null !== $audience && ($claims['aud'] ?? null) !== $audience) {
            return null;
        }

        return $claims;
    }

    private static function sign(string $secret, string $payload): string
    {
        return self::encode(hash_hmac(self::ALGO, $payload, $secret, true));
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
