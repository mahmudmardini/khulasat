import { useEffect, useRef, useState, type RefObject } from 'react';
import { cn } from '@/lib/cn';

export type Device = 'mobile' | 'tablet' | 'desktop';

/**
 * قياسات الأجهزة — T-84. **العرضُ الحقيقيّ لا المصغَّر.**
 *
 * الإطار يُرسم بعرض الجهاز نفسه (٣٩٠ · ٨٢٠ · ١٢٨٠) ثمّ يُصغَّر بـ`scale`
 * ليسع اللوحة. فاستعلامات الوسائط في القالب ترى ٣٩٠ حقّاً وتعمل كما تعمل
 * على جوال الطالب. **وتضييقُ الإطار بدل تصغيره** — وهو ما كان — يُري
 * صفحةً ضيّقة لا صفحةَ جوال.
 */
export const DEVICES: Record<Device, { width: number; height: number; bezel: number; bar: number; radius: number }> = {
  mobile: { width: 390, height: 844, bezel: 12, bar: 0, radius: 46 },
  tablet: { width: 820, height: 1180, bezel: 16, bar: 0, radius: 30 },
  desktop: { width: 1280, height: 800, bezel: 0, bar: 34, radius: 10 },
};

/** ما يُترك حول الجهاز من اللوحة، وما يُترك من ارتفاع الشاشة لرأسها وأفعالها. */
const GUTTER = 48;
const CHROME_HEIGHT = 190;

export function DeviceFrame({
  device, src, srcDoc, title, frameRef,
}: {
  device: Device;
  /** رابطُ الصفحة — المعاينة وشاشة الإنشاء. */
  src?: string;
  /** أو الصفحةُ نفسها — معاينة الهوية ترسم ما لم يُحفظ فتصل HTML لا رابطاً (T-96). */
  srcDoc?: string;
  title: string;
  frameRef: RefObject<HTMLIFrameElement | null>;
}) {
  const box = useRef<HTMLDivElement>(null);
  const [room, setRoom] = useState({ width: 0, height: 0 });
  const [loaded, setLoaded] = useState(false);

  // مصدرٌ جديد (لغةٌ أخرى) يُخفى حتى يكتمل، فلا تومض صفحةٌ نصفُ مرسومة.
  // و`srcDoc` لا يُخفى بتبدّله: يتبدّل بكلّ حرفٍ في الهوية، والوميضُ أسوأ من التبدّل.
  useEffect(() => setLoaded(false), [src]);

  useEffect(() => {
    const node = box.current;

    if (node === null) {
      return undefined;
    }

    const measure = () => setRoom({ width: node.clientWidth, height: window.innerHeight });
    measure();

    const observer = new ResizeObserver(measure);
    observer.observe(node);
    window.addEventListener('resize', measure);

    return () => {
      observer.disconnect();
      window.removeEventListener('resize', measure);
    };
  }, []);

  const spec = DEVICES[device];
  const outerWidth = spec.width + spec.bezel * 2;
  const outerHeight = spec.height + spec.bezel * 2 + spec.bar;

  /*
   * **الجوال واللوح يُريان كاملَين**، فيُصغَّران ليسعا ارتفاع الشاشة أيضاً:
   * الجهاز يُفهم بشكله. والحاسوب يسع العرض وحده، ويُمرَّر داخله كما يُمرَّر
   * على حاسوبٍ حقيقي.
   */
  const byWidth = room.width > 0 ? (room.width - GUTTER) / outerWidth : 1;
  const byHeight = device === 'desktop' || room.height === 0 ? 1 : (room.height - CHROME_HEIGHT) / outerHeight;
  const scale = Math.max(0.2, Math.min(1, byWidth, byHeight));

  const handheld = device !== 'desktop';

  return (
    <div ref={box} className="flex justify-center overflow-hidden bg-surface-alt px-4 py-6">
      <div
        className="relative transition-[width,height] duration-300"
        style={{ width: outerWidth * scale, height: outerHeight * scale }}
      >
        {/*
          `left-0` و`origin-top-left` حرفيّان لا منطقيّان عمداً: الحاوية
          بعرض الجهاز المصغَّر بالضبط، فالموضع لا يتعلّق باتّجاه اللوحة.
        */}
        <div
          className={cn(
            'absolute top-0 left-0 origin-top-left overflow-hidden transition-transform duration-300',
            handheld ? 'bg-text shadow-lifted' : 'border border-border-strong bg-surface shadow-lifted',
          )}
          style={{
            width: outerWidth,
            height: outerHeight,
            padding: spec.bezel,
            borderRadius: spec.radius,
            transform: `scale(${scale})`,
          }}
        >
          {spec.bar > 0 ? <BrowserBar height={spec.bar} /> : null}

          <iframe
            ref={frameRef}
            src={src}
            srcDoc={srcDoc}
            title={title}
            onLoad={() => setLoaded(true)}
            className={cn('block border-0 bg-white transition-opacity duration-300', loaded ? 'opacity-100' : 'opacity-0')}
            style={{
              width: spec.width,
              height: spec.height,
              borderRadius: handheld ? spec.radius - spec.bezel : 0,
            }}
          />
        </div>
      </div>
    </div>
  );
}

/** شريط متصفّحٍ صامت — يقول «هذه صفحة ويب على حاسوب» بلا نصٍّ يُقرأ. */
function BrowserBar({ height }: { height: number }) {
  return (
    <div
      aria-hidden="true"
      dir="ltr"
      style={{ height }}
      className="flex items-center gap-1.5 border-b border-border bg-surface-alt px-3"
    >
      <span className="size-2.5 rounded-full bg-border-strong" />
      <span className="size-2.5 rounded-full bg-border-strong" />
      <span className="size-2.5 rounded-full bg-border-strong" />
      <span className="mx-auto h-5 w-2/5 rounded-md bg-surface" />
    </div>
  );
}
