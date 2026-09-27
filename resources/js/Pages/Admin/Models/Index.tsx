import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { EmptyState } from '@/Components/EmptyState';
import { FieldGroup } from '@/Components/FieldGroup';
import { Icon } from '@/Components/Icon';
import { AuditList, type AuditRow } from '@/Pages/Admin/AuditList';
import { t } from '@/lib/i18n';

interface Config {
  id: number;
  stage: string;
  provider: string;
  model_id: string;
  max_tokens: number;
  thinking_level: string;
  fallback_provider: string | null;
  fallback_model_id: string | null;
  timeout_seconds: number;
  max_retries: number;
  on_exhausted: string;
  input_price_per_m: string;
  output_price_per_m: string;
  is_active: boolean;
  updated_at: string | null;
}

interface Props {
  configs: Config[];
  audit: AuditRow[];
}

/**
 * النماذج — SCREENS.md §د، والمهمّة T-21.
 *
 * «التغيير يسري فوراً **بلا نشر**». **وكلّ تعديل يُقيَّد باسم من فعله**:
 * تبديلُ نموذجٍ يغيّر جودة المنتج وكلفته معاً، وهذان أخطر ما فيه.
 */
export default function ModelsIndex({ configs, audit }: Props) {
  const [editing, setEditing] = useState<number | null>(null);

  return (
    <AdminLayout title={t('admin.models.title')} description={t('admin.models.subtitle')}>
      <div className="flex flex-col gap-5">
        <p className="flex items-center gap-2.5 rounded-lg border border-warning/35 bg-warning/8 px-4 py-3 text-[14px] text-text">
          <Icon name="alert" size={18} className="shrink-0 text-warning" />
          {t('admin.models.warning')}
        </p>

        {configs.length === 0 ? (
          <Card>
            <EmptyState title={t('admin.models.empty')} body={t('admin.models.empty_body')} />
          </Card>
        ) : (
          <ul className="flex flex-col gap-4">
            {configs.map((config) => (
              <li key={config.id}>
                {editing === config.id ? (
                  <EditForm config={config} onDone={() => setEditing(null)} />
                ) : (
                  <Summary config={config} onEdit={() => setEditing(config.id)} />
                )}
              </li>
            ))}
          </ul>
        )}

        <Card title={t('admin.audit.title')} flush>
          {audit.length === 0 ? (
            <EmptyState title={t('admin.audit.empty')} body={t('admin.dashboard.no_activity_body')} />
          ) : (
            <AuditList rows={audit.map((row) => ({ ...row, action: 'model_config.updated', sensitive: true }))} />
          )}
        </Card>
      </div>
    </AdminLayout>
  );
}

function Summary({ config, onEdit }: { config: Config; onEdit: () => void }) {
  return (
    <Card
      title={config.stage}
      action={
        <Button variant="secondary" onClick={onEdit}>
          {t('admin.models.edit')}
        </Button>
      }
    >
      <dl className="grid gap-x-6 gap-y-2.5 text-[13px] sm:grid-cols-2 lg:grid-cols-3">
        <Fact label={t('admin.models.provider')} value={config.provider} ltr />
        <Fact label={t('admin.models.model_id')} value={config.model_id} ltr />
        <Fact label={t('admin.models.max_tokens')} value={String(config.max_tokens)} />
        <Fact label={t('admin.models.timeout_seconds')} value={String(config.timeout_seconds)} />
        <Fact label={t('admin.models.max_retries')} value={String(config.max_retries)} />
        <Fact label={t('admin.models.on_exhausted')} value={config.on_exhausted} ltr />
        <Fact label={t('admin.models.fallback_model_id')} value={config.fallback_model_id ?? '—'} ltr />
        <Fact label={t('admin.models.input_price_per_m')} value={`$${config.input_price_per_m}`} />
        <Fact label={t('admin.models.output_price_per_m')} value={`$${config.output_price_per_m}`} />
      </dl>
    </Card>
  );
}

