<?php

namespace Tests\Unit;

use App\Logging\RedactSensitiveData;
use App\Logging\RedactSensitiveLogProcessor;
use DateTimeImmutable;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class RedactSensitiveLogProcessorTest extends TestCase
{
    public function test_the_logging_tap_sanitizes_emitted_records(): void
    {
        $handler = new TestHandler;
        $logger = new Logger('testing', [$handler]);
        (new RedactSensitiveData)($logger);

        $logger->error('Request failed with access_token=github-secret', [
            'Cookie' => 'session=private-session',
        ]);

        $record = $handler->getRecords()[0];

        $this->assertStringNotContainsString('github-secret', $record->message);
        $this->assertSame('[REDACTED]', $record->context['Cookie']);
    }

    public function test_it_redacts_sensitive_context_and_extra_values_recursively(): void
    {
        $record = new LogRecord(
            new DateTimeImmutable,
            'testing',
            Level::Error,
            'GitHub request failed',
            [
                'headers' => [
                    'Authorization' => 'Bearer installation-secret',
                    'Accept' => 'application/json',
                ],
                'password' => 'release-lens-2026',
                'repository' => 'acme/widgets',
            ],
            ['session_token' => 'session-secret'],
        );

        $redacted = (new RedactSensitiveLogProcessor)($record);

        $this->assertSame('[REDACTED]', $redacted->context['headers']['Authorization']);
        $this->assertSame('application/json', $redacted->context['headers']['Accept']);
        $this->assertSame('[REDACTED]', $redacted->context['password']);
        $this->assertSame('acme/widgets', $redacted->context['repository']);
        $this->assertSame('[REDACTED]', $redacted->extra['session_token']);
    }

    public function test_it_redacts_secrets_embedded_in_log_messages(): void
    {
        $privateKey = "-----BEGIN PRIVATE KEY-----\nprivate-material\n-----END PRIVATE KEY-----";
        $record = new LogRecord(
            new DateTimeImmutable,
            'testing',
            Level::Error,
            'Authorization: Bearer secret-value'.PHP_EOL
                .'callback?state=state-secret&installation_id=42'.PHP_EOL
                .$privateKey,
        );

        $message = (new RedactSensitiveLogProcessor)($record)->message;

        $this->assertStringNotContainsString('secret-value', $message);
        $this->assertStringNotContainsString('state-secret', $message);
        $this->assertStringNotContainsString('private-material', $message);
        $this->assertStringContainsString('[REDACTED]', $message);
        $this->assertStringContainsString('installation_id=42', $message);
    }
}
