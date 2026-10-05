/**
 * البحث في دليل الاستخدام — T-215. **في المتصفّح وحده**: الدليلُ كلّه في
 * الصفحة أصلاً، فلا طلبَ يُرسل ولا فهرسَ يُبنى على الخادم.
 *
 * ★ **والمطابقةُ بعد التطبيع**: من يكتب «اضافة» يجد «إضافة»، ومن يكتب
 * «المراجعه» يجد «المراجعة». فالعربيّ لا يكتب الهمزةَ والتاءَ المربوطة
 * في مربّع بحثٍ كما يكتبهما في متن، والتشكيلُ لا يُكتب أصلاً.
 */

export interface GuideEntry {
  id: string;
  title: string;
  chapter: string;
  text: string;
}

export interface GuideHit {
  entry: GuideEntry;
  title: Highlighted;
  snippet: Highlighted;
}

/** نصٌّ مقطَّع: ما طابق البحثَ يُرسم مظلَّلاً. */
export type Highlighted = ReadonlyArray<{ text: string; match: boolean }>;

const DIACRITICS = /[ؐ-ًؚ-ٰٟۖ-ۭـ]/;

const FOLD: Record<string, string> = {
  'أ': 'ا', 'إ': 'ا', 'آ': 'ا', 'ٱ': 'ا', 'ى': 'ي', 'ئ': 'ي', 'ؤ': 'و', 'ة': 'ه',
  '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
};

/**
 * النصُّ مطبَّعاً، ومعه موضعُ كلّ حرفٍ منه في الأصل — ليُظلَّل الأصلُ
 * بتشكيله لا النسخةُ المطبَّعة.
 */
function normalize(source: string, locale: string): { text: string; map: number[] } {
  let text = '';
  const map: number[] = [];

  for (let index = 0; index < source.length; index += 1) {
    const char = source[index];

    if (DIACRITICS.test(char)) {
      continue;
    }

    const folded = (FOLD[char] ?? char).toLocaleLowerCase(locale);

    for (const piece of folded) {
      text += piece;
      map.push(index);
    }
  }

  return { text, map };
}

function terms(query: string, locale: string): string[] {
  return normalize(query, locale)
    .text.split(/\s+/)
    .map((term) => term.trim())
    .filter((term) => term.length > 0);
}

/** المقاطعُ المطابِقة في النصّ الأصليّ، مدمجةً إن تداخلت. */
function ranges(source: string, words: string[], locale: string): Array<[number, number]> {
  const { text, map } = normalize(source, locale);
  const found: Array<[number, number]> = [];

  for (const word of words) {
    let from = text.indexOf(word);

    while (from !== -1) {
      const end = from + word.length - 1;
      let stop = map[end] + 1;

      // تشكيلُ الحرف الأخير من المطابَق منه، فلا تُقطع الحركةُ عن حرفها.
      while (stop < source.length && DIACRITICS.test(source[stop])) {
        stop += 1;
      }

      found.push([map[from], stop]);
      from = text.indexOf(word, end + 1);
    }
  }

  found.sort((a, b) => a[0] - b[0]);

  return found.reduce<Array<[number, number]>>((merged, range) => {
    const last = merged[merged.length - 1];

    if (last !== undefined && range[0] <= last[1]) {
      last[1] = Math.max(last[1], range[1]);
    } else {
      merged.push([...range]);
    }

    return merged;
  }, []);
}

function cut(source: string, marks: Array<[number, number]>, from = 0, to = source.length): Highlighted {
  const pieces: Array<{ text: string; match: boolean }> = [];
  let cursor = from;

  for (const [start, end] of marks) {
    if (end <= from || start >= to) {
      continue;
    }

    if (start > cursor) {
      pieces.push({ text: source.slice(cursor, start), match: false });
    }

    pieces.push({ text: source.slice(Math.max(start, from), Math.min(end, to)), match: true });
    cursor = Math.min(end, to);
  }

  if (cursor < to) {
    pieces.push({ text: source.slice(cursor, to), match: false });
  }

  return pieces;
}

/**
 * مقتطفٌ حول أوّل موضعٍ طابق، لا أوّلُ النصّ: من بحث عن «CSV» يريد أن
 * يرى الجملةَ التي فيها CSV.
 */
function snippet(source: string, marks: Array<[number, number]>): Highlighted {
  const width = 150;

  if (marks.length === 0) {
    return [{ text: source.slice(0, width) + (source.length > width ? '…' : ''), match: false }];
  }

  const start = Math.max(0, marks[0][0] - 50);
  const end = Math.min(source.length, start + width);
  const pieces = [...cut(source, marks, start, end)];

  if (start > 0) {
    pieces.unshift({ text: '…', match: false });
  }

  if (end < source.length) {
    pieces.push({ text: '…', match: false });
  }

  return pieces;
}

/**
 * يُبنى الفهرس من متن الفصول نفسه: لكلّ عنوانٍ ما تحته حتى العنوان التالي.
 */
export function buildIndex(chapters: ReadonlyArray<{ id: string; title: string; html: string }>): GuideEntry[] {
  const entries: GuideEntry[] = [];
  const parser = new DOMParser();

  for (const chapter of chapters) {
    const body = parser.parseFromString(chapter.html, 'text/html').body;
    let current: GuideEntry = { id: chapter.id, title: chapter.title, chapter: chapter.title, text: '' };

    for (const node of Array.from(body.children)) {
      if (node.tagName === 'H2') {
        continue;
      }

      if (node.tagName === 'H3') {
        entries.push(current);
        current = {
          id: node.id,
          title: node.querySelector('.guide-heading__text')?.textContent?.trim() ?? '',
          chapter: chapter.title,
          text: '',
        };
        continue;
      }

      current.text += ` ${node.textContent ?? ''}`;
    }

    entries.push(current);
  }

  return entries.map((entry) => ({ ...entry, text: entry.text.replace(/\s+/g, ' ').trim() }));
}

/** كلُّ كلمةٍ في البحث شرطٌ، والعنوانُ أثقلُ من المتن. */
export function search(index: ReadonlyArray<GuideEntry>, query: string, locale: string, limit = 12): GuideHit[] {
  const words = terms(query, locale);

  if (words.length === 0) {
    return [];
  }

  const scored: Array<{ hit: GuideHit; score: number }> = [];

  for (const entry of index) {
    const title = normalize(entry.title, locale).text;
    const text = normalize(entry.text, locale).text;

    if (!words.every((word) => title.includes(word) || text.includes(word))) {
      continue;
    }

    const score = words.reduce(
      (sum, word) => sum + (title.includes(word) ? 10 : 0) + Math.min(text.split(word).length - 1, 5),
      0,
    );

    scored.push({
      score,
      hit: {
        entry,
        title: cut(entry.title, ranges(entry.title, words, locale)),
        snippet: snippet(entry.text, ranges(entry.text, words, locale)),
      },
    });
  }

  return scored
    .sort((a, b) => b.score - a.score)
    .slice(0, limit)
    .map((item) => item.hit);
}
