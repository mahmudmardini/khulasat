<?php

declare(strict_types=1);

/*
 * إقلاع الاختبارات — T-27، 7 أيلول 2026.
 *
 * **العلّة:** بيئةُ الحاوية تغلب `phpunit.xml` فيُقاس القبول على غير ما
 * كُتب. فحاوية المشروع تُصدّر `APP_ENV=local` و`QUEUE_CONNECTION=redis`،
 * وهذه تصل `$_SERVER`. ولارافل تقرأ الإعداد بترتيبٍ أوّلُه `$_SERVER`
 * ثمّ `$_ENV` ثمّ `putenv`، و`<env>` في phpunit تكتب في الأخيرين لا في
 * الأوّل — **حتى مع `force="true"`**. فالسابقُ يغلب المكتوب.
 *
 * **وأثرُ ذلك ثلاثون إخفاقاً كاذباً** لمن شغّل `php artisan test` داخل
 * الحاوية، وهو الوضع الطبيعي: `runningUnitTests()` تقرأ `APP_ENV` فتعود
 * كاذبةً، فيعمل حارس CSRF وتردّ كلّ دعوة POST بـ419؛ والطابور حقيقيّ
 * فتقف المهامّ في مكانها.
 *
 * **والأخطر أنّ الكذب يقع في الاتّجاهين:** طابورٌ حقيقيّ يُخفي إخفاقاً
 * كما يخترع إخفاقاً. فما يُقاس عليه القبول لا يُترك لمتغيّرٍ في الحاوية.
 *
 * **والعلاج:** تُنزع من `$_SERVER` المفاتيحُ التي يُصرّح بها `phpunit.xml`
 * وحدَها، فيسقط الحجاب وتصل قيمةُ الملفّ. وتُقرأ من الملفّ نفسه لا
 * تُنسَخ هنا، فلا تفترق النسختان بعد أشهر.
 */

require __DIR__.'/../vendor/autoload.php';

$configuration = __DIR__.'/../phpunit.xml';

if (is_file($configuration)) {
    $document = new DOMDocument;
    $document->load($configuration);

    foreach ((new DOMXPath($document))->query('//php/env') as $declaration) {
        /** @var DOMElement $declaration */
        $name = $declaration->getAttribute('name');

        if ($name !== '') {
            unset($_SERVER[$name]);
        }
    }
}
