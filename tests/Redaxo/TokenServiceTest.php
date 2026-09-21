<?php

declare(strict_types=1);

namespace KLXM\Dav\Tests\Redaxo;

use DateTimeImmutable;
use KLXM\Dav\Scope;
use KLXM\Dav\TokenService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TokenServiceTest extends TestCase
{
    /** Eine Benutzer-ID, die es nicht gibt: Die Tests berühren keine echten App-Passwörter. */
    private const int USER = 987654321;

    protected function setUp(): void
    {
        if (!class_exists(\rex::class, false)) {
            self::markTestSkipped('Braucht ein laufendes REDAXO (DAV_REDAXO_BOOT).');
        }
    }

    protected function tearDown(): void
    {
        if (class_exists(\rex::class, false)) {
            new TokenService()->revokeAll(self::USER);
        }
    }

    #[Test]
    public function createdPasswordVerifiesOnceHashedAndCaseInsensitive(): void
    {
        $service = new TokenService();
        [$token, $plain] = $service->create(self::USER, 'iPhone', Scope::ReadWrite);

        self::assertMatchesRegularExpression('/^[a-z2-9]{4}(-[a-z2-9]{4}){3}$/', $plain);
        self::assertSame(Scope::ReadWrite, $token->scope);
        self::assertSame($token->id, $service->verify(self::USER, strtoupper($plain))?->id);
        self::assertNull($service->verify(self::USER, 'falsch'));
        self::assertNull($service->verify(self::USER, ''));
        self::assertNull($service->verify(self::USER + 1, $plain), 'Passwort eines anderen Benutzers');
        self::assertNotNull($service->find($token->id)?->lastUsedAt);
    }

    #[Test]
    public function revokedAndExpiredPasswordsAreRejected(): void
    {
        $service = new TokenService();
        [$token, $plain] = $service->create(self::USER, 'alt', Scope::Read);
        [, $expired] = $service->create(self::USER, 'abgelaufen', Scope::Read, new DateTimeImmutable('-1 hour'));

        self::assertNull($service->verify(self::USER, $expired));
        $service->revoke(self::USER, $token->id);
        self::assertNull($service->verify(self::USER, $plain));
        self::assertCount(1, $service->forUser(self::USER));
    }
}
