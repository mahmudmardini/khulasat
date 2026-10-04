/**
 * علامةُ نهاية الآية «۝» ورقمُها بالعربية الهندية، كما يضعها
 * `AyahText::decorate` في الخادم. **ومكتوبةٌ بترميزها** لأنّ المكوّن لا يحمل
 * حرفاً عربياً خارج تعليقاته (`DesignSystemTest`).
 */
const AYAH_MARK = /(\u06DD[\u0660-\u0669]+)/u;

interface Props {
  kind: string;
  body: string;
  /** أصنافُ النصّ الحرّ وحده. ولفظُ المصدر يُرسم بأصنافه هو. */
  className: string;
}

/**
 * متنُ الشريحة في اللوحة — T-172.
 *
 * **شريحةُ الآية أو الشاهد تحمل لفظ المصدر مزيَّناً**: ﴿…۝٥٦﴾. وخطُّ الواجهة
 * (IBM Plex Sans Arabic) ليس فيه «۝»، فكانت تُرسم دائرةً منقّطة ويخرج الرقم
 * منها، ويأتي القوسان من خطٍّ احتياطي. فيُرسم لفظ المصدر بخطّ المصحف كما في
 * قالب الشرائح (`.slide.ayah .body`)، وتُلبَس العلامةُ ورقمُها صنفَ `.ayah-no`
 * كما في `AyahText::html` — فلا تختلف المعاينةُ عن الصورة المنزَّلة.
 *
 * والنوعان هما `SlideKind::carriesSourceWording()` في الخادم. وما سواهما نصٌّ
 * حرٌّ كتبه النموذج، فيبقى بخطّ الواجهة وبأصناف من يستدعيه.
 */
export function SlideBody({ kind, body, className }: Props) {
  if (kind !== 'ayah' && kind !== 'evidence') {
    return <p className={className}>{body}</p>;
  }

  return (
    <p className="font-quran wrap-anywhere text-text">
      {body.split(AYAH_MARK).map((part, index) =>
        // `split` بمجموعةٍ ملتقَطة يضع العلامات في المواضع الفردية.
        index % 2 === 1 ? (
          <span key={index} className="ayah-no">
            {part}
          </span>
        ) : (
          part
        ),
      )}
    </p>
  );
}
