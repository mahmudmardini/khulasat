import { useEffect, useState, type ReactNode } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { cn } from '@/lib/cn';
import { setTranslations, t } from '@/lib/i18n';
import { BrandMark, ProductFooter, Wordmark } from '@/Components/Brand';
import { Icon, type IconName } from '@/Components/Icon';
import type { SharedProps } from '@/types/inertia';

interface Props {
  title: string;
  description?: string;
  action?: ReactNode;
  children: ReactNode;
}

/**
 * هيكل لوحة المشرف — SCREENS.md القسم الثالث، والمهمّة T-21.
 *
 * «حارس منفصل **وتخطيط منفصل ولون شريط مختلف**، حتى لا تلتبس بلوحة الجهة».
 *
 * **واللون هنا وظيفةٌ لا ذوق.** فالمشرف يفتح اللوحتين في تبويبين، ويفعل في
 * إحداهما ما لا يجوز في الأخرى: يعدّل حدود جهةٍ، ويبدّل نموذجاً، ويدخل
 * بهوية غيره. وشريطٌ داكن يقول بلا قراءة: هذه ليست لوحتك المعتادة.
 *
 * **ولا شريط حصّة هنا**: الحصّة مِلكُ جهةٍ، والمشرف بلا جهة.
 */
export function AdminLayout({ title, description, action, children }: Props) {
  const page = usePage<SharedProps>();
  const [drawer, setDrawer] = useState(false);

  setTranslations(page.props.lang);

  useEffect(() => setDrawer(false), [page.url]);

  return (
    <div className="flex h-[100dvh] flex-col overflow-hidden bg-bg">
      <Head title={`${title} — ${t('admin.title')}`} />

      <a
        href="#main"
        className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded focus:bg-primary focus:px-4 focus:py-2 focus:text-white"
      >
        {t('common.nav.skip_to_content')}
      </a>

      <TopBar
        admin={page.props.auth?.admin ?? null}
        drawer={drawer}
        onToggleDrawer={() => setDrawer((open) => !open)}
      />

      <SpendCapBanner />

      <div className="flex min-h-0 flex-1">
        <Sidebar open={drawer} onClose={() => setDrawer(false)} currentUrl={page.url} />

        <main id="main" className="min-w-0 flex-1 overflow-y-auto px-4 py-6 sm:px-8 sm:py-8">
          <div className="content-shell">
            <header className="mb-6 flex flex-wrap items-start justify-between gap-x-4 gap-y-3">
              <div className="min-w-0">
                <h1 className="wrap-anywhere text-[26px] leading-tight font-semibold text-text">
                  {title}
                </h1>
                {description !== undefined ? (
                  <p className="mt-1.5 max-w-prose text-[14px] text-text-muted">{description}</p>
                ) : null}
              </div>
              {action !== undefined ? <div className="shrink-0">{action}</div> : null}
            </header>

            {children}

            {/* ذيلُ المنتج آخرَ المحتوى، كلوحة الجهة — T-100. */}
            <ProductFooter className="mt-12 border-t border-border pt-4" />
          </div>
        </main>
      </div>
    </div>
  );
}

/**
 * شريطُ وقف سقف الإنفاق — T-151.
 *
 * **ويظهر في كلّ شاشات اللوحة لا في شاشة الكلفة وحدها**: من فتح «الجهات»
 * أو «المهامّ» يجب أن يرى أنّ لا ملخّص يُنشأ الآن، لا أن يكتشف ذلك من شكوى
 * جهة. والرابط يقود إلى شاشة الكلفة حيث التحكّم الفعليّ — فالفعل يطلب سبباً
 * مكتوباً، ولا مكان له في شريطٍ عابر.
 */
