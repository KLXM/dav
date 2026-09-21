<?php

declare(strict_types=1);

use KLXM\Dav\Backend\ConnectPanel;
use KLXM\Dav\Backend\Html;
use KLXM\Dav\Dav;
use KLXM\Dav\I18n;

$user = rex::requireUser();
$providers = Dav::providersFor($user);

if ([] === Dav::providers()) {
    echo rex_view::info(I18n::t('no_providers'));
} elseif ([] === $providers) {
    echo rex_view::info(I18n::e('nothing_available'));
}

// Was erreicht dieser Benutzer? Die Liste kommt von den Provider-Addons.
$offers = '';
$protocols = [];
foreach ($providers as $provider) {
    $items = $provider->describe($user);
    $protocols[] = $provider->protocol();
    $offers .= '<tr><td><strong>' . Html::e($provider->label()) . '</strong></td><td><span class="label label-default">' . Html::e(strtoupper($provider->protocol())) . '</span></td><td>'
        . ([] !== $items ? Html::e(implode(', ', $items)) : '<span class="text-muted">' . I18n::e('offer_nothing') . '</span>') . '</td></tr>';
}
if ('' !== $offers) {
    echo Html::section(
        I18n::e('offers_title'),
        '<p class="dav-intro">' . I18n::e('offers_intro') . '</p><table class="table dav-offers"><thead><tr><th>' . I18n::e('offer') . '</th><th>' . I18n::e('protocol') . '</th><th>' . I18n::e('offer_items') . '</th></tr></thead><tbody>' . $offers . '</tbody></table>',
        class: 'default',
    );
}

// Die Anleitung richtet sich nach dem ersten verfügbaren Angebot, meist dem Kalender.
echo new ConnectPanel($protocols[0] ?? 'caldav')->render();
