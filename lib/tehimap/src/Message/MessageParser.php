<?php

declare(strict_types=1);

namespace Tehimap\Imap\Message;

/**
 * Builds Message and MessagePart trees out of the flat/nested token arrays a FETCH response
 * hands back (see Protocol\ServerResponse and Protocol\ResponseParser). Both entry points are
 * deliberately defensive: real-world servers vary in what extension fields they send, so any
 * shape this class doesn't specifically model is skipped or falls back to a best-effort result
 * rather than throwing.
 */
final class MessageParser
{
    /** The IMAP token for an absent value, e.g. a leaf part with no Content-ID. */
    private const NIL = 'NIL';

    /**
     * Recursively turns a BODYSTRUCTURE token array (RFC 3501 section 7.4.2) into a MessagePart
     * tree.
     *
     * $tokens is the parenthesized list's own contents - for a leaf part that is
     * [type, subtype, parameters, id, description, encoding, size, ...type-specific extensions],
     * and for a multipart node it is [childStructure, childStructure, ..., subtype, ...extension
     * fields], where each childStructure is itself one of these lists.
     *
     * $partNumberPrefix is this node's own IMAP body-part number (e.g. "1.2", or "" for the
     * top-level part), and children of a multipart node are numbered "1", "2", ... beneath it
     * (or "{$partNumberPrefix}.1", "{$partNumberPrefix}.2", ... when nested).
     */
    public static function parseBodyStructure(array $tokens, string $partNumberPrefix = ''): MessagePart
    {
        try {
            return self::doParseBodyStructure($tokens, $partNumberPrefix);
        } catch (\Throwable) {
            // A shape we don't model tripped one of the helpers below; still return something
            // rather than letting a single odd part take down the whole parse.
            return new MessagePart($partNumberPrefix, '', '', [], null, null, '', 0, null, [], []);
        }
    }

    /**
     * Builds a Message from the flat token list that followed the word FETCH in a single FETCH
     * response, e.g. the ["UID", "4827", "FLAGS", ["\Seen"], ...] in
     * "* 12 FETCH (UID 4827 FLAGS (\Seen))". Walks it two tokens at a time as key/value pairs.
     *
     * @param array<int, mixed> $fetchDataItems
     */
    public static function fromFetchTokens(array $fetchDataItems): Message
    {
        $uid = 0;
        $flags = [];
        $headers = null;
        $structure = null;
        $bodyParts = [];

        $count = count($fetchDataItems);

        for ($i = 0; $i + 1 < $count; $i += 2) {
            $key = $fetchDataItems[$i];
            $value = $fetchDataItems[$i + 1];

            if (!is_string($key)) {
                continue;
            }

            $upperKey = strtoupper($key);

            if ($upperKey === 'UID') {
                if (is_string($value) && is_numeric($value)) {
                    $uid = (int) $value;
                }

                continue;
            }

            if ($upperKey === 'FLAGS') {
                if (is_array($value)) {
                    $flags = array_values(array_filter($value, 'is_string'));
                }

                continue;
            }

            if ($upperKey === 'BODYSTRUCTURE') {
                if (is_array($value)) {
                    try {
                        $structure = self::parseBodyStructure($value);
                    } catch (\Throwable) {
                        // Leave $structure as null rather than losing the rest of the message.
                    }
                }

                continue;
            }

            $section = self::extractBodySection($key);

            if ($section !== null) {
                if (is_string($value)) {
                    $bodyParts[$section] = $value;

                    if ($section === '' || stripos($section, 'HEADER') === 0) {
                        $headers = MessageHeaders::parse($value);
                    }
                }

                continue;
            }

            // An unrecognized key (e.g. a field this class hasn't been taught about yet); skip
            // it for forward compatibility rather than throwing.
        }

        return new Message($uid, $flags, $headers, $structure, $bodyParts);
    }

    private static function doParseBodyStructure(array $tokens, string $partNumberPrefix): MessagePart
    {
        if (isset($tokens[0]) && is_array($tokens[0])) {
            return self::parseMultipart($tokens, $partNumberPrefix);
        }

        return self::parseLeaf($tokens, $partNumberPrefix);
    }

    /**
     * A multipart node: one or more child structures, then the multipart subtype atom (e.g.
     * "MIXED", "ALTERNATIVE"), then optional extension fields (parameters, disposition, language,
     * location) that this class doesn't otherwise model.
     */
    private static function parseMultipart(array $tokens, string $partNumberPrefix): MessagePart
    {
        $children = [];
        $index = 0;
        $childNumber = 1;

        while (isset($tokens[$index]) && is_array($tokens[$index])) {
            $childPartNumber = $partNumberPrefix === ''
                ? (string) $childNumber
                : $partNumberPrefix . '.' . $childNumber;

            $children[] = self::parseBodyStructure($tokens[$index], $childPartNumber);
            $index++;
            $childNumber++;
        }

        $subtype = self::stringAt($tokens, $index) ?? '';
        $parameters = self::parseParameterList($tokens[$index + 1] ?? null);
        [$dispositionType, $dispositionParameters] = self::parseDisposition($tokens[$index + 2] ?? null);

        return new MessagePart(
            $partNumberPrefix,
            'multipart',
            $subtype,
            $parameters,
            null,
            null,
            '',
            0,
            $dispositionType,
            $dispositionParameters,
            $children,
        );
    }

