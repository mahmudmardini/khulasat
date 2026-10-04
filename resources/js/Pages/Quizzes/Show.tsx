import { Fragment, useEffect, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Layouts/AppLayout';
import { Card } from '@/Components/Card';
import { BarList } from '@/Components/Charts/BarList';
import { Columns, DailyColumns, Stat } from '@/Components/Charts/QuizCharts';
import { EmptyState } from '@/Components/EmptyState';
import { Icon } from '@/Components/Icon';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { tabular, toArabicIndic } from '@/lib/numerals';
import { clock, percent, when } from '@/lib/quizReport';

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

interface Participant {
  id: number;
  name: string;
  ip: string;
  score: number | null;
  total: number;
  percent: number | null;
  duration: number | null;
  started_at: string;
  finished: boolean;
  attempt_number: number;
  counted: boolean;
}

type SortKey = 'date' | 'name' | 'score' | 'duration' | 'attempt';

interface Props {
  quiz: { id: number; job_id: number; title: string | null; status: 'open' | 'closed'; url: string };
  report: {
    opens: number;
    started: number;
    finished: number;
    completion: number | null;
    participants: number;
    average: number | null;
    median: number | null;
    duration_median: number | null;
    duration_average: number | null;
    distribution: Array<{ score: number; count: number }>;
    questions: QuestionStat[];
    axes: Array<{ index: number; name: string; rate: number | null; questions: number }>;
    daily: Array<{ date: string; count: number }>;
  };
  participants: { data: Participant[]; current_page: number; last_page: number; total: number };
  filters: { search: string; sort: SortKey; dir: 'asc' | 'desc' };
}

/**
 * تقريرُ اختبارٍ واحد — T-201.
 *
 * من الأعمّ إلى الأخصّ: أرقامُ المشاركة، ثمّ الدرجات، ثمّ **محاورُ الدرس من
 * الأضعف فهماً** — وهي ما يعني الملقي أوّلاً — ثمّ كلُّ سؤال، ثمّ المشاركون
 * بإجاباتهم.
 */
export default function Show({ quiz, report, participants, filters }: Props) {
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
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
          <Stat label={t('quiz.reports.kpi_opens')} value={tabular(report.opens)} />
          {/* رقمٌ واحدٌ كبير، والبقيةُ في السطر تحته — «١٦ / ١٧» ينقلب في سطرٍ من اليمين. */}
          <Stat
            label={t('quiz.reports.kpi_finished')}
            value={tabular(report.finished)}
            hint={`${t('quiz.reports.kpi_started')}: ${tabular(report.started)}، ${t('quiz.reports.kpi_completion')}: ${percent(report.completion)}`}
          />
          <Stat label={t('quiz.reports.kpi_participants')} value={tabular(report.participants)} />
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

        {report.participants === 0 ? (
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

            <Card title={t('quiz.reports.questions')}>
              <ol className="flex flex-col divide-y divide-border">
                {report.questions.map((question) => (
                  <QuestionRow key={question.id} question={question} participants={report.participants} />
                ))}
              </ol>
            </Card>
          </>
        )}

        <Participants quiz={quiz} participants={participants} filters={filters} />

        <Card title={t('quiz.reports.daily')}>
          <DailyColumns days={report.daily} caption={t('quiz.reports.daily')} />
        </Card>
      </div>
    </AppLayout>
  );
}

