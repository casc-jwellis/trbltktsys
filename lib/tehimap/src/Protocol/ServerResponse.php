<?php

declare(strict_types=1);

namespace Tehimap\Imap\Protocol;

/**
 * One fully-parsed IMAP response line (RFC 3501 section 7): untagged ("*"), a continuation
 * request ("+"), or a tagged completion result.
 *
 * $tokens holds everything after the tag (and after the status word, when one is present) as a
 * flat list where each element is either:
 *   - string   an atom, quoted string, or literal's decoded content
 *   - string[] (recursively) a parenthesized list, e.g. FLAGS (\Seen \Answered) becomes
 *              ['FLAGS', ['\Seen', '\Answered']]
 *
 * Examples:
 *   "A0003 OK LOGIN completed"
 *     -> tag="A0003" status="OK" tokens=["LOGIN", "completed"]
 *   "* 172 EXISTS"
 *     -> tag="*" status=null tokens=["172", "EXISTS"]
 *   "* 12 FETCH (UID 4827 FLAGS (\Seen))"
 *     -> tag="*" status=null tokens=["12", "FETCH", ["UID", "4827", "FLAGS", ["\Seen"]]]
 *   "+ ready"
 *     -> tag="+" status=null tokens=["ready"]
 */
final class ServerResponse
{
    /**
     * @param array<int, string|array<mixed>> $tokens
     */
    public function __construct(
        public readonly string $tag,
        public readonly ?string $status,
        public readonly array $tokens,
        public readonly string $raw,
    ) {
    }

    public function isUntagged(): bool
    {
        return $this->tag === '*';
    }

    public function isContinuation(): bool
    {
        return $this->tag === '+';
    }

    public function isTagged(): bool
    {
        return !$this->isUntagged() && !$this->isContinuation();
    }

    public function isOk(): bool
    {
        return $this->status === 'OK';
    }
}
