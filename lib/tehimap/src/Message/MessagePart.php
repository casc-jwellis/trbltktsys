<?php

declare(strict_types=1);

namespace Tehimap\Imap\Message;

/**
 * One node of a BODYSTRUCTURE MIME tree (RFC 3501 section 7.4.2): either a leaf part (a single
 * text, binary, or message body) or a multipart container whose own children are its constituent
 * parts.
 *
 * $partNumber follows IMAP's dotted body-part numbering (RFC 3501 section 6.4.5) used to fetch
 * that part individually, e.g. BODY[1.2]: "1", "1.2", and so on for a child of a multipart, or the
 * empty string for the top-level part of a message that is not itself multipart.
 */
final class MessagePart
{
    /**
     * @param array<string, string> $parameters MIME type parameters (e.g. CHARSET, NAME), keyed
     *   by parameter name exactly as the server sent it.
     * @param MessagePart[] $children Child parts of a multipart node, in order; always empty for
     *   a leaf.
     */
    public function __construct(
        public readonly string $partNumber,
        public readonly string $type,
        public readonly string $subtype,
        public readonly array $parameters,
        public readonly ?string $id,
        public readonly ?string $description,
        public readonly string $encoding,
        public readonly int $size,
        public readonly array $children,
    ) {
    }

    /**
     * True for a "multipart/*" node (one with children), false for a leaf part.
     */
    public function isMultipart(): bool
    {
        return strcasecmp($this->type, 'multipart') === 0;
    }
}
