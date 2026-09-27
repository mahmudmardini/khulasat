import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

interface Props {
  title?: ReactNode;
  action?: ReactNode;
  footer?: ReactNode;
  flush?: boolean;
  children: ReactNode;
}

/** سطح المحتوى — §الرموز: `--surface` مع `--border` و`--shadow`. */
export function Card({ title, action, footer, flush = false, children }: Props) {
  return (
    <section className="rounded-lg border border-border bg-surface shadow-card">
      {title !== undefined ? (
        <header className="flex items-center justify-between gap-3 border-b border-border px-5 py-3">
          <h2 className="text-[17px] font-semibold text-text">{title}</h2>
          {action}
        </header>
      ) : null}

      <div className={cn(!flush && 'p-5')}>{children}</div>

      {footer !== undefined ? (
        <footer className="border-t border-border bg-surface-alt px-5 py-3">{footer}</footer>
      ) : null}
    </section>
  );
}
