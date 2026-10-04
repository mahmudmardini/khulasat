import { Link } from '@inertiajs/react';
import { AppLayout } from '@/Layouts/AppLayout';
import { Card } from '@/Components/Card';
import { BarList } from '@/Components/Charts/BarList';
import { Columns, DailyColumns, Stat } from '@/Components/Charts/QuizCharts';
import { EmptyState } from '@/Components/EmptyState';
import { Icon } from '@/Components/Icon';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { tabular, toArabicIndic } from '@/lib/numerals';
import { clock, percent } from '@/lib/quizReport';

interface QuestionStat {
  id: number;
  position: number;
  prompt: string;
  axis_index: number | null;
  correct_index: number;
  options: string[];
  counts: number[];
  correct_rate: number | null;
  top_wrong: { index: number; rate: number } | null;
  median_seconds: number | null;
}

interface Props {
  quiz: { id: number; job_id: number; title: string | null; status: 'open' | 'closed'; url: string };
  report: {
    opens: number;
    started: number;
    finished: number;
    completion: number | null;
    average: number | null;
    median: number | null;
    duration_median: number | null;
    duration_average: number | null;
    distribution: Array<{ score: number; count: number }>;
    questions: QuestionStat[];
    axes: Array<{ index: number; name: string; rate: number | null; questions: number }>;
    daily: Array<{ date: string; count: number }>;
  };
}

/**
 * تقريرُ اختبارٍ واحد — T-201.
 *
 * ★ **أرقامٌ مجموعة لا قائمةُ أشخاص** — قرار @HasanSiwi، ٤ أكتوبر ٢٠٢٦: المحاولاتُ
 * بلا أصحاب. ومن الأعمّ إلى الأخصّ: المشاركة، ثمّ الدرجات، ثمّ **محاورُ الدرس
 * من الأضعف فهماً** — وهي ما يعني الملقي أوّلاً — ثمّ كلُّ سؤال.
 */
export default function Show({ quiz, report }: Props) {
  const total = report.questions.length;

  return (
    <AppLayout
      title={t('quiz.reports.report')}
      description={quiz.title ?? undefined}
      action={
        <div className="flex flex-wrap gap-2">
          <a
            href={`/panel/quizzes/${quiz.id}/export.csv`}
            className="inline-flex items-center gap-2 rounded-lg border border-border bg-surface px-4 py-2 text-[14px] font-medium text-text transition-colors hover:bg-surface-alt"
          >
            <Icon name="upload" size={16} className="rotate-180" />
            {t('quiz.reports.export')}
          </a>
          <Link
            href={`/panel/jobs/${quiz.job_id}/quiz`}
            className="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-[14px] font-medium text-primary transition-colors hover:bg-surface-alt"
          >
            <Icon name="pen" size={16} />
            {t('quiz.reports.manage')}
          </Link>
        </div>
      }
    >
      <div className="flex flex-col gap-5">
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <Stat label={t('quiz.reports.kpi_opens')} value={tabular(report.opens)} />
          {/* رقمٌ واحدٌ كبير، والبقيةُ في السطر تحته — «١٦ / ١٧» ينقلب في سطرٍ من اليمين. */}
          <Stat
            label={t('quiz.reports.kpi_finished')}
            value={tabular(report.finished)}
            hint={`${t('quiz.reports.kpi_started')}: ${tabular(report.started)}، ${t('quiz.reports.kpi_completion')}: ${percent(report.completion)}`}
          />
          <Stat
            label={t('quiz.reports.kpi_average')}
            value={percent(report.average)}
            hint={`${t('quiz.reports.kpi_median')}: ${percent(report.median)}`}
          />
          <Stat
            label={t('quiz.reports.kpi_duration_median')}
            value={clock(report.duration_median)}
            hint={`${t('quiz.reports.kpi_duration_average')}: ${clock(report.duration_average)}`}
          />
        </div>

        {report.finished === 0 ? (
          <Card>
            <EmptyState title={t('quiz.reports.none')} body={t('quiz.reports.none_body')} />
          </Card>
        ) : (
          <>
            <div className="grid gap-5 lg:grid-cols-2">
              <Card title={t('quiz.reports.axes')} footer={<p className="text-[12.5px] text-text-faint">{t('quiz.reports.axes_note')}</p>}>
                <BarList
                  items={report.axes.map((axis) => ({ label: axis.name, value: axis.rate ?? 0 }))}
                  formatValue={(value) => `${value}%`}
                />
              </Card>

              <Card title={t('quiz.reports.distribution')}>
                <Columns
                  caption={t('quiz.reports.distribution')}
                  label={(key) => t('quiz.reports.distribution_label', { score: key, total })}
                  items={report.distribution.map((bucket) => ({
                    key: String(bucket.score),
                    value: bucket.count,
                    tick: `${bucket.score}`,
                  }))}
                />
              </Card>
            </div>

            <Card title={t('quiz.reports.questions')} footer={<p className="text-[12.5px] text-text-faint">{t('quiz.reports.retake_note')}</p>}>
              <ol className="flex flex-col divide-y divide-border">
                {report.questions.map((question) => (
                  <QuestionRow key={question.id} question={question} finished={report.finished} />
                ))}
              </ol>
            </Card>
          </>
        )}

        <Card title={t('quiz.reports.daily')}>
          <DailyColumns days={report.daily} caption={t('quiz.reports.daily')} />
        </Card>
      </div>
    </AppLayout>
  );
}

