import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import { AppLayout } from '@/Layouts/AppLayout';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { ConfirmDialog } from '@/Components/ConfirmDialog';
import { FieldGroup } from '@/Components/FieldGroup';
import { Icon } from '@/Components/Icon';
import { Segmented } from '@/Components/Segmented';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import type { SharedProps } from '@/types/inertia';

interface Member {
  id: number;
  name: string;
  email: string;
  role: string;
  is_self: boolean;
  is_last_owner: boolean;
}

interface Invitation {
  id: number;
  email: string;
  role: string;
  expired: boolean;
  expires_at: string;
}

interface Props {
  members: Member[];
  invitations: Invitation[];
  roles: string[];
  can_manage: boolean;
  tenant: { name: string };
}

type Asking =
  | { kind: 'remove'; member: Member }
  | { kind: 'revoke'; invitation: Invitation }
  | { kind: 'role'; member: Member; role: string };

/**
 * ما يملكه كلُّ دور — T-97. **كما في `role_hints` نصّاً**، والخادمُ هو
 * الحاكم: الجدول يشرح ولا يمنح.
 */
const ABILITIES: ReadonlyArray<{ key: string; roles: readonly string[] }> = [
  { key: 'view', roles: ['owner', 'editor', 'viewer'] },
  { key: 'create', roles: ['owner', 'editor'] },
  { key: 'review', roles: ['owner', 'editor'] },
  { key: 'publish', roles: ['owner', 'editor'] },
  { key: 'brand', roles: ['owner'] },
  { key: 'billing', roles: ['owner'] },
  { key: 'team', roles: ['owner'] },
];

/**
 * الفريق — SCREENS.md §10، والمهمّة T-33، وأُعيد تصميمها في T-97.
 *
 * ★ **وأثرُ غيابها أنّ الجهة حسابٌ واحد.** فمن أراد محرّراً ثانياً راسلَنا،
 * **ومن ترك موظّفٌ عندها لم تستطع نزع وصوله** — حسابٌ قائمٌ لمن فارق الجهة،
 * ينشر باسمها ولا يُقفل إلّا منّا.
 *
 * ★ **وما تغيّر في T-97 — مراجعة مالك المنتج، ١١ أيلول ٢٠٢٦:**
 * - **الدعوة فعلٌ رئيسيّ** في رأس الشاشة، وكانت في آخرها بتخطيطٍ مضطرب.
 * - **المعلّقةُ صفوفٌ «بانتظار القبول» في قائمة الأعضاء**، لا بطاقةٌ فارغة.
 * - **تبديلُ الصلاحية خلف تأكيدٍ يذكر الأثر** — كان يقع بمجرّد تبديل القائمة.
 * - جدولٌ مطويّ بما يملكه كلُّ دور، وما يحدث بعد إرسال الرابط.
 */
