import { useEffect, useState } from 'react';

const QUERY = '(prefers-reduced-motion: reduce)';

/**
 * أطلب المستخدمُ تقليل الحركة؟ — SCREENS.md §إتاحة.
 *
 * `app.css` يُقصّر كلّ حركةٍ في CSS لمن طلب. وهذا لما يتحرّك بالشيفرة —
 * الكتابةُ حرفاً حرفاً والتبديلُ الآليّ — فلا تُطفئه ورقةُ الأنماط.
 */
export function useReducedMotion(): boolean {
  const [reduced, setReduced] = useState(
    () => typeof window !== 'undefined' && window.matchMedia?.(QUERY).matches === true,
  );

  useEffect(() => {
    const list = window.matchMedia?.(QUERY);

    if (list === undefined) {
      return undefined;
    }

    const update = () => setReduced(list.matches);
    list.addEventListener('change', update);

    return () => list.removeEventListener('change', update);
  }, []);

  return reduced;
}
