<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The ten failure modes of the transcription stage — المواصفة §5-أ-7.
 *
 * **الرمز لا يُعرض للمستخدم قطّ.** هو مفتاح السجلّ والقياس، ورسالتُه
 * العربية في `lang/ar/errors.php` — انظر {@see self::message()}.
 *
 * وأعطال هذه المرحلة تأتي من خارج النظام: يوتيوب يحجب، والفيديو يُحذف،
 * والترجمة تنقص. ولذلك كلّ عطل رمزٌ مستقلّ برسالة تقترح إجراءً، لا
 * «فشل التفريغ» — المواصفة §5-أ-6 البند ٣.
 */
enum TranscriptErrorCode: string
{
    /** المضيف خارج قائمة السماح — المواصفة §12 المخطر الثالث. */
    case HostNotAllowed = 'host_not_allowed';

    /** أطول من `tenant.max_lecture_minutes`. يُرفض قبل تنزيل بايت واحد. */
    case DurationExceeded = 'duration_exceeded';

    case VideoUnavailable = 'video_unavailable';
    case VideoPrivate = 'video_private';
    case GeoBlocked = 'geo_blocked';

    /** فحص «لست روبوتاً». واقعٌ متكرّر على عناوين مراكز البيانات، لا احتمال. */
    case BotCheck = 'bot_check';

    /** لا ترجمة عربية — يُجرَّب التفريغ الصوتي بعده (المسار ٣). */
    case NoArabicSource = 'no_arabic_source';

    case TranscriptionFailed = 'transcription_failed';

    /** أقلّ من {@see self::MINIMUM_WORDS} كلمة: مؤشّر ترجمة ناقصة. */
    case TranscriptTooShort = 'transcript_too_short';

    case YtdlpTimeout = 'ytdlp_timeout';

    /**
     * المواصفة §5-أ-7: نصّ أقلّ من ٥٠٠ كلمة يُرفع لمدير المحتوى **قبل صرف
     * أيّ توكن على النماذج**. فالنصّ الناقص يُنتج ملخّصاً ناقصاً بكلفة تامّة.
     */
    public const MINIMUM_WORDS = 500;

    /** الرسالة العربية التي تُعرض، بلا رمز ولا تفصيل تقني. */
    public function message(): string
    {
        return (string) __('errors.transcript.'.$this->value);
    }

    /**
     * Whether this failure should hand the job to the manual path.
     *
     * المواصفة §5-أ-6 البند ١: الحجب يُحوَّل تلقائياً إلى المسار اليدوي
     * برسالة واضحة، ولا يُترك المستخدم أمام عطل لا مخرج منه. والمسار
     * اليدوي «هو ما يُنقذ الجهة حين يُخفق كلّ ما سبق» — §5-أ-5.
     *
     * وما ليس في هذه القائمة لا يُنقَذ بالمسار اليدوي: الرابط المرفوض
     * والمدّة الزائدة يُصلحهما المستخدم في المدخل نفسه.
     */
    public function fallsBackToManualPath(): bool
    {
        return match ($this) {
            self::BotCheck, self::TranscriptionFailed, self::YtdlpTimeout => true,
            default => false,
        };
    }

    /**
     * Whether the preflight rejected this before any resource was spent.
     *
     * المواصفة §5-أ-1: هذه الثلاثة تُقطع قبل التنزيل، فلا تُحتسب من
     * دقائق التفريغ ولا من الحصّة.
     */
    public function rejectedByPreflight(): bool
    {
        return match ($this) {
            self::HostNotAllowed, self::DurationExceeded => true,
            default => false,
        };
    }

    /**
     * Whether this failure stops the source queue instead of falling through.
     *
     * جدول §5-أ-2 يُجرّب المصادر بالترتيب، **وأكثرُ الإخفاقات انتقالٌ لا
     * وقوف**: `no_arabic_source` من الترجمات ليس عطلاً، بل هو معنى المسار
     * الثالث. وثلاثةٌ تقف:
     *
     *   - `host_not_allowed` و`duration_exceeded`: رُدّا قبل صرف مورد، ولا
     *     مصدر بعدهما يُصلح رابطاً مرفوضاً ولا مدّةً زائدة.
     *   - `transcript_too_short`: المواصفة §5-أ-7 تقول **«يُرفع لمدير
     *     المحتوى»** — أي إلى إنسان، لا إلى مصدرٍ أغلى. والانتقال منه إلى
     *     التفريغ الصوتي يصرف من دقائق الجهة على نصٍّ عُلم نقصُه.
     */
    public function haltsSourceFallback(): bool
    {
        return $this->rejectedByPreflight() || $this === self::TranscriptTooShort;
    }
}
