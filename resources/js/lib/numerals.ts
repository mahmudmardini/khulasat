/**
 * الأرقام — SCREENS.md §الخطوط.
 *
 * «الأرقام في الجُمل النثرية عربية هندية. وفي الجداول والحقول لاتينية،
 * **لأنها تُقارن وتُحاذى**.» فالقاعدة ليست ذوقاً: الرقم اللاتيني بـ
 * `tabular-nums` يصطفّ في عمود، والهندي في جملةٍ يقرأه القارئ العربي بلا تعثّر.
 */

const ARABIC_INDIC = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

/** اللوحةُ عربيةٌ الآن؟ — تُقرأ من `<html lang>` الذي يضبطه `app.tsx`. */
export function isArabic(): boolean {
  return typeof document === 'undefined' || document.documentElement.lang === 'ar';
}

/**
 * لاتيني ← عربي هندي. للجُمل النثرية وحدها.
 *
 * ★ **ولا تُحوّل على لغةٍ غير العربية** — T-133. الأرقامُ الهندية اصطلاحُ
 * القارئ العربي (SCREENS.md §الخطوط)، و«٤ minutes» في سطرٍ إنجليزيّ خطأٌ
 * لا اصطلاح. **والفحصُ هنا لا في المنادي**: ثمانيةُ مواضع تناديها، وتصحيحُ
 * كلٍّ منها على حدةٍ يترك واحداً منسيّاً.
 */
export function toArabicIndic(value: string | number): string {
  const text = String(value);

  return isArabic() ? text.replace(/\d/g, (digit) => ARABIC_INDIC[Number(digit)]) : text;
}

/**
 * نصّ فيه معاملات مستبدَلة، تُحوَّل أرقامه إلى الهندية.
 *
 * يُستعمل مع `t()` في الجُمل: «استعملت ٤ من ٢٠ هذا الشهر».
 */
export function prose(text: string): string {
  return toArabicIndic(text);
}

/**
 * الرقم كما يُعرض في جدول أو حقل: لاتيني، بفواصل الآلاف.
 *
 * ويُقرن في الترميز بـ `class="nums-tabular"` كي يصطفّ رأسياً.
 */
export function tabular(value: number): string {
  return new Intl.NumberFormat('en-US').format(value);
}
