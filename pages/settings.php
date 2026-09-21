<?php

declare(strict_types=1);

use KLXM\Dav\Backend\Html;
use KLXM\Dav\Dav;
use KLXM\Dav\I18n;

$addon = rex_addon::get('dav');
$csrf = rex_csrf_token::factory('dav_settings');

if ('post' === rex_request_method()) {
    $path = trim(rex_post('path', 'string'), '/ ');
    if (!$csrf->isValid()) {
        echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } elseif (1 !== preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $path)) {
        echo rex_view::error(I18n::e('settings_path_invalid'));
    } else {
        $addon->setConfig('path', $path);
        $addon->setConfig('browser', rex_post('browser', 'bool'));
        echo rex_view::success(I18n::e('settings_saved'));
    }
}

$providers = '';
foreach (Dav::providers() as $provider) {
    $providers .= '<li><strong>' . Html::e($provider->label()) . '</strong> <span class="label label-default">' . Html::e(strtoupper($provider->protocol())) . '</span> <code>' . Html::e($provider->key()) . '</code></li>';
}

$body = Html::field(I18n::t('settings_path'), '<input class="form-control" type="text" name="path" id="dav-path" value="' . Html::e(Dav::path()) . '" pattern="[A-Za-z0-9][A-Za-z0-9_-]*" required>', 'dav-path', I18n::t('settings_path_help', Dav::url()))
    . Html::field(I18n::t('settings_browser'), '<label><input type="hidden" name="browser" value="0"><input type="checkbox" name="browser" id="dav-browser" value="1"' . ($addon->getConfig('browser') ? ' checked' : '') . '> ' . I18n::e('settings_browser_label') . '</label>', 'dav-browser', I18n::t('settings_browser_help'));

echo '<form method="post" action="' . Html::e(rex_url::currentBackendPage([], false)) . '">' . $csrf->getHiddenField()
    . Html::section(I18n::e('settings_server'), $body, '<button class="btn btn-save" type="submit">' . I18n::e('save') . '</button>') . '</form>';

echo Html::section(I18n::e('settings_providers'), '' !== $providers ? '<ul class="dav-providers">' . $providers . '</ul>' : '<p class="dav-empty">' . I18n::e('settings_providers_empty') . '</p>', class: 'default');
