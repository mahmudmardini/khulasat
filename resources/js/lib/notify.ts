import { useCallback, useEffect, useState } from 'react';

/**
 * نبأُ الانتهاء — T-83.
 *
 * «يمكنك مغادرة هذه الصفحة» وعدٌ نصفُه: من غادر لا يعرف متى يعود. فهنا
 * نغمةٌ خافتة وإشعارُ متصفّح وعلامةٌ في عنوان التبويب.
 *
 * ★ **ولا شيء منها بلا طلبٍ صريح.** المتصفّح يمنع الصوت قبل لمسةٍ من
 * المستخدم، ويطلب الإذنَ للإشعار — وطلبُه عند فتح الصفحة بلا سببٍ ظاهر
 * يُرفض غالباً ويُحجب بعدها. فالزرّ «نبّهني عند الانتهاء» هو اللمسةُ
 * والسببُ معاً.
 */

export type AlertPermission = 'unsupported' | 'default' | 'granted' | 'denied';

let context: AudioContext | null = null;
let restoreTitle: (() => void) | null = null;

function audioContext(): AudioContext | null {
  if (context !== null) {
    return context;
  }

  const Ctor = window.AudioContext
    ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;

  context = Ctor === undefined ? null : new Ctor();

  return context;
}

/**
 * نغمتان صاعدتان خافتتان، تخفتان في ثانية.
 *
 * **مولَّدةٌ لا ملفّ**: لا طلبَ شبكة ولا أصلَ صوتيّ يُحمَّل ويُخزَّن. وقويّةٌ
 * بقدر ما تُسمع في غرفةٍ هادئة — «بلا احتفال بالإنجاز»، المبدأ الثاني.
 */
export function playChime(): void {
  const audio = audioContext();

  if (audio === null) {
    return;
  }

  void audio.resume();
  const now = audio.currentTime;

  for (const [frequency, offset] of [[587.33, 0], [880, 0.2]] as const) {
    const oscillator = audio.createOscillator();
    const gain = audio.createGain();

    oscillator.type = 'sine';
    oscillator.frequency.value = frequency;
    gain.gain.setValueAtTime(0.0001, now + offset);
    gain.gain.exponentialRampToValueAtTime(0.07, now + offset + 0.03);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + offset + 1.1);

    oscillator.connect(gain).connect(audio.destination);
    oscillator.start(now + offset);
    oscillator.stop(now + offset + 1.2);
  }
}

function currentPermission(): AlertPermission {
  return typeof Notification === 'undefined' ? 'unsupported' : Notification.permission;
}

function remembered(key: string): boolean {
  try {
    return window.sessionStorage.getItem(key) === '1';
  } catch {
    return false;
  }
}

function remember(key: string, on: boolean): void {
  try {
    if (on) {
      window.sessionStorage.setItem(key, '1');
    } else {
      window.sessionStorage.removeItem(key);
    }
  } catch {
    // تخزينٌ محجوب: يبقى الاختيار لهذه الزيارة وحدها.
  }
}

/**
 * علامةٌ في عنوان التبويب حتى يعود إليه.
 *
 * فمن حجب الإشعارات أو كان في نافذةٍ أخرى يرى التبويبَ نفسه يقول «اكتمل».
 */
function markTitle(marker: string): void {
  restoreTitle?.();

  const original = document.title;
  document.title = `${marker} · ${original}`;

  const back = () => {
    if (!document.hidden) {
      restoreTitle?.();
    }
  };

  restoreTitle = () => {
    document.title = original;
    document.removeEventListener('visibilitychange', back);
    restoreTitle = null;
  };

  document.addEventListener('visibilitychange', back);
}

export function useCompletionAlert(id: string) {
  const key = `khulasah.notify.${id}`;
  const [enabled, setEnabled] = useState(() => remembered(key));
  const [permission, setPermission] = useState<AlertPermission>(currentPermission);

  useEffect(() => () => restoreTitle?.(), []);

  const enable = useCallback(async () => {
    // **في اللمسة نفسها**: المتصفّح لا يفتح الصوت من مؤقّتٍ بعدها.
    void audioContext()?.resume();
    setEnabled(true);
    remember(key, true);

    if (currentPermission() === 'default') {
      try {
        setPermission(await Notification.requestPermission());
      } catch {
        setPermission(currentPermission());
      }
    }
  }, [key]);

  const disable = useCallback(() => {
    setEnabled(false);
    remember(key, false);
  }, [key]);

  const fire = useCallback(
    (title: string, body: string, marker: string) => {
      if (!enabled) {
        return;
      }

      playChime();
      remember(key, false);

      if (!document.hidden) {
        return;
      }

      markTitle(marker);

      if (currentPermission() === 'granted') {
        try {
          // اللغةُ والاتّجاه من الوثيقة لا ثابتَين — T-133. والإشعارُ يُرسم
          // خارج الصفحة، فلا يرث `dir` منها كما ترث عناصرُها.
          const notice = new Notification(title, {
            body,
            tag: key,
            lang: document.documentElement.lang || 'ar',
            dir: (document.documentElement.dir as NotificationDirection) || 'rtl',
          });

          notice.onclick = () => {
            window.focus();
            notice.close();
          };
        } catch {
          // متصفّحٌ يمنع الإشعار من الصفحة (بعض الجوّالات): العنوانُ يكفي.
        }
      }
    },
    [enabled, key],
  );

  return { enabled, permission, enable, disable, fire };
}
