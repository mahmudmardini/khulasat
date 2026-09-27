import { useLayoutEffect, useRef, type InputHTMLAttributes } from 'react';
import { Icon } from '@/Components/Icon';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';

interface Props extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type' | 'className'> {
  /** هل الكلمةُ ظاهرة؟ الحالةُ عند الأب ليُعيدها مخفيّةً متى شاء، كبعد إرسالٍ فاشل. */
  visible: boolean;
  onVisibleChange: (visible: boolean) => void;
}

/**
 * حقلُ كلمة المرور بزرّ إظهارٍ وإخفاء.
 *
 * يُلفّ بـ {@see FieldGroup} كأيّ حقل: يمرّر `...rest` إلى `<input>` فتصله
 * `id` و`aria-*` التي يحقنها الغلاف، ولا يُفقد الربطُ بالتسمية والخطأ.
 *
 * ★ **الزرُّ على الحافّة المنطقية النهائية** (`end-*`)، فيقع يساراً في RTL
 * حيث يقع سهمُ `select` — والحقلُ `dir="ltr"` كحقل البريد، فلا يقفز النصُّ
 * ولا تنقلب محاذاتُه بين الحالتين.
 *
 * ★ **`onMouseDown` يمنع سرقة التركيز**: نقرُ الزرّ بالفأرة أو باللمس يُبقي
 * المؤشّرَ في الحقل فيُكمل المستخدم الكتابة، والتنقّلُ بلوحة المفاتيح
 * (Tab ثم Enter/Space) لا يمرّ بهذا الحدث فيبقى سليماً.
 *
 * ★ **وموضعُ المؤشّر يُحفظ ويُعاد** — T-155. تغييرُ `type` من `password` إلى
 * `text` يُصفّر التحديدَ في المتصفّحات فيقفز المؤشّرُ إلى أول النصّ. فنقرأ
 * `selectionStart/End` قبل التبديل، ونعيدهما بعد رسمه مباشرةً، ثم مرّةً أخرى
 * بعد الإطار التالي. ولا نُعيدهما إلا إن كان الحقلُ مركَّزاً، كي لا نسرق التركيز
 * ممّن ضغط الزرّ بلوحة المفاتيح.
 */
export function PasswordInput({ visible, onVisibleChange, ...rest }: Props) {
  const inputRef = useRef<HTMLInputElement>(null);
  const selection = useRef<{ start: number; end: number; direction: 'forward' | 'backward' | 'none' } | null>(null);

  useLayoutEffect(() => {
    const input = inputRef.current;
    const saved = selection.current;
    selection.current = null;

    if (input === null || saved === null) {
      return;
    }

    const restore = (): void => {
      if (document.activeElement === input) {
        input.setSelectionRange(saved.start, saved.end, saved.direction);
      }
    };

    restore();
    // Chrome يُصفّر التحديدَ مرّةً ثانية بحدثٍ غير متزامن بعد تبديل `type`،
    // فتُعاد الاستعادةُ بعد الإطار التالي — وقد قيس ذلك، لا افتراض.
    const frame = requestAnimationFrame(restore);

    return () => cancelAnimationFrame(frame);
  }, [visible]);

  function toggle(): void {
    const input = inputRef.current;

    if (input !== null && document.activeElement === input) {
      selection.current = {
        start: input.selectionStart ?? input.value.length,
        end: input.selectionEnd ?? input.value.length,
        direction: input.selectionDirection ?? 'none',
      };
    }

    onVisibleChange(!visible);
  }

  return (
    <div className="relative">
      <input
        {...rest}
        ref={inputRef}
        type={visible ? 'text' : 'password'}
        dir="ltr"
        /* الحقلُ `ltr` فبدايتُه المنطقية يسارٌ — حيث الزرّ — فالحشوةُ `ps` لا `pe`. */
        className="field ps-11 text-start"
      />

      <button
        type="button"
        aria-pressed={visible}
        aria-label={t(visible ? 'auth.hide_password' : 'auth.show_password')}
        title={t(visible ? 'auth.hide_password' : 'auth.show_password')}
        onMouseDown={(event) => event.preventDefault()}
        onClick={toggle}
        className={cn(
          'absolute end-1.5 top-1/2 grid size-8 -translate-y-1/2 cursor-pointer place-items-center rounded',
          'transition-colors duration-150 hover:bg-surface-alt hover:text-text',
          visible ? 'text-primary' : 'text-text-faint',
        )}
      >
        <Icon name={visible ? 'eyeOff' : 'eye'} size={18} />
      </button>
    </div>
  );
}
