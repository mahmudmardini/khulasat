import type { SVGProps } from 'react';

/**
 * أيقونات الواجهة — T-27.
 *
 * **مرسومةٌ هنا لا مستوردةٌ من حزمة**: المطلوب أقلّ من عشرين رمزاً، وحزمةُ
 * أيقوناتٍ كاملة تزيد الحُزمة بما لا يُستعمل. وكلّها على شبكة 24 بخطٍّ
 * موحَّد العرض، فلا تختلف الأوزان في السطر الواحد.
 *
 * ولا نصّ فيها: الأيقونة `aria-hidden` دائماً، والمعنى يحمله النصّ
 * المجاور — §إتاحة: «لا يحمل اللون ولا الشكل معنى وحده».
 */

export type IconName =
  | 'index'
  | 'create'
  | 'brand'
  | 'menu'
  | 'close'
  | 'search'
  | 'logout'
  | 'link'
  | 'upload'
  | 'text'
  | 'check'
  | 'alert'
  | 'clock'
  | 'chevron'
  | 'external'
  | 'page'
  | 'carousel'
  | 'images'
  | 'sparkle'
  // الكاروسيل — T-19.
  | 'copy'
  // لوحة المشرف — T-21.
  | 'grid'
  | 'building'
  | 'cpu'
  | 'shield'
  | 'eye'
  | 'money'
  // المتابعة الحيّة والمعاينة والفهرس — T-83 إلى T-86.
  | 'bell'
  | 'pause'
  | 'play'
  | 'phone'
  | 'tablet'
  | 'desktop'
  | 'share'
  | 'printer'
  | 'globe'
  | 'sort'
  | 'list'
  | 'expand'
  // معالجُ الإعداد: أيقونةٌ لكلّ مرحلة — T-92.
  | 'mic'
  | 'layers'
  | 'quote'
  | 'shieldCheck'
  | 'pen'
  // إظهار كلمة المرور — T-154.
  | 'eyeOff';

const PATHS: Record<IconName, string> = {
  index: 'M4 6h16M4 12h16M4 18h10',
  create: 'M12 5v14M5 12h14',
  brand: 'M12 3l2.4 5.3 5.6.7-4.2 3.9 1.1 5.6L12 15.8 7.1 18.5l1.1-5.6L4 9l5.6-.7z',
  menu: 'M4 7h16M4 12h16M4 17h16',
  close: 'M6 6l12 12M18 6L6 18',
  search: 'M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14zM20 20l-4-4',
  logout: 'M14 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 8l-4 4 4 4M6 12h10',
  link: 'M10 13a4 4 0 0 0 5.7 0l2.6-2.6a4 4 0 0 0-5.7-5.7L11 6.3M14 11a4 4 0 0 0-5.7 0l-2.6 2.6a4 4 0 0 0 5.7 5.7L13 17.7',
  upload: 'M12 16V4M8 8l4-4 4 4M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3',
  text: 'M5 5h14M5 10h14M5 15h9M5 20h6',
  check: 'M4.5 12.5l5 5L20 7',
  alert: 'M12 8v5M12 16.5v.5M12 3.5L2.5 20h19z',
  clock: 'M12 21a9 9 0 1 1 0-18 9 9 0 0 1 0 18zM12 7v5.2l3.2 2',
  chevron: 'M9 5l7 7-7 7',
  external: 'M14 4h6v6M20 4l-8.5 8.5M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5',
  page: 'M6 3h8l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 0-1zM14 3v4h4M9 13h6M9 17h4',
  carousel: 'M8 5h8a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM4 8v8M20 8v8',
  copy: 'M9 9V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1h-4M5 9h9a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1z',
  images: 'M4 6h13a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1zM3 15l4-3.5 4 3.5 3-2.5 4 3.5M21 8v11a1 1 0 0 1-1 1H7',
  grid: 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z',
  building: 'M4 21h16M6 21V5a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v16M14 10h3a1 1 0 0 1 1 1v10M9 8h2M9 12h2M9 16h2',
  cpu: 'M8 8h8v8H8zM5 9h3M5 15h3M16 9h3M16 15h3M9 5v3M15 5v3M9 16v3M15 16v3',
  shield: 'M12 3l7 3v5.5c0 4.2-2.9 7.9-7 9-4.1-1.1-7-4.8-7-9V6z',
  eye: 'M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12zM12 15a3 3 0 1 1 0-6 3 3 0 0 1 0 6z',
  money: 'M3 7h18a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1zM12 14.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z',
  sparkle: 'M12 4l1.6 4.4L18 10l-4.4 1.6L12 16l-1.6-4.4L6 10l4.4-1.6zM18 15l.8 2.2L21 18l-2.2.8L18 21l-.8-2.2L15 18l2.2-.8z',
  bell: 'M6 16v-5a6 6 0 1 1 12 0v5l1.5 2h-15zM10 20.5a2 2 0 0 0 4 0',
  pause: 'M9 6v12M15 6v12',
  play: 'M8 5.5v13l10-6.5z',
  phone: 'M8 3h8a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zM11 18h2',
  tablet: 'M5.5 3h13a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1h-13a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zM11 18h2',
  desktop: 'M4 4h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1zM9 20h6M12 16v4',
  share: 'M8.5 12a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0zM20.5 6a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0zM20.5 18a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0zM8.2 10.9l7.6-3.8M8.2 13.1l7.6 3.8',
  printer: 'M7 9V4h10v5M7 17H5a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-2M7 14h10v6H7z',
  globe: 'M12 21a9 9 0 1 1 0-18 9 9 0 0 1 0 18zM3.5 9h17M3.5 15h17M12 3c2.5 2.6 3.7 5.6 3.7 9s-1.2 6.4-3.7 9c-2.5-2.6-3.7-5.6-3.7-9S9.5 5.6 12 3z',
  sort: 'M7 4v16M4 7l3-3 3 3M17 20V4M14 17l3 3 3-3',
  list: 'M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01',
  expand: 'M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5',
  mic: 'M12 3a3 3 0 0 1 3 3v5a3 3 0 0 1-6 0V6a3 3 0 0 1 3-3zM5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21',
  layers: 'M12 3l9 5-9 5-9-5zM3 13l9 5 9-5',
  quote: 'M6 7h4v4c0 3-1.5 5-4 6M14 7h4v4c0 3-1.5 5-4 6',
  shieldCheck: 'M12 3l7 3v5.5c0 4.2-2.9 7.9-7 9-4.1-1.1-7-4.8-7-9V6zM9 12l2.2 2.2L15.5 10',
  pen: 'M4 20l4-1 10.5-10.5a2.1 2.1 0 0 0-3-3L5 16zM13.5 6.5l3 3',
  eyeOff: 'M3.5 3.5l17 17M10.6 5.6A9 9 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a16 16 0 0 1-3.1 3.8M6.5 7.7C3.9 9.4 2.5 12 2.5 12S6 18.5 12 18.5c1.5 0 2.8-.4 4-.9M9.9 9.9a3 3 0 0 0 4.2 4.2',
};

interface Props extends Omit<SVGProps<SVGSVGElement>, 'name'> {
  name: IconName;
  /** الحجم بالبكسل — الافتراض 20، وهو مقاس السطر في الواجهة. */
  size?: number;
}

export function Icon({ name, size = 20, className, ...rest }: Props) {
  return (
    <svg
      {...rest}
      className={className}
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.7}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
    >
      <path d={PATHS[name]} />
    </svg>
  );
}
