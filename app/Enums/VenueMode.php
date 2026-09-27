<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which party the summary is attributed to — المواصفة §4 و`khulasah.skill`.
 */
enum VenueMode: string
{
    /** جهة ومكان: اسم المؤسّسة والمقرّ في الترويسة. */
    case Institution = 'institution';

    /** الشيخ وحده، بلا جهة. */
    case SpeakerOnly = 'speaker_only';

    /** الناشر وحده — محاضرة منقولة عن مصدر خارجي. */
    case PublisherOnly = 'publisher_only';

    /** **الجهة والمكان هو الافتراض** — وهو حال أكثر من يستعمل المنتج. */
    public static function default(): self
    {
        return self::Institution;
    }

    public function showsVenueBlock(): bool
    {
        return $this === self::Institution;
    }
}
