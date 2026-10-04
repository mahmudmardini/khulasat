<?php

declare(strict_types=1);

/*
 * Landing page copy — English (T-131).
 *
 * Adapted from `lang/ar/landing.php`, not translated line for line. The
 * Arabic is deliberate literary prose; rendering it word for word in
 * English produces something stilted and vaguely devotional, which is the
 * opposite of how it reads to an Arabic reader.
 *
 * Arabic remains the source of truth: where the two disagree about what
 * the product does, the Arabic wins and this file is wrong.
 *
 * ★ Scripture is never translated in place of its wording — Qur'anic and
 * hadith text stays Arabic with a labelled meaning beneath it, exactly as
 * a non-Arabic summary is published. {@see App\Enums\Locale::meaningLabel}
 */

return [

    'meta' => [
        'title' => 'Khulasat — from recorded lessons to sourced, scholarly summaries',
        'description' => 'Turn hours of lessons and lectures into a sourced page that carries your identity. Every verse and hadith is matched word for word to its trusted source and fully cited, and nothing is published until it is verified.',
        'og_title' => 'Khulasat — from recorded lessons to sourced, scholarly summaries',
        'og_description' => 'A lecture is delivered once; a summary is read for years. One page under your own identity that keeps what was taught, with every verse and hadith traced to its source. Nothing goes out unsourced.',
        'og_image_alt' => 'Khulasat — from recorded lessons to sourced, scholarly summaries, with every citation traced to its source',
        'site_name' => 'Khulasat',
        'og_locale' => 'en_US',
        'org_description' => 'A platform that keeps what a lecture taught on a single page, with every verse and hadith traced to its source.',
        'app_description' => 'Give it a lecture link and it returns a single page: an ordered summary read in minutes, with every Qur\'anic verse and hadith traced to its source after a word-for-word check against the established texts.',
    ],

    'nav' => [
        'home_aria' => 'Khulasat — home',
        'sections_aria' => 'Page sections',
        'menu_aria' => 'Menu',
        'anatomy' => 'What you get',
        'how' => 'How it works',
        'verify' => 'Accuracy',
        'tool' => 'Verify tool',
        'audience' => 'Who it is for',
        'faq' => 'Questions',
        'login' => 'Sign in',
        'contact' => 'Get in touch',
        'lang_aria' => 'Page language',
        'skip' => 'Skip to content',
    ],

    'hero' => [
        'h1' => 'From recorded lessons to sourced, scholarly summaries you can read in minutes',
        'eyebrow' => 'Summaries, traced to their sources',
        'lede' => 'Turn hours of lessons and lectures into a sourced page that carries your identity. We match every verse and hadith word for word to its trusted source and cite it in full, so you can share your knowledge with confidence and integrity.',
        'cta_primary' => 'Try your first summary',
        'cta_ghost' => 'Explore the sample summary',
        'cta_real' => 'Explore a published summary',
        'assure_time' => 'An hour-long lecture, ready in minutes',
        'assure_youtube' => 'Works with YouTube links',
        'assure_noinstall' => 'Nothing to install',
        'assure_langs' => 'Publishes in four languages',
        'access' => 'Access is by private invitation for now. Get in touch to activate your account, or try the platform on your first lecture.',
        'caption' => 'A sample page — the described slots are where your content goes.',
        'caption_real' => 'A genuinely published summary — open it in full →',
    ],

    'demo' => [
        'title' => 'Lecture title',
        'subtitle' => 'Subtitle — one line describing what the lesson covers',
        'ayah' => 'The key verse goes here, in the wording of the Qur\'an rather than as it was heard',
        'ayah_src' => 'Surah name · verse number',
        'speaker_label' => 'Delivered by',
        'speaker' => 'Speaker name',
        'place_label' => 'Location',
        'place' => 'Where it was delivered',
        'axis_title' => 'Section heading',
        'lead' => 'The opening paragraph goes here — three or four lines stating the governing idea, written as prose to be read rather than lifted verbatim from what was said.',
        'sacred' => 'The Qur\'anic evidence goes here, fixed to the wording of the Qur\'an once checked against it',
        'sources_head' => 'Verses and hadith cited',
        'ayah_ref' => 'Surah name · verse number',
        'ayah_text' => 'The verse in its source wording',
        'hadith_ref' => 'Collection · book · hadith number',
        'hadith_text' => 'The hadith in its source wording',
        'attest' => 'This summary is drawn from the lecture and is not a verbatim transcript of it, and it has not been reviewed by the speaker. The original recording remains the reference.',
        'attest_link' => 'Report an error in this summary',
        'mk_by' => 'Produced with',
        'mk_name' => 'Khulasat',
    ],

    'problem' => [
        'eyebrow' => 'What becomes of a lesson a week later',
        'h2' => 'Dozens of hours of teaching, lost in forgotten playlists',
        'lede' => 'A recording does its job the hour it is delivered, then stays a lengthy audio recording nobody returns to. The speaker and the reader both pay the price.',
        'head_aspect' => 'Aspect',
        'head_now' => 'Traditional transcription',
        'head_effect' => 'With Khulasat',
        'axes' => [
            ['t' => 'Wasted time', 'f' => 'Recordings of 60 to 90 minutes, hard to listen to in full', 'e' => 'A focused summary you read in minutes, with every idea of the lesson'],
            ['t' => 'Hard to search', 'f' => 'Knowledge locked inside audio clips, hard to search or quote', 'e' => 'Structured digital text you can search, quote and save directly'],
            ['t' => 'The burden of transcribing and sourcing', 'f' => '3 to 5 hours per lesson of manual transcription, without citations', 'e' => 'Automated processing and accurate citations; you only review what did not match'],
        ],
        'verdict' => 'Khulasat is not just a summarising tool; it is a commitment to scholarly integrity. General-purpose AI tools shorten the words but cut corners on accuracy: they alter the wording of verses and attribute hadith without citation. Khulasat restructures the lecture into focused themes and matches every citation word for word to its original.',
    ],

    'anatomy' => [
        'eyebrow' => 'What you get',
        'h2' => 'One page: read by the busy, relied on by the researcher',
        'lede_html' => 'Not a wall of running text: sections that bring the idea forward, comparisons that fix understanding, evidence traced to its source, and a card carrying your own identity. What you see below is the real page — its structure, colours and typefaces — with <b>described slots</b> in place of content, which your own material fills at publication.',
        'lede_real' => 'Not a wall of running text: sections that bring the idea forward, evidence traced to its source, and a card carrying the institution\'s identity. Below are excerpts from a genuinely published summary, worded exactly as published.',
        'showcase_title' => 'This summary is genuinely published',
        'showcase_body' => 'A real page on the platform right now, with real content and a real speaker. Open it in full to see the page as its readers do.',
        'showcase_cta' => 'Open the published summary',
        'rows' => [
            ['t' => 'The idea first', 'b' => 'The governing idea of the lesson at the head of the page, with its Qur\'anic evidence set apart by a gold border that catches the eye before anything else.'],
            ['t' => 'Verses and hadith cited', 'b' => 'A citation list at the foot of the page: every piece of evidence in its source wording, with its verse number or hadith reference — for the reader to check independently.'],
            ['t' => 'Integrity and scholarly sourcing', 'b' => '"An unofficial summary, not reviewed by the speaker", a link to the original recording, and a visible route to request removal — on every page without exception.'],
        ],
        'note' => 'What you see above is a sample page, not a real institution\'s: no speaker name, no venue, no actual lecture text. Every described slot here is filled with your content when you publish.',
        'note_real' => 'The excerpts above come from a genuinely published summary, worded as published, and show only evidence that matched its source.',
        'extra' => 'And from the same page — with no rework — a file to print and keep, and, on institutional plans, an Instagram carousel to share. What was taught is sourced once, then reaches people wherever they are.',
    ],

    'how' => [
        'eyebrow' => 'How it works',
        'h2' => 'Three stages, from link to published page',
        'lede' => 'From sending the link to the published page — nothing to install, and no technical work on your side.',
        'steps' => [
            ['t' => 'Send the lecture', 'b' => 'A YouTube link, an audio or video file, or the written text, with the speaker\'s name and date.'],
            ['t' => 'Review the sourcing', 'b' => 'The text is cleared of filler, repetition and digressions, its ideas are set out in themes without changing the meaning, and every citation is matched to its source. You only see what did not match.'],
            ['t' => 'Publish and share', 'b' => 'The summary is laid out in your template and published at a permanent link that carries your identity, ready to share.'],
        ],
        'gate_flag' => 'Your review is needed only when something is in doubt',
    ],

    'verify' => [
        'eyebrow' => 'Keeping faith with the text',
        'h2' => 'We match the text to its source, and add nothing of our own',
        'lede' => 'We never ask AI whether a citation is sound or what it means. We match its text word for word against a copy of trusted sources that we keep ourselves. The source decides, not guesswork or a machine\'s estimate: if a citation matches, it is published with its reference; if it differs, it is held until you decide.',
        'mission' => 'Khulasat is built as a leading model of responsible Islamic AI: it pairs the speed of algorithms with scholarly integrity, with no hallucinated texts and no distorted wording of sacred sources.',
        'tracks' => [
            ['tag' => 'Exact match', 't' => 'Approved at once', 'b' => 'The citation matches the trusted sources word for word, so it is set in the source wording, with its verse number or hadith reference attached automatically.'],
            ['tag' => 'Partial match', 't' => 'Needs your review', 'b' => 'The citation was reported by sense or in close wording, so you see what was said in the lecture next to the source wording, and confirm the source wording in one click.'],
            ['tag' => 'Not matched', 't' => 'Publication stops automatically', 'b' => 'The citation was not found in the trusted sources, or is graded very weak or fabricated, so it is not published without your explicit decision. There is no option on the platform that skips this step.'],
        ],
        'gate' => [
            'title' => 'Review gate',
            'pill' => 'Needs your decision',
            'counter' => 'Citation 1 of 14 — partial match',
            'from_key' => 'Wording as it came in the lecture',
            'from_meaning' => 'Actions are but by intentions, and every man shall have what he intended.',
            'from_src' => 'From: the recording of the lesson',
            'to_key' => 'Wording as it appears in the source',
            'to_meaning' => 'Actions are but by intentions, and indeed every man shall have what he intended.',
            'to_src' => 'Sahih al-Bukhari · Book of the Beginning of Revelation · hadith 1',
            'btn_keep' => 'Use the source wording',
            'btn_drop' => 'Remove the citation',
            'foot' => 'Nothing is published until you choose',
            'caption' => 'A simulation of the review gate as it appears in your own dashboard. The decision is always yours — we surface what we doubt instead of hiding it.',
        ],
        'note_title' => 'A line we stop at',
        'note_body' => 'We trace text back to its source; we do not rule on whether a hadith is authentic. The difference between a tool and a jurist is a line the platform crosses in no page and on no screen.',
        'tool' => [
            'title' => 'Try it on your own text',
            'body' => 'Paste any article, sermon or forwarded message. We pull out every verse and hadith in it, match each one to its source, and give the reason for every verdict. No account needed.',
            'cta' => 'Open the verify tool',
            'arabic_only' => 'The tool works in Arabic, on Arabic text.',
        ],
    ],

    'audience' => [
        'eyebrow' => 'Who it is for',
        'h2' => 'Four different needs, and one page wide enough for all of them',
        'cards' => [
            [
                't' => 'Students and researchers',
                'pain' => 'Review a two-hour lecture in minutes, without losing accuracy.',
                'wins' => [
                    'Focused themes and comparisons instead of replaying the whole lecture',
                    'Text you can search directly and quote from',
                    'Every quote and citation linked to its verified source',
                ],
            ],
            [
                't' => 'Content creators and speakers',
                'pain' => 'Turn your lesson into a readable archive and ready-made posts.',
                'wins' => [
                    'A file to read and print, straight from the summary',
                    'Ready Instagram slides on institutional plans',
                    'One link that carries your words to a wider audience',
                ],
            ],
            [
                't' => 'Mosques and Islamic centres',
                'pain' => 'Archive your mosque\'s lessons as well-organised pages.',
                'wins' => [
                    'This week\'s lesson summary as one link in your group chats',
                    'A page bearing the centre\'s name and logo',
                    'An archive of teaching that builds year after year',
                ],
            ],
            [
                't' => 'Academies and institutes',
                'pain' => 'A written, sourced reference for your academy\'s students.',
                'wins' => [
                    'Written material for every recorded course',
                    'Easy to index and review before exams',
                    'Pages that get indexed in search, so you are found by them',
                ],
            ],
        ],
    ],

    'brand' => [
        'eyebrow' => 'Template and identity',
        'h2' => 'Published under your name, in a template that suits your material',
        'lede' => 'You pick a ready-made template and colour palette, and every page carrying your name looks equally professional.',
        'templates_title' => 'Six templates, each with its own structure',
        'templates_sub' => 'Order and character change; the content is complete in every one — the presentation shifts, nothing is dropped.',
        'templates' => ['Classical', 'Modern', 'Editorial', 'Lesson', 'Brief', 'Research'],
        'palettes_title' => 'Six calibrated palettes',
        'palettes_sub' => 'Calibrated for paper and screen alike, and they work in any template you choose with no second round of setup.',
        'palettes' => ['Emerald', 'Indigo', 'Earth brown', 'Slate', 'Burgundy', 'Teal'],
        'logo_title' => 'Your mark first; ours adapts to your palette',
        'logo_sub' => 'Your name and mark head every page, and your links sit in its footer. The Khulasat mark takes on your page\'s colour, so it never competes with yours.',
        'langs_title' => 'Publishing in four global languages',
        'langs_sub' => 'Summaries publish in Arabic, and alongside it in one or more other languages — each with its own permanent link.',
    ],

    'faq' => [
        'eyebrow' => 'Questions',
        'h2' => 'Nine questions people ask first',
        'items' => [
            ['q' => 'Do you rule on whether a hadith is authentic?', 'a' => 'No — we match and cite, nothing more. The platform matches the hadith\'s wording to its trusted source, gives its location and number, and reports its grading exactly as the source states it, without judging it on its own. Ruling on a hadith is the work of scholars; integrity means passing the text on as it is, so the researcher can check it for themselves.'],
            ['q' => 'And if a citation in the lecture does not match its source?', 'a' => 'Publishing stops at that point. If a citation is worded differently from its source, the platform alerts you and shows what was said in the lecture next to the source wording, so you can keep the source wording or remove the citation. There is no option anywhere on the platform that skips this step.'],
            ['q' => 'How long does a summary take?', 'a' => 'Just a few minutes. A one-hour lecture is ready for your review in minutes; longer ones take more time. Review then takes you a few minutes, and is not needed at all if every citation matches its source.'],
            ['q' => 'Does it work for non-religious lectures?', 'a' => 'Yes — for lectures, lessons, courses and seminars. The sourcing engages when the material contains a verse or a hadith; if there is no scriptural citation, the summary goes through without a stop. Among the six templates are ones suited to structured teaching, briefs and research, not only to the sermon.'],
            ['q' => 'How do I get an account?', 'a' => 'Accounts are invitation-only at this stage; there is no self sign-up. Try it on one of your own lectures from the "Try it on your lecture" tab at the foot of this page, or write to us from the "Contact us" tab, and we will get back to you.'],
            ['q' => 'What does a subscription cost?', 'a' => 'Pricing is settled with each institution individually, according to its size and how many summaries it publishes a month. There is no online payment at this stage. Write to us through the form at the foot of the page, tell us how much you publish, and we will come back with a figure for you.'],
            ['q' => 'Where do the texts of verses and hadith come from?', 'a' => 'From established sources we hold a complete copy of on our own systems, so we ask no outside service and rely on no machine\'s memory. Every citation has its reference visible in the "Verses and hadith cited" list at the foot of the page, for the reader to check independently.'],
            ['q' => 'Who owns the resulting page?', 'a' => 'All rights are yours. The page is published under your name and logo at a permanent link you own, and you can update it, unpublish it or delete it permanently at any time.'],
            ['q' => 'How do I request removal of a page, or report an error in one?', 'a' => 'There is a visible link in the footer of every published page, and it works without signing in — because whoever objects is usually not the account holder: a scholar to whom words have been attributed, or a reader who spotted a wrong reference. The request opens a record on our side that is followed up.'],
        ],
    ],

    'invite' => [
        'h2' => 'Write to us, or try it on one lecture',
        'lede' => 'Ask us a question or tell us what you need. Or send a lecture link, and we will produce a complete summary from it and put its real citations in front of you one by one — then you judge for yourself.',
        'alt_showcase' => 'Or browse a genuinely published summary first →',
        'alt_anatomy' => 'Or see the sample page first →',
        'tabs_aria' => 'Type of request',
        'tab_contact' => 'Contact us',
        'tab_lecture' => 'Try it on your lecture',
        'contact_hint' => 'A question, something about pricing, or anything else you would like to tell us.',
        'lecture_hint' => 'Try Khulasat on one of your own lectures: send its link, and we will produce a complete summary and show you its citations before anything is published.',
        'name' => 'Name',
        'role' => 'You are',
        'role_placeholder' => 'Choose…',
        'roles' => ['A student or researcher', 'A content creator or speaker', 'A mosque or Islamic centre', 'An academy or institute', 'Something else'],
        'contact' => 'Email or WhatsApp number',
        'link' => 'Lecture link',
        'optional' => '(optional)',
        'message' => 'Your message',
        'message_placeholder' => 'Your question, or what you need.',
        'lecture_message' => 'Notes',
        'lecture_message_placeholder' => 'Anything you would like us to know about the lecture or your institution.',
        'submit_contact' => 'Send message',
        'submit_lecture' => 'Request a trial',
        'tiny' => 'Every message is read and answered by a person on our team, and we send no marketing email.',
        'sent_contact' => 'We have your message. Someone on our team will read it and reply on the contact you gave us.',
        'sent_lecture' => 'We have your request and the lecture link. Someone on our team will look at it and reply on the contact you gave us.',
        'errors' => [
            'name' => 'Please give us a name.',
            'role' => 'Tell us who you are, so we know what suits you.',
            'contact' => 'We need an email or WhatsApp number to reply on.',
            'link' => 'That link is not valid. Copy it in full from the address bar.',
            'message' => 'That message is longer than the field takes. Shorten it, or send it on your contact.',
            'message_required' => 'Write your message so we know how to help.',
            'lecture_link' => 'Add the link to the lecture you would like us to try.',
        ],
    ],

    'footer' => [
        'slogan' => 'A summary to read, a citation verified.',
        'page' => 'This page',
        'links' => 'Links',
        'login_tenants' => 'Institution sign-in',
        'complaint' => 'Request removal or report an error',
        'anatomy_link' => 'Sample page',
        'rights' => 'Khulasat · © 2026',
    ],

];
