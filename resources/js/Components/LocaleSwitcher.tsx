import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Icon } from '@/Components/Icon';
import { t } from '@/lib/i18n';
import type { SharedProps } from '@/types/inertia';

/*
 * أسماءُ اللغات بألسنتها — **وليست نصَّ واجهةٍ يُترجَم**.
 *
 * SCREENS.md يمنع النصّ داخل المكوّن، وعلّتُه أنّ النصّ يتبدّل باللغة
 * فيُجمع في مكانٍ واحد. وهذه لا تتبدّل: «Türkçe» هي هي في الأربع —
 * ووضعُها في `lang/` أربعَ مرّاتٍ بقيمةٍ واحدة تكرارٌ لا تجميع.
 *
 * وترتيبُها ترتيبُ {@see App\Enums\Locale}، والعربيةُ أولاها لأنّها الأصل.
 */
const LOCALES = [
  { value: 'ar', native: 'العربية' },
  { value: 'en', native: 'English' },
  { value: 'tr', native: 'Türkçe' },
  { value: 'ru', native: 'Русский' },
] as const;

/**
 * مبدّلُ لغة اللوحة — T-133.
 *
 * ويُعرض المختارُ وحده والبقيّةُ عند الطلب، كمبدّل صفحة التعريف: الزائرُ
 * يختار لغته مرّةً ثمّ لا يعود، فلا تستحقّ أربعةُ أسماءٍ حيّزاً دائماً في
 * ترويسةٍ ضيّقة.
 */
interface Props {
  /*
   * جهةُ الانفتاح — بلاغُ مالك المنتج بلقطةٍ من شاشة الباب.
   *
   * **والمبدّلُ هناك في أسفل الصفحة**، ففتحُه لأسفل يتجاوز حدّها فيُحدث
   * تمريراً لقائمةٍ من أربعة أسطر. وهو في ترويسة اللوحة أعلاها، فيفتح
   * لأسفل كما هو متوقَّع.
   *
   * ★ **وصريحةٌ لا مقيسة**: الموضعان معروفان — ترويسةٌ في الأعلى وذيلٌ في
   * الأسفل — وقياسُ الفراغ عند كلّ فتحةٍ يُدخل ارتجاجاً وحساباً لا يشتريان
   * شيئاً هنا.
   */
  placement?: 'top' | 'bottom';
}

export function LocaleSwitcher({ placement = 'bottom' }: Props = {}) {
  const page = usePage<SharedProps>();
  const [open, setOpen] = useState(false);
  const box = useRef<HTMLDivElement>(null);

  const current = LOCALES.find((item) => item.value === page.props.locale) ?? LOCALES[0];

  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const away = (event: MouseEvent) => {
      if (box.current !== null && !box.current.contains(event.target as Node)) {
        setOpen(false);
      }
    };

    const escape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false);
      }
    };

    document.addEventListener('mousedown', away);
    document.addEventListener('keydown', escape);

    return () => {
      document.removeEventListener('mousedown', away);
      document.removeEventListener('keydown', escape);
    };
  }, [open]);

  /*
   * ★★ **نموذجٌ يُرسَل، لا زيارةُ Inertia** — وهذا أصلحَ عطلاً ظهر عند
   * تشغيل التطبيق فعلاً، ولم تكشفه الاختبارات ولا `curl`.
   *
   * **والعلّة أنّ `lang` و`dir` على `<html>`**، وهو في القالب لا في شجرة
   * React. فزيارةُ Inertia تستبدل المكوّن وتترك القالب على حاله، فيردّ
   * الخادمُ `en` وتبقى الصفحة `ar` في المتصفّح. وجُرّبت مزامنةٌ يدوية
   * للسمتين ثمّ `reload()` بعد الزيارة، وكلتاهما تعتمد على وقوع حدثٍ في
   * الوقت الصحيح — وسقطتا في التشغيل.
   *
   * **وإرسالُ نموذجٍ عاديّ يُنهي البابَ كلَّه**: تنقّلٌ كاملٌ من الخادم،
   * فما يُرسِله هو ما يُعرَض، بلا سمةٍ تُزامَن ولا حدثٍ يُنتظر. **ويعمل
   * بلا JavaScript** — وهو لائقٌ بمبدّلٍ قد يحتاجه من لا يقرأ الصفحة أصلاً.
   */
  // من الخصائص لا من `<meta>`: هي ما رُسمت به هذه الصفحة بعينها، فلا
  // تتخلّف عن تجديد الجلسة كما تخلّف الوسمُ قبل هذا الإصلاح.
  const token = page.props.csrf_token;

  return (
    <div ref={box} className="relative">
      <button
        type="button"
        onClick={() => setOpen((state) => !state)}
        aria-expanded={open}
        aria-haspopup="menu"
        aria-label={t('common.locale.label')}
        className="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-[13px] text-text-muted transition-colors hover:bg-surface-alt hover:text-text"
      >
        <Icon name="globe" size={16} />
        <span className="hidden sm:inline">{current.native}</span>
        <Icon name="chevron" size={12} className="rotate-90 text-text-faint" />
      </button>

      {open ? (
        <ul
          role="menu"
          className={[
            'absolute end-0 z-40 min-w-[150px] rounded-lg border border-border bg-surface p-1 shadow-lg',
            placement === 'top' ? 'bottom-[calc(100%+4px)]' : 'top-[calc(100%+4px)]',
          ].join(' ')}
        >
          {LOCALES.map((item) => (
            <li key={item.value} role="none">
              <form method="POST" action="/panel/locale">
                <input type="hidden" name="_token" value={token} />
                <input type="hidden" name="locale" value={item.value} />
                <button
                  type="submit"
                  role="menuitem"
                  lang={item.value}
                  aria-current={item.value === page.props.locale}
                  className={[
                    'flex w-full items-center justify-between gap-2 rounded-md px-2.5 py-2 text-start text-[13px] transition-colors hover:bg-surface-alt',
                    item.value === page.props.locale ? 'font-medium text-primary' : 'text-text',
                  ].join(' ')}
                >
                  {item.native}
                  {item.value === page.props.locale ? <Icon name="check" size={14} /> : null}
                </button>
              </form>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
