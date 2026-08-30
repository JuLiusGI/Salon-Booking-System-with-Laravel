import { useId, useState } from 'react';
import type { ChangeEvent, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';

interface WrapperProps {
    label: string;
    error?: string;
    hint?: ReactNode;
    htmlFor: string;
    children: ReactNode;
}

function Wrapper({ label, error, hint, htmlFor, children }: WrapperProps) {
    return (
        <div className="space-y-1.5">
            <label htmlFor={htmlFor} className="block text-sm font-medium text-ink">
                {label}
            </label>
            {children}
            {hint && (
                <p id={`${htmlFor}-hint`} className="text-xs text-ink-muted">
                    {hint}
                </p>
            )}
            {error && (
                <p id={`${htmlFor}-error`} role="alert" className="text-xs font-medium text-red-700">
                    {error}
                </p>
            )}
        </div>
    );
}

const CONTROL =
    'w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink transition-colors placeholder:text-ink-muted/60';

/* -------------------------------------------------------------------------- */

interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
    label: string;
    error?: string;
    hint?: ReactNode;
    options: { value: string | number; label: string }[];
    placeholder?: string;
}

export function Select({ label, error, hint, options, placeholder, id, ...props }: SelectProps) {
    const fieldId = id ?? props.name ?? label.toLowerCase().replace(/\s+/g, '-');

    return (
        <Wrapper label={label} error={error} hint={hint} htmlFor={fieldId}>
            <select
                id={fieldId}
                aria-invalid={error ? true : undefined}
                aria-describedby={
                    [error ? `${fieldId}-error` : null, hint ? `${fieldId}-hint` : null].filter(Boolean).join(' ') ||
                    undefined
                }
                className={`${CONTROL} ${error ? 'border-red-600' : 'border-line-strong'}`}
                {...props}
            >
                {placeholder && <option value="">{placeholder}</option>}
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </Wrapper>
    );
}

/* -------------------------------------------------------------------------- */

interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
    label: string;
    error?: string;
    hint?: ReactNode;
}

export function Textarea({ label, error, hint, id, rows = 4, ...props }: TextareaProps) {
    const fieldId = id ?? props.name ?? label.toLowerCase().replace(/\s+/g, '-');

    return (
        <Wrapper label={label} error={error} hint={hint} htmlFor={fieldId}>
            <textarea
                id={fieldId}
                rows={rows}
                aria-invalid={error ? true : undefined}
                aria-describedby={
                    [error ? `${fieldId}-error` : null, hint ? `${fieldId}-hint` : null].filter(Boolean).join(' ') ||
                    undefined
                }
                className={`${CONTROL} ${error ? 'border-red-600' : 'border-line-strong'}`}
                {...props}
            />
        </Wrapper>
    );
}

/* -------------------------------------------------------------------------- */

interface CheckboxProps {
    label: string;
    description?: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    error?: string;
    disabled?: boolean;
    name?: string;
}

export function Checkbox({ label, description, checked, onChange, error, disabled, name }: CheckboxProps) {
    const generated = useId();
    const fieldId = name ?? generated;

    return (
        <div>
            <div className="flex items-start gap-3">
                <input
                    id={fieldId}
                    name={name}
                    type="checkbox"
                    checked={checked}
                    disabled={disabled}
                    onChange={(e) => onChange(e.target.checked)}
                    aria-describedby={description ? `${fieldId}-description` : undefined}
                    className="mt-0.5 h-4 w-4 rounded border-line-strong accent-primary"
                />
                <div>
                    <label htmlFor={fieldId} className="text-sm font-medium text-ink">
                        {label}
                    </label>
                    {description && (
                        <p id={`${fieldId}-description`} className="text-xs text-ink-muted">
                            {description}
                        </p>
                    )}
                </div>
            </div>
            {error && (
                <p role="alert" className="mt-1 text-xs font-medium text-red-700">
                    {error}
                </p>
            )}
        </div>
    );
}

/* -------------------------------------------------------------------------- */

interface CheckboxGroupProps {
    legend: string;
    hint?: string;
    options: { value: number; label: string }[];
    selected: number[];
    onChange: (values: number[]) => void;
    error?: string;
    emptyMessage?: string;
}

