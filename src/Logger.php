<?php

namespace App;

class Logger
{
    private $handle;
    private string $logFile;
    private string $logLevel;

    private const LEVELS = [
        'DEBUG'   => 0,
        'INFO'    => 1,
        'WARNING' => 2,
        'ERROR'   => 3,
        'CRITICAL' => 4,
    ];

    public function __construct(string $logFile, string $level = 'INFO')
    {
        $this->logFile = $logFile;
        $this->logLevel = strtoupper($level);

        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $this->handle = fopen($logFile, 'a');
        if (!$this->handle) {
            throw new \RuntimeException("Impossibile aprire log file: $logFile");
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if (self::LEVELS[$level] < self::LEVELS[$this->logLevel]) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $entry = "[$timestamp] [$level] $message$contextStr\n";

        fwrite($this->handle, $entry);
        fflush($this->handle);
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
}
