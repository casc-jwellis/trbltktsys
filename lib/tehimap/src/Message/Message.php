<?php

declare(strict_types=1);

namespace Tehimap\Imap\Message;

/**
 * One IMAP message as assembled from a single FETCH response: its UID and flags, its parsed
 * headers and BODYSTRUCTURE when those were fetched, and any raw body sections that were fetched
 * (keyed by section specifier, e.g. "", "HEADER", "TEXT", "1.2").
 */
final class Message
{
    /**
     * @param string[] $flags
     * @param array<string, string> $bodyParts Raw fetched body sections, keyed by whatever
     *   appeared between a FETCH response's BODY[...] brackets (e.g. "" for the whole message,
     *   "HEADER", "1.2").
     */
    public function __construct(
        public readonly int $uid,
        public readonly array $flags,
        public readonly ?MessageHeaders $headers,
        public readonly ?MessagePart $structure,
        public readonly array $bodyParts,
    ) {
    }

    /**
     * Whether $flag (e.g. "\Seen") is among this message's flags, case-insensitively.
     */
    public function hasFlag(string $flag): bool
    {
        foreach ($this->flags as $existing) {
            if (strcasecmp($existing, $flag) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the raw body section fetched for $section (e.g. "" or "HEADER" or "1.2"), or null
     * if that section was never fetched for this message.
     */
    public function getBodyPart(string $section): ?string
    {
        return $this->bodyParts[$section] ?? null;
    }

    public function getHeaders(): ?MessageHeaders
    {
        return $this->headers;
    }

    public function getStructure(): ?MessagePart
    {
        return $this->structure;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    /**
     * @return string[]
     */
    public function getFlags(): array
    {
        return $this->flags;
    }
}
