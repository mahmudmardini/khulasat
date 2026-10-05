import { Link } from '@inertiajs/react';
import { t } from '@/lib/i18n';
import { Icon, type IconName } from '@/Components/Icon';
import { GuideLayout, type GuideLocale } from '@/Layouts/GuideLayout';

interface Role {
  key: 'owner' | 'editor' | 'admin';
  title: string;
  who: string;
  covers: string;
}

interface Props {
  locale: string;
  locales: GuideLocale[];
  roles: Role[];
}

const ICONS: Record<Role['key'], IconName> = {
  owner: 'building',
  editor: 'pen',
  admin: 'shield',
};

/**
 * بابُ دليل الاستخدام — T-215: ثلاثةُ أدلّة، ويختار القارئ دليلَ دوره.
 */
export default function GuideIndex({ locale, locales, roles }: Props) {
  return (
    <GuideLayout title={t('guide.title')} locale={locale} locales={locales} hrefFor={(next) => `/guide/${next}`}>
      <main id="guide-main" className="mx-auto max-w-[1040px] px-4 pt-12 pb-16 sm:px-5 sm:pt-16">
        <p className="text-[13px] font-semibold tracking-wide text-accent">{t('guide.kicker')}</p>
        <h1 className="mt-2 text-[32px] leading-tight font-semibold text-text sm:text-[38px]">{t('guide.title')}</h1>
        <p className="mt-3 max-w-[62ch] text-[16px] leading-8 text-text-muted">{t('guide.intro')}</p>

        <h2 className="mt-10 mb-4 text-[15px] font-semibold text-text-muted">{t('guide.choose')}</h2>

        <ul className="grid gap-4 md:grid-cols-3">
          {roles.map((role) => (
            <li key={role.key}>
              <Link
                href={`/guide/${locale}/${role.key}`}
                className="group flex h-full flex-col rounded-lg border border-border bg-surface p-5 shadow-card transition-[border-color,box-shadow] hover:border-primary/40 hover:shadow-lifted"
              >
                <span
                  aria-hidden="true"
                  className={[
                    'flex size-11 items-center justify-center rounded-lg',
                    role.key === 'admin' ? 'bg-night text-night-mark' : 'bg-primary-tint text-primary',
                  ].join(' ')}
                >
                  <Icon name={ICONS[role.key]} size={22} />
                </span>

                <span className="mt-4 text-[19px] font-semibold text-text">{role.title}</span>
                <span className="mt-1 text-[14px] text-text-muted">{role.who}</span>
                <span className="mt-3 flex-1 border-t border-border pt-3 text-[13.5px] leading-7 text-text-muted">
                  {role.covers}
                </span>

                <span className="mt-4 inline-flex items-center gap-1.5 text-[14px] font-medium text-primary">
                  {t('guide.open')}
                  <Icon name="chevron" size={14} className="transition-transform rtl:rotate-180 rtl:group-hover:-translate-x-0.5 ltr:group-hover:translate-x-0.5" />
                </span>
              </Link>
            </li>
          ))}
        </ul>

        <p className="mt-8 flex max-w-[70ch] gap-2.5 rounded-lg border border-border bg-surface-alt px-4 py-3 text-[14px] leading-7 text-text-muted">
          <Icon name="alert" size={18} className="mt-1 shrink-0 text-info" />
          {t('guide.not_sure')}
        </p>
      </main>
    </GuideLayout>
  );
}
