<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * ما يُسجَّل من فعل المشرف العامّ — المواصفة §10، والمهمّة T-21.
 *
 * **ولا يُسجَّل كلّ شيء.** القراءةُ لا تُسجَّل — فتصفّحُ لوحةٍ للمشرف فعلُها
 * الطبيعي، وتسجيلُه يُغرق السجلّ بما لا يُسأل عنه حتى يضيع فيه ما يُسأل.
 * والمسجَّل ما **يغيّر حالاً** أو **يمنح رؤيةً ليست له**.
 */
enum AuditAction: string
{
    /** إنشاء جهة ومالكها. */
    case TenantCreated = 'tenant.created';

    /**
     * تعديل الحدود الخمسة.
     *
     * **وهذا حدثٌ ماليّ لا ضبطُ إعداد** — لا بوّابة دفع في هذه المرحلة،
     * والجهة تحوّل إلى الحساب البنكي، فرفعُ الحصّة هو التفعيل نفسُه.
     */
    case TenantLimits = 'tenant.limits';

    /**
     * تطبيق شريحة على جهة — T-23.
     *
     * **وهو تعديلُ حدودٍ بضغطةٍ واحدة**، فيُقيَّد كما تُقيَّد الحدود
     * ويُطلب له سببٌ مثلها. والفرقُ في الصفّ أنّه يذكر اسم الشريحة، فيُقرأ
     * بعد أشهر «رُقّيت إلى مؤسسة» لا «تبدّلت خمسة أرقام».
     */
    case TenantPlan = 'tenant.plan';

    /** تعليقُ جهةٍ أو رفعُ التعليق. */
    case TenantStatus = 'tenant.status';

    /**
     * تبديلُ وضع البيان — T-163.
     *
     * **وهو يغيّر ما يُنشر من الشواهد بلا إنسان**، فيُبرز ويُطلب له سببٌ كما
     * يُطلب لتعليق جهة.
     */
    case TenantVerification = 'tenant.verification';

    /**
     * تعليماتُ توليد قوالب الكاروسيل لجهةٍ بعينها — T-173.
     *
     * نصٌّ يصل نموذجاً مدفوعاً باسم الجهة، فيُعرف من كتبه ومتى.
     */
    case TenantCarouselPrompt = 'tenant.carousel_prompt';

    /** توليدُ قوالب الكاروسيل لجهةٍ من لوحة المشرف — نداءٌ مدفوع. */
    case TenantCarouselDesigns = 'tenant.carousel_designs';

    /**
     * تعديل `model_config`.
     *
     * تغييرُ نموذجٍ يغيّر جودة المنتج وكلفته معاً، فلا يمرّ بلا أثر.
     */
    case ModelConfigUpdated = 'model_config.updated';

    /** الدخول بهوية جهةٍ للدعم، والخروج منها. */
    case ImpersonationStarted = 'impersonation.started';
    case ImpersonationEnded = 'impersonation.ended';

    /**
     * الاعتراضات — T-28، ودراسة المشروع المادة 15.
     *
     * **وثلاثةٌ لا واحد.** فإزالةُ صفحةٍ نُشرت وشاركها الناس فعلٌ لا يُستردّ
     * يُسأل عنه بعد أشهر، وردُّ شكوى قرارٌ يُراجَع. وسجلٌّ يقول «عولجت شكوى»
     * عن الاثنين لا يُجيب أحدهما.
     */
    case ComplaintUnpublished = 'complaint.unpublished';
    case ComplaintResolved = 'complaint.resolved';
    case ComplaintDismissed = 'complaint.dismissed';

    /** إعادة تشغيل مهمّةٍ أو إلغاء عالقة. */
    case JobRetried = 'job.retried';
    case JobCancelled = 'job.cancelled';

    /**
     * تعليمُ طلبِ دعوةٍ معالَجاً — T-135.
     *
     * **ويُقيَّد وإن لم يغيّر حالَ المنتج**: الطلبُ وصلَ من غريبٍ ينتظر
     * جواباً، ومن علَّمه معالَجاً أخبر الفريقَ أن لا يعود إليه. فالسجلُّ
     * هنا يُجيب «من تولّاه» لا «ما تبدّل».
     */
    case InviteRequestHandled = 'invite_request.handled';

    /**
     * وقفُ سقف الإنفاق يدوياً أو رفعه — T-151.
     *
     * **وهو وقفٌ عامٌّ يمنع الطابور كلَّه**، لا حدُّ جهةٍ بعينها — فسببُه
     * يُكتب كما تُكتب الأفعال المالية الأخرى، ويُبرز في السجلّ مثلها.
     */
    case SpendCapHalted = 'spend_cap.halted';
    case SpendCapReleased = 'spend_cap.released';

    /** هل يُبرز الفعلُ في السجلّ؟ الماليّ والانتحال يُبرزان. */
    public function isSensitive(): bool
    {
        return in_array($this, [
            self::TenantLimits,
            self::TenantPlan,
            self::TenantStatus,
            self::TenantVerification,
            self::ImpersonationStarted,
            self::ModelConfigUpdated,
            // إزالةُ منشورٍ لا تُستردّ، فتُبرز كما تُبرز الأفعال المالية.
            self::ComplaintUnpublished,
            self::SpendCapHalted,
            self::SpendCapReleased,
        ], true);
    }
}
