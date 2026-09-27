<?php

declare(strict_types=1);

namespace App\Support\Render;

/**
 * لوحات ألوان الجهة — **ستٌّ ثوابت، ولا حقول HEX من المستخدم** (T-14 البند 4).
 *
 * والقيم مأخوذة من `:root` في قالب المهارة حرفاً بحرف للوحة الأولى، وما
 * بعدها مشتقٌّ منها بالبنية نفسها. **وتُحقن متغيّراتِ CSS في `:root`**،
 * فلا يُمسّ حرفٌ من ورقة أنماط القالب — CLAUDE.md §2 القاعدة الأولى.
 *
 * **ولماذا لا حقل HEX:** لونٌ حرّ يفتح باب تباينٍ لا يُقرأ ونصٍّ يضيع على
 * ورقٍ مطبوع. والستُّ مضبوطةٌ على ورقٍ وشاشة، ويُختار منها.
 */
final readonly class Palette
{
    private function __construct(
        public string $key,
        public string $nameAr,
        /** @var array<string, string> متغيّرات `:root` كما في القالب. */
        public array $vars,
    ) {}

    /** اللوحة الافتراضية — **قيم القالب نفسها بلا تبديل حرف**. */
    public const DEFAULT = 'emerald';

    /** @return array<string, self> */
    public static function all(): array
    {
        return [
            'emerald' => new self('emerald', 'الزمرّدي', [
                'paper' => '#F3EEE1', 'paper-2' => '#E9E1CD', 'paper-3' => '#DED3B9',
                'emerald' => '#1B4D3E', 'emerald-deep' => '#0E2C24', 'emerald-soft' => '#3A6B58',
                'gold' => '#A87C33', 'gold-light' => '#D2AC63',
                'clay' => '#8E3B2E', 'clay-soft' => '#B0685C',
                'ink' => '#22201A', 'ink-soft' => '#565043',
                'rule' => 'rgba(168,124,51,.28)',
                // بطاقةُ التطبيق — T-79. **قيمتا القالب حرفاً**، فمخرَجُ الزمرّدية لا يتبدّل.
                'night' => '#123028', 'night-deep' => '#0A1F19',
            ]),
            'indigo' => new self('indigo', 'النيلي', [
                'paper' => '#F2F1EC', 'paper-2' => '#E6E5DE', 'paper-3' => '#D6D5CB',
                'emerald' => '#243B6B', 'emerald-deep' => '#152444', 'emerald-soft' => '#4A5F8E',
                'gold' => '#9A7B3C', 'gold-light' => '#C6A468',
                'clay' => '#8A3B34', 'clay-soft' => '#AB6962',
                'ink' => '#1E1E24', 'ink-soft' => '#4F4F58',
                'rule' => 'rgba(154,123,60,.28)',
                'night' => '#192848', 'night-deep' => '#0F192F',
            ]),
            'sepia' => new self('sepia', 'البنّي الترابي', [
                'paper' => '#F4EEE4', 'paper-2' => '#EAE0D1', 'paper-3' => '#DCCDB6',
                'emerald' => '#5A4227', 'emerald-deep' => '#3A2916', 'emerald-soft' => '#7C6144',
                'gold' => '#9E7A38', 'gold-light' => '#C9A469',
                'clay' => '#8B4034', 'clay-soft' => '#AC6E63',
                'ink' => '#241F18', 'ink-soft' => '#574E41',
                'rule' => 'rgba(158,122,56,.28)',
                'night' => '#3E2D1A', 'night-deep' => '#291D0F',
            ]),
            'slate' => new self('slate', 'الرمادي', [
                'paper' => '#F2F2F0', 'paper-2' => '#E5E5E2', 'paper-3' => '#D3D3CE',
                'emerald' => '#33403F', 'emerald-deep' => '#1E2827', 'emerald-soft' => '#576564',
                'gold' => '#8E7E52', 'gold-light' => '#B6A67C',
                'clay' => '#7E4038', 'clay-soft' => '#A06B63',
                'ink' => '#1F2120', 'ink-soft' => '#4E504F',
                'rule' => 'rgba(142,126,82,.28)',
                'night' => '#222C2B', 'night-deep' => '#151C1B',
            ]),
            'plum' => new self('plum', 'العنّابي', [
                'paper' => '#F4EFEC', 'paper-2' => '#E9E0DC', 'paper-3' => '#D9CBC5',
                'emerald' => '#4B2740', 'emerald-deep' => '#2E1628', 'emerald-soft' => '#6E4661',
                'gold' => '#A07539', 'gold-light' => '#C9A369',
                'clay' => '#8B3A3A', 'clay-soft' => '#AC6767',
                'ink' => '#231C21', 'ink-soft' => '#544A51',
                'rule' => 'rgba(160,117,57,.28)',
                'night' => '#321A2C', 'night-deep' => '#21101C',
            ]),
            'teal' => new self('teal', 'الفيروزي', [
                'paper' => '#EFF2F1', 'paper-2' => '#E1E7E5', 'paper-3' => '#CBD6D3',
                'emerald' => '#1C4A4C', 'emerald-deep' => '#0D2A2C', 'emerald-soft' => '#3D6C6E',
                'gold' => '#98803F', 'gold-light' => '#C0A96E',
                'clay' => '#87413A', 'clay-soft' => '#A86E67',
                'ink' => '#1B2222', 'ink-soft' => '#4B5352',
                'rule' => 'rgba(152,128,63,.28)',
                'night' => '#112E30', 'night-deep' => '#091E1F',
            ]),
        ];
    }

    public static function find(?string $key): self
    {
        return self::all()[$key ?? self::DEFAULT] ?? self::all()[self::DEFAULT];
    }

    /**
     * المتغيّرات كما تُحقن في `:root`.
     *
     * و`--maxw` **لا تُحقن**: هي قياسُ تخطيطٍ لا لونَ هوية، وتبديلها يعبث
     * بضبط القالب للجوال والطباعة. وذاك حدٌّ لا يُتجاوز.
     */
    public function css(): string
    {
        $lines = [];

        foreach ($this->vars as $name => $value) {
            $lines[] = "  --{$name}:{$value};";
        }

        return implode("\n", $lines);
    }
}
