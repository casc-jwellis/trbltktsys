<?php

declare(strict_types=1);

namespace Tehimap\Imap\Message;

/**
 * A parsed set of RFC 5322 message headers, as fetched via a BODY[HEADER] or
 * BODY[HEADER.FIELDS (...)] FETCH section - i.e. a raw header blob, not yet split into
 * name/value pairs.
 *
 * Header names are matched case-insensitively (per RFC 5322, "Received" and "received" name the
 * same header), but the original name casing the server sent is preserved in all(). A header may
 * legitimately repeat (e.g. "Received" is added once per relay hop), so lookups can return every
 * occurrence, not just the first.
 */
final class MessageHeaders
{
    /**
     * @param array<int, array{name: string, value: string}> $entries Every header line, in the
     *   order it appeared, with its original name casing and its RFC 2047-decoded value.
     */
    private function __construct(private readonly array $entries)
    {
    }

    /**
     * Parses a raw RFC 5322 header blob into a MessageHeaders instance: unfolds continuation
     * lines, splits each header line on its first colon, and RFC 2047-decodes each value.
     */
    public static function parse(string $rawHeaderBlock): self
    {
        $entries = [];

        foreach (self::unfold($rawHeaderBlock) as $line) {
            $colonPosition = strpos($line, ':');

            if ($colonPosition === false) {
                // A header line with no colon is malformed; skip it rather than guessing.
                continue;
            }

            $name = substr($line, 0, $colonPosition);
            $rawValue = ltrim(substr($line, $colonPosition + 1));

            $entries[] = ['name' => $name, 'value' => self::decodeValue($rawValue)];
        }

        return new self($entries);
    }

    /**
     * Returns the first value stored for $name (case-insensitive), or null if it is absent.
     */
    public function get(string $name): ?string
    {
        foreach ($this->entries as $entry) {
            if (strcasecmp($entry['name'], $name) === 0) {
                return $entry['value'];
            }
        }

        return null;
    }

    /**
     * Returns every value stored for $name (case-insensitive), in the order they appeared. A
     * header that never appeared returns an empty array.
     *
     * @return string[]
     */
    public function getAll(string $name): array
    {
        $values = [];

        foreach ($this->entries as $entry) {
            if (strcasecmp($entry['name'], $name) === 0) {
                $values[] = $entry['value'];
            }
        }

        return $values;
    }

    /**
     * Returns every header as a name/value pair, in the order it appeared, with the name's
     * original casing preserved.
     *
     * @return array<int, array{name: string, value: string}>
     */
    public function all(): array
    {
        return $this->entries;
    }

    /**
     * Splits a raw header blob into physical lines and unfolds continuations: any line starting
     * with a space or tab is not a new header, but a continuation of the previous header's value,
     * and is joined onto it with a single space (per RFC 5322 section 2.2.3).
     *
     * @return string[]
     */
    private static function unfold(string $rawHeaderBlock): array
    {
        $normalized = str_replace("\r\n", "\n", $rawHeaderBlock);
        $lines = [];

        foreach (explode("\n", $normalized) as $rawLine) {
            if ($rawLine === '') {
                // A blank line ends the header block (or is stray leading/trailing whitespace);
                // either way there is nothing to unfold it into.
                continue;
            }

            $startsFolded = $rawLine[0] === ' ' || $rawLine[0] === "\t";

            if ($startsFolded && count($lines) > 0) {
                $lines[count($lines) - 1] .= ' ' . ltrim($rawLine, " \t");
            } else {
                $lines[] = $rawLine;
            }
        }

        return $lines;
    }

    /**
     * Decodes an RFC 2047 encoded-word value (e.g. "=?UTF-8?B?...?=") using mbstring's built-in
     * MIME header decoder. A value with no encoded words passes through unchanged.
     */
    private static function decodeValue(string $value): string
    {
        return mb_decode_mimeheader($value);
    }
}
