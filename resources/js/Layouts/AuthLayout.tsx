import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

interface AuthLayoutProps {
    title: string;
    description?: string;
    children: ReactNode;
}

export default function AuthLayout({ title, description, children }: AuthLayoutProps) {
    return (
        <>
            <Head title={title} />

            <div className="flex min-h-screen flex-col items-center justify-center bg-neutral-50 px-4 py-12">
                <Link href="/" className="mb-8 text-lg font-semibold tracking-tight text-neutral-900">
                    Salon Booking
                </Link>

                <main className="w-full max-w-md rounded-lg border border-neutral-200 bg-white p-8 shadow-sm">
                    <h1 className="text-xl font-semibold text-neutral-900">{title}</h1>
                    {description && <p className="mt-1 text-sm text-neutral-600">{description}</p>}

                    <div className="mt-6">{children}</div>
                </main>
            </div>
        </>
    );
}
