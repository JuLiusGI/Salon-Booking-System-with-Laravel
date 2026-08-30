import type { InputHTMLAttributes, ReactNode } from 'react';

interface FieldProps extends InputHTMLAttributes<HTMLInputElement> {
    label: string;
    error?: string;
    hint?: ReactNode;
}

/**
 * A labelled input that ties its label, error, and hint together for screen
 * readers. Server-side validation errors are surfaced here, never hidden.
 */
export default function Field({ label, error, hint, id, className = '', ...props }: FieldProps) {
    const inputId = id ?? props.name ?? label.toLowerCase().replace(/\s+/g, '-');
    const errorId = `${inputId}-error`;
    const hintId = `${inputId}-hint`;

    return (
        <div className="space-y-1.5">
            <label htmlFor={inputId} className="block text-sm font-medium text-neutral-800">
                {label}
            </label>

            <input
                id={inputId}
                aria-invalid={error ? true : undefined}
                aria-describedby={[error ? errorId : null, hint ? hintId : null].filter(Boolean).join(' ') || undefined}
                className={`w-full rounded-md border px-3 py-2 text-sm shadow-sm outline-none transition focus:ring-2 focus:ring-offset-1 ${
                    error
                        ? 'border-red-400 focus:ring-red-300'
                        : 'border-neutral-300 focus:border-neutral-400 focus:ring-neutral-300'
                } ${className}`}
                {...props}
            />

            {hint && (
                <p id={hintId} className="text-xs text-neutral-500">
                    {hint}
                </p>
            )}

            {error && (
                <p id={errorId} role="alert" className="text-xs font-medium text-red-600">
                    {error}
                </p>
            )}
        </div>
    );
}
