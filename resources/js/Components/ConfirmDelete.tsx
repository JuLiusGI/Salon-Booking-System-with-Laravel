import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import Button from '@/Components/Button';

interface ConfirmDeleteProps {
    url: string;
    /** What is being deleted, e.g. the service name. */
    subject: string;
    /** What actually happens, so the consequence is stated before confirming. */
    consequence: string;
    triggerLabel?: string;
    confirmLabel?: string;
}

/**
 * A confirmation step in front of every destructive action (MASTER_SPEC
 * section 13). Implemented as a native <dialog> so Escape, focus trapping, and
 * the backdrop come from the platform rather than being reimplemented.
 */
export default function ConfirmDelete({
    url,
    subject,
    consequence,
    triggerLabel = 'Delete',
    confirmLabel = 'Delete',
}: ConfirmDeleteProps) {
    const dialogRef = useRef<HTMLDialogElement>(null);
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        const dialog = dialogRef.current;

        if (!dialog) {
            return;
        }

        if (open && !dialog.open) {
            dialog.showModal();
        } else if (!open && dialog.open) {
            dialog.close();
        }
    }, [open]);

    const confirm = () => {
        setProcessing(true);
        router.delete(url, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setOpen(false);
            },
        });
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="text-sm font-medium text-red-700 underline underline-offset-4 hover:text-red-800"
            >
                {triggerLabel}
            </button>

            <dialog
                ref={dialogRef}
                onClose={() => setOpen(false)}
                aria-labelledby="confirm-title"
                className="max-w-md rounded-2xl border border-line bg-surface p-0 backdrop:bg-primary/40"
            >
                <div className="p-7">
                    <h2 id="confirm-title" className="text-xl text-ink">
                        Delete {subject}?
                    </h2>

                    <p className="mt-3 text-sm leading-relaxed text-ink-muted">{consequence}</p>

                    <div className="mt-7 flex justify-end gap-3">
                        <Button type="button" variant="secondary" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="button" variant="danger" onClick={confirm} disabled={processing}>
                            {processing ? 'Deleting...' : confirmLabel}
                        </Button>
                    </div>
                </div>
            </dialog>
        </>
    );
}
