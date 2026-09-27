import { useEffect, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Layouts/AppLayout';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { DataTable, type Column } from '@/Components/DataTable';
import { EmptyState } from '@/Components/EmptyState';
import { Icon } from '@/Components/Icon';
import { Segmented } from '@/Components/Segmented';
import { StatusBadge, type JobStatus } from '@/Components/StatusBadge';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { toArabicIndic } from '@/lib/numerals';

interface JobRow {
  id: number;
  title: string;
  speaker: string;
  date: string | null;
  status: JobStatus;
  needs_review: boolean;
  outputs: { page: boolean; carousel: boolean; images: boolean };
}

type SortKey = 'smart' | 'newest' | 'oldest' | 'title';
type ViewKey = 'grid' | 'table';

interface Filters {
  search: string | null;
  status: string | null;
  month: string | null;
  speaker: string | null;
  sort: SortKey;
}

interface Props {
  jobs: { data: JobRow[]; current_page: number; last_page: number; total: number };
  filters: Filters;
  speakers: string[];
  months: string[];
  has_any: boolean;
  needs_review_count: number;
}

/** مهلةٌ بعد آخر حرف قبل البحث — طلبٌ بكلّ حرف يُثقل الخادم ويُومض القائمة. */
const SEARCH_DEBOUNCE_MS = 350;
const VIEW_KEY = 'khulasah.index.view';
const SORTS: readonly SortKey[] = ['smart', 'newest', 'oldest', 'title'];

/**
 * الفهرس — SCREENS.md §2، وأُعيد تصميمه في T-27، وفي T-86.
 *
 * **`needs_review` أوّلاً ثم الأحدث** افتراضاً، وهي الصفّ الوحيد الملوَّن:
 * «هي الحالة الوحيدة التي تطلب فعلاً من المستخدم». والترتيب من الخادم لا من
 * المتصفّح، فالصفحة الثانية لا تعرف ما في الأولى.
 *
 * **وما تغيّر في T-86 — من تدقيق T-82:**
 * - **شبكة بطاقاتٍ بجانب الجدول** لا بدله (§2 يسمّي `DataTable`)، ويُحفظ
 *   الاختيار في المتصفّح.
 * - **فرزٌ يُختار**، والذكيّ — ما ينتظرك أوّلاً — هو الافتراض.
 * - **بحثٌ فوريّ** بعد توقّف الكتابة، وكان لا يُطلق إلّا بمغادرة الحقل.
 * - **الحالة رقاقاتٌ بنقرةٍ واحدة**، وكانت منسدلةً بين ثلاث.
 */
export default function Index({
  jobs, filters, speakers, months, has_any, needs_review_count,
}: Props) {
  const [view, setView] = useState<ViewKey>(rememberedView);

  const columns: Array<Column<JobRow>> = [
    {
      key: 'title',
      header: t('lectures.index.columns.title'),
      render: (row) => (
        <Link
          href={`/panel/jobs/${row.id}`}
          className="font-medium text-text underline-offset-4 hover:text-primary hover:underline"
        >
          {row.title}
        </Link>
      ),
    },
    { key: 'speaker', header: t('lectures.index.columns.speaker'), render: (row) => row.speaker },
    {
      key: 'date',
      header: t('lectures.index.columns.date'),
      // لاتينية في الجداول «لأنها تُقارن وتُحاذى» — §الخطوط.
      numeric: true,
      render: (row) => <span className="text-text-muted">{row.date ?? '—'}</span>,
    },
    {
      key: 'status',
      header: t('lectures.index.columns.status'),
      render: (row) => <StatusBadge status={row.status} />,
    },
    {
      key: 'outputs',
      header: t('lectures.index.columns.outputs'),
      render: (row) => <OutputIcons outputs={row.outputs} />,
    },
    {
      key: 'action',
      header: t('lectures.index.columns.action'),
      render: (row) => <RowAction row={row} />,
    },
  ];

  const tiles =
    jobs.data.length === 0 ? (
      <Card><NoMatches /></Card>
    ) : (
      <ul className="grid gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-3">
        {jobs.data.map((row) => (
          <li key={row.id}>
            <JobTile row={row} />
          </li>
        ))}
      </ul>
    );

  return (
    <AppLayout
      title={t('lectures.index.title')}
      description={has_any ? t('lectures.index.subtitle') : undefined}
      action={
        <Button onClick={() => router.visit('/panel/lectures/create')}>
          <Icon name="create" size={17} />
          {t('common.nav.create')}
        </Button>
      }
    >
      {has_any ? (
        <div className="flex flex-col gap-5">
          <QuickCreate />

          {needs_review_count > 0 ? <ReviewCallout count={needs_review_count} /> : null}

          <div>
            <FilterBar
              filters={filters}
              speakers={speakers}
              months={months}
              reviewCount={needs_review_count}
              view={view}
              onView={(next) => {
                setView(next);
                rememberView(next);
              }}
            />

            {/*
              الجدول اختيارٌ من 768px فما فوق، والبطاقات دونها دائماً: الجدولُ
              بستّة أعمدة على الجوال ينزلق أفقياً، ومن ينزلق أفقياً لا يقرأ.
            */}
            {view === 'table' ? (
              <>
                <div className="hidden md:block">
                  <Card flush>
                    <DataTable
                      columns={columns}
                      rows={jobs.data}
                      rowKey={(row) => row.id}
                      highlight={(row) => row.needs_review}
                      empty={<NoMatches />}
                    />
                  </Card>
                </div>
                <div className="md:hidden">{tiles}</div>
              </>
            ) : (
              tiles
            )}

            <Pager page={jobs.current_page} last={jobs.last_page} total={jobs.total} />
          </div>
        </div>
      ) : (
        <Card>
          <EmptyState
            title={t('jobs.empty.title')}
            body={t('jobs.empty.body')}
            action={<Button onClick={() => router.visit('/panel/lectures/create')}>{t('common.nav.create')}</Button>}
          />
        </Card>
      )}
    </AppLayout>
  );
}

/**
 * شريط الإلصاق — T-27.
 *
 * **ولا يُنشئ من هنا.** الرابط يُمرَّر إلى شاشة الإنشاء فيُفحص هناك قبل
 * صرف أيّ مورد، ويُقرّ المستخدم العنوانَ والملقي. والإنشاءُ بضغطةٍ من
 * الفهرس بلا فحصٍ ولا بيانات يصرف حصّةً على درسٍ لم يُنظر فيه.
 */
function QuickCreate() {
  const [url, setUrl] = useState('');

  const go = () => {
    if (url.trim() === '') {
      return;
    }

    router.visit('/panel/lectures/create', { data: { source_url: url.trim() } });
  };

  return (
    <div className="rounded-lg border border-border bg-surface p-4 shadow-card sm:p-5">
      <div className="flex items-center gap-2">
        <Icon name="sparkle" size={18} className="shrink-0 text-accent" />
        <h2 className="text-[15px] font-semibold text-text">{t('lectures.index.quick.title')}</h2>
      </div>

      <p className="mt-1 text-[13px] text-text-muted">{t('lectures.index.quick.hint')}</p>

      <div className="mt-3 flex flex-col gap-2 sm:flex-row">
        <div className="relative flex-1">
          <Icon
            name="link"
            size={17}
            className="pointer-events-none absolute inset-y-0 start-3 my-auto text-text-faint"
          />
          <input
            type="url"
            name="quick_source_url"
            inputMode="url"
            dir="ltr"
            value={url}
            onChange={(event) => setUrl(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Enter') {
                event.preventDefault();
                go();
              }
            }}
            placeholder="https://www.youtube.com/watch?v=…"
            aria-label={t('lectures.create.source.url_label')}
            className="field text-start pe-10"
          />
        </div>

        <Button onClick={go} disabled={url.trim() === ''}>
          {t('lectures.index.quick.cta')}
        </Button>
      </div>
    </div>
  );
}

