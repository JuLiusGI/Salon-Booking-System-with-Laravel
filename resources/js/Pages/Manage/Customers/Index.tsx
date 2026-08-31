import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button, { ButtonLink } from '@/Components/Button';
import Field from '@/Components/Field';
import { Select, StatusBadge } from '@/Components/Form';
import type { PageProps, Paginated } from '@/types';

interface CustomerRow {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    visits_count: number;
    upcoming_count: number;
    has_allergies: boolean;
}

interface IndexProps {
    customers: Paginated<CustomerRow>;
    filters: { search?: string; status?: string };
}

export default function Index({ customers, filters }: PageProps<IndexProps>) {
    const [search, setSearch] = useState(filters.search ?? '');

    const apply = (overrides: Record<string, string | undefined> = {}) => {
        router.get('/manage/customers', { ...filters, search, ...overrides }, {
            preserveState: true,
            replace: true,
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply();
    };

    return (
        <AppLayout title="Customers">
            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <p className="text-sm text-ink-muted">
                    Everyone with an account. Records here hold health information, so every edit is logged.
                </p>
                <ButtonLink href="/manage/customers/new">Add customer</ButtonLink>
            </div>

            <form onSubmit={submit} className="mb-6 flex flex-wrap items-end gap-3">
                <div className="w-72">
                    <Field
                        label="Search"
                        name="search"
                        placeholder="Name, email, or phone"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>

                <div className="w-44">
                    <Select
                        label="Status"
                        name="status"
                        placeholder="All"
                        options={[
                            { value: 'active', label: 'Active' },
                            { value: 'inactive', label: 'Inactive' },
                        ]}
                        value={filters.status ?? ''}
                        onChange={(e) => apply({ status: e.target.value || undefined })}
                    />
                </div>

                <Button type="submit" variant="secondary">
                    Search
                </Button>
            </form>

            <div className="overflow-x-auto rounded-2xl border border-line bg-surface">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-line bg-canvas-soft text-xs tracking-wide text-ink-muted uppercase">
                        <tr>
                            <th scope="col" className="px-5 py-3 font-medium">
                                Name
                            </th>
                            <th scope="col" className="px-5 py-3 font-medium">
                                Contact
                            </th>
                            <th scope="col" className="px-5 py-3 font-medium">
                                Visits
                            </th>
                            <th scope="col" className="px-5 py-3 font-medium">
                                Upcoming
                            </th>
                            <th scope="col" className="px-5 py-3 font-medium">
                                Account
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-line">
                        {customers.data.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-5 py-12 text-center text-ink-muted">
                                    No customers match these filters.
                                </td>
                            </tr>
                        )}

                        {customers.data.map((customer) => (
                            <tr key={customer.id}>
                                <td className="px-5 py-4">
                                    <Link
                                        href={`/manage/customers/${customer.id}`}
                                        className="font-medium text-ink underline-offset-4 hover:underline"
                                    >
                                        {customer.name}
                                    </Link>
                                    {customer.has_allergies && (
                                        <span className="ml-2 rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-[11px] font-medium text-red-900">
                                            Allergy noted
                                        </span>
                                    )}
                                </td>
                                <td className="px-5 py-4 text-ink-muted">
                                    <span className="block">{customer.email}</span>
                                    {customer.phone && <span className="block text-xs">{customer.phone}</span>}
                                </td>
                                <td className="px-5 py-4 text-ink-muted">{customer.visits_count}</td>
                                <td className="px-5 py-4 text-ink-muted">{customer.upcoming_count}</td>
                                <td className="px-5 py-4">
                                    <StatusBadge
                                        active={customer.is_active}
                                        activeLabel="Active"
                                        inactiveLabel="Inactive"
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <p className="mt-4 text-sm text-ink-muted">
                Showing {customers.from ?? 0}&ndash;{customers.to ?? 0} of {customers.total}
            </p>
        </AppLayout>
    );
}
