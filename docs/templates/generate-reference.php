<?php

declare(strict_types=1);

/*
 * يُولّد `BLOCKS-REFERENCE.html` — مثالٌ من كلّ كتلةٍ يُخرجها النظام،
 * بأصنافها الخام بلا ورقة أنماط. للمعاينة المنسَّقة انظر
 * `generate-previews.php` — T-77.
 *
 * **يُعاد تشغيله كلّما تغيّرت الكتل**، وإلّا صار المرجعُ يكذب على من يصمّم
 * عليه قالباً. والملفّ يُرفق مع برومبتات `docs/templates/`، وكلود هناك لا
 * يرى الكود — فهذا الملفّ كلُّ ما يعرفه عن مفرداتنا.
 *
 *   php artisan tinker \
 *     --execute="require 'docs/templates/generate-reference.php';"
 */
use App\Support\Render\BodyBlocks;

/** @var array<string, mixed> $body مشتركةٌ مع `generate-previews.php` — انظر `fixtures.php`. */
$body = require __DIR__.'/fixtures.php';

$html = BodyBlocks::toHtml($body);

// تنسيقٌ للقراءة وحده — من يصمّم على هذا الملفّ يقرؤه بعينه.
$html = preg_replace('#(<(?:section|div|article|p|label|h2|h3|h4)\b)#', "\n$1", $html);
$html = preg_replace('#(</(?:section|div|article)>)#', "$1\n", $html);
$html = implode("\n", array_filter(array_map('rtrim', explode("\n", (string) $html)), fn ($l) => $l !== ''));

file_put_contents(__DIR__.'/BLOCKS-REFERENCE.html', $html."\n");

echo 'تمّ — '.strlen($html)." محرف\n";
