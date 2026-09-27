/**
 * اقتراحُ التاريخ الآخر — SCREENS.md §3-ج: «إدخال أحدهما يقترح الآخر،
 * والاقتراح قابل للتعديل».
 *
 * **اقتراحٌ لا تحويل.** وترحيلُ `lectures` يقول: «الهجري نصّ لا تاريخ،
 * ولا تحويل آلي بين التقويمين **لأنّ بداية الشهر تختلف باختلاف الجهة**».
 * فهذه تملأ الحقل بما يغلب، ويبقى الحقل نصّاً يكتب فيه صاحب الجهة ما
 * تعتمده جهته — والمخزَّن ما كتبه هو، لا ما حسبناه نحن.
 *
 * والتقويم أمّ القرى، وهو ما تعتمده أكثر الجهات في الجزيرة.
 */

const HIJRI = new Intl.DateTimeFormat('ar-SA-u-ca-islamic-umalqura-nu-arab', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  timeZone: 'UTC',
});

/** «2026-09-07» ← «١٢ ربيع الأول ١٤٤٨». */
export function toHijri(gregorian: string): string {
  const date = new Date(`${gregorian}T00:00:00Z`);

  if (Number.isNaN(date.getTime())) {
    return '';
  }

  // الصيغة الافتراضية تُلحق «هـ»، وتُنزع فيبقى ما يُكتب في الترويسة.
  return HIJRI.format(date).replace(/\s*هـ\s*$/u, '').trim();
}

/**
 * «١٢ رجب ١٤٤٧» ← «2026-01-12».
 *
 * ولا معكوسَ في `Intl`، فيُقدَّر اليوم من طول السنة الهجرية ثمّ يُمسح ما
 * حوله حتى يُطابق التنسيقُ المدخلَ. والمسح ±٥ أيام يكفي: الخطأ في التقدير
 * لا يتجاوز يومين، والزيادة احتياط.
 */
export function toGregorian(hijri: string): string {
  const parsed = parseHijri(hijri);

  if (parsed === null) {
    return '';
  }

  const [year, month, day] = parsed;

  // عدد الأيام منذ بداية التقويم الهجري، بمتوسّط السنة ٣٥٤٫٣٦٧ يوماً.
  const days = (year - 1) * 354.367 + (month - 1) * 29.53 + day;
  const epoch = Date.UTC(622, 6, 16);
  const guess = new Date(epoch + days * 86_400_000);

  for (let shift = -5; shift <= 5; shift += 1) {
    const candidate = new Date(guess.getTime() + shift * 86_400_000);

    if (sameHijriDay(candidate, year, month, day)) {
      return candidate.toISOString().slice(0, 10);
    }
  }

  return '';
}

const MONTHS = [
  'محرم', 'صفر', 'ربيع الأول', 'ربيع الآخر', 'جمادى الأولى', 'جمادى الآخرة',
  'رجب', 'شعبان', 'رمضان', 'شوال', 'ذو القعدة', 'ذو الحجة',
];

/** @returns [سنة، شهر، يوم] أو null إن لم يُقرأ المدخل. */
function parseHijri(value: string): [number, number, number] | null {
  const text = latinDigits(value).trim();
  const numbers = text.match(/\d+/g);

  if (numbers === null || numbers.length < 2) {
    return null;
  }

  const monthIndex = MONTHS.findIndex((name) => text.includes(name));

  if (monthIndex === -1) {
    return null;
  }

  const day = Number(numbers[0]);
  const year = Number(numbers[numbers.length - 1]);

  return day >= 1 && day <= 30 && year > 1 ? [year, monthIndex + 1, day] : null;
}

function sameHijriDay(date: Date, year: number, month: number, day: number): boolean {
  const parsed = parseHijri(HIJRI.format(date));

  return parsed !== null && parsed[0] === year && parsed[1] === month && parsed[2] === day;
}

/** الأرقام العربية الهندية والفارسية إلى لاتينية، فتُقرأ بـ`Number`. */
function latinDigits(value: string): string {
  return value
    .replace(/[٠-٩]/g, (digit) => String(digit.charCodeAt(0) - 0x0660))
    .replace(/[۰-۹]/g, (digit) => String(digit.charCodeAt(0) - 0x06F0));
}
