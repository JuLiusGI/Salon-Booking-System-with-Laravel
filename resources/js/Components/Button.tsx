import { Link } from '@inertiajs/react';
import type { ButtonHTMLAttributes, ReactNode } from 'react';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
type Size = 'md' | 'lg';

const BASE =
    'inline-flex items-center justify-center gap-2 rounded-full font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-60';

const VARIANTS: Record<Variant, string> = {
    primary: 'bg-primary text-ink-inverted hover:bg-primary-hover',
    secondary: 'bg-surface text-ink border border-line-strong hover:bg-canvas-soft',
    ghost: 'text-secondary hover:text-secondary-hover underline underline-offset-4',
    danger: 'bg-red-700 text-white hover:bg-red-800',
};

const SIZES: Record<Size, string> = {
    md: 'px-5 py-2.5 text-sm',
    lg: 'px-7 py-3.5 text-base',
};

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    size?: Size;
}

export default function Button({ variant = 'primary', size = 'md', className = '', ...props }: ButtonProps) {
    return <button className={`${BASE} ${VARIANTS[variant]} ${SIZES[size]} ${className}`} {...props} />;
}

interface ButtonLinkProps {
    href: string;
    variant?: Variant;
    size?: Size;
    className?: string;
    children: ReactNode;
}

/**
 * The same visual treatment for navigation. Rendered as a real anchor so it
 * keeps link semantics: focusable, activatable with Enter, openable in a new tab.
 */
export function ButtonLink({ href, variant = 'primary', size = 'md', className = '', children }: ButtonLinkProps) {
    return (
        <Link href={href} className={`${BASE} ${VARIANTS[variant]} ${SIZES[size]} ${className}`}>
            {children}
        </Link>
    );
}
