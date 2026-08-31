import { Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ButtonLink } from '@/Components/Button';
import { StatTile } from '@/Components/Charts';
import StatusPill from '@/Components/StatusPill';
import type { AppointmentStatus, PageProps } from '@/types';

interface ScheduleRow {
    reference: string;
    time: string;
    customer_name: string;
    staff_name: string;
    services: string[];
    status: AppointmentStatus;
    status_label: string;
}

interface StylistProps {
    today: { date: string; schedule: ScheduleRow[] };
    upcoming_count: number;
    timezone: string;
}

export default function Stylist({ auth, today, upcoming_count }: PageProps<StylistProps>) {
    return (
        <AppLayout title={`Good day, ${auth.user?.name ?? ''}`}>
            <p className="mb-6 text-sm text-ink-muted">{today.date}</p>

            <div className="grid gap-4 sm:grid-cols-3">
                <StatTile label="Appointments today" value={today.schedule.length} />
                <StatTile label="Next seven days" value={upcoming_count} />
                <StatTile
                    label="In the chair"
                    value={today.schedule.filter((r) => r.status === 'in_progress').length}
                />
            </div>

            <section aria-labelledby="my-day" className="mt-8">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <h2 id="my-day" className="text-lg text-ink">
                        Your day
                    </h2>
                    <ButtonLink href="/manage/calendar" variant="secondary">
                        Your calendar
                    </ButtonLink>
                </div>

                {today.schedule.length === 0 ? (
                    <p className="rounded-2xl border border-dashed border-line-strong bg-surface p-10 text-center text-sm text-ink-muted">
                        Nothing booked with you today.
                    </p>
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2">
                        {today.schedule.map((row) => (
                            <li key={row.reference} className="rounded-2xl border border-line bg-surface p-5">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="text-lg text-ink">{row.time}</p>
                                        <p className="text-sm text-ink-muted">{row.customer_name}</p>
                                    </div>
                                    <StatusPill status={row.status} label={row.status_label} />
                                </div>

                                <p className="mt-3 text-sm text-ink-muted">{row.services.join(', ')}</p>

                                <Link
                                    href={`/manage/appointments/${row.reference}`}
                                    className="mt-4 inline-block border-t border-line pt-3 text-sm font-medium text-secondary underline underline-offset-4"
                                >
                                    Open appointment
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </AppLayout>
    );
}
