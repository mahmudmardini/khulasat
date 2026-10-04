import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { cn } from '@/lib/cn';
import { setTranslations, t } from '@/lib/i18n';
import { BrandMark, ProductFooter, Wordmark } from '@/Components/Brand';
import { Icon, type IconName } from '@/Components/Icon';
import { LocaleSwitcher } from '@/Components/LocaleSwitcher';
import { QuotaBar } from '@/Components/QuotaBar';
import type { SharedProps } from '@/types/inertia';

interface Props {
  title: string;
  /** سطرٌ تحت العنوان يقول ما هذه الشاشة. اختياري. */
  description?: string;
  action?: ReactNode;
  /** شاشةٌ تملأ عرضها — كالمراجعة، فقياسها يخصّها. */
  wide?: boolean;
  children: ReactNode;
}

/**
 * هيكل الصفحة — SCREENS.md §هيكل الصفحة، وأُعيد تصميمه في T-27.
 *
 * **والهيكل قوقعةٌ بارتفاع الشاشة، لا مستنداً يطول.** وهذا هو إصلاح العلّة
 * التي كانت: `sticky bottom-0` على شريط الحصّة يجعله يطفو **فوق** المحتوى
 * أثناء التمرير، فيحجب آخر حقلٍ في نموذج الإنشاء وآخر صفٍّ في الجدول.
 * فصار التمرير داخل `main` وحدها، والشريط خارجه لا يغطّي شيئاً.
 *
 * و`100dvh` لا `100vh`: شريط عنوان المتصفّح في الجوال يقتطع من `vh` فيبقى
 * جزءٌ من الشريط السفليّ تحت حافّة الشاشة.
 */
export function AppLayout({ title, description, action, wide = false, children }: Props) {
  const page = usePage<SharedProps>();
  const [drawer, setDrawer] = useState(false);

  // النصوص تُضبط قبل أوّل رسم لكل استجابة، فلا يومض مفتاحٌ مكان نصّه.
  setTranslations(page.props.lang);

  // الانتقال بين الصفحات يغلق الدرج، وإلا بقي مفتوحاً فوق الصفحة الجديدة.
  useEffect(() => setDrawer(false), [page.url]);

  return (
    <div className="flex h-[100dvh] flex-col overflow-hidden bg-bg">
      <Head title={title} />

      {/*
        أوّل ما يبلغه Tab: تخطّي إلى المحتوى. ومن لا يستعمل الفأرة يمرّ
        على كامل القائمة الجانبية في كل صفحة بدونه.
      */}
      <a
        href="#main"
        className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded focus:bg-primary focus:px-4 focus:py-2 focus:text-white"
      >
        {t('common.nav.skip_to_content')}
      </a>

      {/*
        الشريط التحذيري **فوق كلّ شيء وقبل كلّ شيء** — SCREENS.md §أ من
        لوحة المشرف: «مع شريط تحذير دائم». ومن نسي أنّه داخلٌ بهوية غيره
        قد يظنّ ما يراه حالَ المنصّة، أو يفعل باسم الجهة ما لم تطلبه.
      */}
      <ImpersonationBanner />

      <TopBar
        user={page.props.auth?.user ?? null}
        drawer={drawer}
        onToggleDrawer={() => setDrawer((open) => !open)}
      />

      <div className="flex min-h-0 flex-1">
        <Sidebar
          open={drawer}
          onClose={() => setDrawer(false)}
          currentUrl={page.url}
          tenant={page.props.auth?.user?.tenant ?? null}
        />

        <main id="main" className="min-w-0 flex-1 overflow-y-auto px-4 py-6 sm:px-8 sm:py-8">
          {/*
            **`flex min-h-full flex-col` والفاصلُ `flex-1` — T-111.** محتوًى
            أقصر من الشاشة (فهرسٌ بصفحةٍ واحدة، حالةٌ فارغة) كان يترك ذيلَ
            المنتج معلَّقاً بعد آخر سطر، وفراغاً أبيض يمتدّ حتى شريط الحصّة
            الملتصق تحت `main` — فيُخيَّل أنّ التمرير لا ينتهي. الفاصلُ يمتصّ
            الفراغَ الزائد فيدفع الذيلَ إلى حافّة `main` السفلى، ويبقى صفراً
            حين يطول المحتوى فلا يُفقد شيءٌ من سلوكه الحالي. **و`mt-12` على
            الذيل يبقى كما هو** — حدٌّ أدنى للفراغ فوقه لا يُذيبه الفاصلُ في
            حالة المحتوى الطويل، بخلاف `margin-top:auto` الذي كان سيصفّره.
            بلا مساس بفصل T-27 بين المتصفَّح والملتصق.
          */}
          <div className={cn(!wide && 'content-shell', 'flex min-h-full flex-col')}>
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

            <div aria-hidden="true" className="flex-1" />

            {/*
              ذيلُ المنتج آخرَ المحتوى لا تحت القوقعة — T-100. يمرّ مع
              التمرير فلا يقتطع من ارتفاع الشاشة شيئاً، ولا يزاحم شريط الحصّة.
            */}
            <ProductFooter className="mt-12 border-t border-border pt-4" />
          </div>
        </main>
      </div>

      <QuotaFooter />
    </div>
  );
}

