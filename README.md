<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/brand/svg/khulasat-lockup-horizontal-reverse.svg">
    <img src="docs/brand/svg/khulasat-lockup-horizontal.svg" alt="خُلاصات" width="300">
  </picture>
</p>

# خُلاصات

منصّة تحوّل دروس المساجد والمراكز الإسلامية إلى ملخّصات بصرية موثّقة الشواهد، تُنشر بالعربية وبثلاث لغات أخرى.

تدخل المحاضرة رابطَ يوتيوب أو ملفّاً صوتياً أو نصّاً، فيُفرَّغ الكلام وتستخرج النماذج اللغوية محاوره وشواهده ثمّ تكتب الملخّص. **ولا يُنشر شاهدٌ لم يُطابَق.** فكلّ آية تُطابَق حرفاً على المصحف المحلّي، وكلّ حديث على الكتب الستّة وموطّأ مالك، مطابقةً نصّية حتمية لا يشارك فيها نموذج لغوي. وما لم يُطابَق لا يُنشر: إمّا يُحذف من الملخّص، وإمّا تقف المهمّة بحالة `needs_review` حتى يقرّر فيه إنسان. ولا إعداد في المنصّة يتجاوز هذا.

> **Khulasat** turns Islamic lectures into visual, multilingual summaries. Every Qur'anic verse and hadith quoted by the language models is checked against a local corpus by deterministic text matching — no LLM in the verification layer — and nothing unverified is ever published.

---

## المشاركة في تحدّي الذكاء الاصطناعي في خدمة المحتوى الإسلامي

| | |
|---|---|
| **المسار** | الرابع — أدوات المعرفة والتحقّق للمتخصّصين |
| **حالة المشروع** | مشروع قائم. بدأ العمل عليه في 5 سبتمبر 2026 في مستودع خاصّ |
| **النسخة السابقة** | الوسم [`baseline-2026-09-27`](../../releases/tag/baseline-2026-09-27)، والإفصاح الكامل عنها في [PRIOR-WORK.md](PRIOR-WORK.md) |
| **ما يُقيَّم** | ما أُضيف بين 4 و6 أكتوبر 2026 فقط، وهو مسجّل في القسم الأخير من [PRIOR-WORK.md](PRIOR-WORK.md) |
| **خطة التحدّي** | [CHALLENGE.md](CHALLENGE.md)، والمهامّ المفتوحة كلّها ومن يعمل عليها في [TASKS.md](TASKS.md) |
| **المصادر والرخص** | [SOURCES.md](SOURCES.md) |
| **رخصة المستودع** | إتاحة المصدر للاطّلاع والتقييم، لا مصدر مفتوح. انظر [LICENSE](LICENSE) |

**لا بيانات في المستودع.** لا محاضرات ولا تفريغات ولا ملخّصات، ولا جهات ولا مستخدمين، حقيقيةً كانت أو مصنوعة. فكلّ بيانات المنصّة تُنشأ على النسخة المنشورة نفسها. والذي في المستودع من النصوص بياناتٌ مرجعية للتحقّق فقط: مدوّنة الحديث، وعيّنات من المصحف والحديث في الاختبارات، وعيّنة الشواهد المدسوسة. ومصادرها في [SOURCES.md](SOURCES.md).

---

## ما يميّز المنصّة

- **طبقة تحقّق بلا نموذج لغوي.** تُطبَّع النصوص العربية أوّلاً: التشكيل، وصور الهمزة، والرسم العثماني مقابل الإملائي. ثمّ تُطابَق الآية على المصحف (6236 آية، حفص عن عاصم) والحديثُ على الكتب الستّة والموطّأ. التنفيذ في `app/Services/Verification/` و`app/Support/Hadith/`.
- **عيّنة شواهد مدسوسة معيارُ قبول.** يحوي `fixtures/evidence-fixtures.json` ثماني عشرة حالة، منها آية بُدّلت فيها كلمة، وآية رُكّبت من سورتين، وحديث صحيح حُرّف لفظه، وأحاديث مشهورة على الألسنة لا أصل لها. وتفحص الـCI في كلّ تغيير أنّ كلّ حالة تنتهي إلى حكمها المنصوص.
- **بوّابة نماذج مستقلّة عن المزوّد.** لكلّ مرحلة من المراحل الستّ نموذج أساسيّ وبديل (Anthropic وOpenAI وGoogle)، وتُحسب كلفة كلّ نداء. ولا يُستدعى نموذج قبل فحص حصّة الجهة وسقف الإنفاق اليومي والشهري.
- **نشرٌ بأربع لغات.** العربية والإنجليزية والتركية والروسية. وتأتي الآية في الصفحة المترجَمة بترجمة معتمدة لا بترجمة النموذج.
- **منصّة متعدّدة الجهات.** لكلّ جهة هويتها البصرية وفريقها وحصّتها ونطاقها. وتتيح لوحة المشرف متابعة الكلفة والنماذج والشكاوى.

