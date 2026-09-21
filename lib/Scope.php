<?php

declare(strict_types=1);

namespace KLXM\Dav;

enum Scope: string
{
    case Read = 'read';
    case ReadWrite = 'readwrite';

    public function label(): string
    {
        return I18n::t('scope_' . $this->value);
    }
}
