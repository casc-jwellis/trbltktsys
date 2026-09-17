<?php

declare(strict_types=1);

namespace Tehimap\Imap\Protocol;

/**
 * Internal marker produced by ResponseParser's line tokenizer: appears only as the last element
 * of a tokenized line, meaning that line ended in a literal marker ("{n}") of $length bytes which
 * the caller must now read from the connection before the response is fully assembled.
 */
final class PendingLiteral
{
    public function __construct(
        public readonly int $length,
    ) {
    }
}
