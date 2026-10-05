# The institution page {#admin-tenant}

Click any institution's name in the list to open its page. It holds everything about its subscription and support.

![The institution page: 1 this month's usage, 2 the institution's owners and signing in as the institution, 3 the plan.](shot:admin-tenant)

## This month's usage {#admin-tenant-usage}

A side card with: how many summaries the institution has used of its quota, its plan, its cost to the platform this month, its pages' reads (30 days / all time) split by language, and its subscription date.

## The plan {#admin-tenant-plan}

There is no payment gateway in Khulasat: the institution transfers money to the bank account, and you apply its plan here.

1. Choose the plan: **مجاني** (free), **فردي** (individual), **أعمال** (business) or **مؤسّسي** (enterprise). Under each plan are its limits, and «شرائح وصور» (slides and images) if it includes them.
2. Write **سبب التغيير** (the reason for the change): the transfer reference and amount.
3. Click **طبّق الباقة** (apply the plan).

Applying a plan fills in the five limits in one go. If the institution's limits do not match its plan, you see «الحدود معدَّلة عن الباقة» (limits adjusted from the plan); this is intentional when a limit has been raised as an exception.

## The institution's limits {#admin-tenant-limits}

![The five limits of the institution and the reason for the change.](shot:admin-tenant-limits)

| Limit | Meaning |
|---|---|
| ملخّصات في الشهر (summaries per month) | The monthly quota. |
| ملخّصات في اليوم (summaries per day) | The most that can start in a single day. |
| أقصى مدّة درس (longest lecture, minutes) | A longer lecture is refused. |
| دقائق التفريغ الصوتي (audio transcription minutes) | What may be written down from audio each month. |
| مرّات إعادة التوليد لكلّ ملخّص (regenerations per summary) | — |

- **Zero means no limit.**
- **The reason for the change is required**: write the transfer reference, amount and date. This is a financial event reviewed months later, and it is not saved without a reason.

## Subscription status {#admin-tenant-status}

- **أوقف الاشتراك** (pause the subscription): stops the institution from creating new summaries. **Its published pages keep working as they are.**
- **أعد التفعيل** (reactivate): lets it create again.

Each has a reason that is written and recorded.

## Evidence mode {#admin-tenant-verification}

It decides which verses and hadiths are published **without anyone from the institution reviewing them**:

![Evidence mode and subscription status.](shot:admin-tenant-modes)

- **يُنشر مع بيان درجته** (published with its grading stated, the default): every verse or hadith whose source is known is published with its reference and grading, including weak ones with their weakness stated.
- **يقف للمراجعة** (stops for review): only what matched its source exactly and is sound is published automatically. Everything else waits for a reviewer from the institution, so publishing takes longer.

In both modes, **anything whose source is not known is removed and not published.** Changing the mode needs a written reason, because it changes what gets published without review.

## Carousel templates for this institution {#admin-tenant-carousel}

- **تعليمات توليد القوالب لهذه الجهة** (template generation instructions for this institution): text that replaces the default instructions for this institution only. Leave it empty to return to the default, or click **ابدأ من الافتراضية** (start from the default) to edit it.
- **Generate templates** with these instructions, and preview them as the institution sees them. Generation is a model call guarded by the spending cap.

## Signing in as the institution {#admin-tenant-impersonate}

For technical support: you see the institution's panel **exactly as its owner does**.

1. In the **مالكو الجهة** (institution owners) card, click **ادخل بهوية الجهة** (sign in as the institution).
2. The institution's panel opens with **a red bar** at the top saying you are signed in as that institution.
3. When you are done, click **اخرج من الهوية** (leave the identity) in the bar.

![The red bar while signed in as an institution.](shot:admin-impersonating)

> [!WARNING]
> Everything you do while signed in as the institution **is done in its name**. The whole session is recorded in the log: who entered, when, and for how long. Use it for support only.

## This institution's log {#admin-tenant-audit}

At the end of the page is the audit log for this institution: every change to its plan, limits, status or evidence mode, and every sign-in as it.

![The end of the institution page: template previews and the audit log.](shot:admin-tenant-support)
