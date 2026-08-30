import { Head, Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import FlashMessage from '@/Components/FlashMessage';
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
    { label: 'Users', href: '/admin/users', roles: ['admin'] },
    { label: 'Profile', href: '/profile', roles: ['admin', 'receptionist', 'stylist', 'customer'] },
];

interface AppLayoutProps {
    title: string;
    children: ReactNode;
}

export default function AppLayout({ title, children }: AppLayoutProps) {
    const { auth } = usePage<SharedProps>().props;
    const user = auth.user;

    const visible = NAV_ITEMS.filter((item) => user && item.roles.includes(user.role));

    const logout = () => router.post('/logout');

    return (
        <>
            <Head title={title} />

            <div className="min-h-screen bg-neutral-50">
                <header className="border-b border-neutral-200 bg-white">
                    <div className="mx-auto flex max-w-5xl items-center justify-between gap-6 px-4 py-3">
                        <div className="flex items-center gap-6">
                            <Link href="/" className="font-semibold tracking-tight text-neutral-900">
                                Salon Booking
                            </Link>

                            <nav aria-label="Main" className="flex items-center gap-1">
                                {visible.map((item) => (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        className="rounded-md px-3 py-1.5 text-sm text-neutral-600 transition hover:bg-neutral-100 hover:text-neutral-900"
                                    >
                                        {item.label}
                                    </Link>
                                ))}
                            </nav>
                        </div>

                        {user && (
                            <div className="flex items-center gap-3">
                                <span className="hidden text-sm text-neutral-600 sm:inline">{user.name}</span>
                                <button
                                    type="button"
                                    onClick={logout}
                                    className="rounded-md border border-neutral-300 px-3 py-1.5 text-sm text-neutral-700 transition hover:bg-neutral-50"
                                >
                                    Log out
                                </button>
                            </div>
                        )}
                    </div>
                </header>

                <main className="mx-auto max-w-5xl px-4 py-8">
                    <h1 className="mb-6 text-2xl font-semibold text-neutral-900">{title}</h1>
                    <FlashMessage />
                    {children}
                </main>
            </div>
        </>
    );
}
