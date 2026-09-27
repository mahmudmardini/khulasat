<?php

declare(strict_types=1);

/*
 * يُولّد `preview-<template>.html` — واحدةٌ لكلّ قالبٍ في `SummaryTemplate`،
 * بورقة أنماطه الفعلية (`styleView()` + `blocksView()`) مطبَّقةً على نفس
 * الكتل العشرين التي في `BLOCKS-REFERENCE.html`. **ذاك للتصميم في محادثةٍ
 * لا ترى CSS، وهذا للمعاينة البصرية المباشرة** — T-77.
 *
 * صفحاتٌ للعرض المحلّي وحده: لا تُخدَّم من التطبيق ولا تُدرَج في
 * `routes/`، ولا تمسّ `khulasah.skill` ولا مسار العرض الحقيقي بحرف.
 *
 * **يُعاد تشغيله كلّما تغيّرت الكتل أو ورقةُ قالب**، وإلّا صارت المعاينةُ
 * تكذب.
 *
 *   php artisan tinker \
 *     --execute="require 'docs/templates/generate-previews.php';"
 */
use App\Enums\SummaryTemplate;
use App\Support\Render\BodyBlocks;
use App\Support\Render\Palette;

$body = require __DIR__.'/fixtures.php';
$bodyHtml = BodyBlocks::toHtml($body);
$palette = Palette::find(Palette::DEFAULT);

foreach (SummaryTemplate::cases() as $template) {
    $styleCss = view($template->styleView())->render();
    $blocksCss = view($template->blocksView())->render();

    $html = <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>معاينة الكتل — {$template->value}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Amiri+Quran&family=Amiri:ital,wght@0,400;0,700;1,400&family=Aref+Ruqaa:wght@400;700&family=IBM+Plex+Sans+Arabic:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
body{background:#ddd;margin:0;padding:24px 0}
.page{max-width:640px;margin:0 auto;background:var(--paper,#fff);padding:28px;box-shadow:0 2px 18px rgba(0,0,0,.15)}
{$styleCss}
{$blocksCss}
:root{
{$palette->css()}
}
</style>
</head>
<body>
<div class="page">
<h1 style="font-size:13px;letter-spacing:.08em;color:#888;margin:0 0 18px">قالب: {$template->value}</h1>
{$bodyHtml}
</div>
</body>
</html>
HTML;

    file_put_contents(__DIR__.'/preview-'.$template->value.'.html', $html);
}

echo 'تمّ — '.count(SummaryTemplate::cases())." صفحات في docs/templates/\n";
