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
            className={`mb-6 rounded-md border px-4 py-3 text-sm ${
                isError ? 'border-red-200 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-green-800'
            }`}
        >
            {flash.error ?? flash.success}
        </div>
    );
}
