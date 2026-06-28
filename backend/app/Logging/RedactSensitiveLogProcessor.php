<?php

namespace App\Logging;

use Monolog\LogRecord;

class RedactSensitiveLogProcessor
{
    private const REDACTED = '[REDACTED]';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->redactString($record->message),
            context: $this->redactArray($record->context),
            extra: $this->redactArray($record->extra),
        );
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private function redactArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $values[$key] = self::REDACTED;

                continue;
            }

            $values[$key] = match (true) {
                is_array($value) => $this->redactArray($value),
                is_string($value) => $this->redactString($value),
                default => $value,
            };
        }

        return $values;
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match(
            '/(^|[_.-])(authorization|cookie|password|passwd|secret|token|private[_-]?key|client[_-]?secret|state)($|[_.-])/i',
            $key,
        ) === 1;
    }

    private function redactString(string $value): string
    {
        $patterns = [
            '/-----BEGIN [^-]*PRIVATE KEY-----.*?-----END [^-]*PRIVATE KEY-----/s',
            '/\b(?:gh[pousr]_[A-Za-z0-9_]{20,}|github_pat_[A-Za-z0-9_]{20,})\b/',
            '/\bBearer\s+[^\s,;]+/i',
            '/(\b(?:authorization|cookie|set-cookie)\b\s*:\s*)[^\r\n]*/i',
            '/(["\']?(?:password|passwd|client_secret|private_key|access_token|refresh_token|state)["\']?\s*[:=]\s*)(?:"[^"]*"|\'[^\']*\'|[^\s,;&]+)/i',
        ];
        $replacements = [
            self::REDACTED,
            self::REDACTED,
            'Bearer '.self::REDACTED,
            '$1'.self::REDACTED,
            '$1'.self::REDACTED,
        ];

        return preg_replace($patterns, $replacements, $value) ?? self::REDACTED;
    }
}
