<?php

declare(strict_types=1);

namespace Tehimap\Imap\Sync;

/**
 * A resumable position within one mailbox: the UIDVALIDITY it was recorded under, and the
 * highest UID already processed. If a later UIDVALIDITY no longer matches, the cursor is stale
 * (the mailbox was rebuilt server-side) and callers must treat it as an unsynced mailbox.
 */
final class SyncCursor
{
    public function __construct(
        public readonly int $uidValidity,
        public readonly int $lastUid,
    ) {
    }
}
