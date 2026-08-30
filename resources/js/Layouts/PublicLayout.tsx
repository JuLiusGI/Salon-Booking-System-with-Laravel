import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Container } from '@/Components/Section';
import { ButtonLink } from '@/Components/Button';
import type { SharedProps } from '@/types';

const NAV = [
    { label: 'Services', href: '/services' },
    { label: 'Team', href: '/team' },
    { label: 'Gallery', href: '/gallery' },
    { label: 'About', href: '/about' },
    { label: 'Contact', href: '/contact' },
];

interface PublicLayoutProps {
    title: string;
    description?: string;
    children: ReactNode;
}

export default function PublicLayout({ title, description, children }: PublicLayoutProps) {
    const { auth } = usePage<SharedProps>().props;
    const { url } = usePage();
    const [menuOpen, setMenuOpen] = useState(false);

    const isCurrent = (href: string) => url === href || url.startsWith(href + '/');

    return (
        <>
            <Head>
                <title>{title}</title>
                {description && <meta name="description" content={description} />}
            </Head>

            <a href="#main" className="skip-link">
                Skip to content
            </a>

            <header className="sticky top-0 z-40 border-b border-line bg-canvas/95 backdrop-blur">
                <Container>
                    <div className="flex items-center justify-between gap-4 py-4">
                        <Link href="/" className="font-display text-xl tracking-tight text-ink">
                            Salon Booking
                        </Link>

                        <nav aria-label="Main" className="hidden items-center gap-1 lg:flex">
                            {NAV.map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    aria-current={isCurrent(item.href) ? 'page' : undefined}
                                    className={`rounded-full px-3.5 py-2 text-sm transition-colors ${
                                        isCurrent(item.href)
                                            ? 'bg-surface text-ink'
                                            : 'text-ink-muted hover:bg-surface hover:text-ink'
                                    }`}
                                >
                                    {item.label}
                                </Link>
                            ))}
                        </nav>

                        <div className="hidden items-center gap-3 lg:flex">
                            {auth.user ? (
                                <ButtonLink href="/dashboard" variant="secondary">
                                    My account
                                </ButtonLink>
                            ) : (
                                <Link
                                    href="/login"
                                    className="rounded-full px-3.5 py-2 text-sm text-ink-muted transition-colors hover:text-ink"
                                >
                                    Log in
                                </Link>
                            )}

                            <ButtonLink href="/book">Book appointment</ButtonLink>
                        </div>

                        <button
                            type="button"
                            onClick={() => setMenuOpen((open) => !open)}
                            aria-expanded={menuOpen}
                            aria-controls="mobile-menu"
                            className="rounded-full border border-line-strong px-4 py-2 text-sm text-ink lg:hidden"
                        >
                            {menuOpen ? 'Close' : 'Menu'}
                        </button>
                    </div>

                    {menuOpen && (
                        <div id="mobile-menu" className="border-t border-line py-4 lg:hidden">
                            <nav aria-label="Mobile" className="flex flex-col gap-1">
                                {NAV.map((item) => (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        aria-current={isCurrent(item.href) ? 'page' : undefined}
                                        onClick={() => setMenuOpen(false)}
                                        className="rounded-lg px-3 py-2.5 text-base text-ink-muted hover:bg-surface hover:text-ink"
                                    >
                                        {item.label}
                                    </Link>
                                ))}
                            </nav>

                            <div className="mt-4 flex flex-col gap-3">
                                <ButtonLink href="/book" size="lg">
                                    Book appointment
                                </ButtonLink>
                                <ButtonLink href={auth.user ? '/dashboard' : '/login'} variant="secondary" size="lg">
                                    {auth.user ? 'My account' : 'Log in'}
                                </ButtonLink>
                            </div>
                        </div>
                    )}
                </Container>
            </header>

            <main id="main">{children}</main>

            <footer className="mt-24 bg-primary text-ink-inverted">
                <Container className="py-14">
                    <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <p className="font-display text-xl">Salon Booking</p>
                            <p className="mt-3 max-w-xs text-sm leading-relaxed text-ink-inverted/70">
                                Considered hair, nail, and skin care, booked in a few taps.
                            </p>
                        </div>

                        <nav aria-label="Footer">
                            <h2 className="text-sm font-semibold tracking-wide uppercase">Explore</h2>
                            <ul className="mt-4 space-y-2.5 text-sm">
                                {NAV.map((item) => (
                                    <li key={item.href}>
                                        <Link href={item.href} className="text-ink-inverted/75 hover:text-ink-inverted">
                                            {item.label}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </nav>

                        <div>
                            <h2 className="text-sm font-semibold tracking-wide uppercase">Account</h2>
                            <ul className="mt-4 space-y-2.5 text-sm">
                                <li>
                                    <Link href="/book" className="text-ink-inverted/75 hover:text-ink-inverted">
                                        Book appointment
                                    </Link>
                                </li>
                                {auth.user ? (
                                    <li>
                                        <Link href="/dashboard" className="text-ink-inverted/75 hover:text-ink-inverted">
                                            My account
                                        </Link>
                                    </li>
                                ) : (
                                    <>
                                        <li>
                                            <Link href="/login" className="text-ink-inverted/75 hover:text-ink-inverted">
                                                Log in
                                            </Link>
                                        </li>
                                        <li>
                                            <Link
                                                href="/register"
                                                className="text-ink-inverted/75 hover:text-ink-inverted"
                                            >
                                                Create an account
                                            </Link>
                                        </li>
                                    </>
                                )}
                            </ul>
                        </div>

                        <div>
                            <h2 className="text-sm font-semibold tracking-wide uppercase">Visit</h2>
                            <address className="mt-4 space-y-2.5 text-sm text-ink-inverted/75 not-italic">
                                <p>12 Camia Street, Quezon City</p>
                                <p>
                                    <a href="tel:+6320000000" className="hover:text-ink-inverted">
                                        (02) 8000 0000
                                    </a>
                                </p>
                                <p>
                                    <a href="mailto:hello@salon.test" className="hover:text-ink-inverted">
                                        hello@salon.test
                                    </a>
                                </p>
                            </address>
                        </div>
                    </div>

                    <p className="mt-12 border-t border-ink-inverted/15 pt-6 text-xs text-ink-inverted/60">
                        &copy; {new Date().getFullYear()} Salon Booking. All rights reserved.
                    </p>
                </Container>
            </footer>
        </>
    );
}
