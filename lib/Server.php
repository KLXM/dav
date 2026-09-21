<?php

declare(strict_types=1);

namespace KLXM\Dav;

use rex;
use rex_addon;
use rex_sql;
use rex_user;
// Alias nötig: PHP unterscheidet bei Klassennamen keine Groß- und Kleinschreibung, "DAV" träfe sonst die eigene Klasse Dav.
use Sabre\DAV as SabreDav;
use Sabre\DAVACL;

/**
 * Der DAV-Server. Läuft im Frontend-Kontext unter <basis>/<pfad>/ und übernimmt die Anfrage,
 * bevor REDAXO einen Artikel sucht. Inhalte liefern die registrierten Provider.
 */
final class Server
{
    /** Bekannte Einstiegspunkte, über die Apps den Server selbst finden (RFC 6764). */
    private const array WELL_KNOWN = ['/.well-known/caldav', '/.well-known/carddav'];

    /**
     * Liefert die Basis-URI des Servers, falls die Anfrage ihm gilt.
     */
    public static function match(string $requestUri, string $basePath, string $davPath): ?string
    {
        $path = (string) parse_url($requestUri, PHP_URL_PATH);
        foreach ([$basePath . $davPath, $basePath . 'index.php/' . $davPath] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $prefix . '/';
            }
        }

        return null;
    }

    public static function isWellKnown(string $requestUri): bool
    {
        return in_array(rtrim((string) parse_url($requestUri, PHP_URL_PATH), '/'), self::WELL_KNOWN, true);
    }

    public static function run(string $baseUri): never
    {
        $context = new Context();
        $tokens = new TokenService();

        $auth = new SabreDav\Auth\Backend\BasicCallBack(static function (string $login, string $password) use ($context, $tokens): bool {
            $sql = rex_sql::factory()->setQuery('SELECT `id` FROM ' . rex::getTable('user') . ' WHERE `login` = ? AND `status` = 1', [$login]);
            $user = 1 === $sql->getRows() ? rex_user::get((int) $sql->getValue('id')) : null;
            if (null === $user || !Dav::canUse($user) || [] === Dav::providersFor($user)) {
                return false;
            }
            $token = $tokens->verify($user->getId(), $password);
            if (null === $token) {
                return false;
            }
            $context->user = $user;
            $context->token = $token;

            return true;
        });
        $auth->setRealm((string) rex::getServerName() ?: 'REDAXO');

        $principals = new PrincipalBackend($context);
        $server = new SabreDav\Server(new LazyRoot($context, $principals));
        $server->setBaseUri($baseUri);

        $acl = new DAVACL\Plugin();
        $acl->allowUnauthenticatedAccess = false;
        $acl->hideNodesFromListings = true;

        $server->addPlugin(new SabreDav\Auth\Plugin($auth));
        $server->addPlugin($acl);
        $server->addPlugin(new SabreDav\Sync\Plugin());

        // Plugins der Provider. Gleichartige Plugins, etwa das CalDAV-Plugin zweier Kalender-Addons, nur einmal.
        $loaded = [];
        foreach (Dav::providers() as $provider) {
            foreach ($provider->plugins($context) as $plugin) {
                if (!isset($loaded[$plugin::class])) {
                    $server->addPlugin($plugin);
                    $loaded[$plugin::class] = true;
                }
            }
        }

        if (rex::isDebugMode() || true === rex_addon::get('dav')->getConfig('browser')) {
            $server->addPlugin(new SabreDav\Browser\Plugin());
        }

        $server->start();
        exit;
    }
}
