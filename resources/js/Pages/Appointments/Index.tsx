import { Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ButtonLink } from '@/Components/Button';
import StatusPill from '@/Components/StatusPill';
import type { AppointmentSummary, PageProps } from '@/types';

const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

function AppointmentCard({ appointment }: { appointment: AppointmentSummary }) {
    return (
        <li className="rounded-2xl border border-line bg-surface p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="text-lg text-ink">{appointment.date}</p>
                    <p className="mt-0.5 text-sm text-ink-muted">
                        {appointment.time} &middot; with {appointment.staff_name}
                    </p>
                </div>

                <StatusPill status={appointment.status} label={appointment.status_label} />
            </div>

            <ul className="mt-4 space-y-1 text-sm text-ink-muted">
                {appointment.items.map((item, index) => (
                    <li key={index}>{item.name}</li>
                ))}
            </ul>

            <div className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                <span className="text-sm text-ink">{peso.format(Number(appointment.total_price))}</span>

                <Link
                    href={`/appointments/${appointment.reference}`}
                    className="text-sm font-medium text-secondary underline underline-offset-4"
                >
                    View details
                </Link>
            </div>
        </li>
    );
}

interface IndexProps {
    upcoming: AppointmentSummary[];
    past: AppointmentSummary[];
}

export default function Index({ upcoming, past }: PageProps<IndexProps>) {
    return (
        <AppLayout title="My appointments">
            <section aria-labelledby="upcoming-heading">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 id="upcoming-heading" className="text-lg text-ink">
                        Upcoming
                    </h2>
                    <ButtonLink href="/book">Book another</ButtonLink>
                </div>

                {upcoming.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-line-strong bg-surface p-10 text-center">
                        <h3 className="text-lg text-ink">Nothing booked yet</h3>
                        <p className="mx-auto mt-2 max-w-sm text-sm text-ink-muted">
                            When you book an appointment it will appear here, with everything you need to know
                            beforehand.
                        </p>
                        <div className="mt-6">
                            <ButtonLink href="/book">Book an appointment</ButtonLink>
                        </div>
                    </div>
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2">
                        {upcoming.map((appointment) => (
                            <AppointmentCard key={appointment.reference} appointment={appointment} />
                        ))}
                    </ul>
                )}
            </section>

            {past.length > 0 && (
                <section aria-labelledby="past-heading" className="mt-12">
                    <h2 id="past-heading" className="mb-4 text-lg text-ink">
                        Past appointments
                    </h2>

                    <ul className="grid gap-4 sm:grid-cols-2">
                        {past.map((appointment) => (
                            <AppointmentCard key={appointment.reference} appointment={appointment} />
                        ))}
                    </ul>
                </section>
            )}
        </AppLayout>
    );
}
