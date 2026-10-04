import { useEffect, useState } from 'react';
import { router, usePage, usePoll } from '@inertiajs/react';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Icon } from '@/Components/Icon';
import { t } from '@/lib/i18n';

/** ما يرسله `TenantCarouselDesigns::forScreen()` — T-173. */
export interface CarouselDesignsData {
  approved: Array<{ id: string; name: string | null; is_default: boolean }>;
  candidates: Array<{ id: string; name: string | null; rationale: string }>;
  status: { state: 'generating' | 'ready' | 'failed' | null; error: string | null; at: string | null };
  max_approved: number;
}

interface Props {
  designs: CarouselDesignsData;
  /** أصلُ المسارات: `…/carousel-designs`، وتحته التوليد والمعاينة والأفعال. */
  base: string;
  /** اسمُ الخاصيّة في الصفحة، لتتحدّث وحدها أثناء التوليد. */
  prop: string;
  /** الجهةُ تعتمد وتحذف، والمشرفُ يرى ويولّد. */
  manage: boolean;
  locked?: boolean;
}

/**
 * قوالبُ كاروسيل الجهة — T-173.
 *
 * **المرشّحُ لا يُستعمل حتى يُعتمد**: التوليدُ يُخرج ثلاثةً على كاروسيل العيّنة،
 * والجهةُ تعتمد منها ما يشبهها، وأوّلُ المعتمدة افتراضيُّها. وكلُّ قالبٍ يُرى
 * في إطارٍ بالقالب الحقيقي لا بمحاكاة، كمعاينة الهوية.
 */
export function CarouselDesigns({ designs, base, prop, manage, locked = false }: Props) {
  const [busy, setBusy] = useState(false);
  const error = usePage<{ errors: Record<string, string> }>().props.errors?.[prop];
  const generating = designs.status.state === 'generating';

  const poll = usePoll(4_000, { only: [prop] }, { autoStart: false, keepAlive: false });

  useEffect(() => {
    if (generating) {
      poll.start();
    } else {
      poll.stop();
    }

    return () => poll.stop();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [generating]);

  function act(method: 'post' | 'delete', path: string): void {
    const options = { preserveScroll: true, only: [prop, 'errors'], onFinish: () => setBusy(false) };

    setBusy(true);

    if (method === 'delete') {
      router.delete(path, options);
    } else {
      router.post(path, {}, options);
    }
  }

  const hasAny = designs.approved.length > 0 || designs.candidates.length > 0;

  return (
    <Card
      title={t('common.carousel_designs.title')}
      action={
        locked ? null : (
          <Button
            variant={hasAny ? 'secondary' : 'primary'}
            loading={busy || generating}
            onClick={() => act('post', base)}
          >
            <Icon name="images" size={16} />
            {t(hasAny ? 'common.carousel_designs.regenerate' : 'common.carousel_designs.generate')}
          </Button>
        )
      }
    >
      <p className="text-[14px] leading-relaxed text-text-muted">
        {t(locked ? 'jobs.carousel.locked_body' : 'common.carousel_designs.hint')}
      </p>

      {/* الكلفةُ مكتوبةٌ لا مفترَضة — T-196. */}
      {locked ? null : (
        <p className="mt-2 text-[13px] text-text-faint">{t('common.carousel_designs.cost_hint')}</p>
      )}

      {error ? (
        <p className="mt-4 rounded-lg border border-danger/35 bg-danger/8 px-4 py-3 text-[14px] text-danger">{error}</p>
      ) : null}

      {generating ? (
        <p aria-live="polite" className="mt-4 rounded-lg border border-info/25 bg-info/6 px-4 py-3 text-[14px] text-text">
          {t('common.carousel_designs.generating')}
        </p>
      ) : null}

      {designs.status.state === 'failed' && designs.status.error !== null ? (
        <p className="mt-4 rounded-lg border border-danger/35 bg-danger/8 px-4 py-3 text-[14px] text-danger">
          {designs.status.error}
        </p>
      ) : null}

      {designs.candidates.length > 0 ? (
        <section className="mt-6">
          <h3 className="mb-3 text-[15px] font-semibold text-text">{t('common.carousel_designs.candidates_title')}</h3>
          <ul className="flex flex-col gap-5">
            {designs.candidates.map((design) => (
              <DesignRow
                key={design.id}
                base={base}
                id={design.id}
                name={design.name}
                note={design.rationale}
                actions={
                  manage ? (
                    <>
                      <Button
                        loading={busy}
                        disabled={designs.approved.length >= designs.max_approved}
                        onClick={() => act('post', `${base}/${design.id}/approve`)}
                      >
                        <Icon name="check" size={16} />
                        {t('common.carousel_designs.approve')}
                      </Button>
                      <Button variant="ghost" loading={busy} onClick={() => act('delete', `${base}/${design.id}`)}>
                        {t('common.carousel_designs.discard')}
                      </Button>
                    </>
                  ) : null
                }
              />
            ))}
          </ul>
        </section>
      ) : null}

      {designs.approved.length > 0 ? (
        <section className="mt-6">
          <h3 className="mb-3 text-[15px] font-semibold text-text">{t('common.carousel_designs.approved_title')}</h3>
          <ul className="flex flex-col gap-5">
            {designs.approved.map((design) => (
              <DesignRow
                key={design.id}
                base={base}
                id={design.id}
                name={design.name}
                badge={design.is_default ? t('common.carousel_designs.default_badge') : null}
                actions={
                  manage ? (
                    <>
                      {design.is_default ? null : (
                        <Button variant="secondary" loading={busy} onClick={() => act('post', `${base}/${design.id}/default`)}>
                          {t('common.carousel_designs.make_default')}
                        </Button>
                      )}
                      <Button variant="danger-soft" loading={busy} onClick={() => act('delete', `${base}/${design.id}`)}>
                        {t('common.carousel_designs.remove')}
                      </Button>
                    </>
                  ) : null
                }
              />
            ))}
          </ul>
        </section>
      ) : null}

      {!hasAny && !generating && !locked ? (
        <p className="mt-4 text-[13.5px] text-text-faint">{t('common.carousel_designs.empty')}</p>
      ) : null}
    </Card>
  );
}

/**
 * قالبٌ واحد على كاروسيل العيّنة، في إطارٍ يُمرَّر أفقياً.
 *
 * والقالبُ يصغّر نفسه تحت عرض ١٠٨٠ (`zoom:.34` في أنماطه)، فتُرى الشرائح
 * الثماني متجاورةً كما تُتصفَّح على إنستغرام.
 */
function DesignRow({
  base, id, name, note, badge, actions,
}: {
  base: string;
  id: string;
  name: string | null;
  note?: string;
  badge?: string | null;
  actions: React.ReactNode;
}) {
  const title = name ?? t('common.carousel_designs.unnamed');

  return (
    <li className="overflow-hidden rounded-lg border border-border">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-surface-alt px-4 py-3">
        <div className="flex min-w-0 items-center gap-2">
          <h4 className="truncate text-[15px] font-semibold text-text">{title}</h4>
          {badge ? (
            <span className="shrink-0 rounded border border-accent/40 px-1.5 py-0.5 text-[11px] text-accent">{badge}</span>
          ) : null}
        </div>
        <div className="flex flex-wrap items-center gap-2">{actions}</div>
      </div>

      {note ? <p className="px-4 pt-3 text-[13.5px] leading-relaxed text-text-muted">{note}</p> : null}

      <iframe
        src={`${base}/${id}/preview`}
        title={t('common.carousel_designs.preview_title', { name: title })}
        loading="lazy"
        className="block h-[500px] w-full border-0"
      />
    </li>
  );
}
