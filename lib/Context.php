<?php

declare(strict_types=1);

namespace KLXM\Dav;

use rex_user;

/**
 * Wer ist angemeldet und mit welchem App-Passwort. Die Authentifizierung füllt den Kontext,
 * bevor ein Provider Daten liefert.
 */
final class Context
{
    public ?rex_user $user = null;
    public ?Token $token = null;

    /** Erlaubt das verwendete App-Passwort Schreibzugriffe? */
    public bool $canWrite {
        get => Scope::ReadWrite === $this->token?->scope;
    }

    /** Principal-Adresse des angemeldeten Benutzers, etwa "principals/anna". */
    public ?string $principalUri {
        get => null === $this->user ? null : PrincipalBackend::PREFIX . '/' . $this->user->getLogin();
    }
}