export default function Team({ members, invitations, roles, can_manage, tenant }: Props) {
  const page = usePage<SharedProps & { errors: Record<string, string> }>();
  const link = page.props.flash.invitation_url ?? null;

  const [inviting, setInviting] = useState(false);
  const [asking, setAsking] = useState<Asking | null>(null);

  function confirmAction(): void {
    if (asking === null) {
      return;
    }

    if (asking.kind === 'remove') {
      router.delete(`/panel/settings/team/members/${asking.member.id}`, { preserveScroll: true });
    } else if (asking.kind === 'revoke') {
      router.delete(`/panel/settings/team/invitations/${asking.invitation.id}`, { preserveScroll: true });
    } else {
      router.put(`/panel/settings/team/members/${asking.member.id}`, { role: asking.role }, { preserveScroll: true });
    }

    setAsking(null);
  }

  return (
    <AppLayout
      title={t('team.title')}
      description={t('team.subtitle', { tenant: tenant.name })}
      action={
        can_manage && !inviting ? (
          <Button onClick={() => setInviting(true)}>
            <Icon name="create" size={17} />
            {t('team.invite')}
          </Button>
        ) : undefined
      }
    >
      <div className="flex flex-col gap-5">
        {Object.entries(page.props.errors).map(([key, message]) => (
          <p
            key={key}
            className="rounded-lg border border-danger/35 bg-danger/8 px-4 py-3 text-[14px] text-danger"
          >
            {message}
          </p>
        ))}

        {link !== null ? <InviteLink url={link} /> : null}

        {can_manage && inviting ? (
          <InviteForm roles={roles} onDone={() => setInviting(false)} onCancel={() => setInviting(false)} />
        ) : null}

        <Card title={t('team.members')} flush>
          <ul className="divide-y divide-border">
            {members.map((member) => (
              <MemberRow
                key={member.id}
                member={member}
                roles={roles}
                canManage={can_manage}
                onRole={(role) => setAsking({ kind: 'role', member, role })}
                onRemove={() => setAsking({ kind: 'remove', member })}
              />
            ))}

            {/* المعلّقةُ في القائمة نفسها — من دُعي عضوٌ قادمٌ لا بندٌ في بطاقةٍ أخرى. */}
            {can_manage
              ? invitations.map((invitation) => (
                <PendingRow
                  key={`invitation-${invitation.id}`}
                  invitation={invitation}
                  onRevoke={() => setAsking({ kind: 'revoke', invitation })}
                />
              ))
              : null}
          </ul>
        </Card>

        <RolesTable roles={roles} />
      </div>

      <ConfirmDialog
        open={asking !== null}
        title={dialogTitle(asking)}
        consequence={dialogConsequence(asking)}
        confirmLabel={dialogTitle(asking)}
        // تبديلُ الصلاحية يُستردّ بتبديلٍ آخر، فلا «لا يمكن التراجع» عليه.
        reversible={asking?.kind === 'role'}
        onConfirm={confirmAction}
        onCancel={() => setAsking(null)}
      />
    </AppLayout>
  );
}

/**
 * ★ **الرابط يُعرض مرّةً واحدة**، فالمحفوظ عندنا تعميتُه لا هو.
 *
 * ويُقال ذلك صراحةً، ويُقال ما يحدث بعده (T-97): من ظنّه باقياً أغلق الشاشة
 * ثمّ بحث عنه فلم يجده، ومن لم يعرف ما بعده لم يعرف متى يسأل صاحبه.
 */
function InviteLink({ url }: { url: string }) {
  const [copied, setCopied] = useState(false);

  async function copy(): Promise<void> {
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      // متصفّحٌ منع الحافظة: الرابط ظاهرٌ أمام العين ويُحدَّد باليد.
    }
  }

  return (
    <div className="rounded-lg border border-success/30 bg-success/6 p-4">
      <p className="flex items-center gap-2 text-[15px] font-medium text-text">
        <Icon name="check" size={17} className="text-success" />
        {t('team.link_ready')}
      </p>

      <div className="mt-3 flex flex-wrap items-center gap-2">
        <code
          dir="ltr"
          className="min-w-0 flex-1 truncate rounded-md border border-border bg-surface px-3 py-2 text-start text-[13px] text-text"
        >
          {url}
        </code>

        <Button onClick={copy}>
          <Icon name={copied ? 'check' : 'copy'} size={16} />
          {t(copied ? 'team.link_copied' : 'team.link_copy')}
        </Button>
      </div>

      <p className="mt-3 text-[13px] leading-relaxed text-text-muted">{t('team.link_once')}</p>
      <p className="mt-1 text-[13px] leading-relaxed text-text-muted">{t('team.link_next')}</p>
    </div>
  );
}

/** حرفٌ أوّل — يُعرف العضو بنظرةٍ قبل اسمه، كما في رأس اللوحة. */
function Monogram({ name, muted = false }: { name: string; muted?: boolean }) {
  return (
    <span
      aria-hidden="true"
      className={cn(
        'flex size-9 shrink-0 items-center justify-center rounded-full text-[15px] font-semibold',
        muted ? 'border border-dashed border-border-strong text-text-faint' : 'bg-primary/10 text-primary',
      )}
    >
      {[...name.trim()][0] ?? ''}
    </span>
  );
}

