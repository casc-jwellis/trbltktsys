<?php

declare(strict_types=1);

namespace Tehimap\Imap\Protocol;

use Tehimap\Imap\Connection\ConnectionInterface;
use Tehimap\Imap\Exception\ProtocolException;

/**
 * Sends one IMAP command to completion over a ConnectionInterface, driving continuation
 * requests (literal handoff, or a bare CRLF fallback for anything else, such as a SASL error
 * payload) and collecting every response line until the matching tagged completion arrives.
 */
final class CommandRunner implements CommandRunnerInterface
{
    private const LINE_ENDING = "\r\n";

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly Tag $tag,
        private readonly ResponseParser $parser,
    ) {
    }

    public function send(string $command, array $literals = []): array
    {
        $tag = $this->tag->next();

        // $command may have one or more CommandBuilder::literal() placeholders embedded in it,
        // each of which already carries its own trailing CRLF (the line ending that announces the
        // literal and must reach the server before it will grant a "+" continuation for that
        // literal's payload). A synchronizing literal requires the client to actually pause there
        // and wait for that "+" rather than writing the whole command in one shot, so $command is
        // split on those embedded CRLFs: each part but the last is sent (and waited on) on its
        // own, and a literal's raw payload is written immediately followed by the next part of the
        // command line (which may itself end in another literal announcement, or in the final
        // CRLF that completes the command).
        $segments = explode(self::LINE_ENDING, $command);

        $this->connection->write($tag . ' ' . array_shift($segments) . self::LINE_ENDING);

        $responses = [];
        $nextLiteralIndex = 0;

        while (true) {
            $response = $this->parser->readResponse($this->connection);
            $responses[] = $response;

            if ($response->isContinuation()) {
                if ($nextLiteralIndex < count($literals)) {
                    $rest = $segments !== [] ? array_shift($segments) : '';
                    $this->connection->write($literals[$nextLiteralIndex] . $rest . self::LINE_ENDING);
                    $nextLiteralIndex++;
                } else {
                    // No literal left queued: send a bare line ending to unblock the server. This
                    // generic fallback matters for SASL mechanisms like XOAUTH2, where an
                    // authentication failure is signalled by a continuation carrying an error
                    // payload that must be acknowledged with an empty line before the final
                    // tagged failure response arrives.
                    $this->connection->write(self::LINE_ENDING);
                }

                continue;
            }

            if ($response->isTagged() && $response->tag === $tag) {
                break;
            }
        }

        $final = $responses[count($responses) - 1];

        if ($final->status === 'NO' || $final->status === 'BAD') {
            throw new ProtocolException(
                sprintf(
                    'Command %s failed with %s: %s',
                    $tag,
                    $final->status,
                    $this->stringifyTokens($final->tokens),
                ),
                $final->tag,
                $final->status,
                $this->stringifyTokens($final->tokens),
            );
        }

        return $responses;
    }

    /**
     * Best-effort flattening of a response's tokens back into readable text, for exception
     * messages / logging only.
     *
     * @param array<int, mixed> $tokens
     */
    private function stringifyTokens(array $tokens): string
    {
        $parts = [];

        foreach ($tokens as $token) {
            $parts[] = is_array($token) ? '(' . $this->stringifyTokens($token) . ')' : (string) $token;
        }

        return implode(' ', $parts);
    }
}
