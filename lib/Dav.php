<?php

declare(strict_types=1);

namespace KLXM\Dav;

use rex;
use rex_addon;
use rex_user;

/**
 * Einstiegspunkt des DAV-Addons: Provider registrieren, Adressen erfragen.
 */
final class Dav
{
    public const string PERM = 'dav[]';

    /** @var array<string, Provider> */
    private static array $providers = [];

    public static function register(Provider $provider): void
    {
        self::$providers[$provider->key()] = $provider;
    }

    /**
     * @return array<string, Provider>
     */
    public static function providers(): array
    {
        return self::$providers;
    }

    /**
     * @return array<string, Provider> Provider, die dieser Benutzer nutzen darf
     */
    public static function providersFor(rex_user $user): array
    {
        return array_filter(self::$providers, static fn (Provider $provider): bool => $provider->isAvailableFor($user));
    }

    /** Darf der Benutzer sich überhaupt per DAV anmelden? */
    public static function canUse(rex_user $user): bool
    {
        return $user->isAdmin() || $user->hasPerm(self::PERM);
    }

    /** Pfadbestandteil des Servers, etwa "dav". */
    public static function path(): string
    {
        $path = trim((string) rex_addon::get('dav')->getConfig('path', 'dav'), '/');

        return 1 === preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $path) ? $path : 'dav';
    }

    /** Vollständige Server-Adresse für die Einrichtung in Apps. */
    public static function url(): string
    {
        return rtrim(rex::getServer(), '/') . '/' . self::path() . '/';
    }

    /** Adresse des eigenen Principals; manche Apps fragen danach. */
    public static function principalUrl(rex_user $user): string
    {
        return self::url() . PrincipalBackend::PREFIX . '/' . rawurlencode($user->getLogin()) . '/';
    }
}