function MemberRow({
  member, roles, canManage, onRole, onRemove,
}: {
  member: Member;
  roles: string[];
  canManage: boolean;
  onRole: (role: string) => void;
  onRemove: () => void;
}) {
  const manageable = canManage && !member.is_last_owner && !member.is_self;

  return (
    <li className="flex flex-wrap items-center gap-x-3 gap-y-2 px-5 py-3.5">
      <Monogram name={member.name} />

      <div className="min-w-[min(100%,12rem)] flex-1">
        <p className="flex items-center gap-2 text-[15px] text-text">
          <span className="truncate">{member.name}</span>
          {member.is_self ? (
            <span className="shrink-0 rounded border border-border px-1.5 py-0.5 text-[11px] text-text-faint">
              {t('team.you')}
            </span>
          ) : null}
        </p>
        <p dir="ltr" className="mt-0.5 truncate text-start text-[13px] text-text-muted">
          {member.email}
        </p>
      </div>

      {/*
        **والمالك الأخير لا تُبدَّل صلاحيته ولا يُنزع** — نزعُه يُقفل الجهة
        على نفسها. ويُقال لماذا ولا يُخفى: من لم يجد الزرّ ظنّ المنتج ناقصاً.
      */}
      {manageable ? (
        <div className="flex flex-wrap items-center gap-2">
          <select
            value={member.role}
            aria-label={t('team.change_role')}
            onChange={(event) => onRole(event.target.value)}
            className="field w-auto py-1.5 text-[14px]"
          >
            {roles.map((role) => (
              <option key={role} value={role}>
                {t(`team.roles.${role}`)}
              </option>
            ))}
          </select>

          <Button variant="danger-soft" onClick={onRemove}>
            {t('team.remove')}
          </Button>
        </div>
      ) : (
        <span
          title={member.is_last_owner ? t('team.errors.last_owner') : undefined}
          className={cn(
            'rounded-full border px-3 py-1 text-[13px]',
            member.role === 'owner'
              ? 'border-primary/30 bg-primary/8 text-primary'
              : 'border-border bg-surface-alt text-text-muted',
          )}
        >
          {t(`team.roles.${member.role}`)}
        </span>
      )}
    </li>
  );
}

function PendingRow({ invitation, onRevoke }: { invitation: Invitation; onRevoke: () => void }) {
  return (
    <li className="flex flex-wrap items-center gap-x-3 gap-y-2 bg-surface-alt/60 px-5 py-3.5">
      <Monogram name={invitation.email} muted />

      <div className="min-w-[min(100%,12rem)] flex-1">
        <p dir="ltr" className="truncate text-start text-[15px] text-text">
          {invitation.email}
        </p>
        <p className="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-[12.5px] text-text-faint">
          <span>{t(`team.roles.${invitation.role}`)}</span>
          <span aria-hidden="true">·</span>
          <span className={invitation.expired ? 'text-danger' : 'text-warning'}>
            {invitation.expired ? t('team.expired') : t('team.awaiting')}
          </span>
          {invitation.expired ? null : (
            <>
              <span aria-hidden="true">·</span>
              <span>{t('team.expires', { date: day(invitation.expires_at) })}</span>
            </>
          )}
        </p>
      </div>

      <Button variant="ghost" onClick={onRevoke}>
        {t('team.invite_revoke')}
      </Button>
    </li>
  );
}

/**
 * الدعوة — سطرٌ واحد: البريدُ ثمّ الصلاحيةُ ووصفُها بعرضٍ كامل تحتها (T-97).
 * وكان الوصف محشوراً تحت قائمةٍ منسدلة ضيّقة، والزرّ لا يحاذي الحقلين.
 */
