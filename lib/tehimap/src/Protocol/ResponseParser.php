<?php

declare(strict_types=1);

namespace Tehimap\Imap\Protocol;

use Tehimap\Imap\Connection\ConnectionInterface;

/**
 * The IMAP wire-format tokenizer (RFC 3501 section 7 / 9). Split into two layers:
 *
 *   - tokenizeLine(): pure, socket-free tokenizing of one already-read physical line. Useful for
 *     unit tests that don't want to fake a whole connection.
 *   - readResponse(): orchestrates reading as many physical lines (and literal payloads) as one
 *     full response needs, using tokenizeLine() under the hood, and returns an assembled
 *     ServerResponse.
 */
final class ResponseParser
{
    private const LINE_ENDING = "\r\n";

    /** RFC 3501 resp-cond status words that, when they appear right after the tag, are consumed
     *  as the response's status rather than left in its token list. */
    private const STATUS_WORDS = ['OK', 'NO', 'BAD', 'BYE', 'PREAUTH'];

    /**
     * Tokenizes one already-read physical line (no trailing line ending) into a flat token list.
     *
     * Each element of the returned 'tokens' array is one of:
     *   - string           an atom, or a quoted string with its escapes resolved
     *   - array<int,mixed> a parenthesized list, tokenized the same way, recursively
     *   - PendingLiteral   ONLY as the very last element: the line ends in an open "{n}" literal
     *                      marker whose n bytes the caller must fetch from the connection
     *
     * 'depth' is how many levels of "(" nesting are still open at the end of the line (0 means
     * every list that was opened on this line was also closed on this same line).
     *
     * @return array{tokens: array<int, mixed>, depth: int}
     */
    public function tokenizeLine(string $line): array
    {
        $stack = [[]];
        $this->consumeLine($line, $stack);

        return [
            'tokens' => $this->foldStack($stack),
            'depth' => count($stack) - 1,
        ];
    }

    /**
     * Reads and assembles one full server response from $connection: one or more physical lines,
     * fetching any literal payloads along the way, until nesting depth returns to zero and no
     * literal marker is left dangling.
     */
    public function readResponse(ConnectionInterface $connection): ServerResponse
    {
        $raw = '';
        $stack = [[]];

        $line = $connection->readLine();
        $raw .= $line . self::LINE_ENDING;
        $this->consumeLine($line, $stack);

        while (true) {
            $topIndex = count($stack) - 1;
            $top = $stack[$topIndex];
            $lastIndex = count($top) - 1;

            if ($lastIndex >= 0 && $top[$lastIndex] instanceof PendingLiteral) {
                $length = $top[$lastIndex]->length;
                $literalBytes = $connection->readBytes($length);
                $raw .= $literalBytes;

                // A literal is exactly $length octets - nothing more (RFC 3501 section 4.3). There
                // is no extra line ending appended after it on the wire; whatever comes next
                // (a closing ")", more tokens, ...) directly continues this same response and is
                // read as a normal line by the loop's next iteration below.
                $stack[$topIndex][$lastIndex] = $literalBytes;
                continue;
            }

            if (count($stack) > 1) {
                $line = $connection->readLine();
                $raw .= $line . self::LINE_ENDING;
                $this->consumeLine($line, $stack);
                continue;
            }

            break;
        }

        $tokens = $this->foldStack($stack);

        return $this->buildServerResponse($tokens, $raw);
    }

    /**
     * Parses $line from its very first character, mutating $stack (a stack of "currently open
     * list" frames; $stack[0] is the top-level token list, $stack[1] the content of the first
     * still-open "(", and so on) in place. Meant to be called once for a fresh line via
     * tokenizeLine(), or repeatedly across several lines from readResponse() to resume whatever
     * list was left open by the previous line.
     *
     * @param array<int, array<int, mixed>> $stack
     */
    private function consumeLine(string $line, array &$stack): void
    {
        $pos = 0;
        $len = strlen($line);

        while ($pos < $len) {
            $ch = $line[$pos];

            if ($ch === ' ') {
                $pos++;
                continue;
            }

            if ($ch === '(') {
                $pos++;
                $stack[] = [];
                continue;
            }

            if ($ch === ')') {
                $pos++;

                if (count($stack) > 1) {
                    $closed = array_pop($stack);
                    $stack[count($stack) - 1][] = $closed;
                }
                // A stray ")" with nothing open is malformed input; ignore it rather than throw,
                // since this layer's job is tokenizing, not validating server behaviour.

                continue;
            }

            if ($ch === '"') {
                $value = $this->readQuotedString($line, $pos, $len);
                $stack[count($stack) - 1][] = $value;
                continue;
            }

            $atom = $this->readAtom($line, $pos, $len);

            if ($pos >= $len && preg_match('/^\{(\d+)\}$/', $atom, $matches) === 1) {
                $stack[count($stack) - 1][] = new PendingLiteral((int) $matches[1]);
            } else {
                $stack[count($stack) - 1][] = $atom;
            }
        }
    }

