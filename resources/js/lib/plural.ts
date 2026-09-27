import { t } from '@/lib/i18n';
import { isArabic, tabular, toArabicIndic } from '@/lib/numerals';

/**
 * صيغ العدد العربية — T-83.
 *
 * وكانت «استغرق ٤ دقيقة» تُكتب بصيغةٍ واحدة لكلّ عدد. والعربية تفرّق:
 * دقيقة واحدة · دقيقتان · ٣–١٠ دقائق · ١١–٩٩ دقيقة · ١٠٠ دقيقة. وخطأٌ
 * نحويّ في كلّ مهمّةٍ بين ثلاث دقائق وعشر يُقرأ منتجاً لم يُراجَع.
 */
export type PluralForm = 'one' | 'two' | 'few' | 'many' | 'other';

export function arabicPluralForm(count: number): PluralForm {
  const n = Math.abs(Math.trunc(count));

  if (n === 1) {
    return 'one';
  }

  if (n === 2) {
    return 'two';
  }

  const rest = n % 100;

  if (rest >= 3 && rest <= 10) {
    return 'few';
  }

  return rest >= 11 ? 'many' : 'other';
}

/**
 * النصّ بصيغة عدده، وأرقامُه عربية هندية — للجُمل النثرية.
 *
 * يطلب `key_one` · `key_two` · `key_few` · `key_many` · `key_other`، وما
 * غاب منها سقط إلى `key_many`. والفاصل بين الآلاف `٬` العربية لا الفاصلة.
 */
export function plural(key: string, count: number): string {
  const arabic = isArabic();
  const form = arabic ? arabicPluralForm(count) : intlPluralForm(count);
  const formatted = arabic ? tabular(count).replace(/,/g, '٬') : tabular(count);
  const exact = t(`${key}_${form}`, { count: formatted });
  const text = exact === `${key}_${form}` ? t(`${key}_many`, { count: formatted }) : exact;

  return toArabicIndic(text);
}

/**
 * صيغُ العدد لغير العربية — T-133.
 *
 * **ومسارُ العربية لم يُمسّ**: قاعدتُها مكتوبةٌ باليد أعلاه ومُختبَرة، ولا
 * تُبدَّل بـ`Intl` لأنّ هذه تُرجع `zero` للعربية وليس في الملفّات مفتاحٌ لها.
 *
 * وما تُرجعه `Intl` من صيغٍ هو نفسُه ما تحمله مفاتيحُنا: الإنجليزية
 * `one/other`، والتركية `one/other`، والروسية `one/few/many`. وما لم يوجد
 * مفتاحُه سقط إلى `_many` كما في {@see plural}.
 */
function intlPluralForm(count: number): PluralForm {
  const locale = typeof document === 'undefined' ? 'en' : document.documentElement.lang || 'en';

  try {
    return new Intl.PluralRules(locale).select(count) as PluralForm;
  } catch {
    return 'other';
  }
}
