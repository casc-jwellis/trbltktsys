<?php

declare(strict_types=1);

namespace Tehimap\Imap\Protocol;

/**
 * Generates the sequential tags IMAP commands are prefixed with (RFC 3501 section 2.2): the
 * letter "A" followed by an incrementing counter, left-padded with zeros to at least 4 digits
 * (A0001, A0002, ..., widening naturally past A9999 to A10000 with no truncation).
 *
 * One Tag instance is meant to be shared by every CommandRunner using the same connection, so
 * tags stay unique for the lifetime of a session.
 */
final class Tag
{
    private int $counter = 0;

    /**
     * Increments the counter and returns the newly generated tag.
     */
    public function next(): string
    {
        $this->counter++;

        return sprintf('A%04d', $this->counter);
    }
}
