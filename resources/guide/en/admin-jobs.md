# Jobs {#admin-jobs}

A **job** is one summary with all its preparation stages. **المهامّ** (jobs) shows everything that has happened on the platform, across all institutions.

![The list of jobs.](shot:admin-jobs)

- **Filter** by status and by institution.
- **Sort** by newest, or by most read.
- For each job: its number, the lecture title, the institution, the status, the cost in dollars, the reads and when it finished.

## The job page {#admin-jobs-show}

Click any job to open it.

![The job page: 1 the preparation stages, 2 the cost and times, 3 the reads.](shot:admin-job)

- **مراحل إعداد الملخّص** (preparation stages): as the institution sees them, with each stage's time.
- **الكلفة** (cost): the total, the institution, the attempt number, the start and finish times, and **زمن الإعداد** (preparation time) and **زمن المراجعة** (review time) separately, so time spent waiting for the institution's decision is not counted against the platform.
- **القراءات** (reads) by language.
- **كلفة المراحل** (stage costs): for each model call, its provider, name, tokens sent and received, and cost.
- **سجلّ الانتقالات** (transition log): every change from one status to another with its time, and the **رمز الخطأ** (error code) if it failed.

## Restarting a stopped job {#admin-jobs-retry}

If a job stopped because of a fault on our side (not in the institution's source), click **أعد من المرحلة المتوقّفة** (restart from the stopped stage):

- A new job is created **that starts from the stage that failed**, carrying what came before it: the text, the structure and the evidence. Nothing already transcribed is transcribed again.
- **It does not count against the institution's quota**, because the fault was ours.
- But **the spending cap** still applies to it.

## Cancelling a stuck job {#admin-jobs-cancel}

**ألغِ المهمّة** (cancel the job) is for jobs that are not moving. Cancelling is final.

> [!LIMIT]
> - Only a **stopped** job can be restarted, and a finished one cannot be cancelled.
> - A summary's evidence is not reviewed from here. Review belongs to the institution, or to you if you [sign in as it](#admin-tenant-impersonate) for support.
