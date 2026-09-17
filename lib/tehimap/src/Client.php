<?php

declare(strict_types=1);

namespace Tehimap\Imap;

use DateTimeInterface;
use InvalidArgumentException;
use Tehimap\Imap\Auth\AuthenticatorInterface;
use Tehimap\Imap\Connection\ConnectionInterface;
use Tehimap\Imap\Connection\SocketConnection;
use Tehimap\Imap\Exception\ConnectionException;
use Tehimap\Imap\Mailbox\MailboxInfo;
use Tehimap\Imap\Message\Message;
use Tehimap\Imap\Message\MessageParser;
use Tehimap\Imap\Protocol\CommandBuilder;
use Tehimap\Imap\Protocol\CommandRunner;
use Tehimap\Imap\Protocol\CommandRunnerInterface;
use Tehimap\Imap\Protocol\ResponseParser;
use Tehimap\Imap\Protocol\Tag;
use Tehimap\Imap\Search\SearchQuery;
use Tehimap\Imap\Sync\SyncStateInterface;

/**
 * The library's main entry point: a single IMAP session over one connection, wiring the
 * connection/protocol layers together and translating their response arrays into plain values
 * (mailbox names, UID lists, Message objects, ...) for callers.
 *
 * Nothing here talks to the socket directly except by way of the internal CommandRunner (for
 * ordinary tagged commands) or, once at start-up, the ResponseParser reading the server's
 * untagged greeting straight off the connection before any tag has been sent.
 */
final class Client
{
    private readonly ConnectionInterface $connection;

    private readonly ResponseParser $parser;

    private readonly CommandRunnerInterface $runner;

    /** @var string[]|null Cached CAPABILITY result; null until the first capabilities() call. */
    private ?array $capabilitiesCache = null;

    public function __construct(
        string $host,
        int $port,
        bool $useTls,
        private readonly AuthenticatorInterface $authenticator,
        private readonly ?SyncStateInterface $syncState = null,
    ) {
        $this->connection = new SocketConnection($host, $port, $useTls);
        $this->parser = new ResponseParser();
        $this->runner = new CommandRunner($this->connection, new Tag(), $this->parser);
    }

    /**
     * Opens the connection and reads/validates the server's initial greeting (RFC 3501 section
     * 7.1.5) before anything else is sent.
     *
     * @throws ConnectionException if the connection fails, or the greeting is not a well-formed
     *   untagged OK/PREAUTH response
     */
    public function connect(): void
    {
        $this->connection->connect();

        $greeting = $this->parser->readResponse($this->connection);

        if (!$greeting->isUntagged() || ($greeting->status !== 'OK' && $greeting->status !== 'PREAUTH')) {
            throw new ConnectionException('Unexpected server greeting: ' . trim($greeting->raw));
        }
    }

    /**
     * Authenticates using whichever AuthenticatorInterface this client was constructed with.
     *
     * @throws \Tehimap\Imap\Exception\AuthenticationException if the server rejects it
     */
    public function login(): void
    {
        $this->authenticator->authenticate($this->runner);
    }

    /**
     * Logs out (RFC 3501 section 6.1.3) and closes the underlying connection.
     */
    public function logout(): void
    {
        $this->runner->send('LOGOUT');
        $this->connection->disconnect();
    }

    /**
     * Returns the server's advertised capability strings (e.g. "IMAP4rev1", "STARTTLS",
     * "MOVE"), sending CAPABILITY only on the first call and reusing the result afterwards.
     *
     * @return string[]
     */
    public function capabilities(): array
    {
        if ($this->capabilitiesCache !== null) {
            return $this->capabilitiesCache;
        }

        $responses = $this->runner->send('CAPABILITY');
        $capabilities = [];

        foreach ($responses as $response) {
            if (!$response->isUntagged() || !$this->tokenIs($response->tokens, 0, 'CAPABILITY')) {
                continue;
            }

            foreach (array_slice($response->tokens, 1) as $token) {
                if (is_string($token)) {
                    $capabilities[] = $token;
                }
            }
        }

        $this->capabilitiesCache = $capabilities;

        return $capabilities;
    }

