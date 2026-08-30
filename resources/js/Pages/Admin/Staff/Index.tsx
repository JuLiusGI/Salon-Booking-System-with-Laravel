import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button, { ButtonLink } from '@/Components/Button';
import ConfirmDelete from '@/Components/ConfirmDelete';
import Field from '@/Components/Field';
import { Select, StatusBadge } from '@/Components/Form';
import type { PageProps, Paginated, UserRole } from '@/types';

interface AdminStaff {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    title: string | null;
    is_active: boolean;
    is_bookable: boolean;
    display_order: number;
    services_count: number;
    photo_url: string | null;
}

interface StaffIndexProps {
    staff: Paginated<AdminStaff>;
    filters: { search?: string; status?: string };
}

export default function Index({ staff, filters }: PageProps<StaffIndexProps>) {
    const [search, setSearch] = useState(filters.search ?? '');

    const apply = (overrides: Record<string, string | undefined> = {}) => {
        router.get(
            '/admin/staff',
            { search, status: filters.status, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply();
    };

    return (
        <AppLayout title="Team">
            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <p className="text-sm text-ink-muted">
                    Adding a team member creates their login as well as their salon profile. Only bookable stylists
                    appear on the public team page and in the booking flow.
                </p>
                <ButtonLink href="/admin/staff/create">Add team member</ButtonLink>
            </div>

            <form onSubmit={submit} className="mb-6 flex flex-wrap items-end gap-3">
                <div className="w-64">
                    <Field
                        label="Search"
                        name="search"
                        placeholder="Name or email"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>

                <div className="w-44">
                    <Select
                        label="Status"
                        name="status"
                        placeholder="All statuses"
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
                                Role
                            </th>
                            <th scope="col" className="px-5 py-3 font-medium">
                                Services
                            </th>
                            <th scope="col" className="px-5 py-3 font-medium">
                                Bookable
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
                        {staff.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-5 py-12 text-center text-ink-muted">
                                    No team members match these filters.
                                </td>
                            </tr>
                        )}

                        {staff.data.map((member) => (
                            <tr key={member.id}>
                                <td className="px-5 py-4">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full border border-line bg-canvas-soft font-display text-ink">
                                            {member.photo_url ? (
                                                <img
                                                    src={member.photo_url}
                                                    alt=""
                                                    className="h-full w-full object-cover"
                                                />
                                            ) : (
                                                member.name.charAt(0)
                                            )}
                                        </div>
                                        <div>
                                            <p className="font-medium text-ink">{member.name}</p>
                                            <p className="text-xs text-ink-muted">{member.title ?? member.email}</p>
                                        </div>
                                    </div>
                                </td>
                                <td className="px-5 py-4 text-ink-muted capitalize">{member.role}</td>
                                <td className="px-5 py-4">
                                    {member.is_bookable && member.services_count === 0 ? (
                                        <span className="text-xs font-medium text-red-700">None assigned</span>
                                    ) : (
                                        <span className="text-ink-muted">{member.services_count}</span>
                                    )}
                                </td>
                                <td className="px-5 py-4">
                                    <StatusBadge
                                        active={member.is_bookable}
                                        activeLabel="Bookable"
                                        inactiveLabel="Not bookable"
                                    />
                                </td>
                                <td className="px-5 py-4">
                                    <StatusBadge
                                        active={member.is_active}
                                        activeLabel="Active"
                                        inactiveLabel="Inactive"
                                    />
                                </td>
                                <td className="px-5 py-4">
                                    <div className="flex items-center justify-end gap-4">
                                        <Link
                                            href={`/admin/staff/${member.id}/edit`}
                                            className="text-sm font-medium text-secondary underline underline-offset-4"
                                        >
                                            Edit
                                        </Link>
                                        <ConfirmDelete
                                            url={`/admin/staff/${member.id}`}
                                            subject={member.name}
                                            triggerLabel="Remove"
                                            confirmLabel="Remove from team"
                                            consequence="They are removed from the team and the booking flow, and their login is disabled. Their past appointments and schedule history are kept."
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <p className="mt-4 text-sm text-ink-muted">
                Showing {staff.from ?? 0}&ndash;{staff.to ?? 0} of {staff.total}
            </p>
        </AppLayout>
    );
}
