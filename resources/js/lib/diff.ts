/**
 * فرق على مستوى **الكلمة** بين لفظ المحاضرة ولفظ المصدر.
 *
 * والكلمة لا الحرف: العربية تُكتب متّصلةً، وفرقُ الحروف يُخرج ركاماً لا
 * يُقرأ. وهذا مطابقٌ لما تفعله `HadithVerifier::windowDistance` في الخادم،
 * فما يراه المراجع هو ما قاسه المحقّق.
 *
 * والعرض وحده — **لا حكم هنا**. القرار من الخادم، وهذا يُظهره فقط.
 */

export type DiffOp = 'same' | 'removed' | 'added';

export interface DiffToken {
  op: DiffOp;
  text: string;
}

/** أطول تتابع مشترك على الكلمات، ثم يُقرأ منه الفرق. */
/**
 * مفتاح المقارنة — **يُقارَن به ولا يُعرَض**.
 *
 * ولولاه لاختلفت «أحبُّ» عن «أحب» بالشكل وحده، فيُعلَّم الحديث كلُّه
 * مختلفاً وواحدةٌ فيه هي التي تبدّلت. وهذا أسوأ من ألّا يُعرض فرقٌ أصلاً:
 * يقول للمراجع إنّ اللفظين متباينان وهما لفظٌ واحد.
 *
 * وهو مختصرُ `Arabic::normalize` في الخادم على ما يمسّ المقارنة هنا:
 * التشكيل والتطويل والألف والياء والتاء المربوطة.
 */
function key(word: string): string {
  return word
    .replace(/[\u0610-\u061A\u064B-\u065F\u0670\u06D6-\u06ED]/g, '')
    .replace(/\u0640/g, '')
    .replace(/[\u0623\u0625\u0622\u0671]/g, '\u0627')
    .replace(/\u0649/g, '\u064A')
    .replace(/\u0629/g, '\u0647');
}

export function wordDiff(source: string, quoted: string): DiffToken[] {
  const aWords = source.split(/\s+/).filter(Boolean);
  const bWords = quoted.split(/\s+/).filter(Boolean);

  // يُقارَن على المفتاح، ويُعرَض الأصل — فالشكل لا يصنع فرقاً في المعنى.
  const a = aWords.map(key);
  const b = bWords.map(key);

  const table: number[][] = Array.from({ length: a.length + 1 }, () =>
    new Array<number>(b.length + 1).fill(0),
  );

  for (let i = a.length - 1; i >= 0; i -= 1) {
    for (let j = b.length - 1; j >= 0; j -= 1) {
      table[i][j] = a[i] === b[j]
        ? table[i + 1][j + 1] + 1
        : Math.max(table[i + 1][j], table[i][j + 1]);
    }
  }

  const out: DiffToken[] = [];
  let i = 0;
  let j = 0;

  while (i < a.length && j < b.length) {
    if (a[i] === b[j]) {
      out.push({ op: 'same', text: aWords[i] });
      i += 1;
      j += 1;
    } else if (table[i + 1][j] >= table[i][j + 1]) {
      out.push({ op: 'removed', text: aWords[i] });
      i += 1;
    } else {
      out.push({ op: 'added', text: bWords[j] });
      j += 1;
    }
  }

  while (i < a.length) {
    out.push({ op: 'removed', text: aWords[i] });
    i += 1;
  }

  while (j < b.length) {
    out.push({ op: 'added', text: bWords[j] });
    j += 1;
  }

  return out;
}
