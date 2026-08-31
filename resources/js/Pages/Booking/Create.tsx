import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import { Textarea } from '@/Components/Form';
import type { BookableCategory, PageProps, SharedProps, SlotOption, Stylist } from '@/types';

interface BookingWindow {
    earliest_date: string;
    latest_date: string;
    min_advance_minutes: number;
}

interface CreateProps {
    categories: BookableCategory[];
    stylists: Stylist[];
    selection: { service_ids: number[]; staff_id: number | null; date: string | null };
    summary: { duration_minutes: number; total_price: string };
    slots?: { date: string | null; times: SlotOption[] };
    booking_window: BookingWindow;
    timezone: string;
}

const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

function duration(minutes: number): string {
    if (minutes < 60) return `${minutes} min`;
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    return rest === 0 ? `${hours} hr` : `${hours} hr ${rest} min`;
}

export default function Create({
    categories,
    stylists,
    selection,
    summary,
    slots,
    booking_window,
    timezone,
}: PageProps<CreateProps>) {
    const { errors } = usePage<SharedProps>().props;

    const [serviceIds, setServiceIds] = useState<number[]>(selection.service_ids);
    const [staffId, setStaffId] = useState<number | null>(selection.staff_id);
    const [date, setDate] = useState<string>(selection.date ?? '');
    const [slot, setSlot] = useState<SlotOption | null>(null);

    const confirmation = useForm<{
        service_ids: number[];
        staff_id: number | null;
        starts_at: string;
        notes: string;
    }>({ service_ids: [], staff_id: null, starts_at: '', notes: '' });

    /**
     * Ask the server to recompute. Stylists and totals depend on the services,
     * and slots depend on all three, so the server stays the single source of
     * truth rather than the browser guessing.
     */
    const refresh = (next: { service_ids?: number[]; staff_id?: number | null; date?: string }) => {
        const data = {
            service_ids: next.service_ids ?? serviceIds,
            staff_id: next.staff_id === undefined ? staffId : next.staff_id,
            date: next.date === undefined ? date : next.date,
        };

        router.get('/book/new', data as never, {
            only: ['stylists', 'summary', 'slots', 'selection'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const toggleService = (id: number) => {
        const next = serviceIds.includes(id) ? serviceIds.filter((s) => s !== id) : [...serviceIds, id];

        setServiceIds(next);
        setSlot(null);

        // The chosen stylist may not offer the newly added service.
        const keepStaff = next.length > 0 ? staffId : null;
        setStaffId(keepStaff);

        refresh({ service_ids: next, staff_id: keepStaff });
    };

    const chooseStylist = (id: number) => {
        setStaffId(id);
        setSlot(null);
        refresh({ staff_id: id });
    };

    const chooseDate = (value: string) => {
        setDate(value);
        setSlot(null);
        refresh({ date: value });
    };

    const confirm = () => {
        if (!slot || !staffId) return;

        confirmation.transform(() => ({
            service_ids: serviceIds,
            staff_id: staffId,
            starts_at: slot.starts_at,
            notes: confirmation.data.notes,
        }));

        confirmation.post('/book/new', { preserveScroll: true });
    };

    const chosenServices = categories
        .flatMap((category) => category.services)
        .filter((service) => serviceIds.includes(service.id));

    const stylist = stylists.find((s) => s.id === staffId) ?? null;
    const times = slots?.times ?? [];

    return (
        <AppLayout title="Book an appointment">
            {(errors.starts_at || errors.staff_id || errors.service_ids) && (
                <div
                    role="alert"
                    className="mb-6 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900"
                >
                    {errors.starts_at ?? errors.staff_id ?? errors.service_ids}
                </div>
            )}

            <div className="grid gap-8 lg:grid-cols-[1fr_340px]">
                <div className="space-y-8">
                    {/* 1. Services */}
                    <section aria-labelledby="step-services" className="rounded-2xl border border-line bg-surface p-7">
                        <h2 id="step-services" className="text-lg text-ink">
                            <span className="mr-2 text-sm text-ink-muted">1.</span>
                            Choose your services
                        </h2>
                        <p className="mt-1 text-sm text-ink-muted">
                            Pick as many as you like. We block the total time in one appointment.
                        </p>

                        <div className="mt-6 space-y-7">
                            {categories.map((category) => (
                                <fieldset key={category.id}>
                                    <legend className="text-xs font-semibold tracking-wide text-secondary uppercase">
                                        {category.name}
                                    </legend>

                                    <div className="mt-3 space-y-2">
                                        {category.services.map((service) => {
                                            const checked = serviceIds.includes(service.id);

                                            return (
                                                <label
                                                    key={service.id}
                                                    className={`flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-colors ${
                                                        checked
                                                            ? 'border-primary bg-canvas-soft'
                                                            : 'border-line hover:border-line-strong'
                                                    }`}
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={checked}
                                                        onChange={() => toggleService(service.id)}
                                                        className="mt-1 h-4 w-4 rounded border-line-strong accent-primary"
                                                    />

                                                    <span className="flex-1">
                                                        <span className="flex flex-wrap items-baseline justify-between gap-2">
                                                            <span className="font-medium text-ink">{service.name}</span>
                                                            <span className="text-ink">
                                                                {peso.format(Number(service.price))}
                                                            </span>
                                                        </span>
                                                        <span className="mt-0.5 block text-xs text-ink-muted">
                                                            {duration(service.duration_minutes)}
                                                        </span>
                                                    </span>
                                                </label>
                                            );
                                        })}
                                    </div>
                                </fieldset>
                            ))}
                        </div>
                    </section>

                    {/* 2. Stylist */}
                    <section aria-labelledby="step-stylist" className="rounded-2xl border border-line bg-surface p-7">
                        <h2 id="step-stylist" className="text-lg text-ink">
                            <span className="mr-2 text-sm text-ink-muted">2.</span>
                            Choose your stylist
                        </h2>

                        {serviceIds.length === 0 ? (
                            <p className="mt-4 text-sm text-ink-muted">Choose a service first.</p>
                        ) : stylists.length === 0 ? (
                            <p className="mt-4 rounded-xl border border-dashed border-line-strong p-6 text-sm text-ink-muted">
                                No one stylist offers all of these services together. Try removing one, or book them
                                as separate appointments.
                            </p>
                        ) : (
                            <>
                                <p className="mt-1 text-sm text-ink-muted">
                                    Only stylists who can do everything you chose are shown.
                                </p>

                                <div className="mt-5 grid gap-3 sm:grid-cols-2">
                                    {stylists.map((person) => (
                                        <button
                                            key={person.id}
                                            type="button"
                                            onClick={() => chooseStylist(person.id)}
                                            aria-pressed={staffId === person.id}
                                            className={`flex items-center gap-3 rounded-xl border p-4 text-left transition-colors ${
                                                staffId === person.id
                                                    ? 'border-primary bg-canvas-soft'
                                                    : 'border-line hover:border-line-strong'
                                            }`}
                                        >
                                            <span className="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-canvas font-display text-ink">
                                                {person.photo_url ? (
                                                    <img
                                                        src={person.photo_url}
                                                        alt=""
                                                        className="h-full w-full object-cover"
                                                    />
                                                ) : (
                                                    person.name.charAt(0)
                                                )}
                                            </span>
                                            <span>
                                                <span className="block font-medium text-ink">{person.name}</span>
                                                {person.title && (
                                                    <span className="block text-xs text-ink-muted">
                                                        {person.title}
                                                    </span>
                                                )}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                            </>
                        )}
                    </section>

                    {/* 3. Date and time */}
                    <section aria-labelledby="step-time" className="rounded-2xl border border-line bg-surface p-7">
                        <h2 id="step-time" className="text-lg text-ink">
                            <span className="mr-2 text-sm text-ink-muted">3.</span>
                            Choose a date and time
                        </h2>

                        {!staffId ? (
                            <p className="mt-4 text-sm text-ink-muted">Choose a stylist first.</p>
                        ) : (
                            <>
                                <div className="mt-5 max-w-xs space-y-1.5">
                                    <label htmlFor="booking-date" className="block text-sm font-medium text-ink">
                                        Date
                                    </label>
                                    <input
                                        id="booking-date"
                                        type="date"
                                        value={date}
                                        min={booking_window.earliest_date}
                                        max={booking_window.latest_date}
                                        onChange={(e) => chooseDate(e.target.value)}
                                        className="w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink"
                                    />
                                    <p className="text-xs text-ink-muted">
                                        Times shown in {timezone}. We take bookings up to{' '}
                                        {booking_window.latest_date}.
                                    </p>
                                </div>

                                {date && (
                                    <div className="mt-6">
                                        {times.length === 0 ? (
                                            <p className="rounded-xl border border-dashed border-line-strong p-6 text-sm text-ink-muted">
                                                Nothing free on this date. Try another day, or a different stylist.
                                            </p>
                                        ) : (
                                            <>
                                                <p className="text-sm text-ink-muted">
                                                    {times.length} time{times.length === 1 ? '' : 's'} available
                                                </p>

                                                <div
                                                    role="group"
                                                    aria-label="Available times"
                                                    className="mt-3 flex flex-wrap gap-2"
                                                >
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
                                            </>
                                        )}
                                    </div>
                                )}
                            </>
                        )}
                    </section>
                </div>

                {/* Summary */}
                <aside className="lg:sticky lg:top-24 lg:self-start">
                    <div className="rounded-2xl border border-line bg-surface p-6">
                        <h2 className="text-lg text-ink">Your appointment</h2>

                        {chosenServices.length === 0 ? (
                            <p className="mt-4 text-sm text-ink-muted">Nothing chosen yet.</p>
                        ) : (
                            <ul className="mt-4 divide-y divide-line text-sm">
                                {chosenServices.map((service) => (
                                    <li key={service.id} className="flex justify-between gap-3 py-2.5">
                                        <span className="text-ink">{service.name}</span>
                                        <span className="text-ink-muted">{peso.format(Number(service.price))}</span>
                                    </li>
                                ))}
                            </ul>
                        )}

                        <dl className="mt-5 space-y-2 border-t border-line pt-5 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-ink-muted">Stylist</dt>
                                <dd className="text-ink">{stylist?.name ?? '—'}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-ink-muted">Total time</dt>
                                <dd className="text-ink">
                                    {summary.duration_minutes > 0 ? duration(summary.duration_minutes) : '—'}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-ink-muted">When</dt>
                                <dd className="text-right text-ink">
                                    {slot ? `${date} at ${slot.label}` : '—'}
                                </dd>
                            </div>
                            <div className="flex justify-between border-t border-line pt-3 text-base">
                                <dt className="text-ink">Total</dt>
                                <dd className="font-medium text-ink">
                                    {peso.format(Number(summary.total_price))}
                                </dd>
                            </div>
                        </dl>

                        <div className="mt-5">
                            <Textarea
                                label="Anything we should know?"
                                name="notes"
                                rows={3}
                                hint="Optional."
                                value={confirmation.data.notes}
                                error={errors.notes}
                                onChange={(e) => confirmation.setData('notes', e.target.value)}
                            />
                        </div>

                        <Button
                            type="button"
                            onClick={confirm}
                            disabled={!slot || !staffId || confirmation.processing}
                            className="mt-5 w-full"
                        >
                            {confirmation.processing ? 'Booking...' : 'Confirm booking'}
                        </Button>

                        <p className="mt-3 text-xs text-ink-muted">
                            We check the time is still free before confirming. Nothing is charged online.
                        </p>
                    </div>
                </aside>
            </div>
        </AppLayout>
    );
}
