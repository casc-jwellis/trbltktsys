<?php

declare(strict_types=1);

namespace Tehimap\Imap\Auth;

use Tehimap\Imap\Exception\AuthenticationException;
use Tehimap\Imap\Protocol\CommandRunnerInterface;

/**
 * Performs whatever command(s) are needed to authenticate over an already-connected (and, if
 * required, already TLS-wrapped) command runner. Implementations are swappable at construction
 * time on Client, so a plain-password mechanism today does not preclude an XOAUTH2 one later.
 */
interface AuthenticatorInterface
{
    /**
     * @throws AuthenticationException if the server rejects the credentials/mechanism
     */
    public function authenticate(CommandRunnerInterface $runner): void;
}
