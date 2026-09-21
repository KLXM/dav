<?php

declare(strict_types=1);

namespace KLXM\Dav;

/**
 * Übersetzungen des Addons. Im laufenden REDAXO kommt der Text aus rex_i18n (und lässt sich dort
 * wie gewohnt überschreiben); ohne REDAXO, etwa in Unit-Tests, direkt aus der deutschen Sprachdatei.
 */
final class I18n
{
    private const string PREFIX = 'dav_';

    /** @var array<string, string>|null */
    private static ?array $fallback = null;

    public static function t(string $key, string|int|float ...$args): string
    {
        if (class_exists(\rex_i18n::class, false)) {
            return \rex_i18n::rawMsg(self::PREFIX . $key, ...array_map(strval(...), $args));
        }

        $message = self::fallback()[self::PREFIX . $key] ?? '[' . self::PREFIX . $key . ']';
        foreach (array_values($args) as $index => $value) {
            $message = str_replace('{' . $index . '}', (string) $value, $message);
        }

        return $message;
    }

    /** Wie t(), aber für die Ausgabe in HTML maskiert. */
    public static function e(string $key, string|int|float ...$args): string
    {
        return htmlspecialchars(self::t($key, ...$args), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @return array<string, string>
     */
    private static function fallback(): array
    {
        if (null === self::$fallback) {
            self::$fallback = [];
            foreach (file(dirname(__DIR__) . '/lang/de_de.lang', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if (!str_starts_with(ltrim($line), '#') && str_contains($line, '=')) {
                    [$key, $value] = explode('=', $line, 2);
                    self::$fallback[trim($key)] = trim($value);
                }
            }
        }

        return self::$fallback;
    }
}
