import type { ReactNode } from 'react';

interface ContainerProps {
    className?: string;
    children: ReactNode;
}

export function Container({ className = '', children }: ContainerProps) {
    return <div className={`mx-auto w-full max-w-6xl px-5 sm:px-8 ${className}`}>{children}</div>;
}

interface SectionHeadingProps {
    eyebrow?: string;
    title: string;
    description?: string;
    align?: 'left' | 'center';
    id?: string;
}

export function SectionHeading({ eyebrow, title, description, align = 'left', id }: SectionHeadingProps) {
    const alignment = align === 'center' ? 'text-center mx-auto' : 'text-left';

    return (
        <div className={`max-w-2xl ${alignment}`}>
            {eyebrow && (
                <p className="text-xs font-semibold tracking-[0.18em] text-secondary uppercase">{eyebrow}</p>
            )}

            <h2 id={id} className="mt-3 text-3xl leading-tight text-ink sm:text-4xl">
                {title}
            </h2>

            {description && <p className="mt-4 text-base leading-relaxed text-ink-muted">{description}</p>}
        </div>
    );
}

/**
 * A short decorative rule. Uses the accent colour, which is too low-contrast for
 * text but perfectly good for a non-semantic divider.
 */
export function Rule({ className = '' }: { className?: string }) {
    return <span aria-hidden="true" className={`block h-px w-16 bg-accent ${className}`} />;
}