/**
 * شريط الانتحال — T-21.
 *
 * ولونُه لون الخطر لا التنبيه: هذا ليس إعلاماً بحالٍ، وإنّما تحذيرٌ من أنّ
 * كلّ فعلٍ هنا يقع باسم جهةٍ أخرى.
 */
function ImpersonationBanner() {
  const page = usePage<SharedProps>();
  const session = page.props.impersonating;

  if (session === null || session === undefined) {
    return null;
  }

  return (
    <div className="z-40 flex shrink-0 flex-wrap items-center gap-x-4 gap-y-2 bg-danger px-4 py-2 text-white">
      <Icon name="eye" size={18} className="shrink-0" />

      <div className="min-w-0 flex-1">
        <p className="text-[14px] font-semibold">
          {t('admin.impersonate.banner', { tenant: session.tenant })}
        </p>
        <p className="text-[12px] text-white/80">{t('admin.impersonate.banner_hint')}</p>
      </div>

      <button
        type="button"
        onClick={() => router.post('/admin/impersonate/stop')}
        className="shrink-0 rounded-md border border-white/40 px-3 py-1.5 text-[13px] font-medium transition-colors hover:bg-white/15"
      >
        {t('admin.impersonate.stop')}
      </button>
    </div>
  );
}

function TopBar({
  user,
  drawer,
  onToggleDrawer,
}: {
  user: { name: string; role: string; tenant: string | null } | null;
  drawer: boolean;
  onToggleDrawer: () => void;
}) {
  return (
    <header className="z-30 flex h-14 shrink-0 items-center gap-2 border-b border-border bg-surface px-3 sm:px-4">
      <button
        type="button"
        onClick={onToggleDrawer}
        aria-label={t(drawer ? 'common.nav.close_menu' : 'common.nav.open_menu')}
        aria-expanded={drawer}
        className="rounded-md p-2 text-text-muted transition-colors hover:bg-surface-alt hover:text-text md:hidden"
      >
        <Icon name={drawer ? 'close' : 'menu'} />
      </button>

      {/*
        الشعار رابطٌ إلى الفهرس. وشعارُ المنتج في كل لوحةٍ يُنقَر ويُتوقّع
        منه ذلك، فتركُه نصّاً جامداً خيبةُ توقّعٍ صغيرة تتكرّر كلّ يوم.

        ومنذ T-100 رمزٌ وكلمةٌ مرسومة، كتركيب الشريط الجانبيّ في الهوية
        (§١٠)، لا اسمَ المنتج نصّاً. والاسمُ المقروء نصُّ الكلمة نفسِها،
        والرمزُ `aria-hidden` فلا يُقرأ مرّتين.
      */}
      <Link
        href="/panel"
        className="flex items-center gap-2 rounded-md px-1.5 py-1 text-primary transition-colors hover:bg-surface-alt"
      >
        <BrandMark size={24} />
        <Wordmark className="text-[21px]" />
      </Link>

      {/* المبدّل ثمّ قائمة المستخدم، و`ms-auto` على الغلاف وحده — T-133. */}
      <div className="ms-auto flex items-center gap-1">
        <LocaleSwitcher />
        {user !== null ? <UserMenu user={user} /> : null}
      </div>
    </header>
  );
}

/**
 * من الداخل ومخرجُه.
 *
 * وكان زرَّ خروجٍ عارياً في الشريط، والخروجُ فعلٌ يُنقر خطأً — فصار خلف
 * قائمةٍ تُفتح بقصد، وفيها اسمُ من دخل وصفتُه، وهما ما يُسأل عنه فعلاً
 * حين تُدار جهةٌ بعدّة حسابات.
 */