function QuestionRow({ question, finished }: { question: QuestionStat; finished: number }) {
  const rate = question.correct_rate ?? 0;

  return (
    <li className="py-4 first:pt-0 last:pb-0">
      <details className="group">
        <summary className="flex cursor-pointer list-none flex-col gap-2 [&::-webkit-details-marker]:hidden">
          <div className="flex items-start gap-3">
            <span className="nums-tabular flex size-7 shrink-0 items-center justify-center rounded-full border border-border-strong text-[13px] text-text-muted">
              {toArabicIndic(question.position)}
            </span>
            <p className="min-w-0 flex-1 text-[14.5px] font-medium leading-7 text-text">{question.prompt}</p>
            <Icon name="chevron" size={16} className="mt-1.5 shrink-0 text-text-faint transition-transform group-open:rotate-180" />
          </div>

          <div className="flex flex-wrap items-center gap-x-5 gap-y-1.5 ps-10 text-[13px] text-text-muted">
            <span className="flex items-center gap-2">
              <span className="h-2 w-28 overflow-hidden rounded-full bg-surface-alt" aria-hidden="true">
                <span className={cn('block h-2 rounded-full', rate < 50 ? 'bg-danger' : 'bg-primary')} style={{ width: `${rate}%` }} />
              </span>
              <span className="nums-tabular font-medium text-text">{percent(question.correct_rate)}</span>
              {t('quiz.reports.correct_rate')}
            </span>
            {question.top_wrong !== null ? (
              <span className="min-w-0">
                {t('quiz.reports.top_wrong')}: <span className="text-text">{question.options[question.top_wrong.index]}</span>{' '}
                <span className="nums-tabular">({question.top_wrong.rate}%)</span>
              </span>
            ) : null}
            <span>
              {t('quiz.reports.median_time')}: <span className="nums-tabular text-text">{clock(question.median_seconds)}</span>
            </span>
          </div>
        </summary>

        <div className="mt-3 ps-10">
          <p className="mb-2 text-[12.5px] font-medium text-text-muted">{t('quiz.reports.breakdown')}</p>
          <ul className="flex flex-col gap-1.5">
            {question.options.map((option, index) => {
              const share = finished === 0 ? 0 : Math.round((100 * question.counts[index]) / finished);
              const correct = index === question.correct_index;

              return (
                <li key={index} className="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-1 text-[13.5px]">
                  <span className={cn('flex items-start gap-1.5', correct ? 'font-medium text-text' : 'text-text-muted')}>
                    <Icon name={correct ? 'check' : 'close'} size={13} className={cn('mt-1 shrink-0', correct ? 'text-primary' : 'text-text-faint')} />
                    {option}
                  </span>
                  <span className="nums-tabular text-text-muted">{tabular(question.counts[index])} · {share}%</span>
                  <span className="col-span-2 h-1.5 overflow-hidden rounded-full bg-surface-alt" aria-hidden="true">
                    <span className={cn('block h-1.5 rounded-full', correct ? 'bg-primary' : 'bg-border-strong')} style={{ width: `${share}%` }} />
                  </span>
                </li>
              );
            })}
          </ul>
        </div>
      </details>
    </li>
  );
}
