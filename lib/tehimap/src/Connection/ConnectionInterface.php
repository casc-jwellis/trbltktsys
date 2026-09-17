<?php

declare(strict_types=1);

namespace Tehimap\Imap\Connection;

/**
 * A raw line/byte transport to an IMAP server. Implementations own the socket and any TLS
 * negotiation; everything above this layer speaks in terms of lines and literal byte counts,
 * never raw resources.
 */
interface ConnectionInterface
{
    /**
     * Opens the underlying connection (and negotiates TLS immediately, if the implementation
     * was configured for implicit TLS). Safe to call once; calling twice while connected throws.
     */
    public function connect(): void;

    public function isConnected(): bool;

    /**
     * Upgrades an already-open plaintext connection to TLS in place (for STARTTLS). Implementations
     * that only support implicit TLS may throw ConnectionException.
     */
    public function enableCrypto(): void;

    /** Writes raw bytes exactly as given. Callers are responsible for line terminators. */
    public function write(string $bytes): void;

    /**
     * Reads one CRLF-terminated line and returns it WITHOUT the trailing CRLF.
     * Blocks until a full line is available or the configured timeout elapses.
     *
     * @throws \Tehimap\Imap\Exception\ConnectionException on timeout or unexpected EOF
     */
    public function readLine(): string;

    /**
     * Reads exactly $length raw bytes (used to consume an IMAP literal's payload after a
     * "{n}" marker). A literal carries no line ending of its own (RFC 3501 section 4.3) —
     * whatever follows it on the wire directly continues the same response and should be
     * read normally via readLine(), not as an extra fixed-size read.
     *
     * @throws \Tehimap\Imap\Exception\ConnectionException on timeout or unexpected EOF
     */
    public function readBytes(int $length): string;

    public function disconnect(): void;
}
