<?php

declare(strict_types=1);

namespace Modufolio\PanelModule\Tests\Unit;

use Modufolio\Appkit\Security\Token\TokenInterface;
use Modufolio\Appkit\Security\Token\TokenStorageInterface;
use Modufolio\Appkit\Security\User\UserInterface as AppkitUser;
use Modufolio\Panel\Contracts\UserInterface as PanelUser;
use Modufolio\PanelModule\Security\TokenCurrentUser;
use PHPUnit\Framework\TestCase;

final class TokenCurrentUserTest extends TestCase
{
    private function storage(?object $user): TokenStorageInterface
    {
        $storage = $this->createStub(TokenStorageInterface::class);

        if ($user === null) {
            $storage->method('getToken')->willReturn(null);

            return $storage;
        }

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $storage->method('getToken')->willReturn($token);

        return $storage;
    }

    public function testNobodySignedInIsNull(): void
    {
        self::assertNull((new TokenCurrentUser($this->storage(null)))->user());
    }

    public function testAUserCarryingThePanelsInterfaceIsHandedOverAsIs(): void
    {
        $user = new class implements AppkitUser, PanelUser {
            public function getId(): mixed { return 1; }
            public function getEmail(): string { return 'a@b.c'; }
            public function getRoles(): array { return ['ROLE_ADMIN']; }
            public function eraseCredentials(): void {}
            public function getUserIdentifier(): string { return 'a'; }
            public function isEnabled(): bool { return true; }
        };

        self::assertSame($user, (new TokenCurrentUser($this->storage($user)))->user());
    }

    public function testAUserWithoutThePanelsInterfaceIsAWiringError(): void
    {
        $user = $this->createStub(AppkitUser::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(PanelUser::class);

        (new TokenCurrentUser($this->storage($user)))->user();
    }
}
