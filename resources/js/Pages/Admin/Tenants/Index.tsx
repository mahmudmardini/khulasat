import { useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { DataTable, type Column } from '@/Components/DataTable';
import { EmptyState } from '@/Components/EmptyState';
import { FieldGroup } from '@/Components/FieldGroup';
import { UnverifiedPolicyChoice, type UnverifiedPolicy } from '@/Components/UnverifiedPolicyChoice';
import { Icon } from '@/Components/Icon';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';

interface TenantRow {
  id: number;
  name_ar: string;
  slug: string;
  plan: string;
  status: string;
  monthly_quota: number;
  jobs_count: number;
}

interface Props {
  tenants: { data: TenantRow[]; current_page: number; last_page: number; total: number };
  filters: { search: string | null; status: string | null };
}

/**
 * الجهات — SCREENS.md §أ من لوحة المشرف، والمهمّة T-21.
 *
 * **وهذه هي آلية التفعيل كلُّها**: لا بوّابة دفع في هذه المرحلة، والجهة
 * تحوّل إلى الحساب البنكي، ثمّ تُرفع حدودها من هنا.
 */
export default function TenantsIndex({ tenants, filters }: Props) {
  const [creating, setCreating] = useState(false);

  const columns: Array<Column<TenantRow>> = [
    {
      key: 'name',
      header: t('admin.tenants.name_ar'),
      render: (row) => (
        <Link
          href={`/admin/tenants/${row.id}`}
          className="font-medium text-text underline-offset-4 hover:text-primary hover:underline"
        >
          {row.name_ar}
        </Link>
      ),
    },
    {
      key: 'slug',
      header: t('admin.tenants.slug'),
      render: (row) => <span dir="ltr" className="text-text-muted">{row.slug}</span>,
    },
    { key: 'plan', header: t('admin.tenants.plan'), render: (row) => row.plan },
    {
      key: 'quota',
      header: t('admin.tenants.monthly_quota'),
      numeric: true,
      render: (row) => (row.monthly_quota === 0 ? '—' : row.monthly_quota),
    },
    {
      key: 'jobs',
      header: t('admin.tenants.jobs_count'),
      numeric: true,
      render: (row) => row.jobs_count,
    },
    {
      key: 'status',
      header: t('admin.jobs.state'),
      render: (row) => (
        <span
          className={cn(
            'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[13px]',
            row.status === 'suspended'
              ? 'border-warning/30 bg-warning/8 text-warning'
              : 'border-success/25 bg-success/6 text-success',
          )}
        >
          <span aria-hidden="true" className="size-1.5 rounded-full bg-current" />
          {t(`admin.status.${row.status === 'suspended' ? 'suspended' : 'active'}`)}
        </span>
      ),
    },
  ];

  return (
    <AdminLayout
      title={t('admin.tenants.title')}
      description={t('admin.tenants.subtitle')}
      action={
        <Button onClick={() => setCreating((open) => !open)}>
          <Icon name="create" size={17} />
          {t('admin.tenants.create')}
        </Button>
      }
    >
      <div className="flex flex-col gap-5">
        {creating ? <CreateForm onDone={() => setCreating(false)} /> : null}

        <div>
          <div className="mb-4 flex flex-wrap items-center gap-2">
            <div className="relative min-w-[220px] flex-1">
              <Icon
                name="search"
                size={17}
                className="pointer-events-none absolute inset-y-0 start-3 my-auto text-text-faint"
              />
              <input
                type="search"
                name="search"
                defaultValue={filters.search ?? ''}
                placeholder={t('admin.tenants.search')}
                aria-label={t('admin.tenants.search')}
                onBlur={(event) =>
                  router.get('/admin/tenants', { search: event.target.value }, { preserveState: true, replace: true })}
                className="field ps-10"
              />
            </div>

            <select
              value={filters.status ?? ''}
              aria-label={t('admin.jobs.state')}
              onChange={(event) =>
                router.get('/admin/tenants', { status: event.target.value }, { preserveState: true, replace: true })}
              className={cn('field w-auto shrink-0 text-[14px]', filters.status !== null && 'border-primary/40 bg-primary/5 font-medium text-primary')}
            >
              <option value="">{t('admin.jobs.all')}</option>
              <option value="active">{t('admin.status.active')}</option>
              <option value="suspended">{t('admin.status.suspended')}</option>
            </select>
          </div>

          <Card flush>
            <DataTable
              columns={columns}
              rows={tenants.data}
              rowKey={(row) => row.id}
              highlight={(row) => row.status === 'suspended'}
              empty={
                <EmptyState
                  title={t('admin.tenants.empty')}
                  body={t('admin.tenants.empty_body')}
                  action={<Button onClick={() => setCreating(true)}>{t('admin.tenants.create')}</Button>}
                />
              }
            />
          </Card>
        </div>
      </div>
    </AdminLayout>
  );
}

/**
 * إنشاء جهة ومالكها في خطوة — SCREENS.md §أ.
 *
 * **والمعرّف اللاتيني لا يُبدَّل بعد النشر**، فهو في رابط كل صفحة نشرتها
 * الجهة. ولذلك يُقال ذلك عند إدخاله لا بعد فوات الأوان.
 */
function CreateForm({ onDone }: { onDone: () => void }) {
  const form = useForm({
    name_ar: '',
    slug: '',
    owner_name: '',
    owner_email: '',
    // الافتراضُ مختارٌ ظاهراً لا مفترَضاً صامتاً — T-163.
    on_unverified: 'disclose' as UnverifiedPolicy,
  });

  return (
    <Card title={t('admin.tenants.create')}>
      <form
        onSubmit={(event) => {
          event.preventDefault();
          form.post('/admin/tenants', { onSuccess: onDone });
        }}
        className="grid gap-4 sm:grid-cols-2"
      >
        <FieldGroup label={t('admin.tenants.name_ar')} error={form.errors.name_ar} required>
          <input
            name="name_ar"
            autoComplete="organization"
            value={form.data.name_ar}
            onChange={(event) => form.setData('name_ar', event.target.value)}
            className="field"
          />
        </FieldGroup>

        <FieldGroup
          label={t('admin.tenants.slug')}
          hint={t('admin.tenants.slug_hint')}
          error={form.errors.slug}
          required
        >
          <input
            name="slug"
            dir="ltr"
            autoComplete="off"
            value={form.data.slug}
            onChange={(event) => form.setData('slug', event.target.value)}
            className="field text-start"
          />
        </FieldGroup>

        <FieldGroup label={t('admin.tenants.owner_name')} error={form.errors.owner_name} required>
          <input
            name="owner_name"
            autoComplete="off"
            value={form.data.owner_name}
            onChange={(event) => form.setData('owner_name', event.target.value)}
            className="field"
          />
        </FieldGroup>

        <FieldGroup label={t('admin.tenants.owner_email')} error={form.errors.owner_email} required>
          <input
            name="owner_email"
            type="email"
            dir="ltr"
            autoComplete="off"
            value={form.data.owner_email}
            onChange={(event) => form.setData('owner_email', event.target.value)}
            className="field text-start"
          />
        </FieldGroup>

        <fieldset className="flex flex-col gap-2 sm:col-span-2">
          <legend className="text-[14px] font-medium text-text">{t('admin.verification.legend')}</legend>
          <p className="text-[12px] text-text-muted">{t('admin.verification.hint')}</p>
          <UnverifiedPolicyChoice
            name="on_unverified"
            value={form.data.on_unverified}
            onChange={(policy) => form.setData('on_unverified', policy)}
          />
          {form.errors.on_unverified ? (
            <p className="text-[12px] text-danger">{form.errors.on_unverified}</p>
          ) : null}
        </fieldset>

        <div className="flex gap-2 sm:col-span-2">
          <Button type="submit" loading={form.processing}>{t('admin.tenants.create')}</Button>
          <Button type="button" variant="ghost" onClick={onDone}>{t('common.actions.cancel')}</Button>
        </div>
      </form>
    </Card>
  );
}
