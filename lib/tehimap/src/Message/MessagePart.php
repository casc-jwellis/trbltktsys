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
     * @param string|null $dispositionType The Content-Disposition type (e.g. "attachment",
     *   "inline"), or null if the server sent no disposition field (NIL, malformed, or omitted
     *   entirely).
     * @param array<string, string> $dispositionParameters Content-Disposition parameters (e.g.
     *   FILENAME), shaped the same way as $parameters; empty when there is no disposition.
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
        public readonly ?string $dispositionType,
        public readonly array $dispositionParameters,
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

    /**
     * The attachment/inline filename, if one was given: the Content-Disposition FILENAME
     * parameter when present (case-insensitive key match), else the older Content-Type NAME
     * parameter some senders use instead, else null.
     */
    public function getFilename(): ?string
    {
        $filename = self::findParameterCaseInsensitive($this->dispositionParameters, 'FILENAME');

        if ($filename !== null) {
            return $filename;
        }

        return self::findParameterCaseInsensitive($this->parameters, 'NAME');
    }

    /**
     * True if this part is explicitly marked as an attachment, or - lacking any disposition field
     * at all - has a legacy NAME parameter and isn't a plain text/plain or text/html body (so the
     * readable message body itself isn't misclassified just because it carries an old-style NAME
     * parameter).
     */
    public function isAttachment(): bool
    {
        if ($this->dispositionType !== null) {
            return strcasecmp($this->dispositionType, 'attachment') === 0;
        }

        if ($this->getFilename() === null) {
            return false;
        }

        return !$this->isPlainOrHtmlText();
    }

    /**
     * True if the Content-Disposition type is exactly "inline" (case-insensitive).
     */
    public function isInline(): bool
    {
        return $this->dispositionType !== null && strcasecmp($this->dispositionType, 'inline') === 0;
    }

    /**
     * Every part in this node's own subtree (including itself, but never a multipart container
     * node) for which isAttachment() is true.
     *
     * @return MessagePart[]
     */
    public function collectAttachments(): array
    {
        if ($this->isMultipart()) {
            $attachments = [];

            foreach ($this->children as $child) {
                array_push($attachments, ...$child->collectAttachments());
            }

            return $attachments;
        }

        return $this->isAttachment() ? [$this] : [];
    }

    private function isPlainOrHtmlText(): bool
    {
        if (strcasecmp($this->type, 'text') !== 0) {
            return false;
        }

        return strcasecmp($this->subtype, 'plain') === 0 || strcasecmp($this->subtype, 'html') === 0;
    }

    /**
     * @param array<string, string> $parameters
     */
    private static function findParameterCaseInsensitive(array $parameters, string $name): ?string
    {
        foreach ($parameters as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }
}
