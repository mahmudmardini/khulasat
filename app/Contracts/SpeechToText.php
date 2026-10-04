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
     * «حدّ المزوّد» للملفّ الواحد بالبايت — §5-أ-4. وتجاوزُه هو ما يُوجب التقطيع.
     *
     * **من المزوّد لا من إعدادٍ عامّ**: حدُّ Whisper حجمُ الرفع (25MB)،
     * وحدُّ Gemini حجمُ الطلب كلّه بعد base64 (20MB) — ولكلٍّ رقمُه.
     */
    public function maxBytes(): int;

    /** سعر الدقيقة بالدولار — T-22. يُقيَّد به `usage_ledger.cost_usd`. */
    public function pricePerMinute(): float;

    /**
     * @param  string  $audioPath  ملفّ صوت واحد، مقطَّعٌ سلفاً إن لزم.
     * @param  list<string>  $glossary  مسرد المصطلحات — يرفع دقّة الأعلام كثيراً.
     *
     * @throws TranscriptFailed برمز `transcription_failed`.
     */
    public function transcribe(string $audioPath, string $languageCode, array $glossary = []): string;
}
