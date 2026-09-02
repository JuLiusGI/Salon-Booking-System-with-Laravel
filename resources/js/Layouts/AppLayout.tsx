import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import FlashMessage from '@/Components/FlashMessage';
import { Container } from '@/Components/Section';
import type { SharedProps, UserRole } from '@/types';

interface NavItem {
    label: string;
    href: string;
    roles: UserRole[];
    /**
     * Setup items are the salon's configuration rather than its daily work.
     * They are real destinations, just rarely visited, so they sit behind one
     * disclosure instead of spending seven slots in the main bar.
     */
    group?: 'setup';
}

/**
 * Navigation is filtered by role for usability only. Every route behind these
 * links is independently protected server-side; hiding a link is not a security
 * boundary (MASTER_SPEC section 4).
 */
const NAV_ITEMS: NavItem[] = [
    { label: 'Dashboard', href: '/dashboard', roles: ['admin', 'receptionist', 'stylist', 'customer'] },
    { label: 'Book', href: '/book', roles: ['customer'] },
    { label: 'My appointments', href: '/appointments', roles: ['customer'] },
    { label: 'Calendar', href: '/manage/calendar', roles: ['admin', 'receptionist', 'stylist'] },
    { label: 'Appointments', href: '/manage/appointments', roles: ['admin', 'receptionist', 'stylist'] },
    { label: 'Check in', href: '/manage/check-in', roles: ['admin', 'receptionist', 'stylist'] },
    { label: 'Customers', href: '/manage/customers', roles: ['admin', 'receptionist'] },
    { label: 'Categories', href: '/admin/categories', roles: ['admin'] , group: 'setup' },
    { label: 'Services', href: '/admin/services', roles: ['admin'] , group: 'setup' },
    { label: 'Team', href: '/admin/staff', roles: ['admin'] , group: 'setup' },
    { label: 'Hours', href: '/admin/schedule/hours', roles: ['admin'] , group: 'setup' },
    { label: 'Exceptions', href: '/admin/schedule/exceptions', roles: ['admin'] , group: 'setup' },
    { label: 'Rules', href: '/admin/schedule/rules', roles: ['admin'] , group: 'setup' },
    { label: 'Users', href: '/admin/users', roles: ['admin'] , group: 'setup' },
    { label: 'Reports', href: '/manage/reports', roles: ['admin', 'receptionist'] },
    { label: 'Notifications', href: '/notifications', roles: ['admin', 'receptionist', 'stylist', 'customer'] },
    { label: 'Profile', href: '/profile', roles: ['admin', 'receptionist', 'stylist', 'customer'] },
];

/** One nav pill. Shared so the bar and the Setup panel cannot drift apart. */
function navClasses(current: boolean): string {
    return `rounded-full px-3.5 py-1.5 text-sm transition-colors ${
        current ? 'bg-canvas text-ink' : 'text-ink-muted hover:bg-canvas hover:text-ink'
    }`;
}

/**
 * The Setup group.
 *
 * A disclosure rather than a hover menu: it opens on click, closes on Escape or
 * an outside click, and returns focus to its button, so it works the same with a
 * keyboard as with a mouse.
 */
function SetupMenu({ items, isCurrent }: { items: NavItem[]; isCurrent: (href: string) => boolean }) {
    const [open, setOpen] = useState(false);
    const wrapper = useRef<HTMLDivElement>(null);
    const button = useRef<HTMLButtonElement>(null);

    const holdsCurrentPage = items.some((item) => isCurrent(item.href));

    useEffect(() => {
        if (! open) {
            return;
        }

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
                button.current?.focus();
            }
        };

        const onPointer = (event: MouseEvent) => {
            if (! wrapper.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('keydown', onKey);
        document.addEventListener('mousedown', onPointer);

        return () => {
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('mousedown', onPointer);
        };
    }, [open]);

    if (items.length === 0) {
        return null;
    }

    return (
        <div ref={wrapper} className="relative">
            <button
                ref={button}
                type="button"
                onClick={() => setOpen((was) => ! was)}
                aria-expanded={open}
                aria-controls="setup-menu"
                className={navClasses(holdsCurrentPage)}
            >
                Setup
                <span aria-hidden="true" className="ml-1.5 text-xs">
                    {open ? '▴' : '▾'}
                </span>
            </button>

            {open && (
                <div
                    id="setup-menu"
                    className="absolute left-0 z-50 mt-2 min-w-52 rounded-2xl border border-line bg-surface p-2 shadow-lg"
                >
                    <ul className="flex flex-col gap-0.5">
                        {items.map((item) => (
                            <li key={item.href}>
                                <Link
                                    href={item.href}
                                    onClick={() => setOpen(false)}
                                    aria-current={isCurrent(item.href) ? 'page' : undefined}
                                    className={`block rounded-lg px-3 py-2 text-sm transition-colors ${
                                        isCurrent(item.href)
                                            ? 'bg-canvas text-ink'
                                            : 'text-ink-muted hover:bg-canvas hover:text-ink'
                                    }`}
                                >
                                    {item.label}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}

interface AppLayoutProps {
    title: string;
    children: ReactNode;
}

export default function AppLayout({ title, children }: AppLayoutProps) {
    const { auth } = usePage<SharedProps>().props;
    const { url } = usePage();
    const user = auth.user;

    const visible = NAV_ITEMS.filter((item) => user && item.roles.includes(user.role));
    const primary = visible.filter((item) => item.group !== 'setup');
    const setup = visible.filter((item) => item.group === 'setup');

    const isCurrent = (href: string) => url === href || url.startsWith(href + '/');

    return (
        <>
            <Head title={title} />

            <a href="#main" className="skip-link">
                Skip to content
            </a>

            <div className="min-h-screen">
                <header className="border-b border-line bg-surface">
                    <Container>
                        <div className="flex flex-wrap items-center justify-between gap-4 py-3.5">
                            <div className="flex flex-wrap items-center gap-5">
                                <Link href="/" className="font-display text-lg tracking-tight text-ink">
                                    Salon Booking
                                </Link>

                                <nav aria-label="Account" className="flex flex-wrap items-center gap-1">
                                    {primary.map((item) => (
                                        <Link
                                            key={item.href}
                                            href={item.href}
                                            aria-current={isCurrent(item.href) ? 'page' : undefined}
                                            className={navClasses(isCurrent(item.href))}
                                        >
                                            {item.label}
                                        </Link>
                                    ))}

                                    <SetupMenu items={setup} isCurrent={isCurrent} />
                                </nav>
                            </div>

                            {user && (
                                <div className="flex items-center gap-3">
                                    <span className="hidden text-sm text-ink-muted sm:inline">{user.name}</span>
                                    <button
                                        type="button"
                                        onClick={() => router.post('/logout')}
                                        className="rounded-full border border-line-strong px-3.5 py-1.5 text-sm text-ink transition-colors hover:bg-canvas"
                                    >
                                        Log out
                                    </button>
                                </div>
                            )}
                        </div>
                    </Container>
                </header>

                <main id="main">
                    <Container className="py-10">
                        <h1 className="mb-7 text-3xl text-ink">{title}</h1>
                        <FlashMessage />
                        {children}
                    </Container>
                </main>
            </div>
        </>
    );
}