function UserMenu({ user }: { user: { name: string; role: string; tenant: string | null } }) {
  const [open, setOpen] = useState(false);
  const box = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const away = (event: MouseEvent) => {
      if (box.current !== null && !box.current.contains(event.target as Node)) {
        setOpen(false);
      }
    };

    const escape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false);
      }
    };

    document.addEventListener('mousedown', away);
    document.addEventListener('keydown', escape);

    return () => {
      document.removeEventListener('mousedown', away);
      document.removeEventListener('keydown', escape);
    };
  }, [open]);

  return (
    <div ref={box} className="relative">
      <button
        type="button"
        onClick={() => setOpen((state) => !state)}
        aria-expanded={open}
        aria-haspopup="menu"
        className="flex items-center gap-2 rounded-md py-1 ps-1 pe-2 transition-colors hover:bg-surface-alt"
      >
        <Monogram name={user.tenant ?? user.name} />
        <span className="hidden max-w-[18ch] truncate text-[14px] text-text sm:inline">
          {user.tenant ?? user.name}
        </span>
        <Icon name="chevron" size={14} className="rotate-90 text-text-faint" />
      </button>

      {open ? (
        <div
          role="menu"
          className="absolute end-0 top-full z-40 mt-1.5 w-60 overflow-hidden rounded-lg border border-border bg-surface shadow-lifted"
        >
          <div className="border-b border-border px-4 py-3">
            <p className="truncate text-[14px] font-medium text-text">{user.name}</p>
            <p className="mt-0.5 truncate text-[13px] text-text-muted">
              {t(`common.roles.${user.role}`)}
            </p>
          </div>

          <button
            type="button"
            role="menuitem"
            onClick={() => router.post('/panel/logout')}
            className="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-[14px] text-text transition-colors hover:bg-surface-alt"
          >
            <Icon name="logout" size={17} className="text-text-muted" />
            {t('auth.logout')}
          </button>
        </div>
      ) : null}
    </div>
  );
}

/** أوّل حرفٍ من اسم الجهة. وحرفٌ واحد يكفي للتمييز ولا يزاحم النصّ. */
function Monogram({ name }: { name: string }) {
  return (
    <span
      aria-hidden="true"
      className="flex size-7 shrink-0 items-center justify-center rounded-md bg-primary/10 text-[14px] font-semibold text-primary"
    >
      {[...name.trim()][0] ?? ''}
    </span>
  );
}

/*
 * **ما بُني وحده يُعرض.** ورابطٌ يقود إلى 404 أسوأ من غياب الرابط: الأوّل
 * يُخبر المستخدم أنّ المنتج معطوب، والثاني لا يَعِد بما ليس فيه.
 *
 * و«الاشتراك» عاد مع شاشته (T-23)، و«الفريق» مع شاشته (T-33). فاكتمل
 * قسم الإعدادات كما في SCREENS.md §٨ و§٩ و§١٠.
 */
const SECTIONS: ReadonlyArray<{
  key: string;
  links: ReadonlyArray<{ key: string; href: string; icon: IconName }>;
}> = [
  {
    key: 'section_work',
    links: [
      { key: 'index', href: '/panel', icon: 'index' },
      { key: 'create', href: '/panel/lectures/create', icon: 'create' },
      // أداة «تحقّق» — T-200. عامّةٌ خارج اللوحة، ورابطُها هنا ليصلها من يعمل فيها.
      { key: 'verify', href: '/verify', icon: 'search' },
    ],
  },
  {
    key: 'section_settings',
    links: [
      { key: 'brand', href: '/panel/settings/brand', icon: 'brand' },
      { key: 'billing', href: '/panel/settings/billing', icon: 'money' },
      { key: 'team', href: '/panel/settings/team', icon: 'building' },
    ],
  },
];

