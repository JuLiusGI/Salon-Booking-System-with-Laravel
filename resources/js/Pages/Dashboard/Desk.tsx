import { Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ButtonLink } from '@/Components/Button';
import { StatTile, TrendChart } from '@/Components/Charts';
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

interface DeskProps {
    today: {
        date: string;
        total: number;
        by_status: Record<string, number>;
        remaining: number;
        checked_in: number;
        in_progress: number;
    };
    attention: {
        awaiting_confirmation: number;
        upcoming_week: number;
        unresolved_past: number;
        services_without_staff: number;
    };
    totals: { customers: number; active_staff: number; bookable_staff: number; active_services: number };
    schedule: ScheduleRow[];
    month: {
        value: { booked_value: string; completed_value: string; booked_count: number; completed_count: number };
        attrition: { cancellation_rate: number; no_show_rate: number; cancelled: number; no_show: number };
    };
    trend: { label: string; total: number; completed: number }[];
    timezone: string;
}

const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 });

export default function Desk({ today, attention, totals, schedule, month, trend }: PageProps<DeskProps>) {
    return (
        <AppLayout title="Dashboard">
            <p className="mb-6 text-sm text-ink-muted">{today.date}</p>

            <section aria-labelledby="today-heading">
                <h2 id="today-heading" className="sr-only">
                    Today at a glance
                </h2>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatTile label="Appointments today" value={today.total} />
                    <StatTile label="Still to arrive" value={today.remaining} />
                    <StatTile label="In the chair" value={today.in_progress} />
                    <StatTile
                        label="Awaiting confirmation"
                        value={attention.awaiting_confirmation}
                        tone={attention.awaiting_confirmation > 0 ? 'attention' : 'default'}
                        note={attention.awaiting_confirmation > 0 ? 'Needs a decision' : undefined}
                    />
                </div>
            </section>

            <section aria-labelledby="actions-heading" className="mt-6">
                <h2 id="actions-heading" className="mb-3 text-lg text-ink">
                    Quick actions
                </h2>

                <div className="flex flex-wrap gap-3">
                    <ButtonLink href="/manage/check-in">Check someone in</ButtonLink>
                    <ButtonLink href="/manage/appointments/new" variant="secondary">
                        New appointment
                    </ButtonLink>
                    <ButtonLink href="/manage/calendar" variant="secondary">
                        Calendar
                    </ButtonLink>
                    <ButtonLink href="/manage/reports" variant="secondary">
                        Reports
                    </ButtonLink>
                </div>

                {(attention.unresolved_past > 0 || attention.services_without_staff > 0) && (
                    <ul className="mt-4 space-y-2 text-sm">
                        {attention.unresolved_past > 0 && (
                            <li className="rounded-lg border border-accent/50 bg-accent/10 px-4 py-2.5 text-ink">
                                {attention.unresolved_past} past appointment
                                {attention.unresolved_past === 1 ? '' : 's'} still open.{' '}
                                <Link
                                    href="/manage/appointments"
                                    className="text-secondary underline underline-offset-4"
                                >
                                    Close them off
                                </Link>
                            </li>
                        )}
                        {attention.services_without_staff > 0 && (
                            <li className="rounded-lg border border-accent/50 bg-accent/10 px-4 py-2.5 text-ink">
                                {attention.services_without_staff} active service
                                {attention.services_without_staff === 1 ? '' : 's'} with nobody assigned, so they
                                cannot be booked.{' '}
                                <Link href="/admin/services" className="text-secondary underline underline-offset-4">
                                    Assign staff
                                </Link>
                            </li>
                        )}
                    </ul>
                )}
            </section>

            <div className="mt-8 grid gap-6 lg:grid-cols-[1fr_360px]">
                <section aria-labelledby="trend-heading">
                    <h2 id="trend-heading" className="mb-3 text-lg text-ink">
                        Last 30 days
                    </h2>

                    <TrendChart
                        title="Appointments per day"
                        subtitle="Booked against completed"
                        data={trend}
                    />

                    <div className="mt-4 grid gap-4 sm:grid-cols-3">
                        <StatTile
                            label="Booked value"
                            value={peso.format(Number(month.value.booked_value))}
                            note={`${month.value.booked_count} appointments`}
                        />
                        <StatTile
                            label="Completed value"
                            value={peso.format(Number(month.value.completed_value))}
                            note="Work carried out, not money taken"
                        />
                        <StatTile
                            label="Fall-through"
                            value={`${(month.attrition.cancellation_rate + month.attrition.no_show_rate).toFixed(1)}%`}
                            note={`${month.attrition.cancelled} cancelled, ${month.attrition.no_show} no show`}
                        />
                    </div>
                </section>

                <section aria-labelledby="schedule-heading">
                    <h2 id="schedule-heading" className="mb-3 text-lg text-ink">
                        Today&rsquo;s schedule
                    </h2>

                    {schedule.length === 0 ? (
                        <p className="rounded-2xl border border-dashed border-line-strong bg-surface p-8 text-center text-sm text-ink-muted">
                            Nothing booked today.
                        </p>
                    ) : (
                        <ul className="divide-y divide-line overflow-hidden rounded-2xl border border-line bg-surface">
                            {schedule.map((row) => (
                                <li key={row.reference} className="px-5 py-3">
                                    <div className="flex items-baseline justify-between gap-3">
                                        <Link
                                            href={`/manage/appointments/${row.reference}`}
                                            className="font-medium text-ink underline-offset-4 hover:underline"
                                        >
                                            {row.time}
                                        </Link>
                                        <StatusPill status={row.status} label={row.status_label} />
                                    </div>
                                    <p className="mt-0.5 text-sm text-ink-muted">
                                        {row.customer_name} &middot; {row.staff_name}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}

                    <div className="mt-4 grid grid-cols-2 gap-3">
                        <StatTile label="Customers" value={totals.customers} />
                        <StatTile label="Bookable staff" value={totals.bookable_staff} />
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
