import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Field from '@/Components/Field';
import StatusPill from '@/Components/StatusPill';
import type { AppointmentStatus, PageProps } from '@/types';

export interface Arrival {
    reference: string;
    status: AppointmentStatus;
    status_label: string;
    time: string;
    date: string;
    customer_name: string;
    staff_name: string;
    services: string[];
    allergies: string | null;
    can_check_in: boolean;
}

interface CheckInProps {
    arrivals: Arrival[];
    today: string;
    timezone: string;
    lookup: string;
    found: Arrival | null;
}

export function ArrivalCard({ arrival, highlight = false }: { arrival: Arrival; highlight?: boolean }) {
    const checkIn = () => {
        router.post(`/manage/check-in/${arrival.reference}`, {}, { preserveScroll: true });
    };

    return (
        <div
            className={`rounded-2xl border bg-surface p-5 ${
                highlight ? 'border-primary' : 'border-line'
            }`}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="text-lg text-ink">{arrival.time}</p>
                    <p className="text-sm text-ink-muted">
                        {arrival.customer_name} &middot; {arrival.staff_name}
                    </p>
                </div>
                <StatusPill status={arrival.status} label={arrival.status_label} />
            </div>

            <p className="mt-3 text-sm text-ink-muted">{arrival.services.join(', ')}</p>

            {arrival.allergies && (
                <p className="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-900">
                    <span className="font-semibold">Allergies:</span> {arrival.allergies}
                </p>
            )}

            <div className="mt-4 flex flex-wrap items-center gap-3 border-t border-line pt-4">
                {arrival.can_check_in ? (
                    <Button type="button" onClick={checkIn}>
                        Check in
                    </Button>
                ) : (
                    <span className="text-xs text-ink-muted">
                        {arrival.status_label === 'Checked In'
                            ? 'Already checked in.'
                            : 'Cannot be checked in from here.'}
                    </span>
                )}

                <Link
                    href={`/manage/appointments/${arrival.reference}`}
                    className="text-sm font-medium text-secondary underline underline-offset-4"
                >
                    Open appointment
                </Link>
            </div>
        </div>
    );
}

export default function CheckIn({ arrivals, today, timezone, lookup, found }: PageProps<CheckInProps>) {
    const [reference, setReference] = useState(lookup);

    const search = (event: FormEvent) => {
        event.preventDefault();
        router.get('/manage/check-in', { reference }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout title="Check in">
            <div className="grid gap-8 lg:grid-cols-[1fr_340px]">
                <div>
                    <div className="mb-5 flex flex-wrap items-baseline justify-between gap-3">
                        <h2 className="text-lg text-ink">Arriving today</h2>
                        <p className="text-sm text-ink-muted">
                            {today} &middot; {timezone}
                        </p>
                    </div>

                    {arrivals.length === 0 ? (
                        <p className="rounded-2xl border border-dashed border-line-strong bg-surface p-10 text-center text-sm text-ink-muted">
                            Nobody left to arrive today.
                        </p>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2">
                            {arrivals.map((arrival) => (
                                <ArrivalCard key={arrival.reference} arrival={arrival} />
                            ))}
                        </div>
                    )}
                </div>

                <aside className="space-y-4 lg:sticky lg:top-24 lg:self-start">
                    <div className="rounded-2xl border border-line bg-surface p-6">
                        <h2 className="text-base text-ink">Find by reference</h2>
                        <p className="mt-1 text-xs text-ink-muted">
                            Works without a phone or a code. Scanning is only a shortcut.
                        </p>

                        <form onSubmit={search} className="mt-4 space-y-3">
                            <Field
                                label="Reference"
                                name="reference"
                                placeholder="SB-XXXXXXXXXX"
                                value={reference}
                                onChange={(e) => setReference(e.target.value)}
                            />

                            <Button type="submit" variant="secondary" className="w-full">
                                Look up
                            </Button>
                        </form>

                        {lookup !== '' && !found && (
                            <p className="mt-4 rounded-lg border border-line bg-canvas-soft px-3 py-2.5 text-sm text-ink-muted">
                                No appointment found for that reference.
                            </p>
                        )}
                    </div>

                    {found && (
                        <div>
                            <h2 className="mb-2 text-sm font-medium text-ink">Found</h2>
                            <ArrivalCard arrival={found} highlight />
                        </div>
                    )}
                </aside>
            </div>
        </AppLayout>
    );
}