function InviteForm({ roles, onDone, onCancel }: { roles: string[]; onDone: () => void; onCancel: () => void }) {
  const form = useForm({ email: '', role: 'editor' });

  return (
    <Card title={t('team.invite')}>
      <form
        onSubmit={(event) => {
          event.preventDefault();
          form.post('/panel/settings/team/invitations', {
            preserveScroll: true,
            onSuccess: () => {
              form.reset('email');
              onDone();
            },
          });
        }}
        className="flex flex-col gap-4"
      >
        <FieldGroup label={t('team.invite_email')} error={form.errors.email} required>
          <input
            type="email"
            name="email"
            dir="ltr"
            autoComplete="off"
            autoFocus
            value={form.data.email}
            onChange={(event) => form.setData('email', event.target.value)}
            className="field text-start"
          />
        </FieldGroup>

        <div>
          <p className="mb-2 text-[14px] font-medium text-text">{t('team.invite_role')}</p>
          <Segmented
            legend={t('team.invite_role')}
            value={form.data.role}
            onChange={(role) => form.setData('role', role)}
            options={roles.map((role) => ({ key: role, label: t(`team.roles.${role}`) }))}
          />
          <p className="mt-2 text-[13px] leading-relaxed text-text-muted">{t(`team.role_hints.${form.data.role}`)}</p>
          {form.errors.role ? <p role="alert" className="mt-1 text-[13px] text-danger">{form.errors.role}</p> : null}
        </div>

        <div className="flex flex-wrap items-center gap-2 border-t border-border pt-4">
          <Button type="submit" loading={form.processing}>
            {t('team.invite_submit')}
          </Button>
          <Button variant="ghost" onClick={onCancel}>
            {t('team.invite_cancel')}
          </Button>
        </div>
      </form>
    </Card>
  );
}

/** ما يملكه كلُّ دور — مطويٌّ، يُفتح لمن يسأل قبل أن يدعو. */
function RolesTable({ roles }: { roles: string[] }) {
  return (
    <details className="group rounded-lg border border-border bg-surface shadow-card">
      <summary className="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-3 text-[15px] font-medium text-text">
        {t('team.roles_table')}
        <Icon name="chevron" size={16} className="rotate-90 text-text-faint transition-transform group-open:-rotate-90" />
      </summary>

      <div className="overflow-x-auto border-t border-border">
        <table className="w-full text-[13.5px]">
          <thead>
            <tr className="bg-surface-alt">
              <th scope="col" className="px-5 py-2.5 text-start font-medium text-text-muted">{t('team.ability')}</th>
              {roles.map((role) => (
                <th key={role} scope="col" className="px-4 py-2.5 text-center font-medium text-text-muted">
                  {t(`team.roles.${role}`)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {ABILITIES.map((ability) => (
              <tr key={ability.key} className="border-t border-border">
                <th scope="row" className="px-5 py-2.5 text-start font-normal text-text">
                  {t(`team.abilities.${ability.key}`)}
                </th>
                {roles.map((role) => {
                  const allowed = ability.roles.includes(role);

                  return (
                    <td key={role} className="px-4 py-2.5 text-center">
                      {allowed ? (
                        <Icon name="check" size={16} className="inline text-success" />
                      ) : (
                        <span aria-hidden="true" className="text-text-faint">—</span>
                      )}
                      <span className="sr-only">{t(allowed ? 'team.yes' : 'team.no')}</span>
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </details>
  );
}

function dialogTitle(asking: Asking | null): string {
  if (asking === null) {
    return '';
  }

  return t({ remove: 'team.remove', revoke: 'team.invite_revoke', role: 'team.role_change' }[asking.kind]);
}

/** التأكيد يذكر الأثر بالضبط — §القواعد العامّة: «كل فعلٍ مدمّر له تأكيد». */
function dialogConsequence(asking: Asking | null): string {
  if (asking === null) {
    return '';
  }

  if (asking.kind === 'remove') {
    return t('team.remove_confirm', { name: asking.member.name });
  }

  if (asking.kind === 'revoke') {
    return t('team.invite_revoke_confirm', { email: asking.invitation.email });
  }

  return t('team.role_change_confirm', {
    name: asking.member.name,
    from: t(`team.roles.${asking.member.role}`),
    to: t(`team.roles.${asking.role}`),
    hint: t(`team.role_hints.${asking.role}`),
  });
}

/** اليوم بتقويم من يقرأ — والخادم لا يعرف ساعته. */
function day(iso: string): string {
  const at = new Date(iso);

  return Number.isNaN(at.getTime())
    ? '—'
    : at.toLocaleDateString('ar', { year: 'numeric', month: 'long', day: 'numeric' });
}
