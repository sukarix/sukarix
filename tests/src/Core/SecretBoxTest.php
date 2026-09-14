<?php

declare(strict_types=1);

namespace Core;

use Sukarix\Security\SecretBox;
use Test\Scenario;

/**
 * @internal
 *
 * @coversNothing
 */
final class SecretBoxTest extends Scenario
{
    protected $group = 'Core SecretBox';

    private const KEY   = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
    private const OTHER = 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210';

    public function testSealsAndOpensAConfiguration($f3)
    {
        $box    = SecretBox::fromKeyMaterial(self::KEY);
        $values = ['access_token' => 'ya29.a0AfH6', 'stream_key' => 'live_123'];

        $test = $this->newTest();
        $test->expect($values === $box->decryptArray($box->encryptArray($values)), 'a sealed configuration round-trips');

        return $test->results();
    }

    public function testTheSamePlaintextSealsDifferentlyEachTime($f3)
    {
        $box = SecretBox::fromKeyMaterial(self::KEY);

        $test = $this->newTest();
        $test->expect(
            $box->encrypt('live_123') !== $box->encrypt('live_123'),
            'a fresh nonce makes identical plaintexts indistinguishable'
        );

        return $test->results();
    }

    public function testAnotherKeyCannotOpenTheBlob($f3)
    {
        $blob   = SecretBox::fromKeyMaterial(self::KEY)->encrypt('live_123');
        $thrown = false;

        try {
            SecretBox::fromKeyMaterial(self::OTHER)->decrypt($blob);
        } catch (\RuntimeException) {
            $thrown = true;
        }

        $test = $this->newTest();
        $test->expect($thrown, 'a blob sealed with another key is refused');

        return $test->results();
    }

    public function testATamperedBlobIsRefused($f3)
    {
        $box  = SecretBox::fromKeyMaterial(self::KEY);
        $blob = $box->encrypt('rtmp://live.example/app/genuine');

        // Flip the last byte of the ciphertext: without authentication this
        // would decrypt to attacker-influenced bytes instead of failing.
        $raw      = base64_decode($blob, true);
        $raw[-1]  = \chr(\ord($raw[-1]) ^ 0xFF);
        $tampered = base64_encode($raw);
        $thrown   = false;

        try {
            $box->decrypt($tampered);
        } catch (\RuntimeException) {
            $thrown = true;
        }

        $test = $this->newTest();
        $test->expect($thrown, 'a tampered blob fails authentication');

        return $test->results();
    }

    public function testKeyMaterialFormats($f3)
    {
        $test = $this->newTest();

        $base64 = base64_encode(hex2bin(self::KEY));
        $test->expect(
            SecretBox::fromKeyMaterial($base64)->decrypt(
                SecretBox::fromKeyMaterial(self::KEY)->encrypt('same key')
            ) === 'same key',
            'hex and base64 spellings of one key are equivalent'
        );

        $thrown = false;

        try {
            SecretBox::fromKeyMaterial('too-short');
        } catch (\RuntimeException) {
            $thrown = true;
        }
        $test->expect($thrown, 'a key that is not 32 bytes is refused');

        $generated = SecretBox::generateKey();
        $test->expect(64 === \strlen($generated), 'generateKey() returns 64 hex characters');

        return $test->results();
    }
}
