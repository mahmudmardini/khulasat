import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';

export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'danger-soft';

interface Props extends Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'className'> {
  variant?: ButtonVariant;
  loading?: boolean;
  block?: boolean;
  children: ReactNode;
}

const VARIANTS: Record<ButtonVariant, string> = {
  primary: 'bg-primary text-white hover:bg-primary-hover',
  secondary: 'bg-surface text-text border border-border-strong hover:bg-surface-alt',
  ghost: 'bg-transparent text-text-muted hover:bg-surface-alt hover:text-text',
  danger: 'bg-danger text-white hover:brightness-90',
  /*
   * الفعل المدمّر **حين لا يكون هو الراجح** — T-27.
   *
   * وكان «احذف الشاهد» بوزن `danger` المصمت، فيقف في شاشة المراجعة ندّاً
   * للفعل الراجح («أثبت لفظ المصدر») وأشدَّ منه لفتاً للعين. وترتيب
   * الأزرار ترتيبُ الرجحان — SCREENS.md §5 — فالوزن يتبع الترتيب.
   * والفعل يبقى ظاهراً بلونه، ولا يُزاحم ما هو أولى منه.
   */
  'danger-soft': 'bg-surface text-danger border border-danger/35 hover:bg-danger/8',
};

/**
 * الأفعال — SCREENS.md §مكتبة المكوّنات.
 *
 * والذهبي `--accent` **ليس هنا عمداً**: «ذهبي — تمييز فقط، لا أفعال»
 * (§الرموز). فمن أراد زرّاً ذهبياً فقد خالف النظام لا المكوّن.
 */
export function Button({
  variant = 'primary',
  loading = false,
  block = false,
  disabled,
  type = 'button',
  children,
  ...rest
}: Props) {
  // التحميل يمنع النقر كما يمنعه التعطيل، وإلا أُرسل الطلب مرّتين.
  const inert = disabled === true || loading;

  return (
    <button
      {...rest}
      type={type}
      disabled={inert}
      aria-busy={loading || undefined}
      className={cn(
        'inline-flex items-center justify-center gap-2 rounded px-4 py-2 whitespace-nowrap',
        'text-[15px] font-medium transition-colors duration-150',
        'disabled:cursor-not-allowed disabled:opacity-45 disabled:hover:brightness-100',
        VARIANTS[variant],
        /*
         * **`shrink-0` للمقيس، و`w-full` للممدود — ولا يجتمعان.**
         *
         * والزرّ في صفٍّ مرن يُضغط فيلتفّ نصُّه سطرين («افحصْ الرابط» في
         * شاشة الإنشاء)، فيمنعه `shrink-0`. لكنّ `block` يعني `w-full`،
         * وثلاثةُ أزرارٍ كلٌّ منها عرضُ الصفّ كاملاً لا تنكمش تفيض خارج
         * الشاشة — وهو ما وقع في أزرار المراجعة الثلاثة.
         */
        block ? 'w-full' : 'shrink-0',
      )}
    >
      {loading ? <Spinner /> : null}
      {/*
        بلا `span` لافّة — الأبناء عناصرُ مرنة يفرّق بينها `gap` الزرّ.
        ولفُّها في واحدٍ جامد كان يُلصق الأيقونةَ بالنصّ فيلتفّ تحتها.
      */}
      {children}
    </button>
  );
}

/**
 * دوّار التحميل داخل الزرّ.
 *
 * ولا يخالف قاعدة «لا دوّار مبهم» في §القواعد العامّة: تلك في **العمليات
 * الطويلة** التي لها حالة محدّدة تُعرض في StepTracker. وأمّا الحفظ فثوانٍ
 * معدودة، والدوّار فيه يقول «وصل الطلب» لا أكثر.
 */
function Spinner() {
  return (
    <svg className="size-4 animate-spin" viewBox="0 0 16 16" aria-hidden="true">
      <circle cx="8" cy="8" r="6.5" fill="none" stroke="currentColor" strokeOpacity=".25" strokeWidth="2" />
      <path d="M8 1.5a6.5 6.5 0 0 1 6.5 6.5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
      <title>{t('common.state.loading')}</title>
    </svg>
  );
}
