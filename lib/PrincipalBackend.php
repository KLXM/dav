<?php

declare(strict_types=1);

namespace KLXM\Dav;

use rex_user;
use Sabre\DAV\PropPatch;
use Sabre\DAVACL\PrincipalBackend\AbstractBackend;

/**
 * Bildet REDAXO-Benutzer als DAV-Principals ab. Sichtbar ist immer nur der angemeldete Benutzer.
 *
 * @internal
 */
final class PrincipalBackend extends AbstractBackend
{
    public const string PREFIX = 'principals';

    public function __construct(
        private readonly Context $context,
    ) {}

    public function getPrincipalsByPrefix($prefixPath): array
    {
        return (self::PREFIX === $prefixPath && null !== $this->context->user) ? [$this->describe($this->context->user)] : [];
    }

    public function getPrincipalByPath($path): ?array
    {
        $user = $this->context->user;

        return (null !== $user && $this->context->principalUri === $path) ? $this->describe($user) : null;
    }

    public function updatePrincipal($path, PropPatch $propPatch): void {}

    public function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof'): array
    {
        return [];
    }

    public function findByUri($uri, $principalPrefix): ?string
    {
        return null;
    }

    public function getGroupMemberSet($principal): array
    {
        return [];
    }

    public function getGroupMembership($principal): array
    {
        return [];
    }

    public function setGroupMemberSet($principal, array $members): void {}

    /**
     * @return array<string, string>
     */
    private function describe(rex_user $user): array
    {
        $principal = [
            'uri' => self::PREFIX . '/' . $user->getLogin(),
            '{DAV:}displayname' => '' !== (string) $user->getName() ? (string) $user->getName() : $user->getLogin(),
        ];
        if ('' !== (string) $user->getEmail()) {
            $principal['{http://sabredav.org/ns}email-address'] = (string) $user->getEmail();
        }

        return $principal;
    }
}
