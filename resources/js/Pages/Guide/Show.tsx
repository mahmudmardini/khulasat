import { useCallback, useEffect, useMemo, useRef, useState, type KeyboardEvent as ReactKeyboardEvent } from 'react';
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { toArabicIndic } from '@/lib/numerals';
import { buildIndex, search, type GuideHit, type Highlighted } from '@/lib/guideSearch';
import { Icon } from '@/Components/Icon';
import { GuideLayout, type GuideLocale } from '@/Layouts/GuideLayout';

interface Chapter {
  id: string;
  number: number;
  title: string;
  html: string;
  topics: Array<{ id: string; title: string }>;
}

interface Props {
  locale: string;
  role: string;
  locales: GuideLocale[];
  roles: Array<{ key: string; title: string; who: string }>;
  chapters: Chapter[];
}

/** ارتفاعُ الشريط الملتصق وهامشٌ فوق العنوان — ومثلُه `scroll-margin` في CSS. */
const HEADER_OFFSET = 88;

/**
 * دليلُ الاستخدام لدورٍ واحد — T-215.
 *
 * المتنُ HTML يرسمه الخادم من Markdown (`GuideBook`)، وهذه الصفحةُ تضيف
 * إليه ما لا يكون إلّا في المتصفّح: البحث، ونسخَ رابط كلّ قسم، والمحتوياتِ
 * التي تتبع موضعَ القارئ.
 */
