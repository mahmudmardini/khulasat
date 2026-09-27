import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import type { AlertPermission } from '@/lib/notify';
import { Icon } from './Icon';

/**
 * «نبّهني عند الانتهاء» — T-83.
 *
 * زرٌّ يُضغط ويبقى مضغوطاً (`aria-pressed`)، لا مربّعُ اختيار: الفعلُ هنا
 * طلبٌ يُقدَّم الآن — ويطلب إذن المتصفّح في اللمسة نفسها — لا إعدادٌ يُحفظ.
 */
export function NotifyToggle({
  enabled, permission, onEnable, onDisable,
}: {
  enabled: boolean;
  permission: AlertPermission;
  onEnable: () => void;
  onDisable: () => void;
}) {
  return (
    <div className="flex flex-col items-start gap-1">
      <button
        type="button"
        aria-pressed={enabled}
        onClick={enabled ? onDisable : onEnable}
        className={cn(
          'inline-flex items-center gap-2 rounded border px-3.5 py-2 text-[14px] font-medium whitespace-nowrap transition-colors',
          enabled
            ? 'border-primary/40 bg-primary/8 text-primary hover:bg-primary/12'
            : 'border-border-strong bg-surface text-text hover:bg-surface-alt',
        )}
      >
        <Icon name="bell" size={16} />
        <span>{t(enabled ? 'jobs.live.notify.enabled' : 'jobs.live.notify.enable')}</span>
      </button>

      {/* المحجوب يُقال: من ظنّ أنّ إشعاراً سيأتيه ولم يأتِ فقد وثق بما لا يقع. */}
      {enabled && (permission === 'denied' || permission === 'unsupported') ? (
        <p className="max-w-[34ch] text-[12px] leading-snug text-text-faint">{t('jobs.live.notify.blocked')}</p>
      ) : null}
    </div>
  );
}
