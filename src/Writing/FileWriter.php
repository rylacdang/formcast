<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Writing;

use RuntimeException;

final class FileWriter
{
    public const MANIFEST_FILE = '.formcast.json';

    /**
     * Write generated files with content-hash diffing and stale file cleanup.
     * When `$dryRun` is true, computes exact changes without modifying disk (used by `--dry-run` and `--check`).
     *
     * @param  array<string, string>  $files  Map of output-relative POSIX path => file contents
     */
    public function write(string $outputDirectory, array $files, bool $dryRun = false): WriteResult
    {
        $normalizedFiles = [];

        foreach ($files as $relativePath => $content) {
            $normalizedPath = ltrim(str_replace('\\', '/', $relativePath), '/');
            $normalizedFiles[$normalizedPath] = $content;
        }

        ksort($normalizedFiles, SORT_STRING);

        $previousHashes = $this->readManifest($outputDirectory);
        $nextHashes = [];
        $written = [];
        $unchanged = [];
        $removed = [];

        foreach ($normalizedFiles as $relativePath => $content) {
            $newHash = hash('sha256', $content);
            $nextHashes[$relativePath] = $newHash;

            $fullPath = $this->joinPath($outputDirectory, $relativePath);

            if (is_file($fullPath)) {
                $existingContent = file_get_contents($fullPath);

                if ($existingContent !== false && hash('sha256', $existingContent) === $newHash) {
                    $unchanged[] = $relativePath;

                    continue;
                }
            }

            $written[] = $relativePath;

            if (! $dryRun) {
                $this->writeFile($fullPath, $content);
            }
        }

        foreach (array_keys($previousHashes) as $previousPath) {
            if (array_key_exists($previousPath, $normalizedFiles)) {
                continue;
            }

            $removed[] = $previousPath;

            if (! $dryRun) {
                $fullPath = $this->joinPath($outputDirectory, $previousPath);

                if (is_file($fullPath)) {
                    unlink($fullPath);
                    $this->pruneEmptyDirectories(dirname($fullPath), $outputDirectory);
                }
            }
        }

        sort($removed, SORT_STRING);

        if (! $dryRun && ($written !== [] || $removed !== [] || ! is_file($this->joinPath($outputDirectory, self::MANIFEST_FILE)))) {
            $this->writeManifest($outputDirectory, $nextHashes);
        }

        return new WriteResult(
            written: $written,
            unchanged: $unchanged,
            removed: $removed,
        );
    }

    /**
     * @return array<string, string>
     */
    private function readManifest(string $outputDirectory): array
    {
        $manifestPath = $this->joinPath($outputDirectory, self::MANIFEST_FILE);

        if (! is_file($manifestPath)) {
            return [];
        }

        $raw = file_get_contents($manifestPath);

        if ($raw === false) {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || ! isset($decoded['files']) || ! is_array($decoded['files'])) {
            return [];
        }

        $hashes = [];

        foreach ($decoded['files'] as $path => $hash) {
            if (is_string($path) && is_string($hash)) {
                $hashes[$path] = $hash;
            }
        }

        return $hashes;
    }

    /**
     * @param  array<string, string>  $hashes
     */
    private function writeManifest(string $outputDirectory, array $hashes): void
    {
        ksort($hashes, SORT_STRING);

        $json = json_encode(
            ['version' => 1, 'files' => $hashes],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        $this->writeFile($this->joinPath($outputDirectory, self::MANIFEST_FILE), $json."\n");
    }

    private function writeFile(string $fullPath, string $content): void
    {
        $directory = dirname($fullPath);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Failed to create output directory: {$directory}");
        }

        if (file_put_contents($fullPath, $content) === false) {
            throw new RuntimeException("Failed to write generated file: {$fullPath}");
        }
    }

    private function pruneEmptyDirectories(string $directory, string $stopAt): void
    {
        $normalizedStop = rtrim(str_replace('\\', '/', realpath($stopAt) ?: $stopAt), '/');

        while (is_dir($directory)) {
            $normalizedCurrent = rtrim(str_replace('\\', '/', realpath($directory) ?: $directory), '/');

            if ($normalizedCurrent === $normalizedStop || ! str_starts_with($normalizedCurrent, $normalizedStop.'/')) {
                break;
            }

            $entries = scandir($directory);

            if ($entries === false || count($entries) > 2) {
                break;
            }

            rmdir($directory);
            $directory = dirname($directory);
        }
    }

    private function joinPath(string $base, string $relative): string
    {
        return rtrim($base, '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