function SpendCapBanner() {
  const page = usePage<SharedProps>();
  const halt = page.props.spend_cap_halted;

  if (halt === null || halt === undefined) {
    return null;
  }

  return (
    <div className="z-30 flex shrink-0 flex-wrap items-center gap-x-4 gap-y-2 bg-danger px-4 py-2 text-white">
      <Icon name="alert" size={18} className="shrink-0" />

      <div className="min-w-0 flex-1">
        <p className="text-[14px] font-semibold">{t('admin.spend_cap_banner.title')}</p>
        <p className="text-[12px] text-white/80">
          {t('admin.spend_cap_banner.hint')}
          {halt.reason ? ` ${t('admin.spend_cap_banner.reason', { reason: halt.reason })}` : null}
        </p>
      </div>

      <Link
        href="/admin/costs"
        className="shrink-0 rounded-md border border-white/40 px-3 py-1.5 text-[13px] font-medium transition-colors hover:bg-white/15"
      >
        {t('admin.spend_cap_banner.manage')}
      </Link>
    </div>
  );
}

function TopBar({
  admin,
  drawer,
  onToggleDrawer,
}: {
  admin: { name: string; email: string } | null;
  drawer: boolean;
  onToggleDrawer: () => void;
}) {
  return (
    // الداكن هو الفارق الذي يُرى قبل أن يُقرأ — §القسم الثالث. وهو ليليُّ
    // الهوية منذ T-100 لا أسودُ النصّ: داكنٌ من عائلة المنتج لا غريبٌ عنها.
    <header className="z-30 flex h-14 shrink-0 items-center gap-2 bg-night px-3 text-white sm:px-4">
      <button
        type="button"
        onClick={onToggleDrawer}
        aria-label={t(drawer ? 'common.nav.close_menu' : 'common.nav.open_menu')}
        aria-expanded={drawer}
        className="rounded-md p-2 text-white/70 transition-colors hover:bg-white/10 hover:text-white md:hidden"
      >
        <Icon name={drawer ? 'close' : 'menu'} />
      </button>

      {/*
        الرمز كالأيقونة الداكنة في الهوية (§١١): قوسان فاتحان ونقطةٌ ذهبية،
        ثمّ الكلمة، ثمّ اسمُ اللوحة بدرعه كما كان. والكلمةُ والفاصل يغيبان
        تحت 640px: الشريط يضيق، وسؤالُه «أيّ لوحةٍ هذه؟» لا «أيّ منتج؟».
      */}
      <Link
        href="/admin"
        className="flex items-center gap-2.5 rounded-md px-1.5 py-1 transition-colors hover:bg-white/10"
      >
        <BrandMark size={22} className="text-night-mark" />
        <Wordmark className="hidden text-[19px] text-white sm:inline" />
        <span aria-hidden="true" className="hidden h-5 w-px bg-white/20 sm:block" />
        <span className="flex items-center gap-1.5 text-[15px] font-semibold">
          <Icon name="shield" size={17} className="text-white/70" />
          {t('admin.title')}
        </span>
      </Link>

      {admin !== null ? (
        <div className="ms-auto flex items-center gap-3">
          <span className="hidden max-w-[22ch] truncate text-[13px] text-white/70 sm:inline">
            {admin.email}
          </span>
          <button
            type="button"
            onClick={() => router.post('/panel/logout')}
            className="flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-[13px] text-white/80 transition-colors hover:bg-white/10 hover:text-white"
          >
            <Icon name="logout" size={16} />
            {t('admin.nav.exit')}
          </button>
        </div>
      ) : null}
    </header>
  );
}

const SECTIONS: ReadonlyArray<{
  key: string;
  links: ReadonlyArray<{ key: string; href: string; icon: IconName }>;
}> = [
  {
    key: 'section_operate',
    links: [
      { key: 'home', href: '/admin', icon: 'grid' },
      { key: 'tenants', href: '/admin/tenants', icon: 'building' },
      { key: 'jobs', href: '/admin/jobs', icon: 'index' },
      // الاعتراضات في «التشغيل» لا في «المنصّة»: لها مهلةٌ تجري، وموضعُها
      // مع ما يُنظر فيه كلّ يوم — T-28.
      { key: 'takedowns', href: '/admin/takedowns', icon: 'shield' },
      // وطلباتُ الدعوة معها: صندوقٌ يُفرَّغ كلّ يوم، وصاحبُه ينتظر — T-135.
      { key: 'invites', href: '/admin/invites', icon: 'bell' },
    ],
  },
  {
    key: 'section_platform',
    links: [
      { key: 'models', href: '/admin/models', icon: 'cpu' },
      { key: 'costs', href: '/admin/costs', icon: 'money' },
    ],
  },
];

