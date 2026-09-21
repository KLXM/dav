# dav

DAV-Server für REDAXO. Das Addon stellt den gemeinsamen Unterbau bereit, über den andere Addons ihre Inhalte an Kalender-, Kontakt- und Datei-Apps geben:

- ein Server unter einer Adresse, etwa `https://example.org/dav/`
- Anmeldung mit dem REDAXO-Login und **App-Passwörtern** statt des REDAXO-Passworts
- Weiterleitung von `/.well-known/caldav` und `/.well-known/carddav`, damit Apps den Server selbst finden
- eine Seite *Geräte verbinden* für jeden Benutzer und eine kleine Schnittstelle für Addons

Das Addon selbst enthält keine Kalender, Kontakte oder Dateien. Die liefern **Provider**:

| Addon | Protokoll | Inhalt |
|---|---|---|
| [scheduler](https://github.com/KLXM/scheduler) | CalDAV | Kalender und Termine, lesen und schreiben |
| [contacts](https://github.com/KLXM/contacts) | CardDAV | Adressbücher, Kontakte, Listen und Fotos, lesen und schreiben |

Technische Grundlage ist [sabre/dav](https://sabre.io/dav/).

## Voraussetzungen

- REDAXO ab 5.18, PHP ab 8.4
- HTTPS. DAV überträgt das App-Passwort bei jeder Anfrage, und Apple-Geräte verweigern unverschlüsselte Verbindungen.
- Anfragen unter `/dav/` müssen bei REDAXO ankommen. Mit yrewrite ist das der Fall. Ohne URL-Umschreibung funktioniert `https://example.org/index.php/dav/`.

## Installation aus dem Repository

Das Repository enthält den Ordner `vendor` nicht. Nach dem Klonen nach `redaxo/src/addons/dav` einmal im Addon-Ordner ausführen:

```bash
composer install --no-dev
```

## Einrichten

1. Addon installieren, dazu mindestens einen Provider wie scheduler.
2. Den Rollen das Recht **DAV: Geräte und Apps verbinden** (`dav[]`) geben. Was jemand sieht, regeln zusätzlich die Rechte des jeweiligen Providers, bei scheduler etwa die zugewiesenen Kalender.
3. Jeder Benutzer erzeugt unter **DAV › Geräte verbinden** ein App-Passwort und trägt in seiner App ein:

| Angabe | Wert |
|---|---|
| Server | `https://example.org/dav/` |
| Benutzername | der REDAXO-Login |
| Passwort | das App-Passwort |

## App-Passwörter

- Der Klartext wird genau einmal angezeigt, gespeichert ist nur ein Hash.
- Je Passwort gilt *nur lesen* oder *lesen und schreiben*. Schreibende Provider müssen das beachten, siehe unten.
- Empfehlung: ein Passwort je Gerät. Geht ein Gerät verloren, widerruft man nur dieses.
- Die Liste zeigt, wann ein Passwort zuletzt benutzt wurde.
- Wird ein Benutzer gelöscht, verschwinden seine App-Passwörter. Deaktivierte Benutzer können sich nicht anmelden.

## Einstellungen

| Einstellung | Wirkung |
|---|---|
| Pfad | Pfadbestandteil des Servers, Standard `dav`. Bei einer Änderung brauchen eingerichtete Geräte die neue Adresse. Kein Artikel sollte denselben Pfad haben. |
| Diagnose | zeigt angemeldeten Benutzern unter der Server-Adresse eine Navigationsansicht im Browser. Im Debug-Modus von REDAXO immer an. |

## Fehlersuche

| Symptom | Ursache und Abhilfe |
|---|---|
| 404 unter `/dav/` | Die Anfrage kommt nicht bei REDAXO an, oder ein Artikel belegt den Pfad. |
| 401 trotz richtigem Passwort | Ist der Benutzer aktiv, hat er `dav[]` und mindestens ein Provider-Recht? Manche Server entfernen den Authorization-Header. Abhilfe in der `.htaccess`: `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1` |
| 403 beim Speichern | Das App-Passwort erlaubt nur Lesen, oder dem Benutzer fehlt beim Provider das Schreibrecht. |
| Apple lehnt ab | kein HTTPS oder ungültiges Zertifikat |
| Fehler 500 nach Installation eines Providers | Zwei Addons bringen unterschiedliche Hauptversionen der sabre-Bibliotheken mit, siehe „Versionen“ unten. |

## Für Entwickler: einen Provider schreiben

Ein Provider liefert Knoten für den DAV-Baum und die nötigen sabre-Plugins. Anmeldung, Principals, ACL, Sync und Server kommen von diesem Addon.

```php
use KLXM\Dav\Context;
use KLXM\Dav\Provider;
use Sabre\CardDAV;
use Sabre\DAVACL\PrincipalBackend\BackendInterface;

final class ContactsProvider implements Provider
{
    public function key(): string { return 'contacts'; }
    public function label(): string { return 'Kontakte'; }
    public function protocol(): string { return 'carddav'; }

    public function isAvailableFor(rex_user $user): bool
    {
        return $user->isAdmin() || $user->hasPerm('contacts[]');
    }

    public function describe(rex_user $user): array
    {
        return ['Vereinsmitglieder'];                      // erscheint auf "Geräte verbinden"
    }

    public function nodes(Context $context, BackendInterface $principals): array
    {
        return [new CardDAV\AddressBookRoot($principals, new MyCardBackend($context))];
    }

    public function plugins(Context $context): array
    {
        return [new CardDAV\Plugin()];
    }
}
```

Registrieren in der `boot.php` des eigenen Addons:

```php
if (rex_addon::get('dav')->isAvailable()) {
    KLXM\Dav\Dav::register(new ContactsProvider());
}
```

### Schnittstelle

| Klasse | Inhalt |
|---|---|
| `Dav::register(Provider)` | Provider anmelden |
| `Dav::providers()`, `Dav::providersFor(rex_user)` | alle Provider, oder die für einen Benutzer verfügbaren |
| `Dav::canUse(rex_user): bool` | hat `dav[]` oder ist Administrator |
| `Dav::url()`, `Dav::path()`, `Dav::principalUrl(rex_user)` | Adressen |
| `Context` | `user` (?rex_user), `token` (?Token), `canWrite` (bool), `principalUri` (?string). Wird bei der Anmeldung gefüllt. |
| `Token` | `id`, `userId`, `label`, `scope`, `createdAt`, `lastUsedAt`, `expiresAt` |
| `Scope` | `Read`, `ReadWrite` |
| `TokenService` | `create()`, `forUser()`, `verify()`, `revoke()`, `revokeAll()` |
| `PrincipalBackend::PREFIX` | `principals`; der Principal eines Benutzers ist `principals/<login>` |

### Verbindungs-Assistent einbetten

Ein Provider kann den Assistenten zum Verbinden eines Geräts auf seiner eigenen Seite zeigen. So bleiben Redakteure im gewohnten Addon:

```php
if (rex_addon::get('dav')->isAvailable()) {
    echo new KLXM\Dav\Backend\ConnectPanel('caldav')->render();
}
```

Der Baustein verarbeitet Anlegen und Widerrufen selbst, prüft das CSRF-Token, lädt sein CSS und JavaScript und zeigt die Zugangsdaten mit Kopierknöpfen sowie eine Anleitung je App. Das Argument ist das Protokoll (`caldav`, `carddav`, `webdav`) und bestimmt Adresse und Anleitung.

### Sprachen

Alle Texte stehen in `lang/de_de.lang` und `lang/en_gb.lang` mit dem Präfix `dav_`. Im Code: `KLXM\Dav\I18n::t('key')`, für HTML `I18n::e('key')`. Provider liefern ihr `label()` selbst übersetzt.

### Regeln für Provider

- **Knoten entstehen nach der Anmeldung.** `nodes()` wird erst beim ersten Zugriff auf den Baum aufgerufen, `$context->user` ist dann gesetzt.
- **Schreibzugriffe prüfen.** Vor jeder Änderung `$context->canWrite` abfragen und sonst `Sabre\DAV\Exception\Forbidden` werfen. Das App-Passwort begrenzt, das Rechtesystem des eigenen Addons entscheidet zusätzlich.
- **Nur eigene Daten zeigen.** Backends bekommen die Principal-Adresse übergeben. Für fremde Principals nichts liefern: `if ($principalUri !== $context->principalUri) return [];`
- **Plugins** gleicher Klasse lädt der Server nur einmal, auch wenn mehrere Provider sie nennen.
- **Eindeutige Wurzelnamen.** sabre verwendet `calendars`, `addressbooks` und `principals`. Zwei Provider mit demselben Wurzelknoten vertragen sich nicht.

### Versionen

dav ist die Zentrale für die sabre-Bibliotheken: sabre/dav, sabre/vobject, sabre/xml und sabre/uri liegen nur hier. Ein Provider setzt `dav` in seiner `package.yml` unter `requires: packages` voraus und bringt diese Pakete **nicht** selbst mit. Alle Addons laufen im selben PHP-Prozess, jede Klasse wird nur einmal geladen. Zwei Kopien in verschiedenen Versionen führen zu schwer auffindbaren Fehlern.

dav startet durch die Abhängigkeit vor dem Provider, sein Autoloader steht also bereit. In Unit-Tests ohne REDAXO lädt der Provider `../dav/vendor/autoload.php`.

## Tests

```bash
composer install
vendor/bin/phpunit --testsuite unit
DAV_REDAXO_BOOT=/pfad/boot.php vendor/bin/phpunit
```

## Lizenz

MIT, KLXM Crossmedia GmbH.
