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
     * @param ?string $disposition This leaf's Content-Disposition type ("attachment" or "inline"),
     *   or null if the server didn't send one. Always null for a multipart node.
     * @param ?string $filename The disposition's "filename" parameter (e.g. "photo.png"), or null
     *   if there wasn't one -- callers wanting a best-effort name should fall back to this leaf's
     *   Content-Type "name" parameter (some clients, notably older Outlook, only send that one).
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
        public readonly ?string $disposition = null,
        public readonly ?string $filename = null,
    ) {
    }

    /**
     * True for a "multipart/*" node (one with children), false for a leaf part.
     */
    public function isMultipart(): bool
    {
        return strcasecmp($this->type, 'multipart') === 0;
    }

    /**
     * Best-effort filename for this leaf: its disposition filename if the server sent one,
     * otherwise its Content-Type "name" parameter (a deprecated but still common fallback).
     */
    public function attachmentFilename(): ?string
    {
        if ($this->filename !== null) {
            return $this->filename;
        }

        foreach ($this->parameters as $key => $value) {
            if (strcasecmp($key, 'name') === 0) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Whether this leaf should be treated as a downloadable attachment rather than message body
     * text: explicitly "attachment"-disposed parts always count; parts with no disposition at all
     * (some servers/clients never send one) count unless they're the plain or HTML text making up
     * the message itself. A multipart node is never an attachment -- only its leaves are.
     */
    public function isAttachment(): bool
    {
        if ($this->isMultipart()) {
            return false;
        }

        if ($this->disposition !== null) {
            return strcasecmp($this->disposition, 'attachment') === 0;
        }

        $isBodyText = strcasecmp($this->type, 'text') === 0
            && (strcasecmp($this->subtype, 'plain') === 0 || strcasecmp($this->subtype, 'html') === 0);

        return !$isBodyText;
    }
}
