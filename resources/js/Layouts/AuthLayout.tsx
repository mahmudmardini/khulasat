import type { ReactNode } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { setTranslations, t } from '@/lib/i18n';
import { BrandMark, ProductFooter, Wordmark } from '@/Components/Brand';
import { LocaleSwitcher } from '@/Components/LocaleSwitcher';
import type { SharedProps } from '@/types/inertia';

interface Props {
  title: string;
  subtitle: string;
  children: ReactNode;
  /** سطرٌ تحت البطاقة — «لا تسجيل ذاتي»، أو رجوعٌ إلى الدخول. */
  footer?: ReactNode;
}

/**
 * هيكل شاشات الباب — SCREENS.md §1، واستُخرج في T-32.
 *
 * **وهو خارج `AppLayout`** عمداً: الجانبيّ وشريط الحصّة كلاهما يخصّ جهةً
 * لم تُعرف بعد، وصفحةٌ تعرض قائمةَ من لم يدخل تُربك وتُسرّب بنية المنتج.
 *
 * وكان هذا كلُّه مكتوباً داخل شاشة الدخول وحدها. **ولمّا صارت شاشات الباب
 * ثلاثاً** (دخول · طلب استعادة · كلمة جديدة) كان نسخُه ثلاثاً يعني أنّ
 * تعديلاً في واحدةٍ يترك أختيها تخالفانها — وهو أوّل ما يُرى في المنتج.
 *
 * **والشعار منذ T-100 رمزٌ وكلمةٌ مرسومة** (الهوية البصرية الثانية §١١)،
 * لا اسمَ المنتج نصّاً بخطٍّ مُسنَّنٍ لم يُحمَّل قطّ.
 */
export function AuthLayout({ title, subtitle, children, footer }: Props) {
  const page = usePage<SharedProps>();

  // الصفحة خارج الهيكل، فتضبط نصوصها بنفسها وإلّا ظهرت مفاتيحُها خاماً.
  setTranslations(page.props.lang);

  return (
    <div className="grid min-h-[100dvh] bg-bg lg:grid-cols-2">
      <Head title={title} />

      {/*
        العمودُ طبقتان: النموذج في وسط ما بقي، وذيلُ المنتج في أسفله — T-100.
        فلا يدفع الذيلُ النموذجَ عن وسطه، ولا يطفو فوقه في شاشةٍ قصيرة.
      */}
      <main className="flex flex-col px-4 py-10">
        <div className="flex flex-1 items-center justify-center">
          <div className="w-full max-w-sm">
            {/*
              تحت 1024px يغيب اللوحُ الجانبيّ، فيحمل رأسُ العمود الشعار:
              الرمز فوق الكلمة، كالتركيب الأوسط في الهوية (§٠٣).
            */}
            <div className="mb-7 flex flex-col items-center text-center lg:hidden">
              <BrandMark size={36} className="text-primary" />
              <h1 className="mt-2 text-[34px] text-primary">
                <Wordmark />
              </h1>
              <p className="mt-1 text-[14px] text-text-muted">{subtitle}</p>
            </div>

            <div className="mb-6 hidden lg:block">
              <h1 className="text-[24px] font-semibold text-text">{title}</h1>
              <p className="mt-1.5 text-[14px] text-text-muted">{subtitle}</p>
            </div>

            {children}

            {footer !== undefined ? (
              <div className="mt-5 text-center text-[13px] leading-6 text-text-faint">{footer}</div>
            ) : null}
          </div>
        </div>

        {/*
          المبدّلُ هنا لا في ترويسة: شاشاتُ الباب بلا ترويسة، ومن فُتحت له
          اللوحةُ بلغةٍ لا يقرؤها يحتاج التبديل **قبل** أن يدخل — T-133.
        */}
        <div className="mt-8 flex justify-center">
          {/* لأعلى: المبدّل في ذيل الصفحة، وفتحُه لأسفل يتجاوز حدّها. */}
          <LocaleSwitcher placement="top" />
        </div>

        <ProductFooter className="mt-10 justify-center" />
      </main>

      {/*
        اللوح الجانبيّ زينةٌ محضة، فيغيب تحت 1024px ولا يُطلب من قارئ
        الشاشة أن يمرّ به — §القواعد العامّة: «لا زخرفة تُقرأ».
      */}
      <aside
        aria-hidden="true"
        className="relative hidden overflow-hidden bg-primary p-12 lg:flex lg:flex-col lg:justify-center"
      >
        {/* النقاطُ بـ`currentColor` لا بلونٍ مكتوب — ولا لونَ مكتوباً في الهياكل (T-100). */}
        <div
          className="pointer-events-none absolute inset-0 text-white opacity-[0.07]"
          style={{
            backgroundImage: 'radial-gradient(circle at 1px 1px, currentColor 1px, transparent 0)',
            backgroundSize: '22px 22px',
          }}
        />

        {/*
          الشعار بتركيبه العموديّ على الحبريّ — الهوية §٠٣ و§١١: القوسان
          فاتحان والنقطة ذهب. ثمّ خطٌّ ذهبيّ رفيع يفصل العلامة عن الكلام،
          والشعارُ النصّيّ الأساسيّ (§٠٩) فوق سطر الوصف.
        */}
        <div className="relative">
          <BrandMark size={56} className="text-white/90" />
          <p className="mt-4 text-[52px] text-white">
            <Wordmark />
          </p>
          <div className="mt-6 h-px w-24 bg-accent" />
          <p className="mt-6 text-[20px] font-semibold text-white">{t('common.product.slogan')}</p>
          <p className="mt-3 max-w-sm text-[16px] leading-9 text-white/80">{t('auth.tagline')}</p>
        </div>
      </aside>
    </div>
  );
}
