import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button, { ButtonLink } from '@/Components/Button';
import Field from '@/Components/Field';
import { Select, Textarea } from '@/Components/Form';
import StatusPill from '@/Components/StatusPill';
import type { AppointmentStatus, PageProps } from '@/types';

interface CustomerRecord {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    joined_on: string;
    birthday: string | null;
    gender: string | null;
    address: string | null;
    allergies: string | null;
    preferences: string | null;
    service_notes: string | null;
    notes: string | null;
}

interface HistoryRow {
    reference: string;
    status: AppointmentStatus;
    status_label: string;
    date: string;
    time: string;
    staff_name: string;
    services: string[];
    total_price: string;
    is_upcoming: boolean;
}

interface ShowProps {
    customer: CustomerRecord;
    history: HistoryRow[];
    stats: {
        visits: number;
        completed_value: string;
        cancelled: number;
        no_shows: number;
        last_visit: string | null;
    };
    can: { manage: boolean };
    genders: { value: string; label: string }[];
}

const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

export default function Show({ customer, history, stats, can, genders }: PageProps<ShowProps>) {
    const form = useForm({
        name: customer.name,
        email: customer.email,
        phone: customer.phone ?? '',
        birthday: customer.birthday ?? '',
        gender: customer.gender ?? '',
        address: customer.address ?? '',
        allergies: customer.allergies ?? '',
        preferences: customer.preferences ?? '',
        service_notes: customer.service_notes ?? '',
        notes: customer.notes ?? '',
    });

    const save = (event: FormEvent) => {
        event.preventDefault();
        form.patch(`/manage/customers/${customer.id}`, { preserveScroll: true });
    };

    const upcoming = history.filter((row) => row.is_upcoming);
    const past = history.filter((row) => !row.is_upcoming);

    return (
        <AppLayout title={customer.name}>
            <div className="grid gap-8 lg:grid-cols-[1fr_320px]">
                <div className="space-y-6">
                    {customer.allergies && (
                        <div role="note" className="rounded-2xl border border-red-200 bg-red-50 p-5">
                            <h2 className="text-xs font-semibold tracking-wide text-red-900 uppercase">
                                Allergies and sensitivities
                            </h2>
                            <p className="mt-1.5 text-sm text-red-900">{customer.allergies}</p>
                        </div>
                    )}

                    {can.manage ? (
                        <form onSubmit={save} className="space-y-6 rounded-2xl border border-line bg-surface p-7">
                            <h2 className="text-lg text-ink">Record</h2>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <Field
                                    label="Name"
                                    name="name"
                                    required
                                    value={form.data.name}
                                    error={form.errors.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                />
                                <Field
                                    label="Email"
                                    name="email"
                                    type="email"
                                    required
                                    value={form.data.email}
                                    error={form.errors.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                />
                                <Field
                                    label="Phone"
                                    name="phone"
                                    type="tel"
                                    value={form.data.phone}
                                    error={form.errors.phone}
                                    onChange={(e) => form.setData('phone', e.target.value)}
                                />
                                <Field
                                    label="Birthday"
                                    name="birthday"
                                    type="date"
                                    value={form.data.birthday}
                                    error={form.errors.birthday}
                                    onChange={(e) => form.setData('birthday', e.target.value)}
                                />
                                <Select
                                    label="Gender"
                                    name="gender"
                                    placeholder="Not given"
                                    options={genders}
                                    value={form.data.gender}
                                    error={form.errors.gender}
                                    onChange={(e) => form.setData('gender', e.target.value)}
                                />
                                <Field
                                    label="Address"
                                    name="address"
                                    value={form.data.address}
                                    error={form.errors.address}
                                    onChange={(e) => form.setData('address', e.target.value)}
                                />
                            </div>

                            <Textarea
                                label="Allergies and sensitivities"
                                name="allergies"
                                rows={2}
                                hint="Shown to the stylist before every appointment."
                                value={form.data.allergies}
                                error={form.errors.allergies}
                                onChange={(e) => form.setData('allergies', e.target.value)}
                            />

                            <Textarea
                                label="Preferences"
                                name="preferences"
                                rows={2}
                                hint="How they like things done."
                                value={form.data.preferences}
                                error={form.errors.preferences}
                                onChange={(e) => form.setData('preferences', e.target.value)}
                            />

                            <Textarea
                                label="Service notes"
                                name="service_notes"
                                rows={3}
                                hint="Formulas, timings, and anything worth repeating next visit."
                                value={form.data.service_notes}
                                error={form.errors.service_notes}
                                onChange={(e) => form.setData('service_notes', e.target.value)}
                            />

                            <Textarea
                                label="Desk notes"
                                name="notes"
                                rows={2}
                                hint="Front desk only. Not shown to stylists."
                                value={form.data.notes}
                                error={form.errors.notes}
                                onChange={(e) => form.setData('notes', e.target.value)}
                            />

                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Saving...' : 'Save record'}
                            </Button>
                        </form>
                    ) : (
                        <section className="space-y-5 rounded-2xl border border-line bg-surface p-7">
                            <h2 className="text-lg text-ink">Record</h2>

                            <dl className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <dt className="text-xs tracking-wide text-ink-muted uppercase">Email</dt>
                                    <dd className="mt-1 text-ink">{customer.email}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs tracking-wide text-ink-muted uppercase">Phone</dt>
                                    <dd className="mt-1 text-ink">{customer.phone ?? '—'}</dd>
                                </div>
                            </dl>

                            {customer.preferences && (
                                <div>
                                    <h3 className="text-xs tracking-wide text-ink-muted uppercase">Preferences</h3>
                                    <p className="mt-1 text-sm text-ink">{customer.preferences}</p>
                                </div>
                            )}

                            {customer.service_notes && (
                                <div>
                                    <h3 className="text-xs tracking-wide text-ink-muted uppercase">Service notes</h3>
                                    <p className="mt-1 text-sm text-ink">{customer.service_notes}</p>
                                </div>
                            )}

                            <p className="border-t border-line pt-4 text-xs text-ink-muted">
                                Editing customer records is a front desk task.
                            </p>
                        </section>
                    )}

                    <section className="rounded-2xl border border-line bg-surface p-7">
                        <h2 className="text-lg text-ink">History</h2>

                        {history.length === 0 ? (
                            <p className="mt-4 rounded-xl border border-dashed border-line-strong p-6 text-center text-sm text-ink-muted">
                                No appointments on record.
                            </p>
                        ) : (
                            <>
                                {upcoming.length > 0 && (
                                    <>
                                        <h3 className="mt-5 text-xs tracking-wide text-ink-muted uppercase">
                                            Upcoming
                                        </h3>
                                        <ul className="mt-2 divide-y divide-line">
                                            {upcoming.map((row) => (
                                                <HistoryItem key={row.reference} row={row} />
                                            ))}
                                        </ul>
                                    </>
                                )}

                                {past.length > 0 && (
                                    <>
                                        <h3 className="mt-6 text-xs tracking-wide text-ink-muted uppercase">
                                            Past
                                        </h3>
                                        <ul className="mt-2 divide-y divide-line">
                                            {past.map((row) => (
                                                <HistoryItem key={row.reference} row={row} />
                                            ))}
                                        </ul>
                                    </>
                                )}
                            </>
                        )}
                    </section>
                </div>

                <aside className="space-y-4 lg:sticky lg:top-24 lg:self-start">
                    <div className="rounded-2xl border border-line bg-surface p-6">
                        <h2 className="text-base text-ink">At a glance</h2>

                        <dl className="mt-4 space-y-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-ink-muted">Completed visits</dt>
                                <dd className="text-ink">{stats.visits}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-ink-muted">Last visit</dt>
                                <dd className="text-ink">{stats.last_visit ?? '—'}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-ink-muted">Cancelled</dt>
                                <dd className="text-ink">{stats.cancelled}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-ink-muted">No shows</dt>
                                <dd className="text-ink">{stats.no_shows}</dd>
                            </div>
                            <div className="flex justify-between border-t border-line pt-3">
                                <dt className="text-ink-muted">Completed work</dt>
                                <dd className="text-ink">{peso.format(Number(stats.completed_value))}</dd>
                            </div>
                        </dl>

                        <p className="mt-4 border-t border-line pt-4 text-xs text-ink-muted">
                            Value of completed appointments. The system records no payments, so this is not revenue
                            received.
                        </p>
                    </div>

                    <div className="rounded-2xl border border-line bg-surface p-6 text-sm">
                        <p className="text-ink-muted">Customer since</p>
                        <p className="text-ink">{customer.joined_on}</p>
                    </div>

                    <ButtonLink href="/manage/customers" variant="secondary" className="w-full">
                        All customers
                    </ButtonLink>
                </aside>
            </div>
        </AppLayout>
    );
}

function HistoryItem({ row }: { row: HistoryRow }) {
    return (
        <li className="flex flex-wrap items-center gap-3 py-3 text-sm">
            <Link
                href={`/manage/appointments/${row.reference}`}
                className="font-medium text-ink underline-offset-4 hover:underline"
            >
                {row.date}
            </Link>
            <span className="text-ink-muted">{row.time}</span>
            <span className="text-ink-muted">{row.services.join(', ')}</span>
            <span className="text-ink-muted">{row.staff_name}</span>
            <span className="ml-auto flex items-center gap-3">
                <span className="text-ink">{peso.format(Number(row.total_price))}</span>
                <StatusPill status={row.status} label={row.status_label} />
            </span>
        </li>
    );
}
