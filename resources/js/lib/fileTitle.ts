/**
 * عنوانُ الدرس من اسم ملفّه — T-213، كما يُؤخذ عنوانُ يوتيوب من الفيديو.
 *
 * تُحذف اللاحقة، و`_` فاصلٌ لا حرف، ويُطوى ما تكرّر من المسافات. ولا يُخمَّن
 * الملقي منه: لا نمطَ ثابتاً في الأسماء، واسمٌ خاطئ أسوأ من حقلٍ فارغ.
 */
export function titleFromFileName(name: string): string {
  const dot = name.lastIndexOf('.');
  const stem = dot > 0 ? name.slice(0, dot) : name;

  // حدُّ `title_ar` على الخادم — StoreLectureRequest.
  return stem.replace(/_+/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 255);
}