    /**
     * Reads a quoted string starting at the opening '"' at $pos, resolving \\ and \" escapes, and
     * leaves $pos just past the closing '"' (or at $len, if the line ends without one).
     */
    private function readQuotedString(string $line, int &$pos, int $len): string
    {
        $pos++; // consume the opening quote
        $value = '';

        while ($pos < $len) {
            $ch = $line[$pos];

            if ($ch === '\\' && $pos + 1 < $len && ($line[$pos + 1] === '\\' || $line[$pos + 1] === '"')) {
                $value .= $line[$pos + 1];
                $pos += 2;
                continue;
            }

            if ($ch === '"') {
                $pos++;
                break;
            }

            $value .= $ch;
            $pos++;
        }

        return $value;
    }

    /**
     * Reads a plain atom starting at $pos, stopping at the next unbracketed space, "(" or ")". An
     * atom immediately followed (no space) by "[" absorbs the entire bracketed run verbatim -
     * tracking "[" / "]" nesting depth so parentheses or spaces inside it (e.g.
     * BODY[HEADER.FIELDS (SUBJECT FROM)]) don't end the atom early - before resuming normal
     * scanning for whatever, if anything, follows the closing "]".
     */
    private function readAtom(string $line, int &$pos, int $len): string
    {
        $start = $pos;

        while ($pos < $len) {
            $ch = $line[$pos];

            if ($ch === '[') {
                $pos = $this->skipBracketRun($line, $pos, $len);
                continue;
            }

            if ($ch === ' ' || $ch === '(' || $ch === ')') {
                break;
            }

            $pos++;
        }

        return substr($line, $start, $pos - $start);
    }

    /**
     * Given $pos pointing at an opening "[", returns the position just past its matching closing
     * "]" (tracking nested "[" / "]" so an inner bracket doesn't close the run early). If the line
     * ends before the brackets balance back to zero, returns $len.
     */
    private function skipBracketRun(string $line, int $pos, int $len): int
    {
        $depth = 0;

        while ($pos < $len) {
            $ch = $line[$pos];

            if ($ch === '[') {
                $depth++;
            } elseif ($ch === ']') {
                $depth--;
            }

            $pos++;

            if ($depth === 0) {
                break;
            }
        }

        return $pos;
    }

    /**
     * Collapses a stack of open frames (deepest last) into a single properly nested token array,
     * e.g. [['A','B'], ['C']] (still-open one level deep) folds into ['A', 'B', ['C']].
     *
     * @param array<int, array<int, mixed>> $stack
     * @return array<int, mixed>
     */
    private function foldStack(array $stack): array
    {
        $result = array_pop($stack);

        while (count($stack) > 0) {
            $frame = array_pop($stack);
            $frame[] = $result;
            $result = $frame;
        }

        return $result;
    }

    /**
     * Splits an assembled token list into tag / status / remaining tokens per RFC 3501 section 7.
     *
     * @param array<int, mixed> $tokens
     */
    private function buildServerResponse(array $tokens, string $raw): ServerResponse
    {
        $tag = isset($tokens[0]) && is_string($tokens[0]) ? $tokens[0] : '';
        $rest = array_slice($tokens, 1);

        if ($tag === '+') {
            // A continuation response never carries a status; everything after "+" is its tokens.
            return new ServerResponse($tag, null, $rest, $raw);
        }

        $status = null;

        if (count($rest) > 0 && is_string($rest[0]) && in_array(strtoupper($rest[0]), self::STATUS_WORDS, true)) {
            $status = strtoupper($rest[0]);
            $rest = array_slice($rest, 1);
        }

        return new ServerResponse($tag, $status, $rest, $raw);
    }
}
