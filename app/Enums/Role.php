<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tenant-scoped roles — المواصفة §10.
 */
enum Role: string
{
    case Owner = 'owner';
    case Editor = 'editor';
    case Viewer = 'viewer';

    /** يملك كلّ شيء داخل جهته، ومنه الفريق والاشتراك. */
    public function isOwner(): bool
    {
        return $this === self::Owner;
    }

    /** ينشئ ويراجع وينشر، ولا يمسّ الفريق ولا الاشتراك. */
    public function canPublish(): bool
    {
        return $this === self::Owner || $this === self::Editor;
    }
}
