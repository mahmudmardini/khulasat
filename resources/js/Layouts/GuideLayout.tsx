import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { setTranslations, t } from '@/lib/i18n';
import { BrandMark, ProductFooter, Wordmark } from '@/Components/Brand';
import { Icon } from '@/Components/Icon';
import type { SharedProps } from '@/types/inertia';

export interface GuideLocale {
  value: string;
  native: string;
}

interface Props {
  title: string;
  locale: string;
  locales: ReadonlyArray<GuideLocale>;
  /** الصفحةُ نفسُها بلغةٍ أخرى. */
  hrefFor: (locale: string) => string;
  /** مربّعُ البحث في الشريط — لصفحة الدليل وحدها. */
  search?: ReactNode;
  /** زرُّ المحتويات في الجوال. */
  menu?: ReactNode;
  children: ReactNode;
}

/**
 * هيكلُ دليل الاستخدام — T-215.
 *
 * **صفحةٌ تُقرأ لا لوحةٌ تُدار**: فالتمريرُ للوثيقة كلِّها لا لصندوقٍ
 * داخلها كما في `AppLayout`. وبذلك يعمل الرابطُ إلى قسمٍ (`#review`)،
 * والبحثُ بـCtrl+F، والطباعة — وكلّها ممّا يُطلب من دليل.
 *
 * والشريطُ أبيضُ كلوحة الجهة لا داكنٌ كلوحة المشرف: الدليلُ للجميع.
 */
export function GuideLayout({ title, locale, locales, hrefFor, search, menu, children }: Props) {
  const page = usePage<SharedProps>();

  setTranslations(page.props.lang);

  return (
    <div className="min-h-[100dvh] bg-bg">
      <Head title={title} />

      <a
        href="#guide-main"
        className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded focus:bg-primary focus:px-4 focus:py-2 focus:text-white"
      >
        {t('common.nav.skip_to_content')}
      </a>

      <header className="guide-topbar sticky top-0 z-30 border-b border-border bg-surface/95 backdrop-blur supports-[backdrop-filter]:bg-surface/85">
        <div className="mx-auto flex h-14 max-w-[1240px] items-center gap-2 px-3 sm:px-5">
          {menu}

          <Link
            href={`/guide/${locale}`}
            className="flex shrink-0 items-center gap-2 rounded-md px-1.5 py-1 text-primary transition-colors hover:bg-surface-alt"
          >
            <BrandMark size={24} />
            <Wordmark className="text-[21px]" />
            <span aria-hidden="true" className="hidden h-5 w-px bg-border sm:block" />
            <span className="hidden text-[14px] font-medium text-text-muted sm:inline">{t('guide.title')}</span>
          </Link>

          <div className="ms-auto flex min-w-0 items-center gap-1.5">
            {search}
            <LocaleMenu locale={locale} locales={locales} hrefFor={hrefFor} />
            <a
              href="/panel/login"
              className="hidden shrink-0 items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-[13px] font-medium text-text transition-colors hover:border-border-strong hover:bg-surface-alt lg:flex"
            >
              <Icon name="logout" size={15} className="rotate-180 text-text-muted" />
              {t('guide.to_panel')}
            </a>
          </div>
        </div>
      </header>

      {children}

      <div className="mx-auto max-w-[1240px] px-4 pb-10 sm:px-5">
        <ProductFooter className="border-t border-border pt-4" />
      </div>
    </div>
  );
}

/**
 * مبدّلُ لغة الدليل — **روابطُ لا نموذج**، بخلاف مبدّل اللوحة: اللغةُ هنا
 * في الرابط. ويُحمل القسمُ الذي يقرؤه القارئ (`#…`) إلى اللغة الأخرى،
 * فالمعرّفاتُ واحدةٌ في اللغات الأربع.
 *
 * **وتحميلٌ كامل لا تنقّلُ Inertia**: خطُّ الكيريلّية يُضاف في القالب عند
 * أوّل رسم وحده (`app.blade.php`)، فالانتقالُ إلى الروسية بلا تحميلٍ يرسمها
 * بخطٍّ بديل.
 */
function LocaleMenu({ locale, locales, hrefFor }: { locale: string; locales: ReadonlyArray<GuideLocale>; hrefFor: (locale: string) => string }) {
  const [open, setOpen] = useState(false);
  const box = useRef<HTMLDivElement>(null);
  const current = locales.find((item) => item.value === locale) ?? locales[0];

  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const away = (event: MouseEvent) => {
      if (box.current !== null && !box.current.contains(event.target as Node)) {
        setOpen(false);
      }
    };
    const escape = (event: KeyboardEvent) => event.key === 'Escape' && setOpen(false);

    document.addEventListener('mousedown', away);
    document.addEventListener('keydown', escape);

    return () => {
      document.removeEventListener('mousedown', away);
      document.removeEventListener('keydown', escape);
    };
  }, [open]);

  return (
    <div ref={box} className="relative shrink-0">
      <button
        type="button"
        onClick={() => setOpen((state) => !state)}
        aria-expanded={open}
        aria-haspopup="menu"
        aria-label={t('guide.language')}
        className="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-[13px] text-text-muted transition-colors hover:bg-surface-alt hover:text-text"
      >
        <Icon name="globe" size={16} />
        <span className="hidden sm:inline">{current?.native}</span>
        <Icon name="chevron" size={12} className="rotate-90 text-text-faint" />
      </button>

      {open ? (
        <ul role="menu" className="absolute end-0 top-[calc(100%+4px)] z-40 min-w-[150px] rounded-lg border border-border bg-surface p-1 shadow-lifted">
          {locales.map((item) => (
            <li key={item.value} role="none">
              <a
                role="menuitem"
                lang={item.value}
                href={hrefFor(item.value)}
                aria-current={item.value === locale ? 'true' : undefined}
                onClick={(event) => {
                  event.preventDefault();
                  window.location.assign(hrefFor(item.value) + window.location.hash);
                }}
                className={[
                  'flex w-full items-center justify-between gap-2 rounded-md px-2.5 py-2 text-start text-[13px] transition-colors hover:bg-surface-alt',
                  item.value === locale ? 'font-medium text-primary' : 'text-text',
                ].join(' ')}
              >
                {item.native}
                {item.value === locale ? <Icon name="check" size={14} /> : null}
              </a>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
