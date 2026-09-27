import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

/**
 * شريطُ الأفعال الملتصق بأسفل المحتوى — T-94، وأُصلح شكلُه في T-102.
 *
 * **بساطٌ صلبٌ إلى حافّة المحتوى، لا بطاقةٌ طافية.** كان الشريط بطاقةً
 * ملتصقة وحدها، فيمرّ المحتوى تحتها ويُرى من حولها ومن تحتها في حشوة
 * `main` السفلى — فتبدو بطاقةً وقعت فوق بطاقة. والبساطُ يُغطّي ما تحته
 * إلى الحافّة، فيُقرأ ذيلاً للشاشة كما هو.
 *
 * ★ **والالتصاقُ يُحاذي صندوقَ المحتوى لا حافّة التمرير**: قيس فوُجد الشريط
 * يقف فوق الحافّة بمقدار حشوة `main` السفلى (`py-6` و`sm:py-8`)، فيمرّ
 * المحتوى في ذلك الشريط ويُرى تحته. **وهامشٌ سالب لا يُزحزحه** — المتصفّح
 * يُحاذي صندوق الحدّ لا صندوق الهامش — فيمتدّ البساط بـ`::after` بقدر تلك
 * الحشوة نفسها (`h-6` و`sm:h-8` من سلّم الجذر عينه)، فيُغطّى ما تحته.
 */
export function StickyBar({ className, children }: { className?: string; children: ReactNode }) {
  return (
    <div
      className={cn(
        'relative sticky bottom-0 z-10 bg-bg pt-3 after:absolute after:inset-x-0 after:top-full after:h-6 after:bg-bg sm:after:h-8',
        className,
      )}
    >
      <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-surface px-4 py-3 shadow-lifted">
        {children}
      </div>
    </div>
  );
}
