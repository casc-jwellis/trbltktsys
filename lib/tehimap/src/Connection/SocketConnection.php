<?php

declare(strict_types=1);

namespace Tehimap\Imap\Connection;

use Tehimap\Imap\Exception\ConnectionException;

/**
 * A ConnectionInterface backed by a plain PHP stream socket (stream_socket_client), speaking
 * either implicit TLS (connect straight over an "ssl://" transport, e.g. port 993) or plaintext
 * with an optional later upgrade via enableCrypto() (for STARTTLS, e.g. port 143).
 */
final class SocketConnection implements ConnectionInterface
{
    private const LINE_ENDING = "\r\n";

    /** Bounded retry count for enableCrypto() when the negotiation reports it needs more data. */
    private const MAX_CRYPTO_RETRIES = 100;

    /** @var resource|null */
    private $stream = null;

    /**
     * @param array<string, array<string, mixed>> $contextOptions Stream context option overrides,
     *        keyed by wrapper (e.g. ['ssl' => ['allow_self_signed' => true, 'verify_peer' => false]]).
     *        Merged over (and taking precedence over) the secure defaults.
     */
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly bool $useTls = true,
        private readonly float $connectTimeout = 10.0,
        private readonly float $readTimeout = 30.0,
        private readonly array $contextOptions = [],
    ) {
    }

    public function connect(): void
    {
        if ($this->isConnected()) {
            throw new ConnectionException('Already connected.');
        }

        $transport = $this->useTls ? 'ssl' : 'tcp';
        $url = sprintf('%s://%s:%d', $transport, $this->host, $this->port);

        $defaults = [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'SNI_enabled' => true,
                'peer_name' => $this->host,
            ],
        ];
        $options = $this->mergeContextOptions($defaults, $this->contextOptions);
        $context = stream_context_create($options);

        $errno = 0;
        $errstr = '';

        $stream = @stream_socket_client(
            $url,
            $errno,
            $errstr,
            $this->connectTimeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($stream === false) {
            throw new ConnectionException(sprintf(
                'Failed to connect to %s: %s (errno %d)',
                $url,
                $errstr,
                $errno,
            ));
        }

        $this->stream = $stream;
        $this->applyReadTimeout();
    }

    public function isConnected(): bool
    {
        return is_resource($this->stream) && !feof($this->stream);
    }

    public function enableCrypto(): void
    {
        if ($this->useTls) {
            throw new ConnectionException(
                'enableCrypto() is only for upgrading a plaintext connection (STARTTLS); '
                . 'this connection was configured for implicit TLS.',
            );
        }

        if (!$this->isConnected() || $this->stream === null) {
            throw new ConnectionException('Cannot enable crypto: not connected.');
        }

        $method = STREAM_CRYPTO_METHOD_TLS_CLIENT
            | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
            | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;

        for ($attempt = 0; $attempt < self::MAX_CRYPTO_RETRIES; $attempt++) {
            $result = @stream_socket_enable_crypto($this->stream, true, $method);

            if ($result === true) {
                return;
            }

            if ($result === false) {
                throw new ConnectionException('TLS handshake failed while enabling crypto.');
            }

            // $result === 0: negotiation needs more data. Wait briefly for the socket to become
            // readable and retry, rather than busy-spinning.
            $read = [$this->stream];
            $write = [];
            $except = [];
            @stream_select($read, $write, $except, 0, 20_000);
        }

        throw new ConnectionException(
            'TLS handshake did not complete after ' . self::MAX_CRYPTO_RETRIES . ' attempts.',
        );
    }

    public function write(string $bytes): void
    {
        if (!$this->isConnected() || $this->stream === null) {
            throw new ConnectionException('Cannot write: not connected.');
        }

        $length = strlen($bytes);

        if ($length === 0) {
            return;
        }

        $written = 0;

        while ($written < $length) {
            $chunk = @fwrite($this->stream, substr($bytes, $written));

            if ($chunk === false) {
                throw new ConnectionException('Failed to write to connection.');
            }

            if ($chunk === 0) {
                // Nothing went out this call; distinguish a stalled-but-alive socket from one
                // that has actually died, to avoid spinning forever on a dead connection.
                if (!$this->isConnected()) {
                    throw new ConnectionException('Connection closed while writing.');
                }

                continue;
            }

            $written += $chunk;
        }
    }

    public function readLine(): string
    {
        if ($this->stream === null) {
            throw new ConnectionException('Cannot read: not connected.');
        }

        $line = '';

        while (!str_ends_with($line, self::LINE_ENDING)) {
            $chunk = @fgets($this->stream);

            $this->assertNotTimedOut();

            if ($chunk === false) {
                throw new ConnectionException('Unexpected end of stream while reading a line.');
            }

            $line .= $chunk;
        }

        return substr($line, 0, -strlen(self::LINE_ENDING));
    }

    public function readBytes(int $length): string
    {
        if ($length === 0) {
            return '';
        }

        if ($this->stream === null) {
            throw new ConnectionException('Cannot read: not connected.');
        }

        $data = '';

        while (strlen($data) < $length) {
            $remaining = $length - strlen($data);
            $chunk = @fread($this->stream, $remaining);

            $this->assertNotTimedOut();

            if ($chunk === false || $chunk === '') {
                throw new ConnectionException('Unexpected end of stream while reading bytes.');
            }

            $data .= $chunk;
        }

        return $data;
    }

    public function disconnect(): void
    {
        if (is_resource($this->stream)) {
            @fclose($this->stream);
        }

        $this->stream = null;
    }

    /**
     * Deep-merges stream context options, with $overrides taking precedence over $defaults at
     * the leaf level (e.g. an 'ssl' => ['verify_peer' => false] override doesn't wipe out other
     * default 'ssl' options such as 'peer_name').
     *
     * @param array<string, array<string, mixed>> $defaults
     * @param array<string, array<string, mixed>> $overrides
     * @return array<string, array<string, mixed>>
     */
    private function mergeContextOptions(array $defaults, array $overrides): array
    {
        $merged = $defaults;

        foreach ($overrides as $wrapper => $wrapperOptions) {
            $merged[$wrapper] = array_merge($merged[$wrapper] ?? [], $wrapperOptions);
        }

        return $merged;
    }

    private function applyReadTimeout(): void
    {
        if ($this->stream === null) {
            return;
        }

        $seconds = (int) floor($this->readTimeout);
        $microseconds = (int) round(($this->readTimeout - $seconds) * 1_000_000);

        stream_set_timeout($this->stream, $seconds, $microseconds);
    }

    private function assertNotTimedOut(): void
    {
        if ($this->stream === null) {
            return;
        }

        $meta = stream_get_meta_data($this->stream);

        if ($meta['timed_out']) {
            throw new ConnectionException('Connection timed out while reading.');
        }
    }
}
