import { Head } from '@inertiajs/react';
import type { PageProps } from '@/types';

/**
 * Foundation smoke-test page for Phase 0.
 *
 * This exists to prove the Laravel -> Inertia -> React -> TypeScript -> Tailwind
 * pipeline resolves and renders. The real public site is built in Phase 3.
 */
export default function Welcome({ name, laravelVersion, phpVersion }: PageProps<{
    laravelVersion: string;
    phpVersion: string;
}>) {
    return (
        <>
            <Head title="Welcome" />

            <main className="flex min-h-screen items-center justify-center bg-neutral-50 p-6">
                <div className="w-full max-w-md rounded-lg border border-neutral-200 bg-white p-8 shadow-sm">
                    <h1 className="text-2xl font-semibold text-neutral-900">{name}</h1>
                    <p className="mt-2 text-sm text-neutral-600">
                        Foundation is running. Application modules are not built yet.
                    </p>

                    <dl className="mt-6 space-y-2 text-sm">
                        <div className="flex justify-between border-t border-neutral-100 pt-2">
                            <dt className="text-neutral-500">Laravel</dt>
                            <dd className="font-medium text-neutral-900">{laravelVersion}</dd>
                        </div>
                        <div className="flex justify-between border-t border-neutral-100 pt-2">
                            <dt className="text-neutral-500">PHP</dt>
                            <dd className="font-medium text-neutral-900">{phpVersion}</dd>
                        </div>
                        <div className="flex justify-between border-t border-neutral-100 pt-2">
                            <dt className="text-neutral-500">Inertia + React</dt>
                            <dd className="font-medium text-neutral-900">connected</dd>
                        </div>
                    </dl>
                </div>
            </main>
        </>
    );
}
