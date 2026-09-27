import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { toArabicIndic } from '@/lib/numerals';

export interface LocaleViews {
  /** `null` = قراءاتٌ سُجّلت قبل فصل اللغات — T-140. */
  locale: string | null;
  locale_label: string;
  total: number;
  recent: number;
}

/**
 * توزيعُ القراءات على ألسنة الصفحات — T-140، بلاغُ مالك المنتج.
 *
 * ★ **ومكوّنٌ واحدٌ للشاشتين**: شاشةُ الجهة ولوحةُ المشرف تعرضان التوزيعَ
 * نفسَه، وبناؤه مرّتين يعني عرضين يفترقان — ومالكُ جهةٍ يرى غيرَ ما نراه.
 *
 * **وشريطٌ لا جدول**: النسبةُ هي المقروءة لا الرقم — «الإنجليزية ضعفُ
 * العربية» يُرى في طولِ شريطٍ قبل أن يُحسَب من رقمين. والرقمُ بجانبه على
 * كلّ حال.
 *
 * **ولا يُعرض للسانٍ واحد**: شريطٌ يملأ عرضَه كلَّه لا يقول شيئاً، والمجموعُ
 * معروضٌ فوقه أصلاً.
 */
export function ViewsByLocale({ rows, className }: { rows: LocaleViews[]; className?: string }) {
  if (rows.length < 2) {
    return null;
  }

  // والأعلى هو المقياس لا المجموع: نسبةٌ إلى المجموع تُصغّر الأشرطة كلَّها
  // حين تتعدّد اللغات، فلا يُقارَن شيءٌ بشيء.
  const top = Math.max(...rows.map((row) => row.total));

  return (
    <div className={cn('flex flex-col gap-2.5', className)}>
      <p className="text-[12px] text-text-faint">{t('common.views.by_locale')}</p>

      <ul className="flex flex-col gap-2">
        {rows.map((row) => (
          <li key={row.locale ?? 'unattributed'} className="flex flex-col gap-1">
            <div className="flex items-baseline justify-between gap-3 text-[13px]">
              <span
                className={cn('text-text', row.locale === null && 'text-text-muted')}
                title={row.locale === null ? t('common.views.unattributed_hint') : undefined}
              >
                {row.locale_label}
              </span>
              <span className="shrink-0 text-text-muted">
                {toArabicIndic(
                  t('common.views.recent_of_total', {
                    recent: String(row.recent),
                    total: String(row.total),
                  }),
                )}
              </span>
            </div>

            {/*
              **والشريطُ مزيَّنٌ لا مُخبِر**: الرقمُ إلى جانبه يقول العدد،
              فالشريطُ `aria-hidden` ولا يُقرأ مرّتين على قارئ الشاشة.
            */}
            <div aria-hidden="true" className="h-1.5 overflow-hidden rounded-full bg-surface-alt">
              <div
                className={cn('h-full rounded-full', row.locale === null ? 'bg-border-strong' : 'bg-primary')}
                style={{ width: `${top === 0 ? 0 : Math.max(2, Math.round((row.total / top) * 100))}%` }}
              />
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}
