import { Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import Button, { ButtonLink } from '@/Components/Button';
import { Select } from '@/Components/Form';
import StatusPill from '@/Components/StatusPill';
import type { AppointmentStatus, PageProps } from '@/types';

interface CalendarEntry {
    reference: string;
    status: AppointmentStatus;
    status_label: string;
    date: string;
    start_label: string;
    end_label: string;
    start_minute: number;
    end_minute: number;
    customer_name: string;
    staff_name: string;
    staff_id: number;
    services: string[];
    holds_slot: boolean;
}

interface CalendarDay {
    date: string;
    label: string;
    weekday: string;
    is_today: boolean;
    in_month: boolean;
}

interface Option {
    value: number | string;
    label: string;
}

interface CalendarProps {
    view: 'day' | 'week' | 'month';
    anchor: string;
    range: { from: string; to: string; label: string };
    days: CalendarDay[];
    appointments: CalendarEntry[];
    filters: { staff?: string; status?: string; service?: string };
    staff: Option[];
    services: Option[];
    statuses: Option[];
    timezone: string;
}

/** The visible span of the day grid, in minutes from midnight. */
const DAY_START = 7 * 60;
const DAY_END = 21 * 60;
const PIXELS_PER_MINUTE = 1.1;

function shiftDate(anchor: string, view: string, direction: number): string {
    const date = new Date(anchor + 'T00:00:00');

    if (view === 'day') date.setDate(date.getDate() + direction);
    else if (view === 'week') date.setDate(date.getDate() + direction * 7);
    else date.setMonth(date.getMonth() + direction);

    return date.toISOString().slice(0, 10);
}

function EntryBlock({ entry }: { entry: CalendarEntry }) {
    const top = (Math.max(entry.start_minute, DAY_START) - DAY_START) * PIXELS_PER_MINUTE;
    const height = Math.max((entry.end_minute - Math.max(entry.start_minute, DAY_START)) * PIXELS_PER_MINUTE, 26);

    return (
        <Link
            href={`/manage/appointments/${entry.reference}`}
            style={{ top: `${top}px`, height: `${height}px` }}
            className={`absolute right-1 left-1 overflow-hidden rounded-lg border px-2 py-1 text-xs transition-colors ${
                entry.holds_slot
                    ? 'border-primary/30 bg-canvas-soft text-ink hover:border-primary'
                    : 'border-line bg-surface text-ink-muted line-through hover:border-line-strong'
            }`}
        >
            <span className="block font-medium">{entry.start_label}</span>
            <span className="block truncate">{entry.customer_name}</span>
            <span className="block truncate text-ink-muted">{entry.staff_name}</span>
        </Link>
    );
}

export default function Calendar({
    view,
    anchor,
    range,
    days,
    appointments,
    filters,
    staff,
    services,
    statuses,
    timezone,
}: PageProps<CalendarProps>) {
    const go = (overrides: Record<string, string | undefined>) => {
        router.get(
            '/manage/calendar',
            { view, date: anchor, ...filters, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    const hours = Array.from({ length: (DAY_END - DAY_START) / 60 + 1 }, (_, i) => DAY_START + i * 60);
    const gridHeight = (DAY_END - DAY_START) * PIXELS_PER_MINUTE;

    const forDay = (date: string) => appointments.filter((a) => a.date === date);

    return (
        <AppLayout title="Calendar">
            <div className="mb-5 flex flex-wrap items-center justify-between gap-4">
                <div className="flex items-center gap-2">
                    <Button type="button" variant="secondary" onClick={() => go({ date: shiftDate(anchor, view, -1) })}>
                        &larr;
                    </Button>
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() => go({ date: new Date().toISOString().slice(0, 10) })}
                    >
                        Today
                    </Button>
                    <Button type="button" variant="secondary" onClick={() => go({ date: shiftDate(anchor, view, 1) })}>
                        &rarr;
                    </Button>

                    <p className="ml-3 text-lg text-ink">{range.label}</p>
                </div>

                <div className="flex items-center gap-2">
                    <div role="group" aria-label="Calendar view" className="flex gap-1">
                        {(['day', 'week', 'month'] as const).map((option) => (
                            <button
                                key={option}
                                type="button"
                                onClick={() => go({ view: option })}
                                aria-pressed={view === option}
                                className={`rounded-full px-3.5 py-1.5 text-sm capitalize transition-colors ${
                                    view === option
                                        ? 'bg-primary text-ink-inverted'
                                        : 'border border-line-strong bg-surface text-ink hover:bg-canvas-soft'
                                }`}
                            >
                                {option}
                            </button>
                        ))}
                    </div>

                    <ButtonLink href="/manage/appointments" variant="secondary">
                        List
                    </ButtonLink>
                </div>
            </div>

            <div className="mb-5 flex flex-wrap items-end gap-3">
                <div className="w-44">
                    <Select
                        label="Stylist"
                        name="staff"
                        placeholder="Everyone"
                        options={staff}
                        value={filters.staff ?? ''}
                        onChange={(e) => go({ staff: e.target.value || undefined })}
                    />
                </div>
                <div className="w-44">
                    <Select
                        label="Status"
                        name="status"
                        placeholder="Any status"
                        options={statuses}
                        value={filters.status ?? ''}
                        onChange={(e) => go({ status: e.target.value || undefined })}
                    />
                </div>
                <div className="w-52">
                    <Select
                        label="Service"
                        name="service"
                        placeholder="Any service"
                        options={services}
                        value={filters.service ?? ''}
                        onChange={(e) => go({ service: e.target.value || undefined })}
                    />
                </div>
                <p className="pb-2.5 text-xs text-ink-muted">All times {timezone}</p>
            </div>

            {appointments.length === 0 && (
                <p className="mb-5 rounded-xl border border-dashed border-line-strong bg-surface p-6 text-center text-sm text-ink-muted">
                    Nothing booked in this period.
                </p>
            )}

            {/* Month: a simple grid of day cells. */}
            {view === 'month' ? (
                <div className="overflow-hidden rounded-2xl border border-line bg-surface">
                    <div className="grid grid-cols-7 border-b border-line bg-canvas-soft text-xs tracking-wide text-ink-muted uppercase">
                        {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((day) => (
                            <div key={day} className="px-3 py-2.5">
                                {day}
                            </div>
                        ))}
                    </div>

                    <div className="grid grid-cols-7">
                        {days.map((day) => {
                            const entries = forDay(day.date);

                            return (
                                <div
                                    key={day.date}
                                    className={`min-h-28 border-r border-b border-line p-2 ${
                                        day.in_month ? '' : 'bg-canvas-soft/60'
                                    }`}
                                >
                                    <div className="flex items-center justify-between">
                                        <span
                                            className={`text-xs ${
                                                day.is_today ? 'font-semibold text-ink' : 'text-ink-muted'
                                            }`}
                                        >
                                            {day.label}
                                            {day.is_today && <span className="ml-1">&middot; today</span>}
                                        </span>
                                        {entries.length > 0 && (
                                            <span className="text-[11px] text-ink-muted">{entries.length}</span>
                                        )}
                                    </div>

                                    <ul className="mt-1.5 space-y-1">
                                        {entries.slice(0, 3).map((entry) => (
                                            <li key={entry.reference}>
                                                <Link
                                                    href={`/manage/appointments/${entry.reference}`}
                                                    className="block truncate rounded px-1.5 py-0.5 text-[11px] text-ink hover:bg-canvas-soft"
                                                >
                                                    {entry.start_label} {entry.customer_name}
                                                </Link>
                                            </li>
                                        ))}
                                        {entries.length > 3 && (
                                            <li className="px-1.5 text-[11px] text-ink-muted">
                                                +{entries.length - 3} more
                                            </li>
                                        )}
                                    </ul>
                                </div>
                            );
                        })}
                    </div>
                </div>
            ) : (
                /* Day and week: a time grid. */
                <div className="overflow-x-auto rounded-2xl border border-line bg-surface">
                    <div className="flex min-w-[640px]">
                        <div className="w-16 shrink-0 border-r border-line pt-9">
                            {hours.map((minute) => (
                                <div
                                    key={minute}
                                    style={{ height: `${60 * PIXELS_PER_MINUTE}px` }}
                                    className="pr-2 text-right text-[11px] text-ink-muted"
                                >
                                    {String(Math.floor(minute / 60)).padStart(2, '0')}:00
                                </div>
                            ))}
                        </div>

                        <div className="flex flex-1">
                            {days.map((day) => (
                                <div key={day.date} className="flex-1 border-r border-line last:border-r-0">
                                    <div
                                        className={`border-b border-line px-2 py-2 text-center text-xs ${
                                            day.is_today ? 'bg-canvas-soft font-semibold text-ink' : 'text-ink-muted'
                                        }`}
                                    >
                                        {day.weekday} {day.label}
                                        {day.is_today && <span className="ml-1">&middot; today</span>}
                                    </div>

                                    <div className="relative" style={{ height: `${gridHeight}px` }}>
                                        {hours.slice(0, -1).map((minute) => (
                                            <div
                                                key={minute}
                                                style={{ height: `${60 * PIXELS_PER_MINUTE}px` }}
                                                className="border-b border-line/60"
                                            />
                                        ))}

                                        {forDay(day.date).map((entry) => (
                                            <EntryBlock key={entry.reference} entry={entry} />
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {/* A plain list beneath the grid, so the same data is reachable
                without relying on reading absolutely positioned blocks. */}
            {appointments.length > 0 && (
                <section className="mt-8">
                    <h2 className="mb-3 text-sm font-medium text-ink">
                        {appointments.length} appointment{appointments.length === 1 ? '' : 's'} in this period
                    </h2>

                    <ul className="divide-y divide-line overflow-hidden rounded-2xl border border-line bg-surface">
                        {appointments.map((entry) => (
                            <li key={entry.reference} className="flex flex-wrap items-center gap-3 px-5 py-3 text-sm">
                                <Link
                                    href={`/manage/appointments/${entry.reference}`}
                                    className="font-medium text-ink underline-offset-4 hover:underline"
                                >
                                    {entry.date} {entry.start_label}
                                </Link>
                                <span className="text-ink-muted">{entry.customer_name}</span>
                                <span className="text-ink-muted">{entry.staff_name}</span>
                                <span className="ml-auto">
                                    <StatusPill status={entry.status} label={entry.status_label} />
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </AppLayout>
    );
}
