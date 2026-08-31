import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button, { ButtonLink } from '@/Components/Button';
import type { PageProps, SharedProps, SlotOption } from '@/types';

interface RescheduleProps {
    appointment: {
        reference: string;
        customer_name: string;
        staff_name: string;
        current: string;
        duration_minutes: number;
        services: string[];
    };
    services_resolvable: boolean;
    stylists: { id: number; name: string; title: string | null }[];
    selection: { staff_id: number | null; date: string | null };
    slots?: { date: string | null; times: SlotOption[] };
    booking_window: { earliest_date: string; latest_date: string };
    timezone: string;
}

export default function Reschedule({
    appointment,
    services_resolvable,
    stylists,
    selection,
    slots,
    booking_window,
    timezone,
}: PageProps<RescheduleProps>) {
    const { errors } = usePage<SharedProps>().props;

    const [staffId, setStaffId] = useState<number | null>(selection.staff_id);
    const [date, setDate] = useState<string>(selection.date ?? '');
    const [slot, setSlot] = useState<SlotOption | null>(null);

    const form = useForm<{ starts_at: string; staff_id: number | null }>({ starts_at: '', staff_id: null });

    const refresh = (next: { staff_id?: number | null; date?: string }) => {
        router.get(
            `/appointments/${appointment.reference}/reschedule`,
            {
                staff_id: next.staff_id === undefined ? staffId : next.staff_id,
                date: next.date === undefined ? date : next.date,
            } as never,
            { only: ['slots', 'selection'], preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const confirm = () => {
        if (!slot) return;

        form.transform(() => ({ starts_at: slot.starts_at, staff_id: staffId }));
        form.post(`/appointments/${appointment.reference}/reschedule`);
    };

    const times = slots?.times ?? [];

    return (
        <AppLayout title="Reschedule appointment">
            {!services_resolvable ? (
                <div
                    role="alert"
                    className="max-w-2xl rounded-2xl border border-red-300 bg-red-50 p-7 text-sm text-red-900"
                >
                    <h2 className="text-lg">This appointment cannot be moved automatically</h2>
                    <p className="mt-2">
                        One of its services is no longer in the menu, so rebooking it would quietly change what the
                        customer is getting. Cancel this appointment and book a new one instead.
                    </p>
                    <div className="mt-5">
                        <ButtonLink href={`/manage/appointments/${appointment.reference}`} variant="secondary">
                            Back to the appointment
                        </ButtonLink>
                    </div>
                </div>
            ) : (
                <div className="grid gap-8 lg:grid-cols-[1fr_320px]">
                    <div className="space-y-6">
                        {errors.starts_at && (
                            <div
                                role="alert"
                                className="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900"
                            >
                                {errors.starts_at}
                            </div>
                        )}

                        <section className="rounded-2xl border border-line bg-surface p-7">
                            <h2 className="text-lg text-ink">Choose a stylist</h2>

                            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                {stylists.map((person) => (
                                    <button
                                        key={person.id}
                                        type="button"
                                        onClick={() => {
                                            setStaffId(person.id);
                                            setSlot(null);
                                            refresh({ staff_id: person.id });
                                        }}
                                        aria-pressed={staffId === person.id}
                                        className={`rounded-xl border p-4 text-left transition-colors ${
                                            staffId === person.id
                                                ? 'border-primary bg-canvas-soft'
                                                : 'border-line hover:border-line-strong'
                                        }`}
                                    >
                                        <span className="block font-medium text-ink">{person.name}</span>
                                        {person.title && (
                                            <span className="block text-xs text-ink-muted">{person.title}</span>
                                        )}
                                    </button>
                                ))}
                            </div>
                        </section>

                        <section className="rounded-2xl border border-line bg-surface p-7">
                            <h2 className="text-lg text-ink">Choose a new time</h2>

                            <div className="mt-4 max-w-xs space-y-1.5">
                                <label htmlFor="reschedule-date" className="block text-sm font-medium text-ink">
                                    Date
                                </label>
                                <input
                                    id="reschedule-date"
                                    type="date"
                                    value={date}
                                    min={booking_window.earliest_date}
                                    max={booking_window.latest_date}
                                    onChange={(e) => {
                                        setDate(e.target.value);
                                        setSlot(null);
                                        refresh({ date: e.target.value });
                                    }}
                                    className="w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink"
                                />
                                <p className="text-xs text-ink-muted">Times in {timezone}.</p>
                            </div>

                            {date && (
                                <div className="mt-6">
                                    {times.length === 0 ? (
                                        <p className="rounded-xl border border-dashed border-line-strong p-6 text-sm text-ink-muted">
                                            Nothing free on this date for a {appointment.duration_minutes} minute
                                            appointment.
                                        </p>
                                    ) : (
                                        <div role="group" aria-label="Available times" className="flex flex-wrap gap-2">
                                            {times.map((option) => (
                                                <button
                                                    key={option.starts_at}
                                                    type="button"
                                                    onClick={() => setSlot(option)}
                                                    aria-pressed={slot?.starts_at === option.starts_at}
                                                    className={`rounded-full border px-4 py-2 text-sm transition-colors ${
                                                        slot?.starts_at === option.starts_at
                                                            ? 'border-primary bg-primary text-ink-inverted'
                                                            : 'border-line-strong bg-surface text-ink hover:bg-canvas-soft'
                                                    }`}
                                                >
                                                    {option.label}
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                        </section>
                    </div>

                    <aside className="lg:sticky lg:top-24 lg:self-start">
                        <div className="rounded-2xl border border-line bg-surface p-6">
                            <h2 className="text-base text-ink">Currently booked</h2>

                            <dl className="mt-4 space-y-3 text-sm">
                                <div>
                                    <dt className="text-ink-muted">Customer</dt>
                                    <dd className="text-ink">{appointment.customer_name}</dd>
                                </div>
                                <div>
                                    <dt className="text-ink-muted">When</dt>
                                    <dd className="text-ink">{appointment.current}</dd>
                                </div>
                                <div>
                                    <dt className="text-ink-muted">Services</dt>
                                    <dd className="text-ink">{appointment.services.join(', ')}</dd>
                                </div>
                                <div>
                                    <dt className="text-ink-muted">Moving to</dt>
                                    <dd className="text-ink">{slot ? `${date} at ${slot.label}` : '—'}</dd>
                                </div>
                            </dl>

                            <Button
                                type="button"
                                onClick={confirm}
                                disabled={!slot || form.processing}
                                className="mt-5 w-full"
                            >
                                {form.processing ? 'Moving...' : 'Confirm new time'}
                            </Button>

                            <p className="mt-3 text-xs text-ink-muted">
                                The original booking is cancelled and a new one created, so the change stays on record.
                                The reference will change.
                            </p>
                        </div>
                    </aside>
                </div>
            )}
        </AppLayout>
    );
}