/**
 * نداءُ ما ينتظر قراراً.
 *
 * ولونُه لون `needs_review` نفسه، فيقع عليه النظر أوّلاً — وهو ما يقصده
 * SCREENS.md بأنّها «الحالة الوحيدة التي تطلب فعلاً من المستخدم».
 */
function ReviewCallout({ count }: { count: number }) {
  return (
    <div className="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-lg border border-warning/35 bg-warning/8 px-4 py-3.5">
      <Icon name="alert" size={19} className="shrink-0 text-warning" />

      <div className="min-w-[min(100%,14rem)] flex-1">
        <p className="text-[15px] font-medium text-text">
          {toArabicIndic(t('lectures.index.awaiting.title', { count }))}
        </p>
        <p className="mt-0.5 text-[13px] text-text-muted">{t('lectures.index.awaiting.body')}</p>
      </div>

      <Button variant="secondary" onClick={() => router.get('/panel', { status: 'needs_review' })}>
        {t('lectures.index.awaiting.cta')}
      </Button>
    </div>
  );
}

function NoMatches() {
  return (
    <EmptyState
      title={t('lectures.index.no_matches.title')}
      body={t('lectures.index.no_matches.body')}
      action={
        <Button variant="secondary" onClick={() => router.visit('/panel')}>
          {t('common.actions.clear_filters')}
        </Button>
      }
    />
  );
}

