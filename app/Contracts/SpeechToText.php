<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Exceptions\TranscriptFailed;

/**
 * A speech-to-text service — المواصفة §5-أ-4.
 *
 * **خلف عقدٍ لا نداءً مباشراً** — CLAUDE.md §1: الخدمات الخارجية خلف واجهة
 * في `app/Contracts`. والافتراضي في التطوير والاختبار تنفيذٌ وهميّ، ولا
 * يُبدَّل إلا بإعدادٍ صريح — CLAUDE.md §2 القاعدة السابعة.
 *
 * @see khulasah-build-spec.md §5-أ-4
 */
interface SpeechToText
{
    /** اسمه في السجلّ والقياس. */
    public function name(): string;

    /**
     * @param  string  $audioPath  ملفّ صوت واحد، مقطَّعٌ سلفاً إن لزم.
     * @param  list<string>  $glossary  مسرد المصطلحات — يرفع دقّة الأعلام كثيراً.
     *
     * @throws TranscriptFailed برمز `transcription_failed`.
     */
    public function transcribe(string $audioPath, string $languageCode, array $glossary = []): string;
}
