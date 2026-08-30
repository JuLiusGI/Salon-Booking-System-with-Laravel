import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button, { ButtonLink } from '@/Components/Button';
import ConfirmDelete from '@/Components/ConfirmDelete';
import Field from '@/Components/Field';
import { Select, StatusBadge } from '@/Components/Form';
import type { PageProps, Paginated } from '@/types';

interface AdminService {
    id: number;
    name: string;
    category: string | null;
    duration_minutes: number;
    price: string;
    is_active: boolean;
    display_order: number;
    staff_count: number;
    image_url: string | null;
}

interface ServicesIndexProps {
    services: Paginated<AdminService>;
    filters: { search?: string; category?: string; status?: string };
    categories: { value: number; label: string }[];
}

const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

export default function Index({ services, filters, categories }: PageProps<ServicesIndexProps>) {
    const [search, setSearch] = useState(filters.search ?? '');

    const apply = (overrides: Record<string, string | undefined> = {}) => {
        router.get(
            '/admin/services',
            { search, category: filters.category, status: filters.status, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply();
    };

    return (
        <AppLayout title="Services">
            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <p className="text-sm text-ink-muted">
                    Duration is the time blocked in the diary. Price and duration changes apply to new bookings only;
                    past appointments keep what they were booked at.
                </p>
                <ButtonLink href="/admin/services/create">New service</ButtonLink>
            </div>

            <form onSubmit={submit} className="mb-6 flex flex-wrap items-end gap-3">
                <div className="w-60">
                    <Field
                        label="Search"
                        name="search"
                        placeholder="Service name"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>

                <div className="w-52">
                    <Select
                        label="Category"
                        name="category"
                        placeholder="All categories"
                        options={categories}
                        value={filters.category ?? ''}
                        onChange={(e) => apply({ category: e.target.value || undefined })}
                    />
                </div>

                <div className="w-44">
                    <Select
                        label="Status"
                        name="status"
                        placeholder="All statuses"
                        options={[
                            { value: 'active', label: 'Active' },
                            { value: 'inactive', label: 'Hidden' },
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
                                Service
                            </th>
                            <th scope="col" className="px-5 py-3 font-medium">
                                Category
                            </th>
                            <th scope="col" className="px-5 py-3 font-medium">
                                Duration
                            </th>
                            <th scope="col" className="px-5 py-3 font-medium">
                                Price
                            </th>
                            <th scope="col" className="px-5 py-3 font-medium">
                                Staff
                            </th>
                            <th scope="col" className="px-5 py-3 font-medium">
                                Status
                            </th>
                            <th scope="col" className="px-5 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-line">
                        {services.data.length === 0 && (
                            <tr>
                                <td colSpan={7} className="px-5 py-12 text-center text-ink-muted">
                                    No services match these filters.
                                </td>
                            </tr>
                        )}

                        {services.data.map((service) => (
                            <tr key={service.id}>
                                <td className="px-5 py-4">
                                    <div className="flex items-center gap-3">
                                        <div className="h-11 w-11 shrink-0 overflow-hidden rounded-lg border border-line bg-canvas-soft">
                                            {service.image_url && (
                                                <img
                                                    src={service.image_url}
                                                    alt=""
                                                    className="h-full w-full object-cover"
                                                />
                                            )}
                                        </div>
                                        <span className="font-medium text-ink">{service.name}</span>
                                    </div>
                                </td>
                                <td className="px-5 py-4 text-ink-muted">{service.category ?? '—'}</td>
                                <td className="px-5 py-4 text-ink-muted">{service.duration_minutes} min</td>
                                <td className="px-5 py-4 text-ink">{peso.format(Number(service.price))}</td>
                                <td className="px-5 py-4">
                                    {service.staff_count === 0 ? (
                                        <span className="text-xs font-medium text-red-700">None assigned</span>
                                    ) : (
                                        <span className="text-ink-muted">{service.staff_count}</span>
                                    )}
                                </td>
                                <td className="px-5 py-4">
                                    <StatusBadge active={service.is_active} />
                                </td>
                                <td className="px-5 py-4">
                                    <div className="flex items-center justify-end gap-4">
                                        <Link
                                            href={`/admin/services/${service.id}/edit`}
                                            className="text-sm font-medium text-secondary underline underline-offset-4"
                                        >
                                            Edit
                                        </Link>
                                        <ConfirmDelete
                                            url={`/admin/services/${service.id}`}
                                            subject={service.name}
                                            consequence="The service is removed from the booking menu. Past appointments keep their record of it, including the price and duration they were booked at."
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <p className="mt-4 text-sm text-ink-muted">
                Showing {services.from ?? 0}&ndash;{services.to ?? 0} of {services.total}
            </p>
        </AppLayout>
    );
}
