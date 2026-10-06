<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\TranscriptErrorCode;
use RuntimeException;
use Throwable;

/**
 * A transcription stage failure, carrying its code — المواصفة §5-أ-7.
 *
 * **رسالتان لا واحدة**، وهذا مقصود:
 *   - `getMessage()` تقنية بالتفصيل، للسجلّ والقياس ولا تُعرض.
 *   - {@see self::userMessage()} عربية تقترح إجراءً، وهي ما يراه المستخدم.
 *
 * ولا يظهر الرمز للمستخدم قطّ — المواصفة §5-أ-7.
 */
final class TranscriptFailed extends RuntimeException
{
    private function __construct(
        public readonly TranscriptErrorCode $errorCode,
        string $message,
        ?Throwable $previous = null,
        /**
         * محاولةٌ أخرى قد تنجح — T-227. يقرؤه `YtDlp::run()` وحده، ولا
         * يُعرف من الرمز: `transcription_failed` لعطل SSL عابرٌ، وللمخرَج
         * الذي لا يُقرأ دائم.
         */
        public readonly bool $transient = false,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public static function because(
        TranscriptErrorCode $code,
        string $detail = '',
        ?Throwable $previous = null,
        bool $transient = false,
    ): self {
        return new self(
            $code,
            $detail === '' ? $code->value : $code->value.': '.$detail,
            $previous,
            $transient,
        );
    }

    /** الرسالة العربية التي تُعرض — من `lang/ar/errors.php` وحدها. */
    public function userMessage(): string
    {
        return $this->errorCode->message();
    }
}