export default function GuideShow({ locale, role, locales, roles, chapters }: Props) {
  const current = roles.find((item) => item.key === role);
  const [active, setActive] = useState<string>(chapters[0]?.id ?? '');
  const [drawer, setDrawer] = useState(false);
  const [toast, setToast] = useState<{ message: string; url?: string } | null>(null);
  const content = useRef<HTMLDivElement>(null);

  const number = (value: number) => (locale === 'ar' ? toArabicIndic(value) : String(value));

  // المحتوياتُ تتبع القارئ: آخرُ عنوانٍ تجاوز الشريطَ هو القسمُ الذي يقرؤه.
  useEffect(() => {
    const headings = Array.from(content.current?.querySelectorAll<HTMLElement>('.guide-heading') ?? []);
    let frame = 0;

    const update = () => {
      frame = 0;
      let found = headings[0]?.id ?? '';

      for (const heading of headings) {
        if (heading.getBoundingClientRect().top - HEADER_OFFSET - 8 > 0) {
          break;
        }

        found = heading.id;
      }

      setActive(found);
    };

    const onScroll = () => {
      if (frame === 0) {
        frame = window.requestAnimationFrame(update);
      }
    };

    update();
    window.addEventListener('scroll', onScroll, { passive: true });

    return () => {
      window.removeEventListener('scroll', onScroll);
      window.cancelAnimationFrame(frame);
    };
  }, [chapters]);

  /*
   * ★ **الرابطُ إلى قسمٍ يُفتح عليه** — المتنُ يُرسم بعد تحميل الوثيقة،
   * فقفزةُ المتصفّح إلى `#review` تقع قبل أن يوجد العنوان. فتُعاد هنا،
   * ويُومض القسمُ ليُرى أين وقع القارئ.
   */
  useEffect(() => {
    const reveal = () => {
      const id = decodeURIComponent(window.location.hash.slice(1));
      const target = id === '' ? null : document.getElementById(id);

      if (target === null) {
        return;
      }

      target.scrollIntoView({ block: 'start' });
      target.classList.remove('is-target');
      void target.offsetWidth;
      target.classList.add('is-target');
    };

    reveal();
    window.addEventListener('hashchange', reveal);

    return () => window.removeEventListener('hashchange', reveal);
  }, []);

  const copy = useCallback(async (anchor: string) => {
    const url = `${window.location.origin}${window.location.pathname}#${anchor}`;

    window.history.replaceState(null, '', `#${anchor}`);

    try {
      await navigator.clipboard.writeText(url);
      setToast({ message: t('guide.copied'), url });
    } catch {
      setToast({ message: t('guide.copy_failed') });
    }
  }, []);

  // زرُّ النسخ يرسمه الخادم مع العنوان، ويلتقطه هذا المستمعُ الواحد.
  useEffect(() => {
    const box = content.current;

    if (box === null) {
      return undefined;
    }

    const onClick = (event: MouseEvent) => {
      const button = (event.target as HTMLElement).closest<HTMLButtonElement>('.guide-copy');

      if (button?.dataset.anchor !== undefined) {
        event.preventDefault();
        void copy(button.dataset.anchor);
      }
    };

    box.addEventListener('click', onClick);

    return () => box.removeEventListener('click', onClick);
  }, [copy]);

  useEffect(() => {
    if (toast === null) {
      return undefined;
    }

    const timer = window.setTimeout(() => setToast(null), 5000);

    return () => window.clearTimeout(timer);
  }, [toast]);

  const activeChapter = chapters.find((chapter) => chapter.id === active || chapter.topics.some((topic) => topic.id === active));

  const toc = (
    <Contents
      chapters={chapters}
      active={active}
      activeChapter={activeChapter?.id}
      number={number}
      onNavigate={() => setDrawer(false)}
    />
  );

  return (
    <GuideLayout
      title={current?.title ?? t('guide.title')}
      locale={locale}
      locales={locales}
      hrefFor={(next) => `/guide/${next}/${role}`}
      search={<GuideSearch chapters={chapters} locale={locale} />}
      menu={
        <button
          type="button"
          onClick={() => setDrawer(true)}
          aria-label={t('guide.open_contents')}
          className="rounded-md p-2 text-text-muted transition-colors hover:bg-surface-alt hover:text-text lg:hidden"
        >
          <Icon name="list" />
        </button>
      }
    >
      <div className="mx-auto flex max-w-[1240px] gap-10 px-4 sm:px-5">
        <aside className="guide-sidebar sticky top-14 hidden max-h-[calc(100dvh-3.5rem)] w-[264px] shrink-0 overflow-y-auto py-8 lg:block">
          {toc}
        </aside>

        {drawer ? (
          <div className="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label={t('guide.contents')}>
            <div className="absolute inset-0 bg-black/35" onClick={() => setDrawer(false)} aria-hidden="true" />
            <div className="absolute inset-y-0 start-0 flex w-[min(320px,88vw)] flex-col bg-surface shadow-lifted">
              <div className="flex h-14 shrink-0 items-center justify-between border-b border-border px-4">
                <span className="text-[15px] font-semibold text-text">{t('guide.contents')}</span>
                <button
                  type="button"
                  onClick={() => setDrawer(false)}
                  aria-label={t('guide.close_contents')}
                  className="rounded-md p-2 text-text-muted hover:bg-surface-alt hover:text-text"
                >
                  <Icon name="close" />
                </button>
              </div>
              <div className="overflow-y-auto px-3 py-4">{toc}</div>
            </div>
          </div>
        ) : null}

        <main id="guide-main" className="min-w-0 flex-1 pt-8 pb-16 lg:pt-10">
          <header className="mb-10 border-b border-border pb-8">
            <nav aria-label={t('guide.switch_role')} className="mb-6 flex flex-wrap items-center gap-1.5">
              {roles.map((item) => (
                <Link
                  key={item.key}
                  href={`/guide/${locale}/${item.key}`}
                  aria-current={item.key === role ? 'page' : undefined}
                  preserveScroll={false}
                  className={cn(
                    'rounded-full border px-3 py-1 text-[13px] transition-colors',
                    item.key === role
                      ? 'border-primary bg-primary-tint font-medium text-primary'
                      : 'border-border text-text-muted hover:border-border-strong hover:text-text',
                  )}
                >
                  {item.title}
                </Link>
              ))}
            </nav>

            <p className="text-[13px] font-semibold tracking-wide text-accent">{t('guide.kicker')}</p>
            <h1 className="mt-1.5 text-[30px] leading-tight font-semibold text-text sm:text-[34px]">{current?.title}</h1>
            <p className="mt-2 text-[16px] text-text-muted">{current?.who}</p>

            <div className="mt-5 flex flex-wrap gap-2 print:hidden">
              <button
                type="button"
                onClick={() => window.print()}
                className="flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-[13px] text-text-muted transition-colors hover:bg-surface-alt hover:text-text"
              >
                <Icon name="printer" size={15} />
                {t('guide.print')}
              </button>
              <Link
                href={`/guide/${locale}`}
                className="flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-[13px] text-text-muted transition-colors hover:bg-surface-alt hover:text-text"
              >
                <Icon name="grid" size={15} />
                {t('guide.all_guides')}
              </Link>
            </div>
          </header>

          <div ref={content} className="guide-prose">
            {chapters.map((chapter) => (
              <section
                key={chapter.id}
                className="guide-chapter"
                data-number={t('guide.chapter', { n: number(chapter.number) })}
                dangerouslySetInnerHTML={{ __html: chapter.html }}
              />
            ))}
          </div>

          <a
            href="#guide-main"
            onClick={(event) => {
              event.preventDefault();
              window.history.replaceState(null, '', window.location.pathname);
              window.scrollTo({ top: 0 });
            }}
            className="mt-12 inline-flex items-center gap-1.5 text-[14px] text-primary hover:underline print:hidden"
          >
            <Icon name="chevron" size={14} className="-rotate-90" />
            {t('guide.back_to_top')}
          </a>
        </main>
      </div>

      <div aria-live="polite" className="pointer-events-none fixed inset-x-0 bottom-6 z-50 flex justify-center px-4 print:hidden">
        {toast !== null ? (
          <div className="pointer-events-auto flex max-w-[560px] items-center gap-3 rounded-lg border border-success/40 bg-surface px-4 py-3 shadow-lifted">
            <Icon name="check" size={18} className="shrink-0 text-success" />
            <p className="text-[14px] text-text">{toast.message}</p>
            {toast.url !== undefined && typeof navigator.share === 'function' ? (
              <button
                type="button"
                onClick={() => {
                  void navigator.share({ url: toast.url }).catch(() => undefined);
                }}
                className="flex shrink-0 items-center gap-1 rounded-md border border-border px-2.5 py-1 text-[13px] text-primary hover:bg-surface-alt"
              >
                <Icon name="share" size={14} />
                {t('guide.share')}
              </button>
            ) : null}
            <button
              type="button"
              onClick={() => setToast(null)}
              aria-label={t('common.actions.close')}
              className="shrink-0 text-text-muted hover:text-text"
            >
              <Icon name="close" size={16} />
            </button>
          </div>
        ) : null}
      </div>
    </GuideLayout>
  );
}

