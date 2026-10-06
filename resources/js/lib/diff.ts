/**
 * فرق على مستوى **الكلمة** بين لفظ المحاضرة ولفظ المصدر.
 *
 * والكلمة لا الحرف: العربية تُكتب متّصلةً، وفرقُ الحروف يُخرج ركاماً لا
 * يُقرأ. وهذا مطابقٌ لما تفعله `HadithVerifier::windowDistance` في الخادم،
 * فما يراه المراجع هو ما قاسه المحقّق.
 *
 * والعرض وحده — **لا حكم هنا**. القرار من الخادم، وهذا يُظهره فقط.
 */

export type DiffOp = 'same' | 'removed' | 'added' | 'outside';

export interface DiffToken {
  op: DiffOp;
  text: string;
}

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

/**
 * ★ **يُقارَن الاقتباسُ بما يقابله من المصدر، لا بالمصدر كلّه** — T-182.
 *
 * النصُّ يقتبس بعضَ الآية أو الحديث. وكانت المقارنةُ على المصدر كلّه تُظلّل
 * ما لم يُقتبس «ناقصاً»، فتضيع الكلمةُ المبدَّلة بين خمس كلماتٍ مظلَّلة لم
 * يُخطئ فيها أحد. فيُحاذى الاقتباسُ على أفضل نافذةٍ في المصدر بمسافة تحريرٍ
 * يُتخطّى فيها صدرُ المصدر وعجزُه بلا كلفة، كما يقيس `HadithVerifier`.
 * وما خرج عن النافذة `outside`: يُعرض باهتاً ليبقى السياق، ولا يُظلَّل.
 */
export function wordDiff(source: string, quoted: string): DiffToken[] {
  const aWords = source.split(/\s+/).filter(Boolean);
  const bWords = quoted.split(/\s+/).filter(Boolean);

  // يُقارَن على المفتاح، ويُعرَض الأصل — فالشكل لا يصنع فرقاً في المعنى.
  const a = aWords.map(key);
  const b = bWords.map(key);
  const m = a.length;
  const n = b.length;

  // d[i][j]: كلفةُ محاذاة أوّل i كلمةً من الاقتباس على مصدرٍ ينتهي عند j، ومبدؤه حرّ.
  const d: number[][] = Array.from({ length: n + 1 }, (_, i) => new Array<number>(m + 1).fill(i));

  for (let j = 0; j <= m; j += 1) {
    d[0][j] = 0;
  }

  for (let i = 1; i <= n; i += 1) {
    for (let j = 1; j <= m; j += 1) {
      d[i][j] = Math.min(
        d[i - 1][j - 1] + (b[i - 1] === a[j - 1] ? 0 : 1),
        d[i - 1][j] + 1,
        d[i][j - 1] + 1,
      );
    }
  }

  // نهايةٌ حرّة: أقلُّ كلفةٍ في الصفّ الأخير، وأقربُها عند التساوي.
  let end = 0;

  for (let j = 1; j <= m; j += 1) {
    if (d[n][j] < d[n][end]) {
      end = j;
    }
  }

  const aligned: DiffToken[] = [];
  let i = n;
  let j = end;

  while (i > 0) {
    if (j > 0 && d[i][j] === d[i - 1][j - 1] + (b[i - 1] === a[j - 1] ? 0 : 1)) {
      if (b[i - 1] === a[j - 1]) {
        aligned.push({ op: 'same', text: aWords[j - 1] });
      } else {
        aligned.push({ op: 'added', text: bWords[i - 1] }, { op: 'removed', text: aWords[j - 1] });
      }

      i -= 1;
      j -= 1;
    } else if (d[i][j] === d[i - 1][j] + 1) {
      aligned.push({ op: 'added', text: bWords[i - 1] });
      i -= 1;
    } else {
      aligned.push({ op: 'removed', text: aWords[j - 1] });
      j -= 1;
    }
  }

  aligned.reverse();

  // لا كلمةَ مشتركة: لا نافذةَ تُعرف، فيبقى المصدرُ كلُّه فرقاً كما كان.
  const outside: DiffOp = aligned.some((token) => token.op === 'same') ? 'outside' : 'removed';

  return [
    ...aWords.slice(0, j).map((text) => ({ op: outside, text })),
    ...aligned,
    ...aWords.slice(end).map((text) => ({ op: outside, text })),
  ];
}
