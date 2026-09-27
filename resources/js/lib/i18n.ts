/**
 * جسر نصوص `lang/ar` إلى الواجهة.
 *
 * SCREENS.md §القواعد العامّة: «كلّ نصّ من lang/ar. لا نصّ مكتوب داخل مكوّن،
 * ولا حتى (حفظ)». فالمكوّن يطلب مفتاحاً، والنصّ يبقى في مكان واحد يُراجَع
 * ويُدقَّق لغوياً بلا فتح ملفّات TSX.
 *
 * والنصوص تُبثّ مع كل استجابة Inertia من `HandleInertiaRequests`.
 */

export type Translations = Record<string, unknown>;

let dictionary: Translations = {};

export function setTranslations(next: Translations): void {
  dictionary = next;
}

function lookup(key: string): unknown {
  return key.split('.').reduce<unknown>((node, part) => {
    if (node === null || typeof node !== 'object') {
      return undefined;
    }

    return (node as Record<string, unknown>)[part];
  }, dictionary);
}

/**
 * النصّ بمفتاحه، مع استبدال المعاملات على صيغة Laravel — `:count`.
 *
 * والمفتاح المفقود يعود بنفسه لا بفراغ: صفحةٌ فيها `jobs.status.queued`
 * ظاهرةً تُرى وتُصلَح، وصفحةٌ فيها فراغ تمرّ إلى الإنتاج.
 */
export function t(key: string, replace: Record<string, string | number> = {}): string {
  const value = lookup(key);

  if (typeof value !== 'string') {
    return key;
  }

  return Object.entries(replace).reduce(
    (text, [name, replacement]) => text.replaceAll(`:${name}`, String(replacement)),
    value,
  );
}