function RowAction({ row }: { row: JobRow }) {
  return (
    <Link
      href={row.needs_review ? `/panel/jobs/${row.id}/review` : `/panel/jobs/${row.id}`}
      className={cn(
        'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-[14px] transition-colors',
        row.needs_review
          ? 'bg-warning/12 font-medium text-warning hover:bg-warning/20'
          : 'text-primary hover:bg-surface-alt',
      )}
    >
      {row.needs_review ? t('lectures.index.review') : t('lectures.index.open')}
      {/* السهم في RTL يشير يساراً — أي إلى جهة المتابعة. */}
      <Icon name="chevron" size={14} className="rotate-180" />
    </Link>
  );
}

/**
 * بطاقةٌ في الشبكة — T-86، وهي نفسُها صفُّ الجوال منذ T-27.
 *
 * **الحالة والتاريخ أوّلاً، ثمّ العنوان بخطٍّ أكبر**، والفعلُ في ذيلها بلونه:
 * «راجعْ الآن» بلون `needs_review`، و«فتح» بلون الفعل. والبطاقة كلُّها رابط،
 * فالنقر في أيّ موضعٍ منها يفتحها.
 */
function JobTile({ row }: { row: JobRow }) {
  return (
    <Link
      href={row.needs_review ? `/panel/jobs/${row.id}/review` : `/panel/jobs/${row.id}`}
      className={cn(
        'group flex h-full flex-col rounded-lg border bg-surface p-4 shadow-card transition-[border-color,box-shadow] hover:shadow-lifted',
        row.needs_review ? 'border-warning/40 bg-warning/5' : 'border-border hover:border-border-strong',
      )}
    >
      <div className="flex items-center justify-between gap-3">
        <StatusBadge status={row.status} />
        <span className="nums-tabular text-[12.5px] text-text-faint">{row.date ?? '—'}</span>
      </div>

      <h3 className="mt-3 line-clamp-2 wrap-anywhere text-[16px] leading-snug font-semibold text-text group-hover:text-primary">
        {row.title}
      </h3>
      <p className="mt-1 truncate text-[13.5px] text-text-muted">{row.speaker}</p>

      <div className="mt-auto pt-4">
        <div className="flex items-center justify-between gap-3 border-t border-border pt-3">
          <OutputIcons outputs={row.outputs} />

          <span
            className={cn(
              'inline-flex items-center gap-1 text-[13px] font-medium',
              row.needs_review ? 'text-warning' : 'text-primary',
            )}
          >
            {row.needs_review ? t('lectures.index.review') : t('lectures.index.open')}
            <Icon name="chevron" size={13} className="rotate-180" />
          </span>
        </div>
      </div>
    </Link>
  );
}

/**
 * البحث والحالة والفرز والتصفية وطريقة العرض — في سطرين.
 *
 * **كلّها تُرسل إلى الخادم**: الترتيب والصفحات هناك، فتصفيةٌ في المتصفّح
 * تُصفّي الصفحة الظاهرة وحدها وتُوهم أنّها صفّت الكلّ. وكلُّ تغييرٍ يعود
 * بالصفحة الأولى، فمن رشّح من الصفحة الثالثة لا يقف على صفحةٍ فارغة.
 */
