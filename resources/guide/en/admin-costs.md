# Costs and the spending cap {#admin-costs}

**الكلفة** (costs) shows how much the platform has spent on AI models, **in actual dollars**, not estimates: for each stage, model and institution.

![Costs: 1 the spending cap, 2 the headline figures, 3 the breakdown by stage and model.](shot:admin-costs)

## The spending cap {#admin-costs-cap}

An upper limit on what the platform spends **per day** and **per month**, with how much of each has been spent.

- If spending reaches the cap, **summary creation stops across the whole platform** automatically, for every institution at once, and the «الطابور موقوفٌ الآن» (the queue is halted) bar appears in your panel.
- **أوقف الطابور** (halt the queue): you halt it by hand (when a provider has an outage, or spending is unexpected).
- **ارفعِ الوقف** (lift the halt): creation resumes immediately.

Each comes with **سبب التغيير** (the reason for the change), recorded in the log so that whoever reviews it months later knows why the queue was halted, or why the halt was lifted.

> [!NOTE]
> The cap values themselves are set in the server's configuration, not from the panel. The panel only halts and lifts the halt.

## The headline figures {#admin-costs-kpis}

- **كلفة اليوم** and **كلفة هذا الشهر** (today's cost and this month's cost).
- **متوسّط الملخّص** (average per summary): what one summary costs on average.
- **نسبة الإخفاق (آخر ساعة)** (failure rate, last hour): how many jobs failed in the last hour. A sudden rise usually means a provider is having problems.

## The breakdowns {#admin-costs-breakdown}

![Breakdown by model, monthly trend, by video length, and by institution.](shot:admin-costs-breakdown)

- **By stage**: which stage costs most (usually writing).
- **By model and provider**.
- **By institution**: each institution's cost, its plan, its subscription price, and the **margin** between them (if the plan has a recorded price).
- **By video length**: the cost of a summary according to the lecture's length.
- **Monthly trend**: cost month by month.

> [!NOTE]
> The figures are for this month only. Audio transcription cost is included in the total and in the breakdowns by stage, model and institution, and left out of the breakdown by video length.
