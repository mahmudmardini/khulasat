<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Locale;
use App\Enums\SummaryTemplate;
use App\Support\Render\Palette;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * تحقّق مدخلات هوية الجهة — SCREENS.md الشاشة 8.
 *
 * **ولا حقل HEX هنا ولا في الواجهة:** اللوحة مفتاحٌ من ستّة، ويُتحقَّق منه
 * بقائمة السماح. ومن قبِل لوناً حرّاً فتح باب تباينٍ لا يُقرأ على ورقٍ
 * مطبوع — والجودة الثابتة هي المنتج.
 */
class UpdateBrandRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:120'],
            'name_ar_full' => ['nullable', 'string', 'max:200'],
            'name_latin' => ['nullable', 'string', 'max:120'],

            'palette' => ['required', 'string', Rule::in(array_keys(Palette::all()))],

            // T-45. و`nullable` لأنّ الجهة القديمة لا قالب في `brand_kit`ها،
            // فتُحفظ هويّتها بلا أن تُجبَر على اختيارٍ لم تطلبه.
            'template' => ['nullable', 'string', Rule::in(array_column(SummaryTemplate::cases(), 'value'))],

            /*
             * لغات المخرَج — T-38. **ولا `required`**: جهةٌ لا ترسلها تبقى
             * على العربية وحدها، و`Locale::normalizeSet` تضمّها على كلّ حال.
             */
            'locales' => ['nullable', 'array'],
            'locales.*' => ['string', Rule::in(array_column(Locale::cases(), 'value'))],

            'youtube_url' => ['nullable', 'url', 'max:300'],
            'social_url' => ['nullable', 'url', 'max:300'],

            'disclaimer_text' => ['nullable', 'string', 'max:400'],

            /*
             * النوع والحجم يُفحصان هنا **راحةً**، والحكم في `LogoSanitizer`
             * بالمحتوى لا بالامتداد (§12). ومن اكتفى بهذا وثق بما يملكه الرافع.
             */
            'logo' => ['nullable', 'file', 'max:500'],

            // الشعار اختياريّ — T-99: يُزال المحفوظ بطلبٍ صريح، وملفٌّ جديد يغلبه.
            'remove_logo' => ['nullable', 'boolean'],

            // اللوح خلف الشعار اختياريّ — T-125: شعارٌ فاتحٌ أصلاً لا يحتاجه.
            'logo_transparent' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name_ar.required' => 'اسم الجهة مطلوب، وهو ما يظهر في ترويسة كل ملخّص.',
            'palette.in' => 'اختر لوحةً من الستّ المعروضة.',
            'youtube_url.url' => 'رابط القناة غير صالح. انسخه كاملاً من شريط المتصفّح.',
            'social_url.url' => 'رابط الصفحة غير صالح. انسخه كاملاً من شريط المتصفّح.',
            'logo.max' => 'حجم الشعار يتجاوز ٥٠٠ كيلوبايت.',
        ];
    }
}
