<?php

declare(strict_types=1);

namespace KLXM\Dav\Backend;

use rex_fragment;

/**
 * @internal
 */
final class Html
{
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function section(string $title, string $body, string $buttons = '', string $class = 'edit'): string
    {
        $fragment = new rex_fragment();
        $fragment->setVar('class', $class, false);
        $fragment->setVar('title', $title, false);
        $fragment->setVar('body', $body, false);
        if ('' !== $buttons) {
            $fragment->setVar('buttons', $buttons, false);
        }

        return $fragment->parse('core/page/section.php');
    }

    public static function field(string $label, string $input, string $for, ?string $help = null): string
    {
        return sprintf(
            '<div class="form-group dav-field"><label class="control-label" for="%s">%s</label><div>%s%s</div></div>',
            self::e($for),
            self::e($label),
            $input,
            null !== $help ? '<p class="help-block">' . self::e($help) . '</p>' : '',
        );
    }
}
