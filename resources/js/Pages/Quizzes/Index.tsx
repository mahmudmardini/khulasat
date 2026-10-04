import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Layouts/AppLayout';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { BarList } from '@/Components/Charts/BarList';
import { DailyColumns, Stat } from '@/Components/Charts/QuizCharts';
import { DataTable, type Column } from '@/Components/DataTable';
import { EmptyState } from '@/Components/EmptyState';
import { t } from '@/lib/i18n';
import { tabular } from '@/lib/numerals';
import { clock, percent, when } from '@/lib/quizReport';

interface QuizRow {
  id: number;
  job_id: number;
  title: string | null;
  status: 'open' | 'closed';
  participants: number;
  average: number | null;
  duration_median: number | null;
  last_attempt: string | null;
}

interface Props {
  report: {
    open: number;
    participants: number;
    average: number | null;
    completion: number | null;
    daily: Array<{ date: string; count: number }>;
    quizzes: QuizRow[];
    hardest: QuizRow[];
  };
}

/**
 * تقاريرُ الاختبارات — العامّ، T-201.
 *
 * أرقامٌ على اختبارات الجهة كلِّها، والمشاركةُ عبر الزمن، وقائمةُ الاختبارات
 * يفتح كلٌّ منها تقريره. **والمحسوبُ أوّلُ محاولةٍ منتهية لكلّ اسمٍ من جهاز**،
 * فلا يرفع المعيدُ المتوسّطَ بإعادته.
 */
export default function Index({ report }: Props) {
  const columns: Array<Column<QuizRow>> = [
    {
      key: 'title',
      header: t('quiz.reports.col_title'),
      render: (row) => (
        <Link href={`/panel/quizzes/${row.id}`} className="font-medium text-primary underline-offset-4 hover:underline">
          {row.title ?? '—'}
        </Link>
      ),
    },
    {
      key: 'status',
      header: t('quiz.reports.col_status'),
      render: (row) => (
        <span className={row.status === 'open' ? 'text-success' : 'text-text-muted'}>
          {row.status === 'open' ? t('quiz.panel.status_open') : t('quiz.panel.status_closed')}
        </span>
      ),
    },
    { key: 'participants', header: t('quiz.reports.col_participants'), numeric: true, render: (row) => tabular(row.participants) },
    { key: 'average', header: t('quiz.reports.col_average'), numeric: true, render: (row) => percent(row.average) },
    { key: 'duration', header: t('quiz.reports.col_duration'), numeric: true, render: (row) => clock(row.duration_median) },
    {
      key: 'last',
      header: t('quiz.reports.col_last'),
      render: (row) => (row.last_attempt === null ? <span className="text-text-faint">{t('quiz.reports.never')}</span> : when(row.last_attempt)),
    },
  ];

  return (
    <AppLayout title={t('quiz.reports.title')} description={t('quiz.reports.intro')}>
      {report.quizzes.length === 0 ? (
        <Card>
          <EmptyState
            title={t('quiz.reports.empty')}
            body={t('quiz.reports.empty_body')}
            action={<Button onClick={() => router.visit('/panel/lectures/create')}>{t('quiz.reports.empty_cta')}</Button>}
          />
        </Card>
      ) : (
        <div className="flex flex-col gap-5">
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <Stat label={t('quiz.reports.kpi_open')} value={tabular(report.open)} />
            <Stat label={t('quiz.reports.kpi_participants')} value={tabular(report.participants)} />
            <Stat label={t('quiz.reports.kpi_average')} value={percent(report.average)} />
            <Stat label={t('quiz.reports.kpi_completion')} value={percent(report.completion)} />
          </div>

          <Card title={t('quiz.reports.daily')}>
            <DailyColumns days={report.daily} caption={t('quiz.reports.daily')} />
          </Card>

          <Card title={t('quiz.reports.list')} flush footer={<p className="text-[12.5px] text-text-faint">{t('quiz.reports.name_note')}</p>}>
            <DataTable columns={columns} rows={report.quizzes} rowKey={(row) => row.id} />
          </Card>

          {report.hardest.length > 0 ? (
            <Card title={t('quiz.reports.hardest')} footer={<p className="text-[12.5px] text-text-faint">{t('quiz.reports.hardest_note', { min: 5 })}</p>}>
              <BarList
                items={report.hardest.map((row) => ({ label: row.title ?? '—', value: row.average ?? 0, hint: `${tabular(row.participants)} ${t('quiz.reports.col_participants')}` }))}
                formatValue={(value) => `${value}%`}
              />
            </Card>
          ) : null}
        </div>
      )}
    </AppLayout>
  );
}
