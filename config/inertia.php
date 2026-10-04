<?php

declare(strict_types=1);

/*
 * T-152 — مسار الصفحات وحده.
 *
 * inertia-laravel v3 يبحث افتراضاً في `resources/js/pages` بحرفٍ صغير،
 * والصفحات في `resources/js/Pages` (انظر `import.meta.glob` في app.tsx).
 * وmacOS لا يفرّق بين الحرفين فيمرّ عليه الخطأ، وLinux يفرّق فيسقط
 * فحصُ وجود الصفحة في الاختبارات.
 *
 * ودمجُ إعداد الحزمة سطحيّ عند المفتاح الأعلى، فيُكتب `pages` كاملاً هنا
 * وتبقى سائر المفاتيح من إعداد الحزمة.
 */
return [

    'pages' => [

        'ensure_pages_exist' => false,

        'paths' => [
            resource_path('js/Pages'),
        ],

        'extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],

    ],

];