function Sidebar({
  open,
  onClose,
  currentUrl,
  tenant,
}: {
  open: boolean;
  onClose: () => void;
  currentUrl: string;
  tenant: string | null;
}) {
  return (
    <>
      {/* الحاجب تحت 768px وحده — الدرج يغطّي المحتوى فيُغلق بالنقر خارجه. */}
      {open ? (
        <div
          className="fixed inset-0 z-30 bg-black/35 md:hidden"
          onClick={onClose}
          aria-hidden="true"
        />
      ) : null}

      <nav
        aria-label={t('common.nav.index')}
        className={cn(
          'z-40 flex shrink-0 flex-col overflow-y-auto border-e border-border bg-surface',
          // درج تحت 768px. و`start-0` في RTL هي الحافّة اليمنى، فإخفاؤه
          // يكون بدفعه يميناً — أي `translate-x-full` الموجبة. ولو كانت
          // الواجهة LTR لاحتاجت السالبة. والاتّجاه مثبَّت على RTL.
          'fixed inset-y-0 start-0 w-64 transition-transform duration-200 md:static md:translate-x-0',
          open ? 'translate-x-0 shadow-lifted' : 'translate-x-full md:translate-x-0',
          // أيقونات تحت 1024px، وكامل فوقها
          'md:w-16 lg:w-60',
        )}
      >
        {/*
          هويّة الجهة في رأس القائمة. وكانت في الشريط العلوي مزاحمةً اسمَ
          المنتج، فيلتبس مَن المنتجُ ومَن الجهة. وتحت 1024px تُطوى إلى
          الحرف وحده كسائر الأسطر.
        */}
        {tenant !== null ? (
          <div className="flex items-center gap-2.5 border-b border-border px-4 py-3.5 md:justify-center md:px-2 lg:justify-start lg:px-4">
            <Monogram name={tenant} />
            <span className="truncate text-[14px] font-medium text-text md:sr-only lg:not-sr-only">
              {tenant}
            </span>
          </div>
        ) : null}

        <div className="flex flex-col gap-5 p-3">
          {SECTIONS.map((section) => (
            <div key={section.key}>
              {/*
                عنوان المجموعة يُخفى بصرياً في الوضع المطويّ ويبقى لقارئ
                الشاشة، فلا تصير القائمةُ سرداً واحداً بلا بناء.
              */}
              <h2 className="mb-1.5 px-3 text-[12px] font-semibold tracking-wide text-text-faint md:sr-only lg:not-sr-only">
                {t(`common.nav.${section.key}`)}
              </h2>

              <ul className="flex flex-col gap-0.5">
                {section.links.map((link) => (
                  <li key={link.key}>
                    <NavLink link={link} currentUrl={currentUrl} />
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      </nav>
    </>
  );
}

/**
 * سطرٌ في القائمة.
 *
 * **و`Link` لا `<a>`**: كانت روابط القائمة وسوماً عادية، فكلّ نقلةٍ بين
 * شاشتين تُعيد تحميل المستند كلّه — تُنزل الحُزمة ثانيةً، وتُفقد حالةَ
 * الصفحة، وتُومض الشاشةُ بيضاء. وهذا نقضُ ما بُني المنتج عليه أصلاً.
 */
function NavLink({
  link,
  currentUrl,
}: {
  link: { key: string; href: string; icon: IconName };
  currentUrl: string;
}) {
  // الفهرس يطابق جذرَه وحده، وإلّا صار نشِطاً في كلّ صفحة.
  const path = currentUrl.split('?')[0];
  const active = link.href === '/panel' ? path === '/panel' : path.startsWith(link.href);

  return (
    <Link
      href={link.href}
      aria-current={active ? 'page' : undefined}
      className={cn(
        'relative flex items-center gap-3 rounded-md px-3 py-2 text-[15px] transition-colors',
        'md:justify-center lg:justify-start',
        active
          ? 'bg-primary-tint font-medium text-primary'
          : 'text-text-muted hover:bg-surface-alt hover:text-text',
      )}
    >
      {/*
        شاهدُ النشاط شريطٌ على حافّة البداية — أي اليمين في RTL. ولا يُترك
        اللونُ وحده حاملاً للمعنى (§إتاحة)، ومعه `aria-current` للقارئ.
      */}
      {active ? (
        <span
          aria-hidden="true"
          className="absolute inset-y-1.5 start-0 w-[3px] rounded-full bg-primary"
        />
      ) : null}

      <Icon name={link.icon} className="shrink-0" />

      {/*
        تحت 1024px يبقى النصّ في الشجرة لقارئ الشاشة ويُخفى بصرياً،
        فلا يفقد المستعمل بالأيقونات معنى الرابط.
      */}
      <span className="truncate md:sr-only lg:not-sr-only">{t(`common.nav.${link.key}`)}</span>
    </Link>
  );
}

function QuotaFooter() {
  const page = usePage<SharedProps>();
  const quota = page.props.quota;

  if (quota === null || quota === undefined) {
    return null;
  }

  return (
    <footer className="z-20 shrink-0 border-t border-border bg-surface px-4 py-2 sm:px-8">
      <div className="content-shell">
        <QuotaBar used={quota.used} limit={quota.limit} />
      </div>
    </footer>
  );
}