function FilterBar({
  filters, speakers, months, reviewCount, view, onView,
}: {
  filters: Filters;
  speakers: string[];
  months: string[];
  reviewCount: number;
  view: ViewKey;
  onView: (view: ViewKey) => void;
}) {
  const [term, setTerm] = useState(filters.search ?? '');
  const firstRun = useRef(true);

  const apply = (patch: Record<string, string>) => {
    const next = Object.fromEntries(
      Object.entries({ ...cleaned(filters), ...patch }).filter(([key, value]) => value !== '' && !(key === 'sort' && value === 'smart')),
    );

    router.get('/panel', next, { preserveState: true, preserveScroll: true, replace: true });
  };

  // **فوريٌّ بعد توقّف الكتابة** — T-86. وكان لا يُطلق إلّا بمغادرة الحقل.
  useEffect(() => {
    if (firstRun.current) {
      firstRun.current = false;
      return undefined;
    }

    const id = window.setTimeout(() => apply({ search: term.trim() }), SEARCH_DEBOUNCE_MS);
    return () => window.clearTimeout(id);
  }, [term]); // eslint-disable-line react-hooks/exhaustive-deps

  const active = [filters.search, filters.status, filters.month, filters.speaker].filter((value) => value !== null).length;

  const statuses: Array<{ value: string; label: string }> = [
    { value: '', label: t('lectures.index.filters.all') },
    { value: 'needs_review', label: t('jobs.status.needs_review') },
    { value: 'in_progress', label: t('jobs.status.queued') },
    { value: 'published', label: t('jobs.status.published') },
    { value: 'failed', label: t('jobs.status.failed') },
  ];

  return (
    <div className="mb-4 flex flex-col gap-3">
      <div className="flex flex-wrap items-center gap-2">
        <div className="relative min-w-[220px] flex-1">
          <Icon
            name="search"
            size={17}
            className="pointer-events-none absolute inset-y-0 start-3 my-auto text-text-faint"
          />
          <input
            type="search"
            name="search"
            value={term}
            placeholder={t('lectures.index.search')}
            aria-label={t('lectures.index.search')}
            onChange={(event) => setTerm(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Enter') {
                apply({ search: term.trim() });
              }
            }}
            className="field ps-10"
          />
        </div>

        <label className="relative shrink-0">
          <span className="sr-only">{t('lectures.index.sort.label')}</span>
          <Icon
            name="sort"
            size={16}
            className="pointer-events-none absolute inset-y-0 start-3 my-auto text-text-faint"
          />
          <select
            value={filters.sort}
            onChange={(event) => apply({ sort: event.target.value })}
            className="field w-auto ps-9 text-[14px]"
          >
            {SORTS.map((key) => (
              <option key={key} value={key}>{t(`lectures.index.sort.${key}`)}</option>
            ))}
          </select>
        </label>

        <Select
          label={t('lectures.index.filters.speaker')}
          value={filters.speaker}
          options={speakers.map((name) => ({ value: name, label: name }))}
          onChange={(value) => apply({ speaker: value })}
        />

        <Select
          label={t('lectures.index.filters.month')}
          value={filters.month}
          options={months.map((month) => ({ value: month, label: month }))}
          onChange={(value) => apply({ month: value })}
        />
      </div>

      <div className="flex flex-wrap items-center justify-between gap-2">
        {/*
          الحالةُ رقاقاتٌ بنقرةٍ واحدة — T-86. وهي أكثرُ ما يُرشَّح به،
          وكانت منسدلةً بين ثلاث. و«بانتظار مراجعتك» تحمل عددها من الجهة كلّها.
        */}
        <div role="group" aria-label={t('lectures.index.filters.status')} className="flex flex-wrap items-center gap-1.5">
          {statuses.map((item) => {
            const on = (filters.status ?? '') === item.value;

            return (
              <button
                key={item.value || 'all'}
                type="button"
                aria-pressed={on}
                onClick={() => apply({ status: item.value })}
                className={cn(
                  'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[13px] transition-colors',
                  on
                    ? 'border-primary bg-primary text-white'
                    : 'border-border bg-surface text-text-muted hover:border-border-strong hover:text-text',
                )}
              >
                <span>{item.label}</span>
                {item.value === 'needs_review' && reviewCount > 0 ? (
                  <span
                    className={cn(
                      'nums-tabular rounded-full px-1.5 text-[11.5px] font-semibold',
                      on ? 'bg-white/20 text-white' : 'bg-warning/15 text-warning',
                    )}
                  >
                    {toArabicIndic(reviewCount)}
                  </span>
                ) : null}
              </button>
            );
          })}

          {/*
            مخرجُ التصفية يظهر حين تكون هناك تصفية. وكان الوحيدَ في الحالة
            الفارغة، فمن رشّح ورأى نتائج قليلة لم يجد كيف يرجع.
          */}
          {active > 0 ? (
            <Button variant="ghost" onClick={() => router.visit('/panel')}>
              <Icon name="close" size={15} />
              {t('common.actions.clear_filters')}
            </Button>
          ) : null}
        </div>

        {/* الجدول لا يُعرض دون 768px، فلا مبدّل هناك. */}
        <div className="hidden md:block">
          <Segmented
            legend={t('lectures.index.view.label')}
            value={view}
            onChange={onView}
            options={[
              { key: 'grid', label: t('lectures.index.view.grid'), icon: 'grid' },
              { key: 'table', label: t('lectures.index.view.table'), icon: 'list' },
            ]}
          />
        </div>
      </div>
    </div>
  );
}

