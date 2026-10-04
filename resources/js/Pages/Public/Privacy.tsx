import { Head, Link } from '@inertiajs/react';
import { BrandMark, ProductFooter, Wordmark } from '@/Components/Brand';
import { Card } from '@/Components/Card';

interface Section {
  heading: string;
  items: string[];
}

interface Props {
  title: string;
  intro: string;
  sections: Section[];
  contact: string;
  contactLink: string;
  updated: string;
}

/**
 * سياسة الخصوصية المعلنة — T-207.
 *
 * **صفحةٌ قائمة بذاتها بلا هيكل اللوحة**، كنموذج الاعتراض: يقرؤها من لا
 * حساب له. ونصوصها تأتي من الخادم مملوءةً بمدد الإعداد نفسه.
 */
export default function Privacy({ title, intro, sections, contact, contactLink, updated }: Props) {
  return (
    <div className="mx-auto flex min-h-screen max-w-2xl flex-col px-4 py-10">
      <Head title={title} />

      <Link href="/" className="mb-6 flex items-center gap-2 text-primary">
        <BrandMark size={26} />
        <Wordmark className="text-[22px]" />
      </Link>

      <h1 className="mb-2 text-[24px] font-semibold text-text">{title}</h1>
      <p className="mb-6 text-[15px] leading-relaxed text-text-muted">{intro}</p>

      <Card>
        <div className="flex flex-col gap-6">
          {sections.map((section) => (
            <section key={section.heading}>
              <h2 className="mb-2 text-[17px] font-semibold text-text">{section.heading}</h2>
              <ul className="list-disc space-y-1.5 ps-5 text-[15px] leading-relaxed text-text">
                {section.items.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
            </section>
          ))}
        </div>
      </Card>

      <p className="mt-6 text-[15px] leading-relaxed text-text-muted">
        {contact}{' '}
        <Link href="/complaint" className="text-primary underline">
          {contactLink}
        </Link>
      </p>
      <p className="mt-2 text-[13px] text-text-faint">{updated}</p>

      <ProductFooter className="mt-8" />
    </div>
  );
}
