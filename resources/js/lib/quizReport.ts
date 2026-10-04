import { isArabic } from '@/lib/numerals';

/**
 * أرقامُ تقارير الاختبارات — T-201.
 *
 * **لاتينيةٌ مصفوفة** كأرقام الجداول (SCREENS.md §الخطوط): تُقارن وتُحاذى.
 */

/** الوقتُ بالدقائق والثواني — `3:07`. وما لا يُعرف «—». */
export function clock(seconds: number | null): string {
  if (seconds === null) {
    return '—';
  }

  const minutes = Math.floor(seconds / 60);

  return `${minutes}:${String(seconds % 60).padStart(2, '0')}`;
}

export function percent(value: number | null): string {
  return value === null ? '—' : `${value}%`;
}

/** التاريخُ بلسان اللوحة، قصيراً. */
export function when(iso: string | null): string {
  if (iso === null) {
    return '—';
  }

  const at = new Date(iso);

  return Number.isNaN(at.getTime())
    ? '—'
    : at.toLocaleDateString(isArabic() ? 'ar' : undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}
