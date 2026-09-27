<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The five limits of §11, and the spend cap above them — المواصفة §11.
 *
 * ولكلٍّ **رسالة عربية تشرح السبب والإجراء**: «تجاوزت الحدّ» ليست رسالة،
 * لأنّها تترك مدير المحتوى واقفاً لا يدري أيشتري أم ينتظر أم يقسم الدرس.
 */
enum QuotaLimit: string
{
    /** الحصّة الشهرية من `usage_ledger` — عند التجاوز يُعرض شراء ملخّص إضافي. */
    case MonthlyQuota = 'monthly_quota';

    /** السقف اليومي — ثلاث مهامّ افتراضاً. */
    case DailyCap = 'daily_cap';

    /** طول المحاضرة — **يُرفض قبل صرف أيّ توكن**. */
    case LectureDuration = 'lecture_duration';

    /** إعادة التوليد لكل ملخّص. */
    case Regeneration = 'regeneration';

    /** دقائق التفريغ — يُرفض التفريغ **ويبقى اليدوي مقبولاً**. */
    case TranscriptionMinutes = 'transcription_minutes';

    /** سقف الإنفاق العامّ: يوقف الطابور كلّه لا جهةً واحدة. */
    case SpendCap = 'spend_cap';

    /**
     * الاشتراك موقوف — T-23.
     *
     * **وليس حدَّ استعمال**، فلا يُرفع بمرور الشهر ولا بشراء ملخّص إضافي.
     * وهو مع ذلك هنا لا في Policy: موضعُ الفحص واحدٌ — قبل وضع المهمّة في
     * الطابور — وتفريقُه على حاجزين يترك للثاني بابَ الأوّل مفتوحاً.
     */
    case Suspended = 'suspended';

    public function message(): string
    {
        return (string) __('errors.quota.'.$this->value);
    }

    /**
     * رمز الاستجابة لهذا الرفض.
     *
     * **٤٢٩ لحدّ استعمالٍ، و٤٠٣ لتعليق اشتراك.** والفرق يقرؤه العميل:
     * الأوّل يُرفع بمرور الشهر أو بالشراء فيُعرض «حاول لاحقاً»، والثاني
     * قرارٌ إداريّ لا يرفعه انتظار — وعرضُه «تجاوزتم الحدّ» يدفع الجهة
     * إلى انتظارٍ لا ينتهي بدل أن تراجعنا.
     */
    public function httpStatus(): int
    {
        return $this === self::Suspended ? 403 : 429;
    }

    /**
     * أيبقى المسار اليدوي متاحاً عند تجاوز هذا الحدّ؟
     *
     * المواصفة §11: دقائق التفريغ عند نفادها «رفض التفريغ، **وقبول تفريغ
     * يدوي**». فالجهة لا تُمنع من العمل، وإنّما من المسار الذي يكلّف.
     */
    public function allowsManualPath(): bool
    {
        return $this === self::TranscriptionMinutes;
    }
}