    /**
     * Lists mailbox names matching $pattern relative to $reference (RFC 3501 section 6.3.8).
     *
     * @return string[]
     */
    public function listMailboxes(string $reference = '', string $pattern = '*'): array
    {
        $command = 'LIST ' . CommandBuilder::quoted($reference) . ' ' . CommandBuilder::quoted($pattern);
        $responses = $this->runner->send($command);

        $mailboxes = [];

        foreach ($responses as $response) {
            if (!$response->isUntagged() || !$this->tokenIs($response->tokens, 0, 'LIST')) {
                continue;
            }

            $name = $response->tokens[3] ?? null;

            if (is_string($name)) {
                $mailboxes[] = $name;
            }
        }

        return $mailboxes;
    }

    /**
     * Requests $items about $mailbox via STATUS (RFC 3501 section 6.3.10) without selecting it.
     *
     * @param string[] $items
     * @return array<string, int>
     */
    public function status(string $mailbox, array $items = ['MESSAGES', 'UNSEEN']): array
    {
        $command = 'STATUS ' . CommandBuilder::quoted($mailbox) . ' (' . implode(' ', $items) . ')';
        $responses = $this->runner->send($command);

        $result = [];

        foreach ($responses as $response) {
            if (!$response->isUntagged() || !$this->tokenIs($response->tokens, 0, 'STATUS')) {
                continue;
            }

            $pairs = $response->tokens[2] ?? null;

            if (!is_array($pairs)) {
                continue;
            }

            $count = count($pairs);

            for ($i = 0; $i + 1 < $count; $i += 2) {
                $key = $pairs[$i];
                $value = $pairs[$i + 1];

                if (is_string($key) && is_string($value) && is_numeric($value)) {
                    $result[$key] = (int) $value;
                }
            }
        }

        return $result;
    }

    /**
     * Opens $name via SELECT (read-write) or EXAMINE (read-only), per RFC 3501 sections 6.3.1
     * and 6.3.2, and builds a MailboxInfo from the untagged responses the server sends back.
     */
    public function selectMailbox(string $name, bool $readOnly = false): MailboxInfo
    {
        $command = ($readOnly ? 'EXAMINE ' : 'SELECT ') . CommandBuilder::quoted($name);
        $responses = $this->runner->send($command);

        $messageCount = 0;
        $recentCount = 0;
        $uidValidity = 0;
        $uidNext = 0;
        $permanentFlags = [];

        foreach ($responses as $response) {
            if (!$response->isUntagged()) {
                continue;
            }

            if ($response->status === 'OK') {
                $code = $this->parseResponseCode($response->tokens[0] ?? null);

                if ($code === null) {
                    continue;
                }

                [$codeName, $codeValue] = $code;

                if ($codeName === 'UIDVALIDITY' && $codeValue !== null && is_numeric($codeValue)) {
                    $uidValidity = (int) $codeValue;
                } elseif ($codeName === 'UIDNEXT' && $codeValue !== null && is_numeric($codeValue)) {
                    $uidNext = (int) $codeValue;
                } elseif ($codeName === 'PERMANENTFLAGS' && $codeValue !== null) {
                    $permanentFlags = $this->parseFlagList($codeValue);
                }

                continue;
            }

            if (
                isset($response->tokens[0], $response->tokens[1])
                && is_string($response->tokens[0])
                && is_numeric($response->tokens[0])
                && is_string($response->tokens[1])
            ) {
                $keyword = strtoupper($response->tokens[1]);

                if ($keyword === 'EXISTS') {
                    $messageCount = (int) $response->tokens[0];
                } elseif ($keyword === 'RECENT') {
                    $recentCount = (int) $response->tokens[0];
                }
            }
        }

        return new MailboxInfo($messageCount, $recentCount, $uidValidity, $uidNext, $permanentFlags);
    }

