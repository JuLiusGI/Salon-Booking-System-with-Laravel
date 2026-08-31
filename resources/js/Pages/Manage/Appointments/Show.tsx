import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button, { ButtonLink } from '@/Components/Button';
import { Textarea } from '@/Components/Form';
import StatusPill from '@/Components/StatusPill';
import type { AppointmentStatus, PageProps } from '@/types';

interface TransitionOption {
    value: AppointmentStatus;
    label: string;
}

interface ManagedAppointment {
    reference: string;
    status: AppointmentStatus;
    status_label: string;
    date: string;
    time: string;
    customer_name: string;
    staff_name: string;
    total_duration_minutes: number;
    total_price: string;
    services: string[];
    is_past: boolean;
    notes: string | null;
    internal_notes: string | null;
    source: string;
    booked_on: string;
    checked_in_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    rescheduled_from: string | null;
    customer: {
        name: string;
        email: string;
        phone: string | null;
        allergies: string | null;
        preferences: string | null;
    };
}

interface ShowProps {
    appointment: ManagedAppointment;
    available_transitions: TransitionOption[];
    can: { update: boolean; cancel: boolean; reschedule: boolean };
    deadlines: { cancel_by: string; reschedule_by: string };
    timezone: string;
}

const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

export default function Show({
    appointment,
    available_transitions,
    can,
    deadlines,
    timezone,
}: PageProps<ShowProps>) {
    const [cancelling, setCancelling] = useState(false);

    const notes = useForm({ internal_notes: appointment.internal_notes ?? '' });
    const cancellation = useForm({ reason: '' });

    const move = (status: AppointmentStatus) => {
        router.post(
            `/manage/appointments/${appointment.reference}/status`,
            { status },
            { preserveScroll: true },
        );
    };

    const saveNotes = (event: FormEvent) => {
        event.preventDefault();
        notes.patch(`/manage/appointments/${appointment.reference}`, { preserveScroll: true });
    };

    const confirmCancel = (event: FormEvent) => {
        event.preventDefault();
        cancellation.post(`/appointments/${appointment.reference}/cancel`, {
            preserveScroll: true,
            onSuccess: () => setCancelling(false),
        });
    };

    const timeline = [
        { label: 'Booked', at: appointment.booked_on },
        { label: 'Checked in', at: appointment.checked_in_at },
        { label: 'Started', at: appointment.started_at },
        { label: 'Completed', at: appointment.completed_at },
        { label: 'Cancelled', at: appointment.cancelled_at },
    ].filter((entry) => entry.at);

    return (
        <AppLayout title={`Appointment ${appointment.reference}`}>
            <div className="grid gap-8 lg:grid-cols-[1fr_330px]">
                <div className="space-y-6">
                    <section className="rounded-2xl border border-line bg-surface p-7">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-xs tracking-wide text-ink-muted uppercase">
                                    {appointment.reference} &middot; booked {appointment.source}
                                </p>
                                <h2 className="mt-2 text-2xl text-ink">{appointment.date}</h2>
                                <p className="mt-1 text-ink-muted">
                                    {appointment.time} &middot; {appointment.staff_name} &middot; {timezone}
                                </p>
                            </div>

                            <StatusPill status={appointment.status} label={appointment.status_label} />
                        </div>

                        {appointment.rescheduled_from && (
                            <p className="mt-4 rounded-lg border border-line bg-canvas-soft px-4 py-2.5 text-sm text-ink-muted">
                                Moved from{' '}
                                <Link
                                    href={`/manage/appointments/${appointment.rescheduled_from}`}
                                    className="text-secondary underline underline-offset-4"
                                >
                                    {appointment.rescheduled_from}
                                </Link>
                            </p>
                        )}

                        {appointment.cancellation_reason && (
                            <p className="mt-4 rounded-lg border border-accent/40 bg-accent/15 px-4 py-2.5 text-sm text-ink">
                                Cancellation reason: {appointment.cancellation_reason}
                            </p>
                        )}

                        <ul className="mt-6 divide-y divide-line border-t border-line">
                            {appointment.services.map((service, index) => (
                                <li key={index} className="py-3 text-ink">
                                    {service}
                                </li>
                            ))}
                        </ul>

                        <div className="mt-4 flex justify-between border-t border-line pt-4">
                            <span className="text-ink-muted">
                                {appointment.total_duration_minutes} minutes
                            </span>
                            <span className="font-medium text-ink">
                                {peso.format(Number(appointment.total_price))}
                            </span>
                        </div>
                    </section>

                    <section className="rounded-2xl border border-line bg-surface p-7">
                        <h2 className="text-lg text-ink">Customer</h2>

                        <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt className="text-xs tracking-wide text-ink-muted uppercase">Name</dt>
                                <dd className="mt-1 text-ink">{appointment.customer.name}</dd>
                            </div>
                            <div>
                                <dt className="text-xs tracking-wide text-ink-muted uppercase">Email</dt>
                                <dd className="mt-1 text-ink">{appointment.customer.email}</dd>
                            </div>
                            <div>
                                <dt className="text-xs tracking-wide text-ink-muted uppercase">Phone</dt>
                                <dd className="mt-1 text-ink">{appointment.customer.phone ?? '—'}</dd>
                            </div>
                        </dl>

                        {appointment.customer.allergies && (
                            <div className="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                                <h3 className="text-xs font-semibold tracking-wide text-red-900 uppercase">
                                    Allergies and sensitivities
                                </h3>
                                <p className="mt-1 text-sm text-red-900">{appointment.customer.allergies}</p>
                            </div>
                        )}

                        {appointment.customer.preferences && (
                            <div className="mt-4">
                                <h3 className="text-xs tracking-wide text-ink-muted uppercase">Preferences</h3>
                                <p className="mt-1 text-sm text-ink">{appointment.customer.preferences}</p>
                            </div>
                        )}

                        {appointment.notes && (
                            <div className="mt-4 border-t border-line pt-4">
                                <h3 className="text-xs tracking-wide text-ink-muted uppercase">
                                    Note from the customer
                                </h3>
                                <p className="mt-1 text-sm text-ink">{appointment.notes}</p>
                            </div>
                        )}
                    </section>

                    {can.update && (
                        <section className="rounded-2xl border border-line bg-surface p-7">
                            <h2 className="text-lg text-ink">Internal notes</h2>

                            <form onSubmit={saveNotes} className="mt-4 space-y-4">
                                <Textarea
                                    label="Notes for the team"
                                    name="internal_notes"
                                    rows={4}
                                    hint="Never shown to the customer."
                                    value={notes.data.internal_notes}
                                    error={notes.errors.internal_notes}
                                    onChange={(e) => notes.setData('internal_notes', e.target.value)}
                                />

                                <Button type="submit" disabled={notes.processing}>
                                    {notes.processing ? 'Saving...' : 'Save notes'}
                                </Button>
                            </form>
                        </section>
                    )}
                </div>

                <aside className="space-y-4 lg:sticky lg:top-24 lg:self-start">
                    {available_transitions.length > 0 && (
                        <div className="rounded-2xl border border-line bg-surface p-6">
                            <h2 className="text-base text-ink">Move this appointment on</h2>
                            <p className="mt-1 text-xs text-ink-muted">
                                Only the steps allowed from {appointment.status_label} are shown.
                            </p>

                            <div className="mt-4 flex flex-col gap-2">
                                {available_transitions
                                    .filter((option) => option.value !== 'cancelled')
                                    .map((option) => (
                                        <Button
                                            key={option.value}
                                            type="button"
                                            variant="secondary"
                                            onClick={() => move(option.value)}
                                        >
                                            Mark as {option.label}
                                        </Button>
                                    ))}
                            </div>
                        </div>
                    )}

                    {(can.cancel || can.reschedule) && (
                        <div className="rounded-2xl border border-line bg-surface p-6">
                            <h2 className="text-base text-ink">Changes</h2>

                            <ul className="mt-3 space-y-1 text-xs text-ink-muted">
                                <li>Cancel by {deadlines.cancel_by}</li>
                                <li>Reschedule by {deadlines.reschedule_by}</li>
                            </ul>

                            <div className="mt-4 flex flex-col gap-2">
                                {can.reschedule && (
                                    <ButtonLink
                                        href={`/appointments/${appointment.reference}/reschedule`}
                                        variant="secondary"
                                    >
                                        Reschedule
                                    </ButtonLink>
                                )}

                                {can.cancel && !cancelling && (
                                    <Button type="button" variant="danger" onClick={() => setCancelling(true)}>
                                        Cancel appointment
                                    </Button>
                                )}
                            </div>

                            {cancelling && (
                                <form onSubmit={confirmCancel} className="mt-4 space-y-3 border-t border-line pt-4">
                                    <p className="text-sm text-ink">
                                        This releases the time back into the diary. It cannot be undone.
                                    </p>

                                    <Textarea
                                        label="Reason"
                                        name="reason"
                                        rows={2}
                                        hint="Optional, kept for the salon's records."
                                        value={cancellation.data.reason}
                                        error={cancellation.errors.reason}
                                        onChange={(e) => cancellation.setData('reason', e.target.value)}
                                    />

                                    <div className="flex gap-2">
                                        <Button type="submit" variant="danger" disabled={cancellation.processing}>
                                            {cancellation.processing ? 'Cancelling...' : 'Confirm cancellation'}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            onClick={() => setCancelling(false)}
                                        >
                                            Keep it
                                        </Button>
                                    </div>
                                </form>
                            )}
                        </div>
                    )}

                    <div className="rounded-2xl border border-line bg-surface p-6">
                        <h2 className="text-base text-ink">History</h2>

                        <ol className="mt-4 space-y-3 text-sm">
                            {timeline.map((entry) => (
                                <li key={entry.label}>
                                    <span className="block text-ink">{entry.label}</span>
                                    <span className="text-xs text-ink-muted">{entry.at}</span>
                                </li>
                            ))}
                        </ol>
                    </div>

                    <ButtonLink href="/manage/appointments" variant="secondary" className="w-full">
                        Back to appointments
                    </ButtonLink>
                </aside>
            </div>
        </AppLayout>
    );
}
