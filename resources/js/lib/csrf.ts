/**
 * رمز CSRF لطلبات `fetch` — ما لا يمرّ بـ Inertia فلا يحمله من نفسه.
 *
 * وكان دالّةً داخل `Create.tsx` وحدها، فصار هنا حين احتاجته شاشةُ الهوية
 * أيضاً (T-85): نسختان من القراءة نفسها تفترقان يوم يتغيّر موضع الرمز.
 */
export function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}
