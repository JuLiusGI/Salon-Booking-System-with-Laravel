import { Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import type { PageProps } from '@/types';

interface NotificationData {
    type: string;
    reference: string;
    headline: string;
    date: string;
    time: string;
    staff_name: string;
    services: string[];
    url: string;
}

interface Row {
    id: string;
    read: boolean;
    sent: string;
    data: NotificationData;
}

/**
 * A word as well as a tint, so the kind of notice is never colour alone.
 */
const KIND: Record<string, { label: string; tint: string }> = {
    booked: { label: 'Booked', tint: 'bg-canvas text-ink border-line-strong' },
    confirmed: { label: 'Confirmed', tint: 'bg-support/25 text-ink border-support/40' },
    cancelled: { label: 'Cancelled', tint: 'bg-accent/25 text-ink border-accent/50' },
    reminder: { label: 'Reminder', tint: 'bg-secondary/15 text-ink border-secondary/30' },
};

export default function Index({ notifications, unread }: PageProps<{ notifications: Row[]; unread: number }>) {
    return (
        <AppLayout title="Notifications">
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-ink-muted">
                    {unread > 0 ? `${unread} unread` : 'Everything read'}
                </p>

                {unread > 0 && (
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() => router.post('/notifications/read-all', {}, { preserveScroll: true })}
                    >
                        Mark all as read
                    </Button>
                )}
            </div>

            {notifications.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-line-strong bg-surface p-12 text-center">
                    <h2 className="text-lg text-ink">Nothing yet</h2>
                    <p className="mx-auto mt-2 max-w-sm text-sm text-ink-muted">
                        We will let you know here when an appointment is booked, confirmed, changed, or coming up.
                    </p>
                </div>
            ) : (
                <ul className="space-y-3">
                    {notifications.map((row) => {
                        const kind = KIND[row.data.type] ?? KIND.booked;

                        return (
                            <li
                                key={row.id}
                                className={`rounded-2xl border p-5 ${
                                    row.read ? 'border-line bg-surface' : 'border-line-strong bg-canvas-soft'
                                }`}
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <span
                                        className={`inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium ${kind.tint}`}
                                    >
                                        {kind.label}
                                    </span>
                                    <span className="text-xs text-ink-muted">{row.sent}</span>
                                </div>

                                <p className="mt-3 text-ink">{row.data.headline}</p>

                                <p className="mt-1 text-sm text-ink-muted">
                                    {row.data.date} at {row.data.time} &middot; with {row.data.staff_name}
                                </p>

                                {row.data.services?.length > 0 && (
                                    <p className="mt-0.5 text-sm text-ink-muted">{row.data.services.join(', ')}</p>
                                )}

                                <div className="mt-4 flex flex-wrap items-center gap-4 border-t border-line pt-3">
                                    <Link
                                        href={`/appointments/${row.data.reference}`}
                                        className="text-sm font-medium text-secondary underline underline-offset-4"
                                    >
                                        View appointment
                                    </Link>

                                    {!row.read && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                router.post(
                                                    `/notifications/${row.id}/read`,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                            className="text-sm text-ink-muted underline underline-offset-4"
                                        >
                                            Mark as read
                                        </button>
                                    )}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </AppLayout>
    );
}