function QuestionRow({ question, participants }: { question: QuestionStat; participants: number }) {
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
              const share = participants === 0 ? 0 : Math.round((100 * question.counts[index]) / participants);
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

function Participants({ quiz, participants, filters }: Pick<Props, 'quiz' | 'participants' | 'filters'>) {
  const [search, setSearch] = useState(filters.search);
  const first = useRef(true);

  // البحثُ بعد أن يتوقّف الكاتب لحظةً — لا طلبٌ لكلّ حرف.
  useEffect(() => {
    if (first.current) {
      first.current = false;

      return;
    }

    const timer = window.setTimeout(() => visit({ search, page: 1 }), 350);

    return () => window.clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  function visit(next: Partial<{ search: string; sort: SortKey; dir: 'asc' | 'desc'; page: number }>): void {
    router.get(
      `/panel/quizzes/${quiz.id}`,
      { search: filters.search, sort: filters.sort, dir: filters.dir, ...next },
      { preserveState: true, preserveScroll: true, replace: true, only: ['participants', 'filters'] },
    );
  }

  function sortBy(key: SortKey): void {
    visit({ sort: key, dir: filters.sort === key && filters.dir === 'desc' ? 'asc' : 'desc', page: 1 });
  }

  const headers: Array<{ key: SortKey | null; label: string; numeric?: boolean }> = [
    { key: 'name', label: t('quiz.reports.col_name') },
    { key: null, label: t('quiz.reports.col_ip') },
    { key: 'score', label: t('quiz.reports.col_score'), numeric: true },
    { key: 'duration', label: t('quiz.reports.col_time'), numeric: true },
    { key: 'date', label: t('quiz.reports.col_date') },
    { key: 'attempt', label: t('quiz.reports.col_attempt') },
    { key: null, label: '' },
  ];

  return (
    <Card
      title={t('quiz.reports.participants')}
      action={<span className="nums-tabular text-[13px] text-text-muted">{tabular(participants.total)}</span>}
      flush
      footer={<p className="text-[12.5px] text-text-faint">{t('quiz.reports.name_note')}</p>}
    >
      <div className="border-b border-border px-5 py-3">
        <input
          type="search"
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder={t('quiz.reports.search')}
          aria-label={t('quiz.reports.search')}
          className="w-full max-w-xs rounded-lg border border-border bg-surface px-3 py-2 text-[14px]"
        />
      </div>

      {participants.data.length === 0 ? (
        <p className="px-5 py-10 text-center text-text-muted">{t('quiz.reports.none')}</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full border-collapse text-start text-[14px]">
            <thead>
              <tr className="border-b border-border bg-surface-alt">
                {headers.map((header, index) => (
                  <th
                    key={index}
                    scope="col"
                    aria-sort={header.key !== null && filters.sort === header.key ? (filters.dir === 'asc' ? 'ascending' : 'descending') : undefined}
                    className={cn('px-4 py-2.5 text-[13px] font-medium text-text-muted', header.numeric ? 'text-end' : 'text-start')}
                  >
                    {header.key === null ? header.label : (
                      <button type="button" onClick={() => sortBy(header.key as SortKey)} className="inline-flex items-center gap-1 hover:text-text">
                        {header.label}
                        {filters.sort === header.key ? <Icon name="sort" size={12} /> : null}
                      </button>
                    )}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {participants.data.map((row) => (
                <ParticipantRow key={row.id} quizId={quiz.id} row={row} />
              ))}
            </tbody>
          </table>
        </div>
      )}

      {participants.last_page > 1 ? (
        <div className="flex items-center justify-between gap-3 border-t border-border px-5 py-3 text-[13px] text-text-muted">
          <button
            type="button"
            disabled={participants.current_page <= 1}
            onClick={() => visit({ page: participants.current_page - 1 })}
            className="rounded px-3 py-1.5 font-medium text-primary disabled:text-text-faint"
          >
            {t('quiz.reports.prev')}
          </button>
          <span className="nums-tabular">{t('quiz.reports.page_of', { page: participants.current_page, pages: participants.last_page })}</span>
          <button
            type="button"
            disabled={participants.current_page >= participants.last_page}
            onClick={() => visit({ page: participants.current_page + 1 })}
            className="rounded px-3 py-1.5 font-medium text-primary disabled:text-text-faint"
          >
            {t('quiz.reports.next')}
          </button>
        </div>
      ) : null}
    </Card>
  );
}

interface AttemptAnswers {
  answers: Array<{ position: number; prompt: string; chosen: string | null; correct: string | null; is_correct: boolean }>;
}

function ParticipantRow({ quizId, row }: { quizId: number; row: Participant }) {
  const [open, setOpen] = useState(false);
  const [detail, setDetail] = useState<AttemptAnswers | null>(null);

  async function toggle(): Promise<void> {
    setOpen(!open);

    if (detail === null) {
      const response = await fetch(`/panel/quizzes/${quizId}/attempts/${row.id}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });

      if (response.ok) {
        setDetail((await response.json()) as AttemptAnswers);
      }
    }
  }

  return (
    <Fragment>
      <tr className={cn('border-b border-border', !row.counted && 'bg-surface-alt/40')}>
        <td className="px-4 py-2.5 font-medium text-text">{row.name}</td>
        <td className="px-4 py-2.5 text-text-muted" dir="ltr">
          <span className="nums-tabular text-[13px]">{row.ip}</span>
        </td>
        <td className="nums-tabular px-4 py-2.5 text-end text-text">
          {row.finished ? `${row.score}/${row.total} · ${percent(row.percent)}` : <span className="text-text-faint">{t('quiz.reports.in_progress')}</span>}
        </td>
        <td className="nums-tabular px-4 py-2.5 text-end text-text-muted">{clock(row.duration)}</td>
        <td className="px-4 py-2.5 text-text-muted">{when(row.started_at)}</td>
        <td className="px-4 py-2.5 text-[13px] text-text-muted">
          {row.attempt_number === 1 ? t('quiz.reports.first') : t('quiz.reports.retake', { n: row.attempt_number })}
          {row.finished && !row.counted ? <span className="block text-[12px] text-text-faint">{t('quiz.reports.not_counted')}</span> : null}
        </td>
        <td className="px-4 py-2.5 text-end">
          {row.finished ? (
            <button type="button" onClick={toggle} aria-expanded={open} className="text-[13px] font-medium text-primary hover:underline">
              {open ? t('quiz.reports.hide_answers') : t('quiz.reports.answers')}
            </button>
          ) : null}
        </td>
      </tr>
      {open && detail !== null ? (
        <tr className="border-b border-border bg-surface-alt/60">
          <td colSpan={7} className="px-4 py-3">
            <ol className="flex flex-col gap-2.5">
              {detail.answers.map((answer) => (
                <li key={answer.position} className="text-[13.5px]">
                  <p className="text-text">
                    <span className="nums-tabular text-text-muted">{toArabicIndic(answer.position)}. </span>
                    {answer.prompt}
                  </p>
                  <p className={cn('mt-0.5 flex items-start gap-1.5', answer.is_correct ? 'text-success' : 'text-danger')}>
                    <Icon name={answer.is_correct ? 'check' : 'close'} size={13} className="mt-1 shrink-0" />
                    <span>
                      {t('quiz.reports.chosen')}: {answer.chosen ?? t('quiz.reports.no_answer')}
                    </span>
                  </p>
                  {!answer.is_correct ? (
                    <p className="ps-5 text-text-muted">{t('quiz.reports.right')}: {answer.correct}</p>
                  ) : null}
                </li>
              ))}
            </ol>
          </td>
        </tr>
      ) : null}
    </Fragment>
  );
}
