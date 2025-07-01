<?php

namespace App\Service;

use Psr\Log\AbstractLogger;

class FileLogger extends AbstractLogger
{
    private string $logFile;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
    }

    public function log($level, $message, array $context = []): void
    {
        $message = $this->interpolate($message, $context);
        $line = sprintf("[%s] %s %s\n", date('c'), strtoupper((string) $level), $message);
        file_put_contents($this->logFile, $line, FILE_APPEND);
    }

    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $replace['{' . $key . '}'] = $value;
            }
        }
        return strtr($message, $replace);
    }
}
