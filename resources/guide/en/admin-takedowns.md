# Complaints {#admin-takedowns}

At the foot of every published page is a link to **report an error in this summary**. What readers send through it reaches you here, with **a stated deadline** for a response.

![Complaints: 1 the overdue alert, 2 filtering by status and type, 3 a complaint card.](shot:admin-takedowns)

## Types of complaint and their deadlines {#admin-takedowns-kinds}

| Type | Usually sent by | Deadline |
|---|---|---|
| **خطأ تخريج** (reference error) | A reader who saw a wrong reference or grading. | {{evidence_complaint_days}} days |
| **اعتراض نسبة** (attribution objection) | The speaker, objecting to words attributed to them. | {{takedown_hours}} hours |
| **طلب إزالة** (removal request) | Someone asking for the page to be deleted. | {{takedown_hours}} hours |
| **غير ذلك** (other) | Another remark, such as a broken link. | {{evidence_complaint_days}} days |

## The complaint card {#admin-takedowns-card}

- **The type**, **وصل** (received, when) and **المهلة** (the deadline): hours left, or hours overdue.
- **The page** and its link, **the institution**, and the page's current state: still published, removed, or "no summary matches this link".
- **وسيلة التواصل** (how to reach them) and **نصّ الاعتراض** (the complaint text).

## Settling a complaint {#admin-takedowns-resolve}

First write **ما يُبلَّغ به صاحب الجهة** (what the institution owner is told): text the institution sees on its summary screen if its page is removed. Write it for them, **and do not include the complainant's email or the text of the complaint**. Then choose:

- **أزل الصفحة** (remove the page): the page's files are deleted and replaced with a page saying "This summary has been removed" (with code 410 rather than 404, so anyone opening the link knows the page existed and was removed). The system asks you to confirm, and it cannot be undone with a click.
- **عولجت بغير إزالة** (handled without removal): the error was corrected or the complainant answered, and the page stays.
- **لا إجراء** (no action): write the reason, since a dismissed complaint is reviewed months later just like a removal.

Every decision is recorded in the [audit log](#admin-audit), and a complaint cannot be settled twice.

> [!LIMIT]
> - The system **does not reply to the complainant itself**. Contact them yourself using the details they wrote.
> - The administrator cannot edit a page's text to correct an error. The institution corrects it (by regenerating, for example), or the page is removed.
