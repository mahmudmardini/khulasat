import type { PageProps } from '@inertiajs/core';
import type { Translations } from '@/lib/i18n';

/**
 * الـ props المشتركة التي يبثّها HandleInertiaRequests مع كل استجابة.
 * المفاتيح snake_case مطابقةً لقاعدة البيانات — انظر CLAUDE.md §1.
 */
export interface SharedProps extends PageProps {
  app_name: string;
  /** رمز CSRF — يتجدّد مع كلّ استجابة، فلا يبيت بعد تجديد الجلسة (T-133). */
  csrf_token: string;
  locale: string;
  /** اتّجاه الصفحة — `rtl` للعربية و`ltr` لغيرها (T-133). */
  direction: 'rtl' | 'ltr';
  /** نصوص `lang/<لغة>` — SCREENS.md: لا نصّ مكتوب داخل مكوّن. */
  lang: Translations;
  flash: {
    message: string | null;
    /** رابط الدعوة — يُعرض مرّةً واحدة بعد إنشائها (T-33). */
    invitation_url?: string | null;
  };
  /** حصّة الجهة لشريط الحصّة السفليّ. تغيب قبل تسجيل الدخول. */
  quota?: {
    used: number;
    limit: number;
  } | null;
  /**
   * جلسة الانتحال القائمة — T-21. تُبثّ مع كلّ استجابة لا في شاشةٍ بعينها،
   * فالشريط التحذيري يجب أن يُرى في كلّ شاشةٍ يتنقّل إليها المشرف.
   */
  impersonating?: {
    tenant: string;
    since: string;
  } | null;
  /**
   * وقفُ سقف الإنفاق — T-151. يُبثّ مع كلّ استجابة، ويُرسم بشريطٍ في
   * `AdminLayout` وحدها. `null` ما دام الطابور غير موقوف.
   */
  spend_cap_halted?: {
    reason: string;
    halted_at: string | null;
  } | null;
  auth?: {
    user: { name: string; role: string; tenant: string | null } | null;
    /** المشرف العام على حارسٍ آخر، فلا يظهر في `user`. */
    admin?: { name: string; email: string } | null;
  };
}
