<?php

declare(strict_types=1);

namespace Tehimap\Imap\Auth;

use Tehimap\Imap\Exception\AuthenticationException;
use Tehimap\Imap\Exception\ProtocolException;
use Tehimap\Imap\Protocol\CommandBuilder;
use Tehimap\Imap\Protocol\CommandRunnerInterface;

/**
 * Authenticates with a plain username and password via the IMAP LOGIN command.
 *
 * Both the username and the password are sent as synchronizing literals rather than quoted
 * strings, so that any character either one might contain (spaces, quotes, backslashes, CR/LF,
 * non-ASCII bytes, ...) is transmitted verbatim without needing to be escaped or validated.
 */
final class PlainAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private readonly string $username,
        private readonly string $password,
    ) {
    }

    /**
     * @throws AuthenticationException if the server rejects the credentials
     */
    public function authenticate(CommandRunnerInterface $runner): void
    {
        $command = 'LOGIN ' . CommandBuilder::literal($this->username) . ' ' . CommandBuilder::literal($this->password);

        try {
            $runner->send($command, [$this->username, $this->password]);
        } catch (ProtocolException $exception) {
            throw new AuthenticationException(
                'LOGIN authentication failed: ' . $exception->getMessage(),
                previous: $exception,
            );
        }
    }
}
