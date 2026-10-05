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

/**
 * طولُ المحاضرة نفسها — «ساعة و٥ دقائق». بجانب «استغرق» في الحصيلة، فيُقرأ
 * الفرقُ بين طول المادّة وزمن إعدادها.
 *
 * والساعةُ تُقال ساعةً لا «٦٠ دقيقة»: محاضرةٌ من ساعتين تُعرف بساعتيها.
 */
export function lengthLabel(seconds: number): string {
  let hours = Math.floor(seconds / 3600);
  let minutes = Math.round((seconds % 3600) / 60);

  if (minutes === 60) {
    hours += 1;
    minutes = 0;
  }

  if (hours === 0) {
    return durationLabel(seconds);
  }

  if (minutes === 0) {
    return plural('jobs.follow.hours', hours);
  }

  return t('jobs.live.wizard.tally.source_length', {
    hours: plural('jobs.follow.hours', hours),
    minutes: plural('jobs.follow.minutes', minutes),
  });
}
