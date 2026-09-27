import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { BrandMark, ProductFooter, Wordmark } from '@/Components/Brand';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { FieldGroup } from '@/Components/FieldGroup';
import { t } from '@/lib/i18n';
import { toArabicIndic } from '@/lib/numerals';

interface Kind {
  value: string;
  label: string;
  sla_hours: number;
}

interface Props {
  url: string;
  kinds: Kind[];
}

/**
 * نموذج الاعتراض العامّ — T-24، ودراسة المشروع المادة 15.
 *
 * **وصفحةٌ قائمة بذاتها بلا هيكل اللوحة**: من يصلها ليس مسجَّلاً، ولا معنى
 * لأن يرى قائمةً جانبية لحسابٍ لا يملكه.
 */
export default function Complaint({ url, kinds }: Props) {
  const [sent, setSent] = useState<string | null>(null);

  const form = useForm({
    kind: kinds[0]?.value ?? 'other',
    url,
    contact: '',
    detail: '',
  });

  const selected = kinds.find((kind) => kind.value === form.data.kind);

  function submit(event: React.FormEvent) {
    event.preventDefault();

    form.post('/complaint', {
      preserveScroll: true,
      onSuccess: () => {
        setSent(t('common.complaint.sent', { hours: toArabicIndic(selected?.sla_hours ?? 48) }));
        form.reset('contact', 'detail');
      },
    });
  }

  return (
    <div className="mx-auto flex min-h-screen max-w-2xl flex-col justify-center px-4 py-10">
      {/*
        الاعتراض يصل المنصّةَ لا الجهة، فتحمل الصفحةُ اسمَ من يستقبله — T-100.
        ومن جاء من صفحة مسجدٍ يعرف بهذا إلى من يكتب.
      */}
      <div className="mb-6 flex items-center gap-2 text-primary">
        <BrandMark size={26} />
        <Wordmark className="text-[22px]" />
      </div>

      <h1 className="mb-2 text-[24px] font-semibold text-text">{t('common.complaint.title')}</h1>
      <p className="mb-6 text-[15px] leading-relaxed text-text-muted">{t('common.complaint.intro')}</p>

      {sent !== null ? (
        <div role="status" className="mb-5 rounded-lg border border-success/40 bg-success/5 px-5 py-4">
          <p className="text-[15px] text-text">{sent}</p>
        </div>
      ) : null}

      <Card>
        <form onSubmit={submit} className="flex flex-col gap-4">
          <FieldGroup label={t('common.complaint.kind')} required error={form.errors.kind}>
            <select
              value={form.data.kind}
              onChange={(e) => form.setData('kind', e.target.value)}
              className="w-full rounded border border-border-strong bg-surface px-3 py-2 text-[15px]"
            >
              {kinds.map((kind) => (
                <option key={kind.value} value={kind.value}>{kind.label}</option>
              ))}
            </select>
          </FieldGroup>

          {/* المهلة تُقال **قبل** الإرسال لا بعده: وعدٌ يُعرف قبل الالتزام به. */}
          {selected !== undefined ? (
            <p className="-mt-2 text-[13px] text-text-muted">
              {t('common.complaint.sla', { hours: toArabicIndic(selected.sla_hours) })}
            </p>
          ) : null}

          <FieldGroup label={t('common.complaint.url')} required error={form.errors.url}>
            <input
              type="url"
              dir="ltr"
              value={form.data.url}
              onChange={(e) => form.setData('url', e.target.value)}
              className="w-full rounded border border-border-strong bg-surface px-3 py-2 text-left text-[15px]"
            />
          </FieldGroup>

          <FieldGroup label={t('common.complaint.contact')} required hint={t('common.complaint.contact_hint')} error={form.errors.contact}>
            <input
              type="text"
              value={form.data.contact}
              onChange={(e) => form.setData('contact', e.target.value)}
              className="w-full rounded border border-border-strong bg-surface px-3 py-2 text-[15px]"
            />
          </FieldGroup>

          <FieldGroup label={t('common.complaint.detail')} error={form.errors.detail}>
            <textarea
              rows={5}
              value={form.data.detail}
              onChange={(e) => form.setData('detail', e.target.value)}
              className="w-full rounded border border-border-strong bg-surface px-3 py-2 text-[15px]"
            />
          </FieldGroup>

          <div>
            <Button type="submit" loading={form.processing}>{t('common.complaint.submit')}</Button>
          </div>
        </form>
      </Card>

      <ProductFooter className="mt-8" />
    </div>
  );
}
