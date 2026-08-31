import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button, { ButtonLink } from '@/Components/Button';
import Field from '@/Components/Field';
import { Select } from '@/Components/Form';
import StatusPill from '@/Components/StatusPill';
import type { AppointmentStatus, PageProps, Paginated } from '@/types';

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
}

interface Option {
    value: number | string;
    label: string;
}

interface IndexProps {
    appointments: Paginated<ManagedAppointment>;
    filters: { staff?: string; status?: string; service?: string; from?: string; to?: string; search?: string };
    staff: Option[];
    services: Option[];
    statuses: Option[];
    timezone: string;
}

const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

export default function Index({ appointments, filters, staff, services, statuses }: PageProps<IndexProps>) {
    const [search, setSearch] = useState(filters.search ?? '');

    const apply = (overrides: Record<string, string | undefined> = {}) => {
        router.get(
            '/manage/appointments',
            { ...filters, search, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply();
    };

    const clear = () => {
        setSearch('');
        router.get('/manage/appointments', {}, { replace: true });
    };

    return (
        <AppLayout title="Appointments">
            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <p className="text-sm text-ink-muted">
                    Everything in the diary you are allowed to see. Stylists see their own work only.
                </p>
                <div className="flex gap-3">
                    <ButtonLink href="/manage/calendar" variant="secondary">
                        Calendar
                    </ButtonLink>
                    <ButtonLink href="/manage/appointments/new">New appointment</ButtonLink>
                </div>
            </div>

            <form onSubmit={submit} className="mb-6 flex flex-wrap items-end gap-3">
                <div className="w-56">
                    <Field
                        label="Search"
                        name="search"
                        placeholder="Reference, name, or email"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>

                <div className="w-44">
                    <Select
                        label="Stylist"
                        name="staff"
                        placeholder="Anyone"
                        options={staff}
                        value={filters.staff ?? ''}
                        onChange={(e) => apply({ staff: e.target.value || undefined })}
                    />
                </div>

                <div className="w-44">
                    <Select
                        label="Status"
                        name="status"
                        placeholder="Any status"
                        options={statuses}
                        value={filters.status ?? ''}
                        onChange={(e) => apply({ status: e.target.value || undefined })}
                    />
                </div>

                <div className="w-52">
                    <Select
                        label="Service"
                        name="service"
                        placeholder="Any service"
                        options={services}
                        value={filters.service ?? ''}
                        onChange={(e) => apply({ service: e.target.value || undefined })}
                    />
                </div>

                <div className="w-40">
                    <Field
                        label="From"
                        name="from"
                        type="date"
                        value={filters.from ?? ''}
                        onChange={(e) => apply({ from: e.target.value || undefined })}
                    />
                </div>

                <div className="w-40">
                    <Field
                        label="To"
                        name="to"
                        type="date"
                        value={filters.to ?? ''}
                        onChange={(e) => apply({ to: e.target.value || undefined })}
                    />
                </div>

                <Button type="submit" variant="secondary">
                    Search
                </Button>

                <Button type="button" variant="ghost" onClick={clear}>
                    Clear
                </Button>
            </form>

            <div className="overflow-x-auto rounded-2xl border border-line bg-surface">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-line bg-canvas-soft text-xs tracking-wide text-ink-muted uppercase">
                        <tr>
                            <th scope="col" className="px-5 py-3 font-medium">
                                When
                            </th>
                            <th scope="col" className="px-5 py-3 font-medium">
                                Customer
                            </th>
                            <th scope="col" className="px-5 py-3 font-medium">
                                Stylist
                            </th>
                            <th scope="col" className="px-5 py-3 font-medium">
                                Services
                            </th>
                            <th scope="col" className="px-5 py-3 font-medium">
                                Status
                            </th>
                            <th scope="col" className="px-5 py-3 text-right font-medium">
                                Total
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-line">
                        {appointments.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-5 py-12 text-center text-ink-muted">
                                    No appointments match these filters.
                                </td>
                            </tr>
                        )}

                        {appointments.data.map((appointment) => (
                            <tr key={appointment.reference} className={appointment.is_past ? 'opacity-70' : undefined}>
                                <td className="px-5 py-4">
                                    <Link
                                        href={`/manage/appointments/${appointment.reference}`}
                                        className="font-medium text-ink underline-offset-4 hover:underline"
                                    >
                                        {appointment.date}
                                    </Link>
                                    <p className="mt-0.5 text-xs text-ink-muted">{appointment.time}</p>
                                </td>
                                <td className="px-5 py-4 text-ink">{appointment.customer_name}</td>
                                <td className="px-5 py-4 text-ink-muted">{appointment.staff_name}</td>
                                <td className="px-5 py-4 text-ink-muted">{appointment.services.join(', ')}</td>
                                <td className="px-5 py-4">
                                    <StatusPill status={appointment.status} label={appointment.status_label} />
                                </td>
                                <td className="px-5 py-4 text-right text-ink">
                                    {peso.format(Number(appointment.total_price))}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <p className="mt-4 text-sm text-ink-muted">
                Showing {appointments.from ?? 0}&ndash;{appointments.to ?? 0} of {appointments.total}
            </p>
        </AppLayout>
    );
}