/**
 * القائمة المنسدلة.
 *
 * والتسمية داخل الحقل لا فوقه: أربعُ تسمياتٍ فوق أربعة حقول تُطيل الشريط
 * ضِعفاً بلا معنى يُضاف. وتبقى للقارئ في `aria-label`، ويظهر الاختيار
 * نفسُه حين يُختار فيُعرَف الحقل بمحتواه.
 */
function Select({
  label, value, options, onChange,
}: {
  label: string;
  value: string | null;
  options: Array<{ value: string; label: string }>;
  onChange: (value: string) => void;
}) {
  return (
    <select
      value={value ?? ''}
      aria-label={label}
      onChange={(event) => onChange(event.target.value)}
      className={cn(
        'field w-auto shrink-0 text-[14px]',
        value !== null && 'border-primary/40 bg-primary/5 font-medium text-primary',
      )}
    >
      <option value="">{label}</option>
      {options.map((option) => (
        <option key={option.value} value={option.value}>{option.label}</option>
      ))}
    </select>
  );
}

/**
 * المخرجات المُنتَجة.
 *
 * وكانت ثلاثة مربّعاتٍ صمّاء لا يُعرف أيُّها أيّ إلّا بالتحويم — والتحويم
 * لا يقع على الجوال أصلاً. فصارت لكلٍّ أيقونتُه، والمُنتَج ملوَّنٌ
 * والباقي خافت، مع اسمه لقارئ الشاشة.
 */
function OutputIcons({ outputs }: { outputs: JobRow['outputs'] }) {
  const items = [
    { key: 'page', icon: 'page', on: outputs.page },
    { key: 'carousel', icon: 'carousel', on: outputs.carousel },
    { key: 'images', icon: 'images', on: outputs.images },
  ] as const;

  return (
    <span className="flex items-center gap-1.5">
      {items.map((item) => (
        <span
          key={item.key}
          title={`${t(`jobs.outputs.${item.key}`)}${item.on ? '' : ` — ${t('jobs.outputs.not_produced')}`}`}
          className={cn(
            'flex size-6 items-center justify-center rounded',
            // الرمادي يعني غير مُنتَج — §2. ولا يبقى اللون وحده حاملاً
            // للمعنى: الاسم والحال مكتوبان لقارئ الشاشة (§إتاحة).
            item.on ? 'bg-success/10 text-success' : 'bg-surface-alt text-text-faint',
          )}
        >
          <Icon name={item.icon} size={15} />
          <span className="sr-only">
            {t(`jobs.outputs.${item.key}`)}
            {item.on ? '' : ` ${t('jobs.outputs.not_produced')}`}
          </span>
        </span>
      ))}
    </span>
  );
}

function Pager({ page, last, total }: { page: number; last: number; total: number }) {
  if (last <= 1) {
    return null;
  }

  // الصفحة تُطلب بتصفيتها وفرزها كما هي — فالتالي تاليُ ما يُرى لا تاليُ الكلّ.
  const go = (next: number) => {
    const params = Object.fromEntries(new URLSearchParams(window.location.search));
    router.get('/panel', { ...params, page: String(next) }, { preserveScroll: false });
  };

  return (
    <nav className="mt-4 flex items-center justify-between gap-3 text-[14px] text-text-muted">
      <span>{toArabicIndic(`${page} ${t('common.table.of')} ${last}`)}</span>

      <span className="flex gap-2">
        <Button variant="secondary" disabled={page <= 1} onClick={() => go(page - 1)}>
          {t('common.actions.back')}
        </Button>
        <Button variant="secondary" disabled={page >= last} onClick={() => go(page + 1)}>
          {t('common.actions.next')}
        </Button>
      </span>

      <span className="sr-only">{toArabicIndic(total)}</span>
    </nav>
  );
}

function cleaned(filters: Filters): Record<string, string> {
  return Object.fromEntries(
    Object.entries(filters).filter((entry): entry is [string, string] => entry[1] !== null),
  );
}

/**
 * طريقة العرض المختارة تبقى بين الزيارات — T-86. والشبكةُ افتراضاً: هي ما
 * طلبه مالك المنتج، والجدولُ باقٍ لمن يقارن الأعمدة.
 */
function rememberedView(): ViewKey {
  try {
    return window.localStorage.getItem(VIEW_KEY) === 'table' ? 'table' : 'grid';
  } catch {
    return 'grid';
  }
}

function rememberView(view: ViewKey): void {
  try {
    window.localStorage.setItem(VIEW_KEY, view);
  } catch {
    // تخزينٌ محجوب: يبقى الاختيار لهذه الزيارة وحدها.
  }
}
