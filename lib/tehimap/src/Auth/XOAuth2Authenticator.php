<?php

declare(strict_types=1);

namespace Tehimap\Imap\Auth;

use Tehimap\Imap\Exception\AuthenticationException;
use Tehimap\Imap\Exception\ProtocolException;
use Tehimap\Imap\Protocol\CommandRunnerInterface;

/**
 * Authenticates using the XOAUTH2 SASL mechanism, as used by Gmail and Office365.
 *
 * The initial response is the standard XOAUTH2 string:
 *   "user=" <username> "\x01" "auth=Bearer " <access token> "\x01" "\x01"
 * base64-encoded and sent as plain text after the AUTHENTICATE XOAUTH2 command. Base64 output
 * only ever contains characters that are already safe as a bare IMAP atom, so it needs neither
 * quoting nor a literal placeholder.
 */
final class XOAuth2Authenticator implements AuthenticatorInterface
{
    public function __construct(
        private readonly string $username,
        private readonly string $accessToken,
    ) {
    }

    /**
     * @throws AuthenticationException if the server rejects the token/mechanism
     */
    public function authenticate(CommandRunnerInterface $runner): void
    {
        $initialResponse = "user={$this->username}\x01auth=Bearer {$this->accessToken}\x01\x01";
        $encoded = base64_encode($initialResponse);

        try {
            // The command runner already handles the generic SASL-failure continuation (the
            // server's "+ <base64 error JSON>" line and the client's required empty reply), so
            // this class needs no special-case handling for it.
            $runner->send('AUTHENTICATE XOAUTH2 ' . $encoded);
        } catch (ProtocolException $exception) {
            throw new AuthenticationException(
                'XOAUTH2 authentication failed: ' . $exception->getMessage(),
                previous: $exception,
            );
        }
    }
}
