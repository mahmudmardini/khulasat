import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { toArabicIndic } from '@/lib/numerals';

/**
 * رمزُ خُلاصات وشعارُها — الهوية البصرية الثانية §٠٣ و§٠٤، وT-100.
 *
 * **مكوّنٌ واحد تستعمله الهياكل الثلاثة** (الجهة · المشرف · الباب)، كي لا
 * يُرسم الرمز في كلّ هيكلٍ بيده فتفترق نسخُه — والنسخة الثالثة هي التي
 * تنسى النقطة أو تُثخن القوس.
 *
 * وهذا شعارُ **المنتج** لا شعارُ الجهة: ذاك يُرفع في «هوية الجهة» ويُرسم في
 * صفحتها المنشورة (T-98)، وهذا لا يدخل تلك الصفحة أصلاً.
 */

interface MarkProps {
  size?: number;
  /** الأحاديّ: النقطة بلون القوسين لا بالذهب — للأسطح التي لا يُقرأ عليها الذهب. */
  mono?: boolean;
  className?: string;
}

/**
 * الرمز: قوسا المحقّق ونقطةُ الإعجام بينهما — §٠٤، بمسارات الهوية حرفاً.
 *
 * القوسان يرثان `currentColor` فيلوّنهما السياق، والنقطة ذهبُ `--accent`.
 * **ولا يوضع داخل سطرٍ من الكلام** (§٠٥): مصغّراً بجانب حروفٍ عربية
 * يُقرأ «١٠١». فمكانه بجانب الكلمة المرسومة، أو وحده.
 */
export function BrandMark({ size = 24, mono = false, className }: MarkProps) {
  return (
    <svg
      viewBox="0 0 64 64"
      width={size}
      height={size}
      aria-hidden="true"
      focusable="false"
      className={cn('shrink-0', className)}
    >
      <path d="M23 14H14V50H23" fill="none" stroke="currentColor" strokeWidth="6" />
      <path d="M41 14H50V50H41" fill="none" stroke="currentColor" strokeWidth="6" />
      <circle cx="32" cy="32" r="6" fill={mono ? 'currentColor' : 'var(--accent)'} />
    </svg>
  );
}

/**
 * الكلمة المرسومة «خُلاصات» — Reem Kufi للشعار وحده (§٠٨)، وبضمّتها كما
 * في سطر الاعتماد (T-99). والخطُّ وسعةُ السطر للضمّة في صنف `.wordmark`.
 */
export function Wordmark({ className }: { className?: string }) {
  return <span className={cn('wordmark', className)}>{t('common.product.name')}</span>;
}

/**
 * ذيلُ المنتج — سطرٌ هادئ في آخر المحتوى، يمرّ مع التمرير ولا يقتطع من
 * ارتفاع القوقعة شيئاً.
 *
 * **الرمزُ والكلمةُ معاً بلون العلامة**، ثمّ سطرُ الحقوق — T-111. ولا
 * `khulasat.io` في الذيل (زائدةٌ — لوحة التحكّم على نطاقها أصلاً)، ولا
 * نسبةَ إلى شركةٍ صانعة: المنتجُ يُعرَف باسمه وحده (T-153).
 */
export function ProductFooter({ className }: { className?: string }) {
  return (
    <footer
      className={cn(
        'flex flex-wrap items-baseline gap-x-2 gap-y-1 text-[12.5px] text-text-faint',
        className,
      )}
    >
      <BrandMark size={14} className="self-center text-primary" />
      <Wordmark className="text-[16px] text-primary" />
      <span aria-hidden="true">·</span>
      <span>{t('common.product.rights', { year: toArabicIndic(new Date().getFullYear()) })}</span>
      <span aria-hidden="true">·</span>
      <a href="/privacy" className="hover:text-text-muted hover:underline">
        {t('common.product.privacy')}
      </a>
      {/* دليلُ الاستخدام — T-215. في ذيل كلّ صفحة، فيصله من لم يدخل بعد. */}
      <span aria-hidden="true">·</span>
      <a href="/guide" className="hover:text-text-muted hover:underline">
        {t('common.product.guide')}
      </a>
    </footer>
  );
}
