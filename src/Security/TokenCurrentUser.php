<?php

declare(strict_types=1);

namespace Modufolio\PanelModule\Security;

use Modufolio\Appkit\Security\Token\TokenStorageInterface;
use Modufolio\Panel\Contracts\CurrentUserInterface;
use Modufolio\Panel\Contracts\UserInterface;

/**
 * The signed-in user, read off appkit's token storage.
 *
 * The panel's permission classes receive the host's user object as it is,
 * so the host's user class must carry the panel's {@see UserInterface}. A
 * user that does not is a wiring mistake, and saying so beats every
 * permission quietly answering "no" to a null viewer.
 */
final class TokenCurrentUser implements CurrentUserInterface
{
    public function __construct(private readonly TokenStorageInterface $tokens)
    {
    }

    public function user(): ?UserInterface
    {
        $user = $this->tokens->getToken()?->getUser();

        if ($user === null) {
            return null;
        }

        if (!$user instanceof UserInterface) {
            throw new \LogicException(sprintf(
                'The panel reads roles off the signed-in user, so %s must implement %s; add the interface, its getRoles() already satisfies it.',
                $user::class,
                UserInterface::class,
            ));
        }

        return $user;
    }
}