    /**
     * Runs $query via UID SEARCH (RFC 3501 section 6.4.4) and returns the matching UIDs.
     *
     * @return int[]
     */
    public function search(SearchQuery $query): array
    {
        $responses = $this->runner->send('UID SEARCH ' . $query->build());

        $uids = [];

        foreach ($responses as $response) {
            if (!$response->isUntagged() || !$this->tokenIs($response->tokens, 0, 'SEARCH')) {
                continue;
            }

            foreach (array_slice($response->tokens, 1) as $token) {
                if (is_string($token) && is_numeric($token)) {
                    $uids[] = (int) $token;
                }
            }
        }

        return $uids;
    }

    /**
     * Fetches UID and BODYSTRUCTURE for each of $uids.
     *
     * @param int[] $uids
     * @return Message[]
     */
    public function fetchStructure(array $uids): array
    {
        if ($uids === []) {
            return [];
        }

        $responses = $this->runner->send('UID FETCH ' . $this->toSequenceSet($uids) . ' (UID BODYSTRUCTURE)');

        return $this->collectFetchedMessages($responses);
    }

    /**
     * Fetches UID, FLAGS and a peek-mode header block for each of $uids. Uses BODY.PEEK[HEADER]
     * rather than BODY[HEADER] so that fetching headers never implicitly sets \Seen.
     *
     * @param int[] $uids
     * @return Message[]
     */
    public function fetchHeaders(array $uids): array
    {
        if ($uids === []) {
            return [];
        }

        $responses = $this->runner->send('UID FETCH ' . $this->toSequenceSet($uids) . ' (UID FLAGS BODY.PEEK[HEADER])');

        return $this->collectFetchedMessages($responses);
    }

    /**
     * Fetches a peek-mode body section for each of $uids: the whole message when $partNumber is
     * null, or that specific IMAP body part (e.g. "1.2") otherwise. Uses BODY.PEEK[...] so
     * fetching a body never implicitly sets \Seen.
     *
     * @param int[] $uids
     * @return Message[]
     */
    public function fetchBody(array $uids, ?string $partNumber = null): array
    {
        if ($uids === []) {
            return [];
        }

        $section = $partNumber ?? '';
        $responses = $this->runner->send('UID FETCH ' . $this->toSequenceSet($uids) . " (UID BODY.PEEK[{$section}])");

        return $this->collectFetchedMessages($responses);
    }

    /**
     * Adds, removes, or replaces flags on $uids via UID STORE (RFC 3501 section 6.4.6), using
     * the .SILENT item variants since the caller already knows what flags it asked for.
     *
     * @param int[] $uids
     * @param string[] $flags
     * @param 'add'|'remove'|'replace' $mode
     */
    public function setFlags(array $uids, array $flags, string $mode = 'add'): void
    {
        if ($uids === []) {
            return;
        }

        $item = match ($mode) {
            'add' => '+FLAGS.SILENT',
            'remove' => '-FLAGS.SILENT',
            'replace' => 'FLAGS.SILENT',
            default => throw new InvalidArgumentException("Unknown flag store mode: {$mode}"),
        };

        $command = 'UID STORE ' . $this->toSequenceSet($uids) . ' ' . $item . ' (' . implode(' ', $flags) . ')';

        $this->runner->send($command);
    }

    /**
     * Copies $uids into $targetMailbox via UID COPY (RFC 3501 section 6.4.7).
     *
     * @param int[] $uids
     */
    public function copy(array $uids, string $targetMailbox): void
    {
        if ($uids === []) {
            return;
        }

        $command = 'UID COPY ' . $this->toSequenceSet($uids) . ' ' . CommandBuilder::quoted($targetMailbox);

        $this->runner->send($command);
    }

    /**
     * Moves $uids into $targetMailbox: a direct UID MOVE (RFC 6851) when the server advertises
     * the MOVE capability, otherwise a copy, followed by marking the originals \Deleted and
     * expunging them.
     *
     * @param int[] $uids
     */
    public function move(array $uids, string $targetMailbox): void
    {
        if ($uids === []) {
            return;
        }

        if ($this->hasCapability('MOVE')) {
            $command = 'UID MOVE ' . $this->toSequenceSet($uids) . ' ' . CommandBuilder::quoted($targetMailbox);
            $this->runner->send($command);

            return;
        }

        $this->copy($uids, $targetMailbox);
        $this->setFlags($uids, ['\\Deleted'], 'add');
        $this->expunge();
    }

