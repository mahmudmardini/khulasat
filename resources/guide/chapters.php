<?php

declare(strict_types=1);

/*
 * فصولُ كلّ دليل بترتيبها — T-215. والفصلُ ملفٌّ في `resources/guide/<لغة>/`،
 * يُكتب مرّةً ويقرؤه كلُّ دورٍ ذُكر فيه. وما يخصّ دوراً داخل فصلٍ مشترك
 * يُكتب بين `::: owner` و`:::`.
 */

return [
    'owner' => [
        'welcome', 'account', 'panel', 'summaries', 'create', 'progress', 'review',
        'preview', 'published', 'carousel', 'quiz', 'reports',
        'brand', 'billing', 'team', 'verify', 'limits', 'glossary',
    ],

    'editor' => [
        'welcome', 'account', 'panel', 'summaries', 'create', 'progress', 'review',
        'preview', 'published', 'carousel', 'quiz', 'reports',
        'permissions', 'billing', 'verify', 'limits', 'glossary',
    ],

    'admin' => [
        'admin-welcome', 'admin-access', 'admin-dashboard', 'admin-tenants', 'admin-tenant',
        'admin-jobs', 'admin-takedowns', 'admin-invites', 'admin-models', 'admin-costs',
        'admin-audit', 'verify', 'admin-limits', 'glossary',
    ],
];
