<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\Stage;
use RuntimeException;
use Throwable;

/**
 * A model call that could not be completed — المواصفة §6 و§6-أ.
 *
 * تحمل **أيُعاد الاستدعاء أم لا**، وهو تصنيفٌ تفرضه §6-أ صراحةً:
 *
 *   يُعاد على: المهلة، و429، و5xx، والانقطاع الشبكي.
 *   **لا يُعاد على:** فشل المخطّط بعد الثانية، ورفض المحتوى، وخطأ المصادقة.
 *
 * «هذه أخطاء لا يصلحها التكرار، **وإعادتها تحرق مالاً بلا فائدة**.»
 */
final class ModelCallFailed extends RuntimeException
{
    private function __construct(
        public readonly string $errorCode,
        public readonly bool $retryable,
        string $message,
        public readonly ?Stage $stage = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    /** يُعاد: المهلة و429 و5xx والانقطاع. */
    public static function retryable(string $code, string $detail, ?Stage $stage = null, ?Throwable $previous = null): self
    {
        return new self($code, true, $detail, $stage, $previous);
    }

    /** لا يُعاد: رفض المحتوى، وخطأ المصادقة، والإعداد الناقص. */
    public static function permanent(string $code, string $detail, ?Stage $stage = null, ?Throwable $previous = null): self
    {
        return new self($code, false, $detail, $stage, $previous);
    }

    /**
     * المخرَج خالف المخطّط — المواصفة §6.
     *
     * ويُعاد مرّةً واحدة ثم يقف: «فشل مخطّط الخرج ← إعادة استدعاء واحدة
     * ثم `failed`». والرمز `schema_validation_failed` كما تفرضه T-10.
     */
    public static function schemaValidation(string $detail, ?Stage $stage = null): self
    {
        return new self('schema_validation_failed', false, $detail, $stage);
    }
}
