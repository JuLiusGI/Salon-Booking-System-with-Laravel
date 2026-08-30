import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

export default function FlashMessage() {
    const { flash } = usePage<SharedProps>().props;

    if (!flash.success && !flash.error) {
        return null;
    }

    const isError = Boolean(flash.error);

    return (
        <div
            role="status"
            aria-live="polite"
            className={`mb-6 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm ${
                isError ? 'border-red-300 bg-red-50 text-red-900' : 'border-support/50 bg-canvas-soft text-ink'
            }`}
        >
            {/* A word, not just a colour, so the meaning survives for anyone who
                cannot distinguish the two backgrounds. */}
            <strong className="font-semibold">{isError ? 'Error:' : 'Done:'}</strong>
            <span>{flash.error ?? flash.success}</span>
        </div>
    );
}
