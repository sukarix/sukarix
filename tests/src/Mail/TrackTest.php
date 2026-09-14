<?php

declare(strict_types=1);

namespace Mail;

use Sukarix\Mail\Track;
use Sukarix\Observability\LoggerFactory;
use Test\Scenario;

/**
 * @internal
 *
 * @coversNothing
 */
final class TrackTest extends Scenario
{
    protected $group = 'Mail Track';

    public function testAuthCredentialsAreRedactedFromTheLoggedTranscript($f3)
    {
        $user = base64_encode('rooms-app-7f3c');
        $pass = base64_encode('s3cret-relay-password');
        $log  = "EHLO app.example.org\n250-postal.example.org\nAUTH LOGIN\n334 VXNlcm5hbWU6\n{$user}\n334 UGFzc3dvcmQ6\n{$pass}\n235 2.7.0 Authentication successful\nMAIL FROM: <notifications@example.org>\n550 5.1.1 Recipient rejected";

        $records = $this->captureMailLog(fn() => Track::logError(null, $log));

        $test    = $this->newTest();
        $message = (string) ($records[0]['context']['log'] ?? '');

        $test->expect(1 === \count($records), 'one record is produced');
        $test->expect('error' === $records[0]['level_name'], 'it logs at error level');
        $test->expect(!str_contains($message, $user), 'the base64 username is not in the log');
        $test->expect(!str_contains($message, $pass), 'the base64 password is not in the log');
        $test->expect(str_contains($message, 'AUTH LOGIN'), 'the rest of the transcript is kept');
        $test->expect(str_contains($message, '550 5.1.1 Recipient rejected'), 'the actual failure reason is kept');

        return $test->results();
    }

    public function testATranscriptWithoutAuthIsUntouched($f3)
    {
        $log = "EHLO app.example.org\n250-postal.example.org\nMAIL FROM: <notifications@example.org>\n550 5.1.1 Recipient rejected";

        $records = $this->captureMailLog(fn() => Track::logError(null, $log));

        $test = $this->newTest();
        $test->expect($log === ($records[0]['context']['log'] ?? null), 'a transcript with no AUTH exchange is passed through unchanged');

        return $test->results();
    }

    /**
     * @return list<array{level_name: string, message: string, context: array}>
     */
    private function captureMailLog(callable $callback): array
    {
        // LoggerFactory::channel() hands out a clone per call, so the
        // handler has to be on the shared root before Track ever asks
        // for the 'mail' channel.
        LoggerFactory::reset();
        LoggerFactory::channel('mail');

        $property = new \ReflectionProperty(LoggerFactory::class, 'root');
        $property->setAccessible(true);
        $root = $property->getValue();

        $handler = new \Monolog\Handler\TestHandler();
        $root->pushHandler($handler);

        $callback();

        $root->popHandler();
        LoggerFactory::reset();

        return array_map(
            static fn ($record) => [
                'level_name' => strtolower($record['level_name']),
                'message'    => $record['message'],
                'context'    => $record['context'],
            ],
            $handler->getRecords()
        );
    }
}