export function CheckboxGroup({
    legend,
    hint,
    options,
    selected,
    onChange,
    error,
    emptyMessage,
}: CheckboxGroupProps) {
    const toggle = (value: number) => {
        onChange(selected.includes(value) ? selected.filter((v) => v !== value) : [...selected, value]);
    };

    return (
        <fieldset>
            <legend className="text-sm font-medium text-ink">{legend}</legend>
            {hint && <p className="mt-1 text-xs text-ink-muted">{hint}</p>}

            {options.length === 0 ? (
                <p className="mt-3 rounded-lg border border-dashed border-line-strong px-4 py-6 text-center text-sm text-ink-muted">
                    {emptyMessage ?? 'Nothing to choose from yet.'}
                </p>
            ) : (
                <div className="mt-3 grid max-h-64 gap-2 overflow-y-auto rounded-lg border border-line bg-canvas-soft p-3 sm:grid-cols-2">
                    {options.map((option) => (
                        <label key={option.value} className="flex items-center gap-2.5 text-sm text-ink">
                            <input
                                type="checkbox"
                                checked={selected.includes(option.value)}
                                onChange={() => toggle(option.value)}
                                className="h-4 w-4 rounded border-line-strong accent-primary"
                            />
                            {option.label}
                        </label>
                    ))}
                </div>
            )}

            {error && (
                <p role="alert" className="mt-1.5 text-xs font-medium text-red-700">
                    {error}
                </p>
            )}
        </fieldset>
    );
}

/* -------------------------------------------------------------------------- */

interface ImageUploadProps {
    label: string;
    currentUrl: string | null;
    onSelect: (file: File | null) => void;
    onRemoveExisting: () => void;
    removed: boolean;
    error?: string;
    hint?: string;
}

/**
 * Shows the stored image, a preview of a newly chosen one, and a way to clear an
 * existing image. A broken or deleted file falls back to a caption rather than a
 * broken-image icon (MASTER_SPEC section 19).
 */
export function ImageUpload({
    label,
    currentUrl,
    onSelect,
    onRemoveExisting,
    removed,
    error,
    hint,
}: ImageUploadProps) {
    const fieldId = useId();
    const [preview, setPreview] = useState<string | null>(null);
    const [broken, setBroken] = useState(false);

    const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;
        onSelect(file);
        setPreview(file ? URL.createObjectURL(file) : null);
    };

    const shown = preview ?? (removed ? null : currentUrl);

    return (
        <div className="space-y-2">
            <label htmlFor={fieldId} className="block text-sm font-medium text-ink">
                {label}
            </label>

            <div className="flex items-start gap-5">
                <div className="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-line bg-canvas-soft">
                    {shown && !broken ? (
                        <img src={shown} alt="" className="h-full w-full object-cover" onError={() => setBroken(true)} />
                    ) : (
                        <span className="px-2 text-center text-[11px] text-ink-muted">
                            {broken ? 'Image missing' : 'No image'}
                        </span>
                    )}
                </div>

                <div className="space-y-2">
                    <input
                        id={fieldId}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        onChange={handleChange}
                        aria-invalid={error ? true : undefined}
                        className="block w-full text-sm text-ink-muted file:mr-3 file:rounded-full file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:text-ink-inverted hover:file:bg-primary-hover"
                    />

                    <p className="text-xs text-ink-muted">{hint ?? 'JPEG, PNG, or WebP. Up to 4 MB.'}</p>

                    {currentUrl && !preview && !removed && (
                        <button
                            type="button"
                            onClick={onRemoveExisting}
                            className="text-xs font-medium text-red-700 underline underline-offset-4"
                        >
                            Remove current image
                        </button>
                    )}

                    {removed && !preview && <p className="text-xs text-ink-muted">Image will be removed on save.</p>}
                </div>
            </div>

            {error && (
                <p role="alert" className="text-xs font-medium text-red-700">
                    {error}
                </p>
            )}
        </div>
    );
}

/* -------------------------------------------------------------------------- */

interface StatusBadgeProps {
    active: boolean;
    activeLabel?: string;
    inactiveLabel?: string;
}

/**
 * Carries a word as well as a tint, so the state is never conveyed by colour
 * alone (MASTER_SPEC section 13).
 */
export function StatusBadge({ active, activeLabel = 'Active', inactiveLabel = 'Hidden' }: StatusBadgeProps) {
    return (
        <span
            className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${
                active ? 'bg-support/25 text-ink' : 'bg-canvas text-ink-muted'
            }`}
        >
            {active ? activeLabel : inactiveLabel}
        </span>
    );
}