    /**
     * A leaf (non-multipart) part: type, subtype, parameter list, id, description, encoding and
     * size, in that order (RFC 3501 section 7.4.2's "basic fields", indices 0-6). Depending on
     * type, one or more type-specific fields come next before the standard extension data
     * (body-fld-md5, body-fld-dsp, ...) begins: a single line-count field for a "text/*" part, or
     * an envelope, nested BODYSTRUCTURE, and line count for a "message/rfc822" part. Those
     * type-specific fields themselves are left unmodeled, but they still have to be skipped over
     * correctly to find the disposition field that follows them.
     */
    private static function parseLeaf(array $tokens, string $partNumberPrefix): MessagePart
    {
        $type = self::stringAt($tokens, 0) ?? '';
        $subtype = self::stringAt($tokens, 1) ?? '';
        $parameters = self::parseParameterList($tokens[2] ?? null);
        $id = self::stringAt($tokens, 3);
        $description = self::stringAt($tokens, 4);
        $encoding = self::stringAt($tokens, 5) ?? '';
        $size = self::intAt($tokens, 6) ?? 0;

        // The basic fields end at index 6. Type-specific fields, if any, follow immediately, then
        // the MD5 extension field (ignored here), then the disposition field.
        if (strcasecmp($type, 'message') === 0 && strcasecmp($subtype, 'rfc822') === 0) {
            $typeSpecificFieldCount = 3; // envelope, body structure, line count
        } elseif (strcasecmp($type, 'text') === 0) {
            $typeSpecificFieldCount = 1; // line count
        } else {
            $typeSpecificFieldCount = 0;
        }

        $dispositionIndex = 7 + $typeSpecificFieldCount + 1;
        [$dispositionType, $dispositionParameters] = self::parseDisposition($tokens[$dispositionIndex] ?? null);

        return new MessagePart(
            $partNumberPrefix,
            $type,
            $subtype,
            $parameters,
            $id,
            $description,
            $encoding,
            $size,
            $dispositionType,
            $dispositionParameters,
            [],
        );
    }

    /**
     * Reads a BODYSTRUCTURE body disposition extension field (RFC 3501 section 7.4.2): either the
     * NIL token (no disposition) or a two-element list of [disposition type, parameter list].
     * Falls back to no disposition for anything else - a missing field, a server that skipped it
     * entirely, or a shape this class doesn't recognize - rather than misparsing some other
     * extension field as if it were the disposition.
     *
     * @return array{0: ?string, 1: array<string, string>}
     */
    private static function parseDisposition(mixed $token): array
    {
        if (!is_array($token) || count($token) < 2) {
            return [null, []];
        }

        $type = self::stringAt($token, 0);
        $parameters = self::parseParameterList($token[1] ?? null);

        return [$type, $parameters];
    }

    /**
     * Turns a BODYSTRUCTURE parameter list token, e.g. ["CHARSET", "utf-8"], into an associative
     * array. Returns an empty array for the NIL token (no parameters) or anything else that isn't
     * a flat list of string pairs.
     *
     * @return array<string, string>
     */
    private static function parseParameterList(mixed $token): array
    {
        if (!is_array($token)) {
            return [];
        }

        $parameters = [];
        $count = count($token);

        for ($i = 0; $i + 1 < $count; $i += 2) {
            $key = $token[$i];
            $value = $token[$i + 1];

            if (is_string($key) && is_string($value)) {
                $parameters[$key] = $value;
            }
        }

        return $parameters;
    }

    /**
     * Reads $tokens[$index] as a string, treating the IMAP NIL token as an absent value (null)
     * rather than the literal text "NIL". Returns null if the slot is missing or isn't a string.
     */
    private static function stringAt(array $tokens, int $index): ?string
    {
        $value = $tokens[$index] ?? null;

        if (!is_string($value)) {
            return null;
        }

        return strcasecmp($value, self::NIL) === 0 ? null : $value;
    }

    /**
     * Reads $tokens[$index] as an integer (BODYSTRUCTURE sizes and line counts are sent as plain
     * digit atoms). Returns null if the slot is missing, NIL, or not numeric.
     */
    private static function intAt(array $tokens, int $index): ?int
    {
        $value = self::stringAt($tokens, $index);

        return $value !== null && is_numeric($value) ? (int) $value : null;
    }

    /**
     * Pulls out whatever is between a FETCH data item key's brackets, e.g. "HEADER" out of
     * "BODY[HEADER]", "1.2" out of "BODY[1.2]", or "" out of "BODY[]". Returns null for a key that
     * isn't shaped like a BODY[...] section specifier at all.
     */
    private static function extractBodySection(string $key): ?string
    {
        if (stripos($key, 'BODY') !== 0) {
            return null;
        }

        $bracketStart = strpos($key, '[');

        if ($bracketStart === false) {
            return null;
        }

        $bracketEnd = strrpos($key, ']');

        if ($bracketEnd === false || $bracketEnd <= $bracketStart) {
            return null;
        }

        return substr($key, $bracketStart + 1, $bracketEnd - $bracketStart - 1);
    }
}