function EditForm({ config, onDone }: { config: Config; onDone: () => void }) {
  const form = useForm({
    provider: config.provider,
    model_id: config.model_id,
    max_tokens: config.max_tokens,
    thinking_level: config.thinking_level,
    fallback_provider: config.fallback_provider ?? '',
    fallback_model_id: config.fallback_model_id ?? '',
    timeout_seconds: config.timeout_seconds,
    max_retries: config.max_retries,
    on_exhausted: config.on_exhausted,
    input_price_per_m: config.input_price_per_m,
    output_price_per_m: config.output_price_per_m,
    is_active: config.is_active,
    note: '',
  });

  return (
    <Card title={config.stage}>
      <form
        onSubmit={(event) => {
          event.preventDefault();
          form.put(`/admin/models/${config.id}`, { preserveScroll: true, onSuccess: onDone });
        }}
        className="flex flex-col gap-4"
      >
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Text form={form} field="provider" ltr />
          <Text form={form} field="model_id" ltr />
          <Number_ form={form} field="max_tokens" />
          <Text form={form} field="thinking_level" ltr />
          <Text form={form} field="fallback_provider" ltr />
          <Text form={form} field="fallback_model_id" ltr />
          <Number_ form={form} field="timeout_seconds" />
          <Number_ form={form} field="max_retries" />

          <FieldGroup label={t('admin.models.on_exhausted')} error={form.errors.on_exhausted} required>
            <select
              name="on_exhausted"
              value={form.data.on_exhausted}
              onChange={(event) => form.setData('on_exhausted', event.target.value)}
              className="field"
            >
              <option value="fail">fail</option>
              <option value="fallback">fallback</option>
              <option value="queue">queue</option>
            </select>
          </FieldGroup>

          <Text form={form} field="input_price_per_m" ltr />
          <Text form={form} field="output_price_per_m" ltr />

          <label className="flex items-center gap-2 self-end pb-2 text-[14px] text-text">
            <input
              type="checkbox"
              name="is_active"
              checked={form.data.is_active}
              onChange={(event) => form.setData('is_active', event.target.checked)}
              className="size-4 accent-[var(--primary)]"
            />
            {t('admin.models.is_active')}
          </label>
        </div>

        <FieldGroup
          label={t('admin.models.note')}
          hint={t('admin.models.note_hint')}
          error={form.errors.note}
        >
          <input
            name="note"
            autoComplete="off"
            value={form.data.note}
            onChange={(event) => form.setData('note', event.target.value)}
            className="field"
          />
        </FieldGroup>

        <div className="flex gap-2">
          <Button type="submit" loading={form.processing}>{t('common.actions.save')}</Button>
          <Button type="button" variant="ghost" onClick={onDone}>{t('common.actions.cancel')}</Button>
        </div>
      </form>
    </Card>
  );
}

/* eslint-disable @typescript-eslint/no-explicit-any */
type AnyForm = { data: any; errors: any; setData: (field: any, value: any) => void };

function Text({ form, field, ltr = false }: { form: AnyForm; field: string; ltr?: boolean }) {
  return (
    <FieldGroup label={t(`admin.models.${field}`)} error={form.errors[field]}>
      <input
        name={field}
        dir={ltr ? 'ltr' : undefined}
        autoComplete="off"
        value={form.data[field] ?? ''}
        onChange={(event) => form.setData(field, event.target.value)}
        className={ltr ? 'field text-start' : 'field'}
      />
    </FieldGroup>
  );
}

function Number_({ form, field }: { form: AnyForm; field: string }) {
  return (
    <FieldGroup label={t(`admin.models.${field}`)} error={form.errors[field]} required>
      <input
        type="number"
        name={field}
        value={form.data[field]}
        onChange={(event) => form.setData(field, Number(event.target.value))}
        className="field nums-tabular"
      />
    </FieldGroup>
  );
}

function Fact({ label, value, ltr = false }: { label: string; value: string; ltr?: boolean }) {
  return (
    <div className="flex justify-between gap-3">
      <dt className="shrink-0 text-text-faint">{label}</dt>
      <dd
        dir={ltr ? 'ltr' : undefined}
        className={ltr ? 'truncate text-start text-text' : 'truncate nums-tabular text-text'}
      >
        {value}
      </dd>
    </div>
  );
}
