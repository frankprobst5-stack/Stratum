<?php

declare(strict_types=1);

namespace Stratum\Core;

final class Logger
{
    public function __construct(
        private readonly Database $db,
        private readonly string $logDir
    ) {
    }

    /** @param array<string, mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        $this->writeToFile($level, $message, $context);
        $this->writeToDatabase($level, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function writeToFile(string $level, string $message, array $context): void
    {
        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context === [] ? '' : json_encode($context, JSON_UNESCAPED_SLASHES)
        );

        @file_put_contents($this->logDir . '/app.log', $line, FILE_APPEND);
    }

    /** @param array<string, mixed> $context */
    private function writeToDatabase(string $level, string $message, array $context): void
    {
        try {
            $this->db->insert('core_logs', [
                'level' => $level,
                'message' => $message,
                'context' => $context === [] ? null : json_encode($context, JSON_UNESCAPED_SLASHES),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Table may not exist yet (pre-migration) — file sink above already captured it.
        }
    }
}
