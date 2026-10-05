# Institutions {#admin-tenants}

**الجهات** (institutions) lists every institution subscribed to the platform.

![The list of institutions.](shot:admin-tenants)

- **Search** by name or Latin identifier, and **filter** by status (all, active, paused).
- For each institution: its name, Latin identifier, plan, monthly quota, number of summaries and status.
- Click an institution's name to open [its page](#admin-tenant).

## Creating a new institution {#admin-tenants-create}

Accounts in Khulasat are by invitation, **so every institution starts here**.

1. Click **جهة جديدة** (new institution).
2. Fill in:
   - **اسم الجهة** (the institution's name) in Arabic.
   - **المعرّف اللاتيني** (the Latin identifier): lowercase Latin letters, numbers and hyphens, such as `masjid-alhay`. **It appears in the link of every page the institution publishes, and is not changed after publishing.** Some words are reserved for the system and are not accepted (such as `admin`, `panel`, `verify` and `guide`).
   - **اسم المالك** and **بريد المالك** (the owner's name and email).
   - **وضع الشواهد** (evidence mode): «يُنشر مع بيان درجته» (published with its grading stated, the default) or «يقف للمراجعة» (stops for review). See [Evidence mode](#admin-tenant-verification).
3. Click **جهة جديدة**.
4. The owner's **temporary password** appears.

![The new institution form.](shot:admin-tenant-new)

> [!WARNING]
> **Copy the temporary password now and hand it to the owner yourself.** It is not shown again, not stored in the log, and the system does not email it. The owner should change it after their first sign-in (through "Forgotten your password?").

A new institution starts with **small trial limits**: three summaries a month, lectures of up to 90 minutes, and no audio transcription minutes. So apply its plan from [its page](#admin-tenant-plan).
