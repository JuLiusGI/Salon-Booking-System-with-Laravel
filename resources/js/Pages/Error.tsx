import { Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import { ButtonLink } from '@/Components/Button';
import { Container } from '@/Components/Section';

/**
 * The in-app error page.
 *
 * Shown when an Inertia request fails, so the person stays inside the
 * application instead of being dropped onto a bare HTML document. The Blade
 * pages in resources/views/errors cover the same statuses for plain requests.
 */
const MESSAGES: Record<number, { title: string; body: string }> = {
    403: {
        title: 'That page is not yours to open',
        body: 'You are signed in, but this part of the salon is restricted to other staff. If that looks wrong, ask an administrator to check your role.',
    },
    404: {
        title: 'We could not find that page',
        body: 'The link may be out of date, or the page may have moved. Nothing has gone wrong with your account.',
    },
    429: {
        title: 'Too many attempts',
        body: 'That was a lot of requests in a short time, so we have paused things for a moment. Please wait a minute and try again.',
    },
    500: {
        title: 'Something went wrong at our end',
        body: 'This is our fault, not yours. The problem has been recorded. Please try again shortly, or call the salon if you were part-way through a booking.',
    },
    503: {
        title: 'The salon is briefly offline',
        body: 'We are carrying out short maintenance. Bookings already made are safe. Please try again in a few minutes.',
    },
};

export default function Error({ status }: { status: number }) {
    const { title, body } = MESSAGES[status] ?? {
        title: 'Something went wrong',
        body: 'Please try again, or return to the home page.',
    };

    return (
        <PublicLayout title={title}>
            <Container className="py-24">
                <div className="mx-auto max-w-xl rounded-2xl border border-line bg-surface p-10 text-center">
                    <p className="text-xs font-semibold tracking-[0.18em] text-secondary uppercase">Error {status}</p>

                    <h1 className="mt-3 text-3xl leading-tight text-ink">{title}</h1>

                    <div aria-hidden="true" className="mx-auto my-6 h-[3px] w-12 rounded-full bg-accent" />

                    <p className="text-base leading-relaxed text-ink-muted">{body}</p>

                    <div className="mt-8 flex flex-wrap justify-center gap-3">
                        <ButtonLink href="/">Back to the salon</ButtonLink>
                        <Link
                            href="/dashboard"
                            className="rounded-full border border-line-strong bg-surface px-5 py-2.5 text-sm text-ink transition-colors hover:bg-canvas-soft"
                        >
                            Go to my dashboard
                        </Link>
                    </div>
                </div>
            </Container>
        </PublicLayout>
    );
}
