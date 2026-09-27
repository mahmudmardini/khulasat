import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { toArabicIndic } from '@/lib/numerals';

interface Props {
  used: number;
  limit: number;
}

/**
 * شريط الحصّة — SCREENS.md §هيكل الصفحة، والمواصفة §11.
 *
 * **بالملخّصات لا بالتوكنز**: «لا تظهر كلمة توكن في أيّ شاشة عميل» (T-23).
 * والأرقام هنا **عربية هندية** لأنّ العبارة نثريّة لا عمود جدول — §الخطوط.
 *
 * و`limit === 0` تعني بلا حدّ، وهي قراءة عمود `monthly_quota` نفسها في
 * `QuotaGuard::monthlyQuota()`: صفرٌ يعني لا حدّ، لا حصّةً منتهية.
 */
export function QuotaBar({ used, limit }: Props) {
  const unlimited = limit <= 0;
  const ratio = unlimited ? 0 : Math.min(used / limit, 1);
  const exhausted = !unlimited && used >= limit;

  return (
    <div className="flex items-center gap-3 text-[14px]">
      <span className="whitespace-nowrap text-text-muted">
        {unlimited
          ? t('billing.quota.unlimited')
          : toArabicIndic(t('billing.quota.used', { used, limit }))}
      </span>

      {!unlimited ? (
        <div
          className="h-1.5 w-40 overflow-hidden rounded-full bg-border"
          role="progressbar"
          aria-valuenow={used}
          aria-valuemin={0}
          aria-valuemax={limit}
          aria-label={t('billing.quota.label')}
        >
          {/*
            الشريط يمتلئ من الجهة المنطقية للبداية بـ `inline-size`، فينمو
            يميناً في RTL تلقائياً. ولو كُتب `width` مع `left: 0` لنما من
            الجهة الخطأ — وهذا أكثر ما يُنسى في RTL بعد انعكاس الأسهم.
          */}
          <div
            className={cn(
              'h-full rounded-full transition-[inline-size] duration-300',
              exhausted ? 'bg-danger' : ratio > 0.8 ? 'bg-warning' : 'bg-primary',
            )}
            style={{ inlineSize: `${ratio * 100}%` }}
          />
        </div>
      ) : null}

      {exhausted ? (
        <span className="text-[13px] font-medium text-danger">
          {t('billing.quota.exhausted')}
        </span>
      ) : null}
    </div>
  );
}
