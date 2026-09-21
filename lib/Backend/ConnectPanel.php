<?php

declare(strict_types=1);

namespace KLXM\Dav\Backend;

use KLXM\Dav\Dav;
use KLXM\Dav\I18n;
use KLXM\Dav\Scope;
use KLXM\Dav\Token;
use KLXM\Dav\TokenService;
use rex;
use rex_addon;
use rex_csrf_token;
use rex_user;
use rex_view;

/**
 * Der Assistent "Gerät verbinden" als wiederverwendbarer Baustein. Das dav-Addon nutzt ihn auf seiner
 * eigenen Seite; Provider-Addons binden ihn auf ihren Seiten ein, damit niemand das Addon wechseln muss:
 *
 *     echo (new ConnectPanel('caldav'))->render();
 *
 * Der Baustein verarbeitet seine Formulare selbst (Post/Redirect ist nicht nötig, weil das Passwort
 * genau einmal angezeigt werden muss) und führt in drei Schritten: benennen, kopieren, eintragen.
 */
final class ConnectPanel
{
    private const string FIELD = 'dav_panel';

    private readonly rex_user $user;
    private readonly TokenService $tokens;
    private readonly rex_csrf_token $csrf;

    /**
     * @param string $protocol "caldav", "carddav" oder "webdav": bestimmt die Anleitung in Schritt 3
     */
    public function __construct(
        private readonly string $protocol = 'caldav',
    ) {
        $this->user = rex::requireUser();
        $this->tokens = new TokenService();
        $this->csrf = rex_csrf_token::factory('dav_connect_panel');
    }

    public function render(): string
    {
        if (!Dav::canUse($this->user)) {
            return rex_view::warning(I18n::e('missing_perm'));
        }

        $html = $this->assets();
        $created = null;
        $label = '';

        $action = rex_post(self::FIELD, 'array', []);
        if ([] !== $action) {
            if (!$this->csrf->isValid()) {
                $html .= rex_view::error(\rex_i18n::msg('csrf_token_invalid'));
            } elseif (isset($action['revoke'])) {
                $this->tokens->revoke($this->user->getId(), (int) $action['revoke']);
                $html .= rex_view::success(I18n::e('revoked'));
            } elseif (isset($action['create'])) {
                $label = '' !== trim((string) ($action['label'] ?? '')) ? trim((string) $action['label']) : I18n::t('label_default');
                [, $created] = $this->tokens->create($this->user->getId(), $label, Scope::tryFrom((string) ($action['scope'] ?? '')) ?? Scope::Read);
            }
        }

        if (!str_starts_with(Dav::url(), 'https://')) {
            $html .= rex_view::warning(I18n::e('no_https'));
        }

        $html .= null === $created ? $this->stepOne() : $this->stepsTwoAndThree($label, $created);

        return $html . $this->tokenList();
    }

    private function stepOne(): string
    {
        $scopes = '';
        foreach (Scope::cases() as $index => $scope) {
            $scopes .= sprintf(
                '<label class="dav-choice"><input type="radio" name="%s[scope]" value="%s"%s> <span><strong>%s</strong><br><small>%s</small></span></label>',
                self::FIELD,
                $scope->value,
                0 === $index ? ' checked' : '',
                I18n::e('scope_' . $scope->value),
                I18n::e('scope_' . $scope->value . '_help'),
            );
        }

        $body = '<p class="dav-intro">' . I18n::e('step1_intro') . '</p>'
            . Html::field(I18n::t('label'), sprintf('<input class="form-control" type="text" name="%s[label]" id="dav-label" maxlength="191" placeholder="%s">', self::FIELD, I18n::e('label_placeholder')), 'dav-label', I18n::t('label_help'))
            . Html::field(I18n::t('scope'), '<div class="dav-choices" role="radiogroup">' . $scopes . '</div>', 'dav-scope');

        return $this->form(Html::section(
            $this->stepTitle(1, 'step1_title'),
            $body,
            sprintf('<button class="btn btn-save" type="submit" name="%s[create]" value="1"><i class="rex-icon fa-key"></i> %s</button>', self::FIELD, I18n::e('create')),
        ));
    }

