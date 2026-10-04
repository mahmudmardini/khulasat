<?php

declare(strict_types=1);

namespace App\Support\Render;

use App\Enums\SlideKind;
use App\Support\Quran\AyahText;

/**
 * كاروسيلُ العيّنة الذي تُعاين عليه القوالب — T-173.
 *
 * **ثابتٌ لا من ملخّصٍ حقيقي**: الجهةُ تختار قالبها قبل أن تملك ملخّصاً، ومقارنةُ
 * ثلاثة قوالب لا تصحّ إلّا على محتوى واحد. وأنواعُه الثمانية كلُّها حاضرة،
 * وشريحةُ المتن الطويل فيه عمداً: قالبٌ لا يسعها لا يصلح.
 *
 * ولا بيانات فيه: تسمياتٌ محايدة، وآيةٌ وحديثٌ بلفظ مصدريهما.
 */
final class SampleCarousel
{
    public static function deck(): SlideDeck
    {
        $ayah = AyahText::decorate(
            'مَنْ عَمِلَ صَالِحًا مِنْ ذَكَرٍ أَوْ أُنْثَىٰ وَهُوَ مُؤْمِنٌ فَلَنُحْيِيَنَّهُ حَيَاةً طَيِّبَةً',
            ['ayah_number' => 97, 'is_fragment' => true],
        );

        return new SlideDeck([
            new Slide(1, SlideKind::Cover, 'نموذج المعاينة', 'هكذا تظهر شرائح ملخّصاتكم على إنستغرام.'),
            new Slide(2, SlideKind::Ayah, 'الآية المفتاح', $ayah, 'النحل · ٩٧', true),
            new Slide(3, SlideKind::Concept, 'الفكرة', 'سطرٌ يشرح الفكرة الرئيسية في الدرس بجملةٍ أو جملتين.'),
            new Slide(4, SlideKind::Diagnosis, 'التشخيص', 'وصفٌ قصير للمشكلة التي يعالجها الدرس، كما يكتبه الملخّص.'),
            new Slide(5, SlideKind::Axis, 'المحور الأوّل', 'نصُّ المحور بطوله المعتاد: جملتان أو ثلاث تشرح المحور، وتربطه بما قبله، وتمهّد لما بعده في الدرس نفسه.'),
            new Slide(6, SlideKind::Comparison, 'مقارنة', 'الأوّل كذا، والثاني كذا، والفرق بينهما في كذا.'),
            new Slide(7, SlideKind::Evidence, 'شاهد', 'إِنَّمَا الْأَعْمَالُ بِالنِّيَّاتِ', 'البخاري · ١', true),
            new Slide(8, SlideKind::Closing, 'الخاتمة', 'سطرُ الختام.'),
        ]);
    }
}
