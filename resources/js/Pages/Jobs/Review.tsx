import { useCallback, useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { AppLayout } from '@/Layouts/AppLayout';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { ConfirmDialog } from '@/Components/ConfirmDialog';
import { DiffView } from '@/Components/DiffView';
import { EmptyState } from '@/Components/EmptyState';
import { Icon } from '@/Components/Icon';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/cn';
import { toArabicIndic } from '@/lib/numerals';

interface Item {
  id: number;
  kind: string;
  quoted: string;
  source: string | null;
  match_status: string | null;
  review_status: string;
  settled: boolean;
  source_ref: string | null;
  narrator: string | null;
  book: string | null;
  takhrij: string | null;
  grade: string | null;
  grade_label: string | null;
  surah: string | null;
  ayah_number: number | null;
  context: string | null;
}

interface Props {
  job: { id: number; title: string; needs_review: boolean };
  items: Item[];
  pending: number;
}

type Decision = 'source' | 'as_quoted' | 'remove';

/** صحيح أخضر · حسن أزرق · ضعيف أو موضوع أحمر — SCREENS.md §5. */
const GRADE_TONE: Record<string, string> = {
  sahih: 'border-success/40 bg-success/10 text-success',
  hasan: 'border-info/40 bg-info/10 text-info',
  daif: 'border-danger/40 bg-danger/10 text-danger',
  mawdu: 'border-danger/40 bg-danger/10 text-danger',
  unknown: 'border-warning/40 bg-warning/10 text-warning',
};

/**
 * بوّابة المراجعة — SCREENS.md الشاشة 5، **أهمّ شاشة في المنتج**.
 *
 * **شاهد واحد في الشاشة، لا جدول.** والجدول يُغري بالمرور السريع، وهذه
 * الشاشة يوقّع بها مدير المحتوى على ما يُنشر باسم جهته.
 *
 * ومعيار التصميم: القرار في أقلّ من دقيقة. فكلّ ما يلزم للقرار معروض —
 * اللفظان، والفرق بينهما مظلَّلاً كلمةً كلمة، والتخريج، والدرجة، والسياق
 * الذي ساقه فيه المتكلّم. **من احتاج فتح تبويب آخر ليقرّر، فالشاشة فشلت.**
 */
export default function Review({ job, items, pending }: Props) {
  const [index, setIndex] = useState(() => {
    const firstPending = items.findIndex((item) => !item.settled);

    return firstPending === -1 ? 0 : firstPending;
  });
  const [confirming, setConfirming] = useState<Decision | null>(null);
  const [busy, setBusy] = useState(false);

  const current = items[index];

  const move = useCallback(
    (step: number) => setIndex((at) => Math.min(items.length - 1, Math.max(0, at + step))),
    [items.length],
  );

  const submit = useCallback(
    (decision: Decision) => {
      if (current === undefined) {
        return;
      }

      setBusy(true);
      router.post(
        `/panel/jobs/${job.id}/evidence/${current.id}/decide`,
        { decision },
        {
          preserveScroll: true,
          // يتقدّم إلى التالي من نفسه: المراجع يحسم ولا ينقر مرّتين.
          onSuccess: () => move(1),
          onFinish: () => {
            setBusy(false);
            setConfirming(null);
          },
        },
      );
    },
    [current, job.id, move],
  );

  /** ١ و٢ و٣ للأفعال، والسهمان للتنقّل — SCREENS.md §5. */
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) {
        return;
      }

      if (event.key === '1' && current?.source != null) {
        submit('source');
      }
      // الفعلان الآخران يمرّان بتأكيد، فالمفتاح يفتحه ولا ينفّذ.
      if (event.key === '2') {
        setConfirming('as_quoted');
      }
      if (event.key === '3') {
        setConfirming('remove');
      }
      // RTL: السهم الأيسر يتقدّم.
      if (event.key === 'ArrowLeft') {
        move(1);
      }
      if (event.key === 'ArrowRight') {
        move(-1);
      }
    };

    window.addEventListener('keydown', onKey);

    return () => window.removeEventListener('keydown', onKey);
  }, [current, move, submit]);

  if (items.length === 0) {
    return (
      <AppLayout title={t('review.title')}>
        <Card>
          <EmptyState title={t('review.empty')} body={t('review.empty_body')} />
        </Card>
      </AppLayout>
    );
  }

  return (
    <AppLayout title={t('review.title')}>
      <div className="mx-auto max-w-3xl space-y-4">
        <header className="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
          <p className="text-[15px] font-medium text-text">
            {t('review.counter', {
              current: toArabicIndic(index + 1),
              total: toArabicIndic(items.length),
            })}
          </p>

          {/*
            **شريطٌ يُنقر لا نقاطٌ صمّاء** — T-27. وكانت دوائرَ قطرُها
            عشرة بكسل لا تُنقر ولا يُعرف ما تعني، فصارت قطعاً يقفز بها
            المراجع إلى ما شاء، ولكلٍّ اسمُه لقارئ الشاشة.
          */}
          <ol className="flex min-w-40 flex-1 gap-1 sm:max-w-xs">
            {items.map((item, at) => (
              <li key={item.id} className="flex-1">
                <button
                  type="button"
                  onClick={() => setIndex(at)}
                  aria-current={at === index ? 'true' : undefined}
                  aria-label={`${t('review.title')} ${toArabicIndic(at + 1)}`}
                  className={cn(
                    // الحلقة كانت `ring-2` على شريطٍ ارتفاعه ستّة بكسل،
                    // فتبتلعه ويصير القطعُ كلُّه بلون الحلقة لا بلون حاله.
                    'h-2 w-full rounded-full transition-colors',
                    at === index && 'ring-1 ring-primary ring-offset-2 ring-offset-bg',
                    item.settled ? 'bg-success' : 'bg-warning/50 hover:bg-warning',
                  )}
                />
              </li>
            ))}
          </ol>
        </header>

        {current !== undefined && (
          <Card>
            <section className="space-y-4">
              <div>
                <h2 className="text-[13px] text-text-faint">{t('review.context')}</h2>
                <p className="mt-1 text-[14px] leading-7 text-text-muted">
                  {current.context ?? t('review.context_missing')}
                </p>
              </div>

              {current.source === null ? (
                <div className="rounded-lg border border-warning/40 bg-warning/10 p-4">
                  <p className="font-quran text-text">{current.quoted}</p>
                  <p className="mt-3 text-[13px] text-text-muted">{t('review.none_hint')}</p>
                </div>
              ) : (
                <DiffView source={current.source} quoted={current.quoted} />
              )}

              <dl className="grid gap-x-6 gap-y-2 text-[14px] sm:grid-cols-2">
                {current.narrator !== null && (
                  <div className="flex gap-2">
                    <dt className="text-text-faint">{t('review.labels.narrator')}</dt>
                    <dd className="text-text">{current.narrator}</dd>
                  </div>
                )}
                {current.source_ref !== null && (
                  <div className="flex gap-2">
                    <dt className="text-text-faint">{t('review.labels.takhrij')}</dt>
                    <dd className="text-text">{current.source_ref}</dd>
                  </div>
                )}
                {current.surah !== null && (
                  <div className="flex gap-2">
                    <dt className="text-text-faint">{t('review.labels.surah')}</dt>
                    <dd className="text-text">
                      {current.surah}
                      {current.ayah_number !== null ? ` · ${toArabicIndic(current.ayah_number)}` : ''}
                    </dd>
                  </div>
                )}
                {current.grade !== null && (
                  <div className="flex items-center gap-2">
                    <dt className="text-text-faint">{t('review.labels.ruling')}</dt>
                    <dd>
                      <span
                        className={cn(
                          'rounded-md border px-2 py-0.5 text-[13px]',
                          GRADE_TONE[current.grade] ?? GRADE_TONE.unknown,
                        )}
                      >
                        {current.grade_label ?? t(`review.grade.${current.grade}`)}
                      </span>
                    </dd>
                  </div>
                )}
              </dl>

              {current.settled ? (
                <p className="rounded-lg bg-surface-alt px-4 py-3 text-[14px] text-text-muted">
                  {t(`review.decided.${current.review_status}`)}
                </p>
              ) : (
                <div className="flex flex-col gap-2 sm:flex-row">
                  <Button
                    onClick={() => submit('source')}
                    loading={busy}
                    disabled={current.source === null}
                    block
                  >
                    {t('review.actions.source')}
                  </Button>
                  <Button variant="secondary" onClick={() => setConfirming('as_quoted')} block>
                    {t('review.actions.as_quoted')}
                  </Button>
                  <Button variant="danger-soft" onClick={() => setConfirming('remove')} block>
                    {t('review.actions.remove')}
                  </Button>
                </div>
              )}
            </section>
          </Card>
        )}

        {/*
          التنقّل بأزرارٍ ظاهرة. والمفاتيح تبقى، لكنّ من لم يقرأ السطر
          الذي يذكرها لم يكن يملك سبيلاً إلى الشاهد السابق أصلاً.
        */}
        <nav className="flex items-center justify-between gap-3">
          <Button variant="ghost" disabled={index === 0} onClick={() => move(-1)}>
            <Icon name="chevron" size={15} />
            {t('common.actions.back')}
          </Button>

          <Button
            variant="ghost"
            disabled={index >= items.length - 1}
            onClick={() => move(1)}
          >
            {t('common.actions.next')}
            <Icon name="chevron" size={15} className="rotate-180" />
          </Button>
        </nav>

        <footer className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
          <p className="text-[13px] text-text-faint">{t('review.keyboard')}</p>

          {pending === 0 ? (
            <form method="post" action={`/panel/jobs/${job.id}/resume`} onSubmit={(event) => {
              event.preventDefault();
              router.post(`/panel/jobs/${job.id}/resume`);
            }}>
              <Button type="submit">{t('review.actions.resume')}</Button>
            </form>
          ) : (
            <p className="text-[13px] text-warning">
              {t('review.gate.remaining', { count: toArabicIndic(pending) })}
            </p>
          )}
        </footer>
      </div>

      <ConfirmDialog
        open={confirming !== null}
        title={confirming === 'remove' ? t('review.confirm.remove_title') : t('review.confirm.as_quoted_title')}
        consequence={confirming === 'remove' ? t('review.confirm.remove') : t('review.confirm.as_quoted')}
        onConfirm={() => confirming !== null && submit(confirming)}
        onCancel={() => setConfirming(null)}
      />
    </AppLayout>
  );
}
