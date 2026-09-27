import { t } from '@/lib/i18n';
import { plural } from '@/lib/plural';

/**
 * مدّةٌ بصيغة عددها — T-92: «٤٢ ثانية» · «دقيقتان» · «٤ دقائق».
 *
 * بالثواني تحت الدقيقة: مرحلةٌ تمّت في أربعين ثانية تُقرأ «أقلّ من دقيقة»
 * بلا فائدة، والعدُّ الحيّ للجارية يحتاج الثانية ليُرى أنّه يتحرّك.
 */
export function durationLabel(seconds: number): string {
  if (seconds < 1) {
    return t('jobs.live.wizard.instant');
  }

  if (seconds < 60) {
    return plural('jobs.live.wizard.seconds', Math.round(seconds));
  }

  return plural('jobs.follow.minutes', Math.round(seconds / 60));
}
