import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { wordDiff, type DiffToken } from '@/lib/diff';

interface Props {
  source: string;
  quoted: string;
  /** عنوانُ جانب الاقتباس — «كما ورد في الدرس» افتراضاً، وأداةُ التحقّق تقول «في النصّ» (T-181). */
  quotedLabel?: string;
}

/**
 * مقارنة لفظ الدرس بلفظ المصدر — SCREENS.md الشاشة 5.
 *
 * وهذا أدقّ مكوّن في المنتج، لأنّ عليه يقع قرارُ النشر. وثلاث قواعد فيه:
 *
 * ١. **يعرض ولا يحكم.** الحكم من `HadithVerifier` في الخادم، وهذا يُظهر
 *    ما وجده. ولا يستنبط من الفرق شيئاً.
 * ٢. **لا يستبدل صامتاً.** يُعرض اللفظان معاً، فيرى المراجع ما زاغ.
 * ٣. **النصّ الشرعي بخطّ Amiri** بمقاس 19 وارتفاع 2.1 — §الخطوط. فهو
 *    يُقرأ قراءة تدقيقٍ لا تصفّح.
 *
 * **وعمودان لا سطرٌ مدموج** — كما في مخطّط SCREENS.md §5. والسطر المدموج
 * يُقحم لفظ المصدر داخل لفظ الدرس فيخرج كلامٌ لا يقرؤه أحد، والعربية
 * تُكتب متّصلةً فيزداد الخلط. فكلّ لفظٍ في عموده، ومُظلَّلٌ فيه ما تبدّل
 * وحده — وهو ما يُقرأ في ثوانٍ، والقرار مطلوبٌ في أقلّ من دقيقة.
 */
export function DiffView({ source, quoted, quotedLabel }: Props) {
  const tokens = wordDiff(source, quoted);

  return (
    <figure className="rounded-lg border border-border bg-surface-alt p-4">
      <figcaption className="mb-3 text-[13px] text-text-muted">
        {t('review.labels.diff_legend')}
      </figcaption>

      <div className="grid gap-4 sm:grid-cols-2">
        <Side
          label={quotedLabel ?? t('review.labels.quoted')}
          tokens={tokens}
          keep="added"
          tone="bg-danger/10 text-danger"
        />
        <Side
          label={t('review.labels.source')}
          tokens={tokens}
          keep="removed"
          tone="bg-success/15 text-success"
        />
      </div>
    </figure>
  );
}

/**
 * جانبٌ واحد من المقارنة.
 *
 * `keep` هي العملية التي تخصّ هذا الجانب: `added` كلماتٌ زادها الدرس،
 * و`removed` كلماتٌ في المصدر غابت عنه. والمشترك يظهر في الجانبين بلا
 * تظليل، فيُقرأ اللفظ كاملاً متّصلاً كما هو.
 */
function Side({
  label,
  tokens,
  keep,
  tone,
}: {
  label: string;
  tokens: DiffToken[];
  keep: 'added' | 'removed';
  tone: string;
}) {
  const mine = tokens.filter((token) => token.op === 'same' || token.op === keep);

  return (
    <div>
      <p className="mb-1.5 text-[13px] text-text-faint">{label}</p>
      <p className="font-quran text-text">
        {mine.map((token, index) => (
          <span
            key={`${index}-${token.text}`}
            className={cn('rounded px-0.5', token.op === keep && `${tone} font-semibold`)}
          >
            {token.text}
            {index < mine.length - 1 ? ' ' : ''}
          </span>
        ))}
      </p>
    </div>
  );
}
