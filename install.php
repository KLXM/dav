<?php

declare(strict_types=1);

use KLXM\Dav\TokenService;

require_once __DIR__ . '/vendor/autoload.php';

rex_sql_table::get(rex::getTable(TokenService::TABLE))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('user_id', 'int(11)'))
    ->ensureColumn(new rex_sql_column('label', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('scope', 'varchar(16)', false, 'read'))
    ->ensureColumn(new rex_sql_column('token_hash', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime', true))
    ->ensureColumn(new rex_sql_column('last_used_at', 'datetime', true))
    ->ensureColumn(new rex_sql_column('expires_at', 'datetime', true))
    ->ensureIndex(new rex_sql_index('user', ['user_id']))
    ->ensure();
