<?php

declare(strict_types=1);

namespace Tehimap\Imap\Exception;

/**
 * Socket/TLS level failures: connect refused, timeout, unexpected EOF, TLS handshake failure.
 */
class ConnectionException extends ImapException
{
}
