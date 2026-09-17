<?php

declare(strict_types=1);

namespace Tehimap\Imap\Mailbox;

/**
 * A snapshot of a mailbox's metadata as reported by a SELECT or EXAMINE response (RFC 3501
 * section 6.3.1/6.3.2): how many messages it holds, how many are new since it was last opened,
 * its UIDVALIDITY and UIDNEXT, and the set of flags clients are permitted to change permanently.
 */
final class MailboxInfo
{
    /**
     * @param int $messageCount The number of messages in the mailbox (the untagged EXISTS count).
     * @param int $recentCount The number of messages with the \Recent flag set (the untagged
     *   RECENT count).
     * @param int $uidValidity The mailbox's UIDVALIDITY value; UIDs are only comparable across
     *   sessions when this value has not changed.
     * @param int $uidNext The UID that will be assigned to the next message appended to the
     *   mailbox.
     * @param string[] $permanentFlags Flags the client can set permanently on messages in this
     *   mailbox (from the untagged "OK [PERMANENTFLAGS (...)]" response), e.g. ["\Seen",
     *   "\Answered", "\Flagged", "\Deleted", "\Draft", "\*"].
     */
    public function __construct(
        public readonly int $messageCount,
        public readonly int $recentCount,
        public readonly int $uidValidity,
        public readonly int $uidNext,
        public readonly array $permanentFlags,
    ) {
    }
}
