<?php

declare(strict_types=1);

/*
 * User-facing error messages. Adapted from `lang/ar/errors.php` in T-133.
 *
 * Two rules govern how these are written:
 *   1. **No error code is ever shown to the user** — spec §5-a-7. The code
 *      is for the log and for measurement; the message is for a person.
 *   2. Every message **offers an action**. "Transcription failed" is not a
 *      message, because it leaves the content manager standing there. Some
 *      of our faults are fixable at the input, others move to the manual
 *      path — so the message says which.
 */

return [

    'lecture' => [

        'image_too_large' => 'This image is over the size limit. The limit is 10 MB, and something lighter than a poster is plenty.',

        'image_type' => 'That image format is not accepted. Use PNG, JPEG or WEBP.',

        'image_read_failed' => 'We could not read the lecture details from that image. Enter them by hand — the fields below are ready.',

    ],

    'brand' => [

        'logo_too_large' => 'This logo is over the size limit. The limit is 500 KB, and the accepted formats are PNG and SVG.',

        'logo_type' => 'That file format is not accepted. Use PNG or SVG. If your logo is in another format, convert it and upload it again.',

        'logo_unsafe' => 'We could not accept this logo. Try exporting it from your design software as a plain SVG, or upload it as a PNG.',

    ],

    'transcript' => [

        'host_not_allowed' => 'That link is from a platform we do not support. We currently support YouTube links. You can also upload the lecture file from your device, or paste the lecture text directly.',

        'duration_exceeded' => 'This lecture is longer than your subscription allows. Split it into two parts and create a summary for each, or upgrade your plan.',

        'video_unavailable' => 'We could not find that video. Check the link — it may have been deleted from the platform. If you have the text, paste it directly.',

        'video_private' => 'That video is private and cannot be read from outside its owner\'s account. Make it public or unlisted, or upload the lecture file from your device.',

        'geo_blocked' => 'That video is geographically restricted, so we cannot reach it. Upload the lecture file from your device, or paste the lecture text.',

        'bot_check' => 'The platform has temporarily blocked us from reading this lecture. We have moved your request to the manual path: upload the audio or video file from your device, or paste the lecture text. Nothing you entered has been lost.',

        'no_arabic_source' => 'There are no Arabic captions for this video. We will transcribe its audio, and that counts against your subscription\'s transcription minutes.',

        'transcription_failed' => 'We could not transcribe this lecture. Try again; if it keeps happening, upload a clearer audio file or paste the lecture text.',

        'transcript_too_short' => 'The extracted text is too short to be a full lecture, and the captions look incomplete. Review and complete it before generating — nothing has been taken from your quota.',

        'ytdlp_timeout' => 'Reading this lecture took longer than allowed, so we stopped it. Try again, or upload the lecture file from your device.',

        'playlist_given' => 'That is a playlist link, not a link to a single lecture. Open the lecture you mean and copy its own link — each lecture gets its own summary.',

    ],

    'quota' => [

        'monthly_quota' => 'This month\'s summary quota is used up. It renews at the start of next month, and you can buy an extra summary now.',

        'daily_cap' => 'You have reached the daily limit for summaries. Try again tomorrow, or review your plan if the limit is too tight for your work.',

        'lecture_duration' => 'This lecture is longer than your subscription allows. Split it into two parts and create a summary for each, or upgrade your plan.',

        'regeneration' => 'You have reached the regeneration limit for this summary. Review the output and edit it by hand, or create a new summary — which counts against your monthly quota.',

        'transcription_minutes' => 'Your subscription\'s transcription minutes for this month are used up. You can still paste the lecture text or upload a captions file, and neither counts against the minutes.',

        /*
         * **The message reassures about what is published before it explains
         * the block**: the first thing anyone reading "suspended" fears is
         * that their pages have gone down — and they are up, served as ever.
         */
        'suspended' => 'Your subscription is suspended, so no new summary can be created right now. Your published pages are working exactly as they were and nothing has been touched. Get in touch to reactivate.',

        'spend_cap' => 'We have paused summary creation temporarily for an operational review on our side. Nothing you entered has been lost, and we will let you know as soon as the service is back.',

    ],

    /*
     * Failures after transcription — T-91.
     *
     * **This covers two kinds of fault that have nothing to do with the
     * lecture source or with anything the user pasted**: the model provider
     * failing (its quota or its connection), and an internal fault of ours.
     * Both used to land on "change the lecture source" — a correct message
     * for a transcription fault alone, and one that blames the user for
     * everything else.
     */
    'pipeline' => [

        'rate_limited' => 'This job exceeded the limit at the AI service we use, temporarily, and your lecture source has nothing to do with it. Try again in a few minutes.',

        'connection_failed' => 'We could not reach the AI service we use, and your lecture source has nothing to do with it. Try again — this usually clears on its own.',

        'provider_error' => 'The AI service we use is temporarily down, and your lecture source has nothing to do with it. Try again shortly.',

        /*
         * The internal-fault group: configuration or code on our side, which
         * changing the source will not fix and which the user usually cannot
         * fix at all. The message is deliberately identical for all of them —
         * the difference between their codes is detail for the log, not for
         * the user. All of them say "this is not you, and we will look at it".
         */
        'authentication_failed' => 'The AI service configuration on our side has failed, and your lecture source has nothing to do with it. Try again shortly, and get in touch if it keeps happening.',

        'content_rejected' => 'The AI service refused to process this lecture\'s content. Get in touch so we can look into why.',

        'fixture_broken' => 'We could not finish this job because of a fault in our own configuration, and your lecture source has nothing to do with it. Please get in touch.',

        'model_not_configured' => 'Our model configuration needs attention, and your lecture source has nothing to do with it. Get in touch, or try again later.',

        'provider_not_configured' => 'Our model configuration needs attention, and your lecture source has nothing to do with it. Get in touch, or try again later.',

        'schema_validation_failed' => 'The model did not respond in the expected shape at one stage of processing, and your lecture source has nothing to do with it. Try again — this usually clears on a retry.',

        'structure_missing' => 'An internal error occurred while preparing this summary. It has nothing to do with your lecture source or with anything you entered. Try again, and get in touch if it keeps happening.',

        'transcript_missing' => 'An internal error occurred while preparing this summary. It has nothing to do with your lecture source or with anything you entered. Try again, and get in touch if it keeps happening.',

        'body_missing' => 'An internal error occurred while preparing this summary. It has nothing to do with your lecture source or with anything you entered. Try again, and get in touch if it keeps happening.',

        'body_empty' => 'An internal error occurred while preparing this summary. It has nothing to do with your lecture source or with anything you entered. Try again, and get in touch if it keeps happening.',

        'evidence_unsettled' => 'An internal error occurred while preparing this summary. It has nothing to do with your lecture source or with anything you entered. Try again, and get in touch if it keeps happening.',

        /*
         * ★ The exception with no code — `RunSummaryPipeline::describe()`.
         * Every fault we did not anticipate lands here. **This is the most
         * important line in the file**: it is what used to surface as "change
         * the lecture source", however far the fault was from the source.
         */
        'pipeline_failed' => 'An internal error occurred while preparing this summary. It has nothing to do with your lecture source or with anything you entered. Try again, and get in touch if it keeps happening.',

    ],

];
