import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import { BarChart, RankedBars, StatTile, TrendChart } from '@/Components/Charts';
import Field from '@/Components/Field';
import type { PageProps } from '@/types';

interface Ranked {
    name: string;
    bookings: number;
    value: string;
}

interface StaffRow {
    name: string;
    total: number;
    completed: number;
    cancelled: number;
    no_show: number;
    completion_rate: number;
    completed_value: string;
}

interface ReportsProps {
    range: { from: string; to: string; days: number; label: string };
    status_counts: Record<string, number>;
    value: { booked_value: string; completed_value: string; booked_count: number; completed_count: number };
    attrition: {
        total: number;
        cancelled: number;
        no_show: number;
        cancellation_rate: number;
        no_show_rate: number;
    };
    totals: { customers: number; active_staff: number; bookable_staff: number; active_services: number };
    trend: { label: string; total: number; completed: number }[];
    peaks: {
        by_hour: { label: string; total: number }[];
        by_weekday: { label: string; total: number }[];
    };
    popular_services: Ranked[];
    categories: Ranked[];
    staff: StaffRow[];
    customer_growth: { label: string; total: number }[];
    timezone: string;
}

const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 });

const PRESETS = [
    { label: 'Last 7 days', days: 6 },
    { label: 'Last 30 days', days: 29 },
    { label: 'Last 90 days', days: 89 },
];

export default function Reports({
    range,
    status_counts,
    value,
    attrition,
    totals,
    trend,
    peaks,
    popular_services,
    categories,
    staff,
    customer_growth,
    timezone,
}: PageProps<ReportsProps>) {
    const [from, setFrom] = useState(range.from);
    const [to, setTo] = useState(range.to);

    const apply = (event: FormEvent) => {
        event.preventDefault();
        router.get('/manage/reports', { from, to }, { preserveState: true, replace: true });
    };

    const preset = (days: number) => {
        const end = new Date();
        const start = new Date();
        start.setDate(start.getDate() - days);

        const fmt = (d: Date) => d.toISOString().slice(0, 10);
        setFrom(fmt(start));
        setTo(fmt(end));

        router.get('/manage/reports', { from: fmt(start), to: fmt(end) }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout title="Reports">
            {/* Filters in one row above the charts. */}
            <form onSubmit={apply} className="mb-6 flex flex-wrap items-end gap-3">
                <div className="w-40">
                    <Field label="From" name="from" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                </div>
                <div className="w-40">
                    <Field label="To" name="to" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                </div>

                <Button type="submit" variant="secondary">
                    Apply
                </Button>

                <div className="flex gap-2">
                    {PRESETS.map((p) => (
                        <button
                            key={p.label}
                            type="button"
                            onClick={() => preset(p.days)}
                            className="rounded-full border border-line-strong bg-surface px-3.5 py-2 text-sm text-ink transition-colors hover:bg-canvas-soft"
                        >
                            {p.label}
                        </button>
                    ))}
                </div>

                <p className="pb-2.5 text-xs text-ink-muted">
                    {range.label} &middot; {range.days} days &middot; {timezone}
                </p>
            </form>

            <section aria-labelledby="headline">
                <h2 id="headline" className="sr-only">
                    Headline numbers
                </h2>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatTile label="Appointments" value={attrition.total} note="All statuses" />
                    <StatTile
                        label="Completed"
                        value={value.completed_count}
                        note={peso.format(Number(value.completed_value))}
                    />
                    <StatTile
                        label="Cancellation rate"
                        value={`${attrition.cancellation_rate}%`}
                        note={`${attrition.cancelled} cancelled`}
                        tone={attrition.cancellation_rate > 20 ? 'attention' : 'default'}
                    />
                    <StatTile
                        label="No-show rate"
                        value={`${attrition.no_show_rate}%`}
                        note={`${attrition.no_show} did not arrive`}
                        tone={attrition.no_show_rate > 10 ? 'attention' : 'default'}
                    />
                </div>

                <p className="mt-3 text-xs text-ink-muted">
                    Values describe work booked and carried out. The system records no payments, so nothing here is
                    revenue received.
                </p>
            </section>

            <div className="mt-8 space-y-6">
                <TrendChart title="Appointments per day" subtitle="Booked against completed" data={trend} />

                <div className="grid gap-6 lg:grid-cols-2">
                    <BarChart
                        title="Busiest times of day"
                        subtitle="By appointment start time"
                        data={peaks.by_hour}
                    />
                    <BarChart title="Busiest days" subtitle="Across the whole period" data={peaks.by_weekday} />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <RankedBars
                        title="Most booked services"
                        subtitle="Excluding cancellations and no-shows"
                        data={popular_services}
                    />
                    <RankedBars title="Category performance" subtitle="Bookings by category" data={categories} />
                </div>

                <BarChart
                    title="New customers"
                    subtitle="Accounts created per day"
                    data={customer_growth}
                    unit="new customers"
                />

                {/* Several measures per person: a table reads better than any
                    chart would, so this stays a table. */}
                <section aria-labelledby="staff-heading" className="rounded-2xl border border-line bg-surface p-6">
                    <h3 id="staff-heading" className="text-base text-ink">
                        Staff statistics
                    </h3>

                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-line text-xs tracking-wide text-ink-muted uppercase">
                                <tr>
                                    <th scope="col" className="py-2 font-medium">
                                        Stylist
                                    </th>
                                    <th scope="col" className="py-2 font-medium">
                                        Total
                                    </th>
                                    <th scope="col" className="py-2 font-medium">
                                        Completed
                                    </th>
                                    <th scope="col" className="py-2 font-medium">
                                        Cancelled
                                    </th>
                                    <th scope="col" className="py-2 font-medium">
                                        No show
                                    </th>
                                    <th scope="col" className="py-2 font-medium">
                                        Completion
                                    </th>
                                    <th scope="col" className="py-2 text-right font-medium">
                                        Completed value
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {staff.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="py-8 text-center text-ink-muted">
                                            No staff activity in this period.
                                        </td>
                                    </tr>
                                )}

                                {staff.map((row) => (
                                    <tr key={row.name}>
                                        <th scope="row" className="py-2.5 font-normal text-ink">
                                            {row.name}
                                        </th>
                                        <td className="py-2.5 text-ink-muted">{row.total}</td>
                                        <td className="py-2.5 text-ink-muted">{row.completed}</td>
                                        <td className="py-2.5 text-ink-muted">{row.cancelled}</td>
                                        <td className="py-2.5 text-ink-muted">{row.no_show}</td>
                                        <td className="py-2.5 text-ink">{row.completion_rate}%</td>
                                        <td className="py-2.5 text-right text-ink">
                                            {peso.format(Number(row.completed_value))}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section aria-labelledby="status-heading" className="rounded-2xl border border-line bg-surface p-6">
                    <h3 id="status-heading" className="text-base text-ink">
                        Appointments by status
                    </h3>

                    <dl className="mt-4 grid gap-4 sm:grid-cols-4">
                        {Object.entries(status_counts).map(([status, count]) => (
                            <div key={status}>
                                <dt className="text-xs text-ink-muted capitalize">{status.replace('_', ' ')}</dt>
                                <dd className="font-display text-2xl text-ink">{count}</dd>
                            </div>
                        ))}
                    </dl>
                </section>

                <section className="grid gap-4 sm:grid-cols-4">
                    <StatTile label="Customers" value={totals.customers} />
                    <StatTile label="Active staff" value={totals.active_staff} />
                    <StatTile label="Bookable staff" value={totals.bookable_staff} />
                    <StatTile label="Active services" value={totals.active_services} />
                </section>
            </div>
        </AppLayout>
    );
}
