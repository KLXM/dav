<?php

declare(strict_types=1);

namespace KLXM\Dav;

use rex_user;
use Sabre\DAV\INode;
use Sabre\DAV\ServerPlugin;
use Sabre\DAVACL\PrincipalBackend\BackendInterface as PrincipalBackendInterface;

/**
 * Ein Addon, das Inhalte über DAV anbietet: Kalender (CalDAV), Adressbücher (CardDAV) oder Dateien (WebDAV).
 *
 * Registrieren in der boot.php des Addons:
 *
 *     if (rex_addon::get('dav')->isAvailable()) {
 *         \KLXM\Dav\Dav::register(new MyProvider());
 *     }
 */
interface Provider
{
    /** Eindeutiger Schlüssel, etwa der Addon-Name. */
    public function key(): string;

    /** Bezeichnung für die Seite "Geräte verbinden", etwa "Kalender (scheduler)". */
    public function label(): string;

    /** Protokoll für Anleitung und Weiterleitung: "caldav", "carddav" oder "webdav". */
    public function protocol(): string;

    /** Darf dieser Benutzer das Angebot überhaupt nutzen? */
    public function isAvailableFor(rex_user $user): bool;

    /**
     * Was der Benutzer über diesen Provider sieht, zur Anzeige im Backend: etwa die Namen seiner Kalender.
     *
     * @return list<string>
     */
    public function describe(rex_user $user): array;

    /**
     * Wurzelknoten für den DAV-Baum, etwa eine Sabre\CalDAV\CalendarRoot.
     *
     * @return list<INode>
     */
    public function nodes(Context $context, PrincipalBackendInterface $principals): array;

    /**
     * Zusätzliche Server-Plugins, etwa Sabre\CalDAV\Plugin. Gleichartige Plugins mehrerer Provider
     * werden nur einmal geladen.
     *
     * @return list<ServerPlugin>
     */
    public function plugins(Context $context): array;
}
