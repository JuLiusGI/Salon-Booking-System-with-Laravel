import AppLayout from '@/Layouts/AppLayout';
import { ButtonLink } from '@/Components/Button';
import StatusPill from '@/Components/StatusPill';
import type { AppointmentSummary, PageProps } from '@/types';

const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

interface AppointmentDetail extends AppointmentSummary {
    notes: string | null;
    customer_name: string;
    booked_on: string;
    cancellation_deadline: string;
    reschedule_deadline: string;
    can_still_cancel: boolean;
    can_still_reschedule: boolean;
}

export default function Show({
    appointment,
    timezone,
}: PageProps<{ appointment: AppointmentDetail; timezone: string }>) {
    return (
        <AppLayout title="Appointment">
            <div className="grid gap-8 lg:grid-cols-[1fr_320px]">
                <div className="space-y-6">
                    <section className="rounded-2xl border border-line bg-surface p-7">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-xs tracking-wide text-ink-muted uppercase">
                                    Reference {appointment.reference}
                                </p>
                                <h2 className="mt-2 text-2xl text-ink">{appointment.date}</h2>
                                <p className="mt-1 text-ink-muted">
                                    {appointment.time} &middot; {timezone}
                                </p>
                            </div>

                            <StatusPill status={appointment.status} label={appointment.status_label} />
                        </div>

                        <dl className="mt-7 grid gap-5 border-t border-line pt-6 sm:grid-cols-2">
                            <div>
                                <dt className="text-xs tracking-wide text-ink-muted uppercase">Stylist</dt>
                                <dd className="mt-1 text-ink">{appointment.staff_name}</dd>
                            </div>
                            <div>
                                <dt className="text-xs tracking-wide text-ink-muted uppercase">Booked for</dt>
                                <dd className="mt-1 text-ink">{appointment.customer_name}</dd>
                            </div>
                            <div>
                                <dt className="text-xs tracking-wide text-ink-muted uppercase">Total time</dt>
                                <dd className="mt-1 text-ink">{appointment.total_duration_minutes} minutes</dd>
                            </div>
                            <div>
                                <dt className="text-xs tracking-wide text-ink-muted uppercase">Booked on</dt>
                                <dd className="mt-1 text-ink">{appointment.booked_on}</dd>
                            </div>
                        </dl>

                        {appointment.notes && (
                            <div className="mt-6 border-t border-line pt-6">
                                <h3 className="text-xs tracking-wide text-ink-muted uppercase">Your note</h3>
                                <p className="mt-2 text-sm leading-relaxed text-ink">{appointment.notes}</p>
                            </div>
                        )}
                    </section>

                    <section className="rounded-2xl border border-line bg-surface p-7">
                        <h2 className="text-lg text-ink">Services</h2>

                        <ul className="mt-4 divide-y divide-line">
                            {appointment.items.map((item, index) => (
                                <li key={index} className="flex flex-wrap items-baseline justify-between gap-3 py-3">
                                    <span className="text-ink">{item.name}</span>
                                    <span className="flex items-baseline gap-5 text-sm">
                                        <span className="text-ink-muted">{item.duration_minutes} min</span>
                                        <span className="text-ink">{peso.format(Number(item.price))}</span>
                                    </span>
                                </li>
                            ))}
                        </ul>

                        <div className="mt-4 flex justify-between border-t border-line pt-4">
                            <span className="text-ink">Total</span>
                            <span className="font-medium text-ink">
                                {peso.format(Number(appointment.total_price))}
                            </span>
                        </div>

                        <p className="mt-4 text-xs text-ink-muted">
                            These are the prices and durations at the time you booked. If our menu changes later, this
                            appointment stays as booked.
                        </p>
                    </section>
                </div>

                <aside className="space-y-4 lg:sticky lg:top-24 lg:self-start">
                    {appointment.is_upcoming && appointment.blocks_availability && (
                        <div className="rounded-2xl border border-line bg-surface p-6">
                            <h2 className="text-base text-ink">Changing this appointment</h2>

                            <ul className="mt-4 space-y-3 text-sm">
                                <li>
                                    <span className="block text-ink-muted">Cancel by</span>
                                    <span className="text-ink">{appointment.cancellation_deadline}</span>
                                    {!appointment.can_still_cancel && (
                                        <span className="mt-1 block text-xs text-ink-muted">
                                            This has passed. Please call the salon.
                                        </span>
                                    )}
                                </li>
                                <li>
                                    <span className="block text-ink-muted">Reschedule by</span>
                                    <span className="text-ink">{appointment.reschedule_deadline}</span>
                                    {!appointment.can_still_reschedule && (
                                        <span className="mt-1 block text-xs text-ink-muted">
                                            This has passed. Please call the salon.
                                        </span>
                                    )}
                                </li>
                            </ul>

                            <p className="mt-4 border-t border-line pt-4 text-xs text-ink-muted">
                                To cancel or move this appointment, call us on (02) 8000 0000. Doing it here is coming
                                soon.
                            </p>
                        </div>
                    )}

                    <div className="rounded-2xl border border-line bg-surface p-6">
                        <ButtonLink href="/appointments" variant="secondary" className="w-full">
                            All my appointments
                        </ButtonLink>
                    </div>
                </aside>
            </div>
        </AppLayout>
    );
}
