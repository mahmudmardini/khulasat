# The Verify tool {#verify}

**Verify** (تحقّق) is a public page anyone can use, **with no account and no sign-up**. Paste an article, a sermon or a message that is going around, and it pulls out the verses and hadiths in it, matches each one against its source, and tells you what it found and what it did not, and why.

Open it from **Verify tool** in the side menu, or from the Khulasat address followed by `/verify`.

> [!NOTE]
> The tool is **in Arabic only**, in every language of this guide, because the texts it checks are Arabic. Its buttons are named below as they appear on screen, with their meaning.

## Checking a text {#verify-check}

1. Paste the text into the box. A counter under it shows how many characters you have typed out of the maximum.
2. Or click **جرّب بنصٍّ جاهز** (try a ready-made text) to see the tool work on an example with a verse in which a word was changed and a hadith with no basis.
3. Click **تحقّق من النصّ** (check the text).

![The Verify page before checking.](shot:verify-form)

Three steps appear: extracting the evidence from the text, matching each piece against its source, then "the report is ready". This usually takes **less than a minute**.

## Reading the report {#verify-report}

![The verification report: 1 the number of citations per verdict, 2 the text with its citations marked, 3 the card for each citation.](shot:verify-report)

1. **The top of the report**: the number of citations with each verdict. Click a verdict to see only its citations.
2. **The text with citations marked**: your text as you pasted it, with every citation highlighted. Click one to jump to its card.
3. **The card for each citation**:
   - **Its type**: verse, hadith, saying of a Companion, or saying of a scholar.
   - **Its verdict** and the reason, written out clearly.
   - **As written in the text**, and the **source wording** next to it, and any words missing from a verse or added to it.
   - **The location**: the surah and verse number, or the book and hadith number.
   - **The grading**, the **rulings of hadith scholars as given in the source**, and the **full text with its chain of narration**.
   - A link **to the verse on quran.com**, or **a search for the hadith on Dorar.net** (al-Durar al-Saniyya).

| Verdict | Meaning |
|---|---|
| **مطابق** (matches) | Found word for word in the source. |
| **قريبٌ من لفظ المصدر** (close to the source wording) | Similar but not identical, with the degree of similarity. |
| **لم نجده في مصادرنا** (not found in our sources) | Not in the Mushaf or the seven books. This is not a ruling that it is fabricated. |
| **لا مصدر لنوعه** (no source for its kind) | A scholar's saying or similar, with no reference for us to match it against. |

## Sharing {#verify-share}

- **انسخ رابط التقرير** (copy the report link): a link that opens the same report for whoever you send it to.
- **انسخ التقرير نصّاً** (copy the report as text): a written copy to paste into a message.
- **تحقّق من نصٍّ آخر** (check another text): back to the checking page.

> [!LIMIT]
> - **{{verify_per_hour}} requests an hour** per user. Once you reach it, the tool tells you how many minutes to wait.
> - **Text length** from {{verify_min_chars}} to {{verify_max_chars}} characters. A longer text is split up and each part checked on its own.
> - **The text and its report are deleted after {{verify_retention_days}} days**, and the report link stops working after that.
> - AI is used only to **extract** the verses and hadiths from the text. The **verdict** is a text match against the sources, with no AI.
> - The report **is not a fatwa and not a ruling on a hadith**. Judging hadiths belongs to scholars.