/** خارج SECTIONS عمداً: يفتح تبويباً آخر، فلا حالة «نشِط» له في هذه اللوحة. */
const HORIZON_HREF = '/horizon';

function Sidebar({
  open,
  onClose,
  currentUrl,
}: {
  open: boolean;
  onClose: () => void;
  currentUrl: string;
}) {
  return (
    <>
      {open ? (
        <div
          className="fixed inset-0 z-30 bg-black/35 md:hidden"
          onClick={onClose}
          aria-hidden="true"
        />
      ) : null}

      <nav
        aria-label={t('admin.nav.home')}
        className={cn(
          'z-40 flex shrink-0 flex-col overflow-y-auto border-e border-border bg-surface',
          'fixed inset-y-0 start-0 w-64 transition-transform duration-200 md:static md:translate-x-0',
          open ? 'translate-x-0 shadow-lifted' : 'translate-x-full md:translate-x-0',
          'md:w-16 lg:w-60',
        )}
      >
        <div className="flex flex-col gap-5 p-3">
          {SECTIONS.map((section) => (
            <div key={section.key}>
              <h2 className="mb-1.5 px-3 text-[12px] font-semibold tracking-wide text-text-faint md:sr-only lg:not-sr-only">
                {t(`admin.nav.${section.key}`)}
              </h2>

              <ul className="flex flex-col gap-0.5">
                {section.links.map((link) => (
                  <li key={link.key}>
                    <NavLink link={link} currentUrl={currentUrl} />
                  </li>
                ))}

                {section.key === 'section_platform' ? (
                  <li>
                    <a
                      href={HORIZON_HREF}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="relative flex items-center gap-3 rounded-md px-3 py-2 text-[15px] text-text-muted transition-colors md:justify-center hover:bg-surface-alt hover:text-text lg:justify-start"
                    >
                      <Icon name="clock" className="shrink-0" />
                      <span className="flex min-w-0 flex-1 items-center gap-1.5 truncate md:sr-only lg:not-sr-only">
                        {t('admin.nav.horizon')}
                        <Icon name="external" size={13} className="shrink-0 text-text-faint" />
                      </span>
                    </a>
                  </li>
                ) : null}
              </ul>
            </div>
          ))}
        </div>
      </nav>
    </>
  );
}

function NavLink({
  link,
  currentUrl,
}: {
  link: { key: string; href: string; icon: IconName };
  currentUrl: string;
}) {
  const path = currentUrl.split('?')[0];
  // صدرُ اللوحة يطابق نفسَه وحده، وإلّا صار نشِطاً في كلّ شاشة.
  const active = link.href === '/admin' ? path === '/admin' : path.startsWith(link.href);

  return (
    <Link
      href={link.href}
      aria-current={active ? 'page' : undefined}
      className={cn(
        'relative flex items-center gap-3 rounded-md px-3 py-2 text-[15px] transition-colors',
        'md:justify-center lg:justify-start',
        active
          ? 'bg-text/8 font-medium text-text'
          : 'text-text-muted hover:bg-surface-alt hover:text-text',
      )}
    >
      {active ? (
        <span
          aria-hidden="true"
          className="absolute inset-y-1.5 start-0 w-[3px] rounded-full bg-text"
        />
      ) : null}

      <Icon name={link.icon} className="shrink-0" />
      <span className="truncate md:sr-only lg:not-sr-only">{t(`admin.nav.${link.key}`)}</span>
    </Link>
  );
}
