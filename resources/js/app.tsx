import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { setTranslations, type Translations } from '@/lib/i18n';
import '../css/app.css';

// جمعاً لا مفرداً — الهوية البصرية الثانية §٠٣، T-100.
const appName = import.meta.env.VITE_APP_NAME || 'خُلاصات';

void createInertiaApp({
  title: (title) => (title ? `${title} · ${appName}` : appName),

  /*
   * **قطعةٌ لكلّ صفحة، لا حزمةٌ واحدة** — T-124.
   *
   * كان `{ eager: true }` يضمّ كلَّ صفحات المشروع في ملفٍّ واحد (٦٠١ك)،
   * **فمن فتح شاشة الدخول نزّل لوحةَ المشرف ومعاينةَ القوالب**. وبلا
   * `eager` تصير القيمةُ دالّةَ استيرادٍ كسول، فيقسّمها Vite قطعاً ولا
   * تصل الصفحةُ إلّا عند طلبها. و`resolve` تقبل وعداً، وشريطُ التقدّم
   * أعلاه يغطّي زمنَ الجلب.
   */
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.tsx');
    const page = pages[`./Pages/${name}.tsx`];

    if (!page) {
      throw new Error(`صفحة Inertia غير موجودة: ${name}`);
    }

    return page() as never;
  },

  setup({ el, App, props }) {
    /*
     * النصوص تُضبط هنا لا في `AppLayout` وحده — **وشاشة الدخول خارج
     * الهيكل**، فكانت تعرض مفاتيحها الخام (`auth.email`) بدل نصّها.
     * وكلّ صفحةٍ خارج الهيكل كانت ستقع فيه.
     */
    const apply = (page: { props: Record<string, unknown> }) => {
      setTranslations((page.props.lang as Translations | undefined) ?? {});

      /*
       * ★ **والاتّجاه يُضبط هنا لا في القالب وحده** — T-133.
       *
       * `app.blade.php` يُرسم مرّةً عند أوّل تحميل، وتنقّلُ Inertia بعدها
       * لا يعيد رسمه. فمن بدّل لغته من الترويسة يبقى على الاتّجاه القديم
       * حتى يُحدّث الصفحة بنفسه — والتخطيطُ كلُّه مبنيٌّ على `dir`.
       */
      /*
       * ★ **الوسمُ يُحدَّث هنا لا في كلّ منادٍ** — {@see lib/csrf.ts} يقرؤه
       * الفحصُ المسبق ومعاينةُ الهوية، وكلاهما كان يسقط بـ419 بعد الدخول
       * لأنّ الوثيقة لا تُعاد وفيها رمزُ ما قبله.
       */
      const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
      const freshToken = page.props.csrf_token;

      if (meta !== null && typeof freshToken === 'string') {
        meta.content = freshToken;
      }

      const root = document.documentElement;
      const locale = page.props.locale;
      const direction = page.props.direction;

      if (typeof locale === 'string') {
        root.lang = locale;
      }

      if (typeof direction === 'string') {
        root.dir = direction;
      }
    };

    apply(props.initialPage);
    router.on('navigate', (event) => apply(event.detail.page));

    createRoot(el).render(<App {...props} />);
  },

  // الرمز لا قيمتُه: كان هذا وحده أخضرَ مكتوباً، فكان سيبقى أخضرَ بعد
  // تبدّل الهوية (T-100). والشريط يُحقن CSS نصّاً، فيقرأ المتغيّر.
  progress: { color: 'var(--primary)' },
});