    private function stepsTwoAndThree(string $label, string $password): string
    {
        $copy = static fn (string $value, string $id): string => sprintf(
            '<span class="dav-copy"><code id="%1$s">%2$s</code> <button type="button" class="btn btn-default btn-xs" data-dav-copy="#%1$s" data-dav-copied="%4$s"><i class="rex-icon fa-copy"></i> %3$s</button></span>',
            $id,
            Html::e($value),
            I18n::e('copy'),
            I18n::e('copied'),
        );

        $two = '<p class="dav-intro">' . I18n::t('step2_intro', Html::e($label)) . '</p>'
            . '<p class="dav-secret-row">' . str_replace('<code ', '<code class="dav-secret" ', $copy($password, 'dav-new-password')) . '</p>';

        $howto = '';
        foreach (['apple', 'thunderbird', 'android', 'outlook'] as $index => $app) {
            $key = in_array($app, ['android', 'outlook'], true) ? 'howto_' . $app . '_all' : 'howto_' . $app . '_' . $this->protocol;
            $howto .= sprintf('<details class="dav-howto"%s><summary>%s</summary><p>%s</p></details>', 0 === $index ? ' open' : '', I18n::e('howto_' . $app), I18n::e($key));
        }

        $three = '<p class="dav-intro">' . I18n::e('step3_intro') . '</p>'
            . '<dl class="dav-facts"><dt>' . I18n::e('server') . '</dt><dd>' . $copy(Dav::url(), 'dav-server-url') . '</dd>'
            . '<dt>' . I18n::e('username') . '</dt><dd>' . $copy($this->user->getLogin(), 'dav-login') . '</dd>'
            . '<dt>' . I18n::e('password') . '</dt><dd>' . I18n::e('password_hint') . '</dd></dl>' . $howto;

        return rex_view::success(I18n::e('step2_title'))
            . Html::section($this->stepTitle(2, 'step2_title'), $two, class: 'info')
            . Html::section($this->stepTitle(3, 'step3_title'), $three, '<a class="btn btn-save" href="' . Html::e(\rex_url::currentBackendPage([], false)) . '">' . I18n::e('done') . '</a>');
    }

    private function tokenList(): string
    {
        $dateFormat = new \IntlDateFormatter(\rex_i18n::getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);
        $rows = implode('', array_map(fn (Token $token): string => sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td class="rex-table-action"><button class="btn btn-delete btn-xs" type="submit" name="%s[revoke]" value="%d" data-dav-confirm="%s">%s</button></td></tr>',
            Html::e($token->label),
            I18n::e('scope_' . $token->scope->value),
            Html::e(null !== $token->createdAt ? (string) $dateFormat->format($token->createdAt) : ''),
            null !== $token->lastUsedAt ? Html::e((string) $dateFormat->format($token->lastUsedAt)) : I18n::e('token_never'),
            self::FIELD,
            $token->id,
            I18n::e('revoke_confirm'),
            I18n::e('revoke'),
        ), $this->tokens->forUser($this->user->getId())));

        $table = '' !== $rows
            ? '<table class="table table-striped"><thead><tr><th>' . I18n::e('token_label') . '</th><th>' . I18n::e('token_scope') . '</th><th>' . I18n::e('token_created') . '</th><th>' . I18n::e('token_last_used') . '</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table>'
            : '<p class="dav-empty">' . I18n::e('tokens_empty') . '</p>';

        return $this->form(Html::section(I18n::e('tokens_title'), $table, class: 'default'));
    }

    private function form(string $content): string
    {
        return '<form method="post" action="' . Html::e(\rex_url::currentBackendPage([], false)) . '">' . $this->csrf->getHiddenField() . $content . '</form>';
    }

    private function stepTitle(int $step, string $key): string
    {
        return '<span class="dav-step-badge">' . I18n::e('step', $step, 3) . '</span> ' . I18n::e($key);
    }

    /**
     * Styles und das kleine Skript für "Kopieren" und die Rückfrage. Provider-Seiten laden sie so mit.
     */
    private function assets(): string
    {
        static $sent = false;
        if ($sent) {
            return '';
        }
        $sent = true;
        $addon = rex_addon::get('dav');
        $version = (string) $addon->getVersion();

        return '<link rel="stylesheet" href="' . Html::e($addon->getAssetsUrl('dav.css?v=' . $version)) . '">'
            . '<script type="module" src="' . Html::e($addon->getAssetsUrl('dav.js?v=' . $version)) . '"></script>';
    }
}
