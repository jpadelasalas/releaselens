<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use LogicException;
use Monolog\Logger as MonologLogger;

class RedactSensitiveData
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if (! $monolog instanceof MonologLogger) {
            throw new LogicException('Sensitive log redaction requires a Monolog channel.');
        }

        $monolog->pushProcessor(new RedactSensitiveLogProcessor);
    }
}
