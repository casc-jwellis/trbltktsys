<?php

declare(strict_types=1);

namespace Tehimap\Imap\Protocol;

/**
 * Helpers for building the literal text of an IMAP command line: quoting and literal
 * placeholders for arbitrary string arguments (RFC 3501 section 4.3).
 */
final class CommandBuilder
{
    private const LINE_ENDING = "\r\n";

    /**
     * Returns a synchronizing-literal placeholder ("{n}\r\n") for $value, where n is $value's
     * byte length (not its character length — IMAP literal counts are octet counts, so
     * multi-byte UTF-8 text must be counted in bytes).
     *
     * This only builds the placeholder text that goes inline in the command string; the caller
     * must separately supply $value itself to CommandRunner::send()'s $literals array, in the
     * same left-to-right order placeholders appear in the command, so it can be sent once the
     * server issues its "+" continuation request.
     */
    public static function literal(string $value): string
    {
        return '{' . strlen($value) . '}' . self::LINE_ENDING;
    }

    /**
     * Wraps $value as an IMAP quoted string, escaping any backslash or double-quote characters
     * it contains.
     *
     * @throws \InvalidArgumentException if $value contains a carriage return or line feed
     *         (use literal() instead for values that may contain those).
     */
    public static function quoted(string $value): string
    {
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new \InvalidArgumentException(
                'Quoted strings cannot contain CR or LF; use CommandBuilder::literal() instead.',
            );
        }

        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

        return '"' . $escaped . '"';
    }
}