    /**
     * Permanently removes messages flagged \Deleted from the currently selected mailbox (RFC
     * 3501 section 6.4.3).
     */
    public function expunge(): void
    {
        $this->runner->send('EXPUNGE');
    }

    /**
     * Appends $rawMessage to $mailbox via APPEND (RFC 3501 section 6.3.11), with an optional
     * initial flag list and internal date, sending the message itself as the command's one
     * queued literal.
     *
     * @param string[] $flags
     */
    public function append(
        string $mailbox,
        string $rawMessage,
        array $flags = [],
        ?DateTimeInterface $internalDate = null,
    ): void {
        $command = 'APPEND ' . CommandBuilder::quoted($mailbox);

        if ($flags !== []) {
            $command .= ' (' . implode(' ', $flags) . ')';
        }

        if ($internalDate !== null) {
            $command .= ' ' . CommandBuilder::quoted($internalDate->format('d-M-Y H:i:s O'));
        }

        $command .= ' ' . CommandBuilder::literal($rawMessage);

        $this->runner->send($command, [$rawMessage]);
    }

    /**
     * Turns a list of UIDs into an IMAP sequence-set string. Comma-separated individual UIDs are
     * always valid; range-compressing consecutive runs (e.g. "1:5") is left for a later version.
     *
     * @param int[] $uids
     */
    private function toSequenceSet(array $uids): string
    {
        return implode(',', array_map(static fn (int $uid): string => (string) $uid, $uids));
    }

    /**
     * @return Message[]
     */
    private function collectFetchedMessages(array $responses): array
    {
        $messages = [];

        foreach ($responses as $response) {
            if (!$response->isUntagged() || !$this->tokenIs($response->tokens, 1, 'FETCH')) {
                continue;
            }

            $items = $response->tokens[2] ?? null;

            if (is_array($items)) {
                $messages[] = MessageParser::fromFetchTokens($items);
            }
        }

        return $messages;
    }

    private function hasCapability(string $name): bool
    {
        foreach ($this->capabilities() as $capability) {
            if (strcasecmp($capability, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether $tokens[$index] is the string $expected, case-insensitively.
     *
     * @param array<int, mixed> $tokens
     */
    private function tokenIs(array $tokens, int $index, string $expected): bool
    {
        $token = $tokens[$index] ?? null;

        return is_string($token) && strcasecmp($token, $expected) === 0;
    }

    /**
     * Splits a response code token such as "[UIDVALIDITY 3857529045]" into its code name
     * ("UIDVALIDITY") and the remainder of its content ("3857529045"), or null if $token isn't
     * shaped like a bracketed response code at all.
     *
     * @return array{0: string, 1: ?string}|null
     */
    private function parseResponseCode(mixed $token): ?array
    {
        if (!is_string($token) || !str_starts_with($token, '[')) {
            return null;
        }

        $end = strpos($token, ']');

        if ($end === false) {
            return null;
        }

        $inner = substr($token, 1, $end - 1);
        $parts = explode(' ', $inner, 2);

        return [strtoupper($parts[0]), $parts[1] ?? null];
    }

    /**
     * Parses a PERMANENTFLAGS response code's value, e.g. "(\Answered \Flagged \Seen \*)", into
     * a flat flag list.
     *
     * @return string[]
     */
    private function parseFlagList(string $value): array
    {
        $trimmed = trim($value);

        if (str_starts_with($trimmed, '(') && str_ends_with($trimmed, ')')) {
            $trimmed = substr($trimmed, 1, -1);
        }

        if ($trimmed === '') {
            return [];
        }

        $flags = preg_split('/\s+/', $trimmed);

        return $flags === false ? [] : $flags;
    }
}
