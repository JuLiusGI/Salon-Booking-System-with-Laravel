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

            <a href="#main" className="skip-link">
                Skip to content
            </a>

            <div className="flex min-h-screen flex-col items-center justify-center px-5 py-14">
                <Link href="/" className="mb-9 font-display text-2xl tracking-tight text-ink">
                    Salon Booking
                </Link>

                <main
                    id="main"
                    className="w-full max-w-md rounded-2xl border border-line bg-surface p-8 sm:p-10"
                >
                    <h1 className="text-2xl text-ink">{title}</h1>
                    {description && <p className="mt-2 text-sm leading-relaxed text-ink-muted">{description}</p>}

                    <div className="mt-7">{children}</div>
                </main>

                <Link
                    href="/"
                    className="mt-8 text-sm text-ink-muted underline underline-offset-4 hover:text-ink"
                >
                    Back to the salon site
                </Link>
            </div>
        </>
    );
}
