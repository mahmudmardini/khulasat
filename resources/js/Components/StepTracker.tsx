import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';

export type StepState = 'done' | 'active' | 'awaiting' | 'pending' | 'failed';

export interface Step {
  key: string;
  state: StepState;
}

/**
 * تتبّع مراحل التوليد — SCREENS.md §القواعد العامّة:
 * «كل عملية طويلة لها **حالة محدّدة**، لا دوّار مبهم **ولا نسبة مئوية
 * مخترعة**».
 *
 * ولذلك لا يقبل هذا المكوّن نسبةً مئوية أصلاً. المراحل السبع معلومة
 * بأسمائها (§6 من المواصفة)، والصادق أن يُقال «جارٍ استخراج الشواهد»
 * لا «٦٠٪» مبنيّةً على تقسيمٍ متساوٍ لمراحل غير متساوية.
 *
 * **والرأسيّ هو الأصل منذ T-27.** والأفقيّ كان يلتفّ إلى سطرين على أيّ
 * شاشةٍ دون 1280، فتتعلّق الواصلاتُ في آخر السطر وتنقطع القراءة. والرأسيّ
 * يقرأ من أعلى إلى أسفل بلا التفاف، ويتّسع لسطر شرحٍ تحت كلّ مرحلة.
 *
 * و`aria-live` على الحاوية: تغيّر المرحلة يُنطق لقارئ الشاشة بلا إعادة
 * قراءة الصفحة — §إتاحة.
 */
export function StepTracker({
  steps,
  orientation = 'vertical',
}: {
  steps: Step[];
  orientation?: 'vertical' | 'horizontal';
}) {
  const active = steps.find((step) => step.state === 'active' || step.state === 'awaiting');

  return (
    <div>
      {orientation === 'vertical' ? (
        <ol className="flex flex-col" aria-label={t('jobs.steps_label')}>
          {steps.map((step, index) => (
            <li key={step.key} className="flex gap-3">
              <span className="flex flex-col items-center">
                <Marker state={step.state} />

                {/*
                  الواصل خطٌّ لا سهم. والسهم في RTL ينقلب معناه إن نُسي
                  عكسه، والخطّ لا اتّجاه له فلا يُخطئ. والترتيب يقرأه
                  القارئ من تدفّق الصفحة نفسه.
                */}
                {index < steps.length - 1 ? (
                  <span
                    aria-hidden="true"
                    className={cn(
                      'w-px flex-1',
                      step.state === 'done' ? 'bg-success/40' : 'bg-border',
                    )}
                  />
                ) : null}
              </span>

              <span className={cn('pb-4', index === steps.length - 1 && 'pb-0')}>
                <span className={cn('block text-[15px]', LABEL[step.state])}>
                  {t(`jobs.steps.${step.key}`)}
                </span>

                {/*
                  حالُ المرحلة مكتوبةً لا مرموزةً بلونٍ وحده — §إتاحة.
                  وتُكتب للجارية والمنتظرة والمتوقّفة دون التي تمّت، فتلك
                  علامتُها كافية ولا حاجة لسطرٍ يقول «اكتملت» سبعَ مرّات.
                */}
                {step.state !== 'done' && step.state !== 'pending' ? (
                  <span className={cn('mt-0.5 block text-[13px]', LABEL[step.state])}>
                    {t(`jobs.step_state.${step.state}`)}
                  </span>
                ) : (
                  <span className="sr-only">{t(`jobs.step_state.${step.state}`)}</span>
                )}
              </span>
            </li>
          ))}
        </ol>
      ) : (
        <ol className="flex flex-wrap items-center gap-y-3" aria-label={t('jobs.steps_label')}>
          {steps.map((step, index) => (
            <li key={step.key} className="flex items-center">
              <span className="flex items-center gap-2">
                <Marker state={step.state} />
                <span className={cn('text-[14px] whitespace-nowrap', LABEL[step.state])}>
                  {t(`jobs.steps.${step.key}`)}
                </span>
                <span className="sr-only">{t(`jobs.step_state.${step.state}`)}</span>
              </span>

              {index < steps.length - 1 ? (
                <span
                  aria-hidden="true"
                  className={cn(
                    'mx-3 h-px w-8',
                    step.state === 'done' ? 'bg-border-strong' : 'bg-border',
                  )}
                />
              ) : null}
            </li>
          ))}
        </ol>
      )}

      {/* المنطقة الحيّة منفصلة عن القائمة: تُعلن المرحلة الجارية وحدها. */}
      <p aria-live="polite" className="sr-only">
        {active !== undefined ? t(`jobs.steps.${active.key}`) : ''}
      </p>
    </div>
  );
}

const LABEL: Record<StepState, string> = {
  done: 'text-text-muted',
  active: 'font-semibold text-text',
  awaiting: 'font-semibold text-warning',
  pending: 'text-text-faint',
  failed: 'font-semibold text-danger',
};

function Marker({ state }: { state: StepState }) {
  if (state === 'done') {
    return (
      <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-success/12">
        <svg className="size-3 text-success" viewBox="0 0 16 16" aria-hidden="true">
          <path d="M3 8.5l3.2 3.2L13 5" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
      </span>
    );
  }

  if (state === 'failed') {
    return (
      <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-danger/12">
        <svg className="size-3 text-danger" viewBox="0 0 16 16" aria-hidden="true">
          <path d="M4 4l8 8M12 4l-8 8" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" />
        </svg>
      </span>
    );
  }

  if (state === 'active') {
    return (
      <span className="relative flex size-5 shrink-0 items-center justify-center" aria-hidden="true">
        {/* الحلقة النابضة تقول «يجري الآن» بلا نسبةٍ مخترعة. */}
        <span className="absolute inset-0 animate-ping rounded-full bg-info/25" />
        <span className="relative size-2.5 rounded-full bg-info" />
      </span>
    );
  }

  /*
    **«بانتظارك» غير «جارية»** — SCREENS.md §4. الجارية تعمل من نفسها
    فتنبض، وهذه واقفةٌ حتى يفعل المستخدم شيئاً — فلا تنبض، ولها لون
    `needs_review` نفسه ليقع النظر عليها.
  */
  if (state === 'awaiting') {
    return (
      <span
        className="flex size-5 shrink-0 items-center justify-center rounded-full bg-warning/15"
        aria-hidden="true"
      >
        <span className="size-2.5 rounded-full bg-warning" />
      </span>
    );
  }

  return (
    <span className="flex size-5 shrink-0 items-center justify-center" aria-hidden="true">
      <span className="size-2.5 rounded-full border border-border-strong" />
    </span>
  );
}
