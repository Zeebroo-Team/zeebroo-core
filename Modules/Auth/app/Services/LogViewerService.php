<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

class LogViewerService
{
    /** Only the end of a log file is read; laravel.log can grow to many MB. */
    private const TAIL_BYTES = 2_000_000;

    private const MAX_TRACE_CHARS = 8000;

    private const HEADER = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.*)$/s';

    /**
     * @return list<array{name: string, size: int, modified: int}>
     */
    public function files(): array
    {
        $files = [];
        foreach (glob(storage_path('logs/*.log')) ?: [] as $path) {
            $files[] = [
                'name' => basename($path),
                'size' => (int) filesize($path),
                'modified' => (int) filemtime($path),
            ];
        }

        usort($files, fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return $files;
    }

    public function resolve(?string $name): ?string
    {
        $available = array_column($this->files(), 'name');
        if ($available === []) {
            return null;
        }

        return in_array($name, $available, true) ? $name : $available[0];
    }

    /**
     * Parsed entries from the tail of a log file, newest first.
     *
     * @return list<array{time: string, env: string, level: string, message: string, trace: string}>
     */
    public function entries(string $file, ?string $level = null, ?string $search = null): array
    {
        $raw = $this->tail(storage_path('logs/'.$file));
        if ($raw === '') {
            return [];
        }

        $chunks = preg_split('/(?=^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] )/m', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $entries = [];

        foreach (array_reverse($chunks) as $chunk) {
            if (! preg_match(self::HEADER, rtrim($chunk), $m)) {
                continue; // partial entry cut off by the tail window
            }

            $level = $level ? strtolower($level) : null;
            if ($level && strtolower($m[3]) !== $level) {
                continue;
            }

            $lines = explode("\n", $m[4], 2);
            $entry = [
                'time' => $m[1],
                'env' => $m[2],
                'level' => strtolower($m[3]),
                'message' => $lines[0],
                'trace' => mb_substr(trim($lines[1] ?? ''), 0, self::MAX_TRACE_CHARS),
            ];

            if ($search && stripos($m[4], $search) === false) {
                continue;
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    private function tail(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return '';
        }

        $size = (int) filesize($path);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        if ($size > self::TAIL_BYTES) {
            fseek($handle, -self::TAIL_BYTES, SEEK_END);
        }
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }
}
