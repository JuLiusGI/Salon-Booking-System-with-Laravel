import { Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ButtonLink } from '@/Components/Button';
import { StatTile } from '@/Components/Charts';
import StatusPill from '@/Components/StatusPill';
import type { AppointmentStatus, PageProps } from '@/types';

interface UpcomingRow {
    reference: string;
    date: string;
    time: string;
    staff_name: string;
    services: string[];
    status: AppointmentStatus;
    status_label: string;
}

export default function Customer({
    auth,
    upcoming,
    visits,
}: PageProps<{ upcoming: UpcomingRow[]; visits: number; timezone: string }>) {
    return (
        <AppLayout title={`Welcome, ${auth.user?.name ?? ''}`}>
            <div className="grid gap-4 sm:grid-cols-2">
                <StatTile label="Upcoming appointments" value={upcoming.length} />
                <StatTile label="Visits with us" value={visits} />
            </div>

            <section aria-labelledby="next" className="mt-8">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <h2 id="next" className="text-lg text-ink">
                        What is coming up
                    </h2>
                    <ButtonLink href="/book">Book an appointment</ButtonLink>
                </div>

                {upcoming.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-line-strong bg-surface p-10 text-center">
                        <h3 className="text-lg text-ink">Nothing booked yet</h3>
                        <p className="mx-auto mt-2 max-w-sm text-sm text-ink-muted">
                            When you book, it will appear here with everything you need to know beforehand.
                        </p>
                        <div className="mt-6">
                            <ButtonLink href="/book">Book an appointment</ButtonLink>
                        </div>
                    </div>
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2">
                        {upcoming.map((row) => (
                            <li key={row.reference} className="rounded-2xl border border-line bg-surface p-6">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="text-ink">{row.date}</p>
                                        <p className="mt-0.5 text-sm text-ink-muted">
                                            {row.time} &middot; with {row.staff_name}
                                        </p>
                                    </div>
                                    <StatusPill status={row.status} label={row.status_label} />
                                </div>

                                <p className="mt-3 text-sm text-ink-muted">{row.services.join(', ')}</p>

                                <Link
                                    href={`/appointments/${row.reference}`}
                                    className="mt-4 inline-block border-t border-line pt-3 text-sm font-medium text-secondary underline underline-offset-4"
                                >
                                    View details
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </AppLayout>
    );
}
