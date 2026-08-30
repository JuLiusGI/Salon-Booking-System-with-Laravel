import type { InputHTMLAttributes, ReactNode } from 'react';

interface FieldProps extends InputHTMLAttributes<HTMLInputElement> {
    label: string;
    error?: string;
    hint?: ReactNode;
}

/**
 * A labelled input that ties its label, error, and hint together for screen
 * readers. Errors are announced and are never signalled by colour alone: the
 * message itself is always rendered.
 */
export default function Field({ label, error, hint, id, className = '', ...props }: FieldProps) {
    const inputId = id ?? props.name ?? label.toLowerCase().replace(/\s+/g, '-');
    const errorId = `${inputId}-error`;
    const hintId = `${inputId}-hint`;

    return (
        <div className="space-y-1.5">
            <label htmlFor={inputId} className="block text-sm font-medium text-ink">
                {label}
            </label>

            <input
                id={inputId}
                aria-invalid={error ? true : undefined}
                aria-describedby={
                    [error ? errorId : null, hint ? hintId : null].filter(Boolean).join(' ') || undefined
                }
                className={`w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink transition-colors placeholder:text-ink-muted/60 ${
                    error ? 'border-red-600' : 'border-line-strong hover:border-support'
                } ${className}`}
                {...props}
            />

            {hint && (
                <p id={hintId} className="text-xs text-ink-muted">
                    {hint}
                </p>
            )}

            {error && (
                <p id={errorId} role="alert" className="text-xs font-medium text-red-700">
                    {error}
                </p>
            )}
        </div>
    );
}
