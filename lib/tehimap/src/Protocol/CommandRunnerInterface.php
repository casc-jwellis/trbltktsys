<?php

declare(strict_types=1);

namespace Tehimap\Imap\Protocol;

use Tehimap\Imap\Exception\ConnectionException;
use Tehimap\Imap\Exception\ProtocolException;

/**
 * Sends a single IMAP command to completion and returns every response line the server sent
 * while handling it.
 */
interface CommandRunnerInterface
{
    /**
     * @param string $command The command text AFTER the tag and following space, e.g.
     *   "LOGIN " . CommandBuilder::literal($user) . ' ' . CommandBuilder::literal($pass)
     *   The runner prepends its own generated tag (e.g. "A0007 ") and the trailing CRLF.
     * @param string[] $literals Raw octet strings to send, in the order the command's literal
     *   placeholders ("{n}") appear, whenever the server issues a "+" continuation request.
     * @return ServerResponse[] every line received (untagged and continuation lines included),
     *   with the final tagged ServerResponse always last.
     * @throws ProtocolException if the tagged completion status is NO or BAD
     * @throws ConnectionException on socket failure
     */
    public function send(string $command, array $literals = []): array;
}
