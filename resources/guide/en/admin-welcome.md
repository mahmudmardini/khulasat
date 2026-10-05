# Welcome to the administrator panel {#admin-welcome}

**Khulasat** turns the lectures of mosques and centres into well-documented written pages: an institution gives it a lecture's link, file or text, and it extracts the ideas, picks up the verses and hadiths, matches each one against its source by text, then publishes a page in the institution's identity.

The **platform administrator** runs all of this from above the institutions:

- **Creates institutions** and sets each one's subscription limits, and pauses or reactivates them.
- **Follows every summary** on the platform, restarts what failed and cancels what got stuck.
- **Settles readers' complaints** about published pages, within their deadlines.
- **Answers join requests** sent from the public landing page.
- **Chooses the AI model** for each stage, watches the costs, and halts spending when needed.

## The life of a summary, briefly {#admin-welcome-flow}

To understand what you see in your panel:

1. **The institution gives a source**, and a **job** is created that goes through stages: extracting the text, cleaning it, extracting the structure, extracting the evidence, and verifying it.
2. **Anything that did not match its source stops** for review by the institution, depending on the **evidence mode** you set for it.
3. After review: **writing the body**, building the quiz if requested, and **producing and publishing the page**.
4. Every stage that calls a model **has its cost recorded**, which you see in [Costs](#admin-costs) and in [Jobs](#admin-jobs).

> [!NOTE]
> Matching against the sources is **deterministic, with no AI**: a text comparison against a copy of the Mushaf and the hadith books. Models are used only for extraction, writing and translation.

> [!NOTE]
> **The administrator panel is in Arabic only**, whatever the language of the institutions' panels, because it is an internal working tool. Buttons are named in this guide as they appear on screen, with their meaning in brackets.

## How to use this guide {#admin-welcome-howto}

- **Search** at the top of the page finds any word in the guide. Press `/` to start searching from anywhere.
- **A link to any section**: the small button next to each heading copies a direct link to it.
- To see what institutions see in their panel, read the [Institution owner guide](/guide/en/owner).