---

## التشغيل محلّياً

المتطلّبات: PHP 8.3 وPostgreSQL 16 وNode 20 (وRedis إن أردت Horizon).

```bash
cp .env.example .env && composer install && npm install
php artisan key:generate && php artisan migrate
php artisan db:seed                                    # إعداد النماذج وحده
php artisan khulasah:seed-hadith
php artisan serve                                      # http://localhost:8000
npm run dev                                            # Vite (في طرفية ثانية)
php artisan queue:work                                 # أو php artisan horizon مع Redis
```


**لا حساب جاهزاً.** يُنشأ مشرف المنصّة بأمر واحد، ثمّ يُنشئ الجهات ويدعو فِرَقها من لوحته:

```bash
php artisan tinker --execute="app(App\Support\TenantContext::class)->withoutScope(fn () => App\Models\User::forceCreate(['tenant_id' => null, 'name' => 'مشرف المنصّة', 'email' => 'you@example.com', 'password' => Illuminate\Support\Facades\Hash::make('كلمة-مرور-قويّة'), 'role' => App\Enums\Role::Owner, 'email_verified_at' => now()]));"
```

**النماذج.** قيمة `MODEL_GATEWAY` الافتراضية `fake`، والبوّابة الوهمية تقرأ ردوداً مسجّلة من `tests/Fixtures/model-responses/`. **ولا ردود مسجّلة في المستودع**، فتُسجَّل عند الحاجة بـ`php artisan khulasah:record-response`، وهو ينادي نموذجاً حقيقياً مرّةً لكلّ مرحلة. أو تُستعمل النماذج الحقيقية مباشرةً. والتشغيل الحقيقي يحتاج `MODEL_GATEWAY=real` ومفاتيح المزوّدين في `.env`، ثمّ `php artisan khulasah:preflight` ليبيّن ما ينقص قبل أيّ إنفاق.

**المصحف** يُجلب من واجهة Quran Foundation بحساب مطوّر مجّاني: `QURAN_CLIENT_ID` و`QURAN_CLIENT_SECRET` في `.env`، ثمّ `php artisan khulasah:seed-quran`. أمّا الاختبارات فلا تحتاجه، لأنّها تبذر عيّنة من `tests/Fixtures/quran/`.

## الاختبارات

```bash
npm ci && npm run build
php artisan test                            # 1691 اختباراً
php artisan test --filter=EvidenceFixtures  # الشواهد المدسوسة وحدها
./vendor/bin/pint --test                    # تنسيق PHP
npx tsc --noEmit                            # فحص الأنواع
```

**تعمل الاختبارات على Postgres لا على sqlite**، لأنّ المنصّة تعتمد أعمدة `jsonb` وفهارس نصّية عربية يختلف سلوكها بين المحرّكين.

---

## البنية

| المجلّد | المحتوى |
|---|---|
| `app/Actions/` | منطق المنتج في أفعال أحادية الغرض، لكلّ منها دالّة `handle()` واحدة |
| `app/Services/Verification/` | طبقة التحقّق: محقّق الآيات ومحقّق الأحاديث |
| `app/Services/Model/` | بوّابة النماذج ومحوّلات المزوّدين والبوّابة الوهمية |
| `app/Services/Transcript/` | التفريغ من يوتيوب ومن الملفّات الصوتية |
| `app/Services/Render/` | تحويل كتل الملخّص إلى صفحة HTML وكاروسيل |
| `prompts/islamic/PROMPT-PACK.md` | تعليمات المراحل الستّ للمجال الشرعي |
| `fixtures/evidence-fixtures.json` | عيّنة الشواهد المدسوسة |
| `database/data/hadith/` | مدوّنة الحديث المضغوطة ومصدرها |
| `resources/js/` | الواجهة: React 19 وInertia وTypeScript وTailwind |

## الوثائق

| الملفّ | المحتوى |
|---|---|
| [khulasah-build-spec.md](khulasah-build-spec.md) | المواصفة التقنية: الجداول، وآلة الحالات، والتحقّق، والأمن |
| [SCREENS.md](SCREENS.md) | نظام التصميم وجرد الشاشات |
| [khulasah.skill](khulasah.skill) | قالب الملخّص وكتله البصرية وقواعد الكتابة والتوثيق |
| [docs/templates/](docs/templates/) | قوالب المخرَج ومعايناتها |
| [docs/brand/svg/](docs/brand/svg/) | ملفّات الشعار: الرمز، والكلمة، والتركيبتان الأفقية والعمودية، وأيقونة التطبيق |
| [bench/](bench/) | سكربت قياس النماذج الذي اختيرت به نماذج المراحل |

تبقى في تعليقات الكود إشارات إلى ملفّات داخلية مثل `TASKS.md` و`CLAUDE.md`. هذه ملفّات إدارة عمل في المستودع الخاصّ لم تُنشر هنا، ولا يعتمد عليها الكود.
