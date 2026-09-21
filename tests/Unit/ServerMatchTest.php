<?php

declare(strict_types=1);

namespace KLXM\Dav\Tests\Unit;

use KLXM\Dav\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServerMatchTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, ?string}>
     */
    public static function requests(): iterable
    {
        yield 'Wurzel' => ['/dav/', '/', '/dav/'];
        yield 'ohne Schrägstrich' => ['/dav', '/', '/dav/'];
        yield 'tiefer Pfad mit Query' => ['/dav/calendars/anna/schule/?x=1', '/', '/dav/'];
        yield 'ohne URL-Umschreibung' => ['/index.php/dav/principals/', '/', '/index.php/dav/'];
        yield 'Unterordner-Installation' => ['/cms/dav/calendars/', '/cms/', '/cms/dav/'];
        yield 'ähnlicher Artikelname' => ['/davos/', '/', null];
        yield 'anderer Pfad' => ['/termine/', '/', null];
    }

    #[Test]
    #[DataProvider('requests')]
    public function matchesOnlyTheServerPath(string $requestUri, string $basePath, ?string $expected): void
    {
        self::assertSame($expected, Server::match($requestUri, $basePath, 'dav'));
    }

    #[Test]
    public function recognisesWellKnownEntryPoints(): void
    {
        self::assertTrue(Server::isWellKnown('/.well-known/caldav'));
        self::assertTrue(Server::isWellKnown('/.well-known/carddav/'));
        self::assertFalse(Server::isWellKnown('/.well-known/security.txt'));
    }
}
