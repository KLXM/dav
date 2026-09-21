<?php

declare(strict_types=1);

use KLXM\Dav\Dav;
use KLXM\Dav\Server;
use KLXM\Dav\TokenService;

// sabre/* bringt Funktionsdateien mit, die nur der Composer-Autoloader lädt.
require_once __DIR__ . '/vendor/autoload.php';

if (rex::isBackend()) {
    rex_perm::register(Dav::PERM);

    // Mit dem Benutzer verschwinden auch seine App-Passwörter.
    rex_extension::register('USER_DELETED', static function (rex_extension_point $ep): void {
        new TokenService()->revokeAll((int) $ep->getParam('id'));
    });

    if (null !== rex::getUser() && 'dav' === rex_be_controller::getCurrentPagePart(1)) {
        rex_view::addCssFile(rex_addon::get('dav')->getAssetsUrl('dav.css?v=' . rex_addon::get('dav')->getVersion()));
    }

    return;
}

if ('cli' === PHP_SAPI) {
    return;
}

// Der Server übernimmt die Anfrage, nachdem alle Addons geladen sind und ihre Provider registriert haben,
// aber bevor REDAXO einen Artikel sucht.
$requestUri = (string) rex_server('REQUEST_URI', 'string');
// rex_url::base() ist im Frontend relativ; der absolute Pfad der Installation steht in der Server-URL.
$basePath = rtrim((string) parse_url(rex::getServer(), PHP_URL_PATH), '/') . '/';

if (Server::isWellKnown($requestUri)) {
    rex_response::sendRedirect($basePath . Dav::path() . '/', rex_response::HTTP_MOVED_PERMANENTLY);
}

$baseUri = Server::match($requestUri, $basePath, Dav::path());
if (null !== $baseUri) {
    rex_extension::register('PACKAGES_INCLUDED', static function () use ($baseUri): void {
        Server::run($baseUri);
    }, rex_extension::EARLY);
}