function Contents({
  chapters,
  active,
  activeChapter,
  number,
  onNavigate,
}: {
  chapters: Chapter[];
  active: string;
  activeChapter: string | undefined;
  number: (value: number) => string;
  onNavigate: () => void;
}) {
  return (
    <nav aria-label={t('guide.contents')}>
      <p className="mb-3 px-3 text-[12px] font-semibold tracking-wide text-text-faint">{t('guide.contents')}</p>
      <ol className="flex flex-col gap-0.5">
        {chapters.map((chapter) => {
          const open = chapter.id === activeChapter;

          return (
            <li key={chapter.id}>
              <a
                href={`#${chapter.id}`}
                onClick={onNavigate}
                aria-current={chapter.id === active ? 'location' : undefined}
                className={cn(
                  'flex items-baseline gap-2.5 rounded-md px-3 py-1.5 text-[14px] leading-6 transition-colors',
                  open ? 'bg-primary-tint font-medium text-primary' : 'text-text-muted hover:bg-surface-alt hover:text-text',
                )}
              >
                <span className="nums-tabular w-4 shrink-0 text-[12px] text-text-faint">{number(chapter.number)}</span>
                <span>{chapter.title}</span>
              </a>

              {open && chapter.topics.length > 0 ? (
                <ol className="my-1 ms-[1.65rem] flex flex-col border-s border-border">
                  {chapter.topics.map((topic) => (
                    <li key={topic.id}>
                      <a
                        href={`#${topic.id}`}
                        onClick={onNavigate}
                        aria-current={topic.id === active ? 'location' : undefined}
                        className={cn(
                          '-ms-px block border-s-2 py-1 ps-3 pe-2 text-[13px] leading-6 transition-colors',
                          topic.id === active
                            ? 'border-primary font-medium text-primary'
                            : 'border-transparent text-text-muted hover:text-text',
                        )}
                      >
                        {topic.title}
                      </a>
                    </li>
                  ))}
                </ol>
              ) : null}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}

/**
 * البحث في الدليل — في المتصفّح وحده، والفهرسُ من المتن نفسه.
 *
 * `/` يفتحه من أيّ موضع كما في أكثر مواقع التوثيق، والسهمان وEnter
 * للتنقّل بين النتائج، وEsc يغلقه.
 */
function GuideSearch({ chapters, locale }: { chapters: Chapter[]; locale: string }) {
  const index = useMemo(() => buildIndex(chapters), [chapters]);
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const [expanded, setExpanded] = useState(false);
  const [cursor, setCursor] = useState(0);
  const box = useRef<HTMLDivElement>(null);
  const input = useRef<HTMLInputElement>(null);

  const hits = useMemo(() => search(index, query, locale), [index, query, locale]);

  useEffect(() => setCursor(0), [query]);

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      const typing = event.target instanceof HTMLElement && (event.target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName));

      if (!typing && (event.key === '/' || ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k'))) {
        event.preventDefault();
        setExpanded(true);
        setOpen(true);
        window.requestAnimationFrame(() => input.current?.focus());
      }
    };

    const away = (event: MouseEvent) => {
      if (box.current !== null && !box.current.contains(event.target as Node)) {
        setOpen(false);
        setExpanded(false);
      }
    };

    document.addEventListener('keydown', onKey);
    document.addEventListener('mousedown', away);

    return () => {
      document.removeEventListener('keydown', onKey);
      document.removeEventListener('mousedown', away);
    };
  }, []);

  const go = (hit: GuideHit) => {
    setOpen(false);
    setExpanded(false);
    input.current?.blur();

    if (window.location.hash === `#${hit.entry.id}`) {
      window.dispatchEvent(new HashChangeEvent('hashchange'));
    } else {
      window.location.hash = hit.entry.id;
    }
  };

  const onKeyDown = (event: ReactKeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setCursor((value) => Math.min(value + 1, hits.length - 1));
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      setCursor((value) => Math.max(value - 1, 0));
    } else if (event.key === 'Enter' && hits[cursor] !== undefined) {
      event.preventDefault();
      go(hits[cursor]);
    } else if (event.key === 'Escape') {
      setOpen(false);
      setExpanded(false);
      input.current?.blur();
    }
  };

  const showResults = open && query.trim() !== '';

  return (
    <div ref={box} className="static md:relative">
      <button
        type="button"
        onClick={() => {
          setExpanded(true);
          setOpen(true);
          window.requestAnimationFrame(() => input.current?.focus());
        }}
        aria-label={t('guide.search.label')}
        className={cn('rounded-md p-2 text-text-muted transition-colors hover:bg-surface-alt hover:text-text md:hidden', expanded && 'hidden')}
      >
        <Icon name="search" />
      </button>

      <div
        className={cn(
          'md:static md:block md:w-72 lg:w-80',
          expanded ? 'absolute inset-x-0 top-full block border-b border-border bg-surface p-3 shadow-card md:border-0 md:bg-transparent md:p-0 md:shadow-none' : 'hidden',
        )}
      >
        <label className="relative block">
          <span className="sr-only">{t('guide.search.label')}</span>
          <Icon name="search" size={16} className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-text-faint" />
          <input
            ref={input}
            type="search"
            value={query}
            onChange={(event) => {
              setQuery(event.target.value);
              setOpen(true);
            }}
            onFocus={() => setOpen(true)}
            onKeyDown={onKeyDown}
            placeholder={t('guide.search.placeholder')}
            role="combobox"
            aria-expanded={showResults}
            aria-controls="guide-search-results"
            aria-activedescendant={showResults && hits[cursor] !== undefined ? `guide-hit-${cursor}` : undefined}
            autoComplete="off"
            className="field h-9 !py-1.5 ps-9 pe-9 text-[14px]"
          />
          <kbd className="pointer-events-none absolute end-2.5 top-1/2 hidden -translate-y-1/2 rounded border border-border bg-surface-alt px-1.5 text-[11px] leading-5 text-text-faint md:block" title={t('guide.search.shortcut')}>
            /
          </kbd>
        </label>

        {showResults ? (
          <div
            id="guide-search-results"
            role="listbox"
            aria-label={t('guide.search.label')}
            className="mt-2 max-h-[min(70dvh,560px)] overflow-y-auto rounded-lg border border-border bg-surface p-1.5 shadow-lifted md:absolute md:end-0 md:top-full md:w-[min(560px,92vw)]"
          >
            {hits.length === 0 ? (
              <p className="px-3 py-4 text-[14px] text-text-muted">{t('guide.search.empty', { q: query.trim() })}</p>
            ) : (
              <>
                <p className="px-3 pt-1.5 pb-2 text-[12px] text-text-faint">
                  {t('guide.search.count', { count: locale === 'ar' ? toArabicIndic(hits.length) : hits.length })}
                </p>
                <ul>
                  {hits.map((hit, position) => (
                    <li key={hit.entry.id} id={`guide-hit-${position}`} role="option" aria-selected={position === cursor}>
                      <a
                        href={`#${hit.entry.id}`}
                        onClick={(event) => {
                          event.preventDefault();
                          go(hit);
                        }}
                        onMouseEnter={() => setCursor(position)}
                        className={cn('block rounded-md px-3 py-2.5', position === cursor ? 'bg-primary-tint' : 'hover:bg-surface-alt')}
                      >
                        {hit.entry.title !== hit.entry.chapter ? (
                          <span className="block text-[12px] text-text-faint">{hit.entry.chapter}</span>
                        ) : null}
                        <span className="block text-[14.5px] font-medium text-text">
                          <Marked pieces={hit.title} />
                        </span>
                        <span className="mt-0.5 block text-[13px] leading-6 text-text-muted">
                          <Marked pieces={hit.snippet} />
                        </span>
                      </a>
                    </li>
                  ))}
                </ul>
              </>
            )}
          </div>
        ) : null}
      </div>
    </div>
  );
}

function Marked({ pieces }: { pieces: Highlighted }) {
  return (
    <>
      {pieces.map((piece, position) =>
        piece.match ? (
          <mark key={position} className="rounded-sm bg-accent/20 px-0.5 text-text">
            {piece.text}
          </mark>
        ) : (
          <span key={position}>{piece.text}</span>
        ),
      )}
    </>
  );
}
