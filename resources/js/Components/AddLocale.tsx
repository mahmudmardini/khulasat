import { useEffect, useState } from 'react';
import { router, usePoll } from '@inertiajs/react';
import { Button } from '@/Components/Button';
import { Icon } from '@/Components/Icon';
import { t } from '@/lib/i18n';

/** لغةٌ تُضاف إلى الملخّص، أو تُترجَم الآن، أو سقطت ترجمتُها — T-166. */
export interface LocaleAddition {
  key: string;
  label: string;
  state: 'available' | 'translating' | 'failed';
}

/**
 * «أضف لغة» لملخّصٍ قائم — T-166، طلبُ مالك المنتج.
 *
 * ★ **والفرقُ الذي يجب أن يُقرأ فرقُ الكلفة**: نداءُ ترجمةٍ واحد من المحفوظ،
 * لا ملخّصٌ جديد بمراحله الستّ. فيُقال ذلك تحت الأزرار لا في وثيقة.
 *
 * **وتتحدّث الشاشةُ من نفسها** ما دامت لغةٌ تُترجَم — كمتابعة المهمّة في
 * لوحة المشرف — وتقف حين لا انتظار، فلا طلبَ بلا سبب.
 */
export function AddLocale({
  jobId, additions, published, reload,
}: {
  jobId: number;
  additions: LocaleAddition[];
  /** المنشورُ تُنشر لغتُه الجديدة وحدها — فيُقال ذلك. */
  published: boolean;
  /** ما يُعاد من خصائص الشاشة عند كلّ تحديث. */
  reload: string[];
}) {
  const [busy, setBusy] = useState<string | null>(null);
  const waiting = additions.some((item) => item.state === 'translating');
  const poll = usePoll(4_000, { only: reload }, { autoStart: false, keepAlive: false });

  useEffect(() => {
    if (waiting) {
      poll.start();
    } else {
      poll.stop();
    }

    return () => poll.stop();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [waiting]);

  if (additions.length === 0) {
    return null;
  }

  function add(key: string): void {
    setBusy(key);
    router.post(
      `/panel/jobs/${jobId}/locales`,
      { locale: key },
      { preserveScroll: true, onFinish: () => setBusy(null) },
    );
  }

  return (
    <div className="flex flex-col gap-2.5">
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-[13px] font-medium text-text">{t('jobs.add_locale.legend')}</span>

        {additions.map((item) => {
          if (item.state === 'translating') {
            return (
              <span
                key={item.key}
                role="status"
                className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface-alt px-2.5 py-1.5 text-[13px] text-text-muted"
              >
                <Icon name="clock" size={15} className="animate-pulse text-primary" />
                {t('jobs.add_locale.translating', { locale: item.label })}
              </span>
            );
          }

          if (item.state === 'failed') {
            return (
              <span key={item.key} className="inline-flex items-center gap-1.5">
                <span className="inline-flex items-center gap-1 text-[13px] text-danger">
                  <Icon name="alert" size={15} />
                  {t('jobs.add_locale.failed', { locale: item.label })}
                </span>
                <Button variant="ghost" loading={busy === item.key} disabled={busy !== null} onClick={() => add(item.key)}>
                  {t('jobs.add_locale.retry')}
                </Button>
              </span>
            );
          }

          return (
            <Button
              key={item.key}
              variant="secondary"
              loading={busy === item.key}
              disabled={busy !== null}
              onClick={() => add(item.key)}
            >
              <Icon name="create" size={15} />
              {item.label}
            </Button>
          );
        })}
      </div>

      <p className="text-[12px] leading-relaxed text-text-faint">
        {t('jobs.add_locale.hint')}
        {published ? ` ${t('jobs.add_locale.published_hint')}` : null}
      </p>
    </div>
  );
}
