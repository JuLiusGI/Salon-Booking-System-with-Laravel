import { Head, Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import FlashMessage from '@/Components/FlashMessage';
import { Container } from '@/Components/Section';
import type { SharedProps, UserRole } from '@/types';

interface NavItem {
    label: string;
    href: string;
    roles: UserRole[];
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
    { label: 'Categories', href: '/admin/categories', roles: ['admin'] },
    { label: 'Services', href: '/admin/services', roles: ['admin'] },
    { label: 'Team', href: '/admin/staff', roles: ['admin'] },
    { label: 'Hours', href: '/admin/schedule/hours', roles: ['admin'] },
    { label: 'Exceptions', href: '/admin/schedule/exceptions', roles: ['admin'] },
    { label: 'Rules', href: '/admin/schedule/rules', roles: ['admin'] },
    { label: 'Users', href: '/admin/users', roles: ['admin'] },
    { label: 'Reports', href: '/manage/reports', roles: ['admin', 'receptionist'] },
    { label: 'Notifications', href: '/notifications', roles: ['admin', 'receptionist', 'stylist', 'customer'] },
    { label: 'Profile', href: '/profile', roles: ['admin', 'receptionist', 'stylist', 'customer'] },
];

interface AppLayoutProps {
    title: string;
    children: ReactNode;
}

export default function AppLayout({ title, children }: AppLayoutProps) {
    const { auth } = usePage<SharedProps>().props;
    const { url } = usePage();
    const user = auth.user;

    const visible = NAV_ITEMS.filter((item) => user && item.roles.includes(user.role));

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
                                    {visible.map((item) => (
                                        <Link
                                            key={item.href}
                                            href={item.href}
                                            aria-current={url.startsWith(item.href) ? 'page' : undefined}
                                            className={`rounded-full px-3.5 py-1.5 text-sm transition-colors ${
                                                url.startsWith(item.href)
                                                    ? 'bg-canvas text-ink'
                                                    : 'text-ink-muted hover:bg-canvas hover:text-ink'
                                            }`}
                                        >
                                            {item.label}
                                        </Link>
                                    ))}
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
