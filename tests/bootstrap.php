<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$boot = getenv('DAV_REDAXO_BOOT');
if (is_string($boot) && '' !== $boot) {
    require $boot;
}
