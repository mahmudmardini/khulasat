# Models {#admin-models}

Each stage of preparation is carried out by **an AI model** that you choose. **النماذج** (models) has a card for each stage.

![Models: a card for each stage, with an «تعديل» (edit) button.](shot:admin-models)

> [!WARNING]
> **Switching a model changes both the product's quality and its cost**, and takes effect **immediately** for every summary that starts afterwards, with no deployment or restart. Every change is recorded under your name.

## The stages {#admin-models-stages}

| Stage | What it does |
|---|---|
| `cleaning` | Cleans up the lecture text. |
| `extracting_structure` | Extracts the central idea and the main sections. |
| `extracting_evidence` | Picks up the verses and hadiths from the text (the Verify tool uses it too). |
| `writing` | Writes the body of the summary. |
| `output_metadata` | The page's title, description and link. |
| `translating` | Translates the summary into the publishing languages. |
| `carousel` | The Instagram slide texts. |
| `carousel_design` | Suggests carousel templates for institutions. |
| `quiz` | The comprehension quiz questions. |
| `lecture_details` | Reads the gathering details from a lecture poster (not yet connected to a screen). |

## The card's fields {#admin-models-fields}

- **المزوّد** and **النموذج** (provider and model): the company and the model's name.
- **المزوّد البديل** and **النموذج البديل** (fallback provider and model): used if the first one fails.
- **أقصى مخرَج** (maximum output), **مستوى التفكير** (thinking level), **المهلة** (timeout, seconds), **المحاولات** (retries) and **عند الاستنفاد** (what happens when all retries fail).
- **سعر المدخل / مليون** and **سعر المخرَج / مليون** (input and output price per million tokens): the provider's prices, from which costs are calculated everywhere. **Update them when the provider changes its prices**, or the cost figures become wrong.
- **فعّال** (active).

Click **تعديل** (edit), make your change, write **سبب التغيير** (the reason for the change) if it is an experiment (optional), then save.

> [!LIMIT]
> - **Provider keys are not here.** They are set in the server's configuration only, and never appear in the panel.
> - **Checking verses and hadiths involves no model**, so it has no card. It is a fixed text match.
