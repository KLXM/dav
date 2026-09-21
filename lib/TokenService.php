<?php

declare(strict_types=1);

namespace KLXM\Dav;

use DateTimeImmutable;
use DateTimeZone;
use rex;
use rex_sql;

/**
 * App-Passwörter für DAV-Clients. Das REDAXO-Passwort wird nie an Kalender- oder Kontakt-Apps gegeben;
 * gespeichert wird nur ein Hash, der Klartext ist genau einmal bei der Erzeugung sichtbar.
 */
final class TokenService
{
    public const string TABLE = 'dav_token';

    /**
     * @return array{Token, string} Datensatz und Klartext-Passwort
     */
    public function create(int $userId, string $label, Scope $scope, ?DateTimeImmutable $expiresAt = null): array
    {
        // Gruppiert wie App-Passwörter üblich, ohne verwechselbare Zeichen.
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $plain = implode('-', array_map(
            static fn (): string => implode('', array_map(static fn (): string => $alphabet[random_int(0, strlen($alphabet) - 1)], range(1, 4))),
            range(1, 4),
        ));

        $sql = rex_sql::factory()->setTable(rex::getTable(self::TABLE));
        $sql->setValue('user_id', $userId);
        $sql->setValue('label', '' !== trim($label) ? mb_substr(trim($label), 0, 191) : I18n::t('label_default'));
        $sql->setValue('scope', $scope->value);
        $sql->setValue('token_hash', password_hash($plain, PASSWORD_DEFAULT));
        $sql->setValue('created_at', $this->now()->format('Y-m-d H:i:s'));
        $sql->setValue('expires_at', $expiresAt?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
        $sql->insert();

        return [$this->find((int) $sql->getLastId()) ?? throw new \RuntimeException('The app password could not be stored.'), $plain];
    }

    /**
     * @return list<Token>
     */
    public function forUser(int $userId): array
    {
        return array_map($this->hydrate(...), rex_sql::factory()->getArray('SELECT * FROM ' . rex::getTable(self::TABLE) . ' WHERE user_id = ? ORDER BY id DESC', [$userId]));
    }

    public function find(int $id): ?Token
    {
        $row = rex_sql::factory()->getArray('SELECT * FROM ' . rex::getTable(self::TABLE) . ' WHERE id = ?', [$id])[0] ?? null;

        return null === $row ? null : $this->hydrate($row);
    }

    public function revoke(int $userId, int $tokenId): void
    {
        rex_sql::factory()->setQuery('DELETE FROM ' . rex::getTable(self::TABLE) . ' WHERE user_id = ? AND id = ?', [$userId, $tokenId]);
    }

    /** Entfernt alle App-Passwörter eines Benutzers, etwa wenn er gelöscht wird. */
    public function revokeAll(int $userId): void
    {
        rex_sql::factory()->setQuery('DELETE FROM ' . rex::getTable(self::TABLE) . ' WHERE user_id = ?', [$userId]);
    }

    public function verify(int $userId, string $password): ?Token
    {
        $now = $this->now();
        $password = strtolower(trim($password));
        if ('' === $password) {
            return null;
        }

        foreach (rex_sql::factory()->getArray('SELECT * FROM ' . rex::getTable(self::TABLE) . ' WHERE user_id = ?', [$userId]) as $row) {
            $token = $this->hydrate($row);
            if ($token->isExpired($now) || !password_verify($password, (string) $row['token_hash'])) {
                continue;
            }
            // Nur gelegentlich schreiben: Clients fragen im Minutentakt an.
            if (null === $token->lastUsedAt || $token->lastUsedAt < $now->modify('-10 minutes')) {
                rex_sql::factory()->setQuery('UPDATE ' . rex::getTable(self::TABLE) . ' SET last_used_at = ? WHERE id = ?', [$now->format('Y-m-d H:i:s'), $token->id]);
            }

            return $token;
        }

        return null;
    }

    /**
     * @param array<string, scalar|null> $row
     */
    private function hydrate(array $row): Token
    {
        $date = static fn (mixed $value): ?DateTimeImmutable => null === $value || '' === $value ? null : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));

        return new Token(
            (int) $row['id'],
            (int) $row['user_id'],
            (string) $row['label'],
            Scope::tryFrom((string) $row['scope']) ?? Scope::Read,
            $date($row['created_at']),
            $date($row['last_used_at']),
            $date($row['expires_at']),
        );
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
