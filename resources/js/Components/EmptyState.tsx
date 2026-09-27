import type { ReactNode } from 'react';

interface Props {
  title: string;
  body: string;
  action?: ReactNode;
}

/**
 * الحالة الفارغة — §القواعد العامّة: «كل حالة فارغة لها رسالة وفعل.
 * لا صفحة بيضاء أبداً».
 *
 * ولذلك `title` و`body` مطلوبان في النوع لا اختياريان: الحالة الفارغة بلا
 * شرحٍ تمرّ في المراجعة ولا تمرّ عند المستخدم.
 */
export function EmptyState({ title, body, action }: Props) {
  return (
    <div className="flex flex-col items-center gap-3 px-6 py-14 text-center">
      <h3 className="text-[17px] font-semibold text-text">{title}</h3>
      <p className="max-w-md text-[15px] leading-relaxed text-text-muted">{body}</p>
      {action !== undefined ? <div className="mt-2">{action}</div> : null}
    </div>
  );
}
