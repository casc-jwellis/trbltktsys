<?php

declare(strict_types=1);

namespace Tehimap\Imap\Sync;

interface SyncStateInterface
{
    /** Returns null if no cursor has been recorded yet for this account/folder pair. */
    public function load(string $account, string $folder): ?SyncCursor;

    public function save(string $account, string $folder, SyncCursor $cursor): void;
}
