<?php

declare(strict_types=1);

namespace Tehimap\Imap\Exception;

/**
 * A tagged IMAP command completed with a NO or BAD status.
 */
class ProtocolException extends ImapException
{
    public function __construct(
        string $message,
        public readonly string $tag,
        public readonly string $status,
        public readonly string $responseText,
    ) {
        parent::__construct($message);
    }
}
