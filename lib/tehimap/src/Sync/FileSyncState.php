<?php

declare(strict_types=1);

namespace Tehimap\Imap\Sync;

use RuntimeException;

/**
 * Persists sync cursors as one JSON file per account/folder pair on the local filesystem.
 *
 * Each cursor is written atomically: the new content is written to a temporary file in the
 * same directory first, then that file is renamed into place, so a crash or concurrent reader
 * never observes a partially written cursor file.
 */
final class FileSyncState implements SyncStateInterface
{
    private string $baseDir;

    public function __construct(string $baseDir)
    {
        $baseDir = rtrim($baseDir, "/\\");

        if (!is_dir($baseDir)) {
            if (!mkdir($baseDir, 0777, true) && !is_dir($baseDir)) {
                throw new RuntimeException("Unable to create sync state directory: {$baseDir}");
            }
        }

        if (!is_writable($baseDir)) {
            throw new RuntimeException("Sync state directory is not writable: {$baseDir}");
        }

        $this->baseDir = $baseDir;
    }

    public function load(string $account, string $folder): ?SyncCursor
    {
        $path = $this->pathFor($account, $folder);

        if (!is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $data = json_decode($contents, true);
        if (!is_array($data)) {
            return null;
        }

        if (
            !isset($data['uid_validity'], $data['last_uid'])
            || !is_int($data['uid_validity'])
            || !is_int($data['last_uid'])
        ) {
            return null;
        }

        return new SyncCursor($data['uid_validity'], $data['last_uid']);
    }

    public function save(string $account, string $folder, SyncCursor $cursor): void
    {
        $path = $this->pathFor($account, $folder);

        $json = json_encode([
            'uid_validity' => $cursor->uidValidity,
            'last_uid' => $cursor->lastUid,
        ], JSON_THROW_ON_ERROR);

        $tempPath = tempnam($this->baseDir, 'tmp-');
        if ($tempPath === false) {
            throw new RuntimeException("Unable to create temporary file in: {$this->baseDir}");
        }

        if (file_put_contents($tempPath, $json) === false) {
            @unlink($tempPath);
            throw new RuntimeException("Unable to write sync state to temporary file: {$tempPath}");
        }

        if (!rename($tempPath, $path)) {
            @unlink($tempPath);
            throw new RuntimeException("Unable to move temporary file into place: {$path}");
        }
    }

    private function pathFor(string $account, string $folder): string
    {
        $hash = sha1($account . ':' . $folder);

        return $this->baseDir . DIRECTORY_SEPARATOR . $hash . '.json';
    }
}
