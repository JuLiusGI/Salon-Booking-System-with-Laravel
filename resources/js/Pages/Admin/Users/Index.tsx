import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Field from '@/Components/Field';
import type { Paginated, PageProps, SharedProps, UserRole } from '@/types';

interface DirectoryUser {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    is_active: boolean;
}

interface RoleOption {
    value: UserRole;
    label: string;
}

interface UsersIndexProps {
    users: Paginated<DirectoryUser>;
    filters: { role?: string; search?: string };
    roles: RoleOption[];
}

const STATUS_BASE =
    'rounded-full px-2.5 py-1 text-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-60 ';
const STATUS_ACTIVE = 'bg-green-100 text-green-800 hover:bg-green-200';
const STATUS_INACTIVE = 'bg-neutral-200 text-neutral-700 hover:bg-neutral-300';

export default function Index({ auth, users, filters, roles }: PageProps<UsersIndexProps>) {
    const [search, setSearch] = useState(filters.search ?? '');
    const { errors } = usePage<SharedProps>().props;

    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        router.get('/admin/users', { search, role: filters.role }, { preserveState: true, replace: true });
    };

    const changeRole = (userId: number, role: UserRole) => {
        router.patch('/admin/users/' + userId + '/role', { role }, { preserveScroll: true });
    };

    const toggleActive = (userId: number, isActive: boolean) => {
        router.patch('/admin/users/' + userId + '/status', { is_active: !isActive }, { preserveScroll: true });
    };

    return (
        <AppLayout title="Users">
            {errors.role && (
                <div
                    role="alert"
                    className="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                >
                    {errors.role}
                </div>
            )}

            <form onSubmit={applyFilters} className="mb-6 flex flex-wrap items-end gap-3">
                <div className="w-64">
                    <Field
                        label="Search"
                        name="search"
                        placeholder="Name or email"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>

                <div className="space-y-1.5">
                    <label htmlFor="role-filter" className="block text-sm font-medium text-ink">
                        Role
                    </label>
                    <select
                        id="role-filter"
                        value={filters.role ?? ''}
                        onChange={(e) =>
                            router.get(
                                '/admin/users',
                                { search, role: e.target.value || undefined },
                                { preserveState: true, replace: true },
                            )
                        }
                        className="rounded-md border border-line-strong px-3 py-2 text-sm shadow-sm"
                    >
                        <option value="">All roles</option>
                        {roles.map((role) => (
                            <option key={role.value} value={role.value}>
                                {role.label}
                            </option>
                        ))}
                    </select>
                </div>

                <Button type="submit" variant="secondary">
                    Search
                </Button>
            </form>

            <div className="overflow-x-auto rounded-2xl border border-line bg-surface">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-line bg-canvas text-xs tracking-wide text-ink-muted uppercase">
                        <tr>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Name
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Email
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Role
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-line">
                        {users.data.length === 0 && (
                            <tr>
                                <td colSpan={4} className="px-4 py-10 text-center text-ink-muted">
                                    No users match these filters.
                                </td>
                            </tr>
                        )}

                        {users.data.map((user) => {
                            const isSelf = user.id === auth.user?.id;
                            const statusClass = STATUS_BASE + (user.is_active ? STATUS_ACTIVE : STATUS_INACTIVE);

                            return (
                                <tr key={user.id}>
                                    <td className="px-4 py-3 font-medium text-ink">
                                        {user.name}
                                        {isSelf && <span className="ml-2 text-xs text-ink-muted">(you)</span>}
                                    </td>
                                    <td className="px-4 py-3 text-ink-muted">{user.email}</td>
                                    <td className="px-4 py-3">
                                        <select
                                            aria-label={'Role for ' + user.name}
                                            value={user.role}
                                            disabled={isSelf}
                                            onChange={(e) => changeRole(user.id, e.target.value as UserRole)}
                                            className="rounded-md border border-line-strong px-2 py-1 text-sm disabled:cursor-not-allowed disabled:bg-canvas"
                                        >
                                            {roles.map((role) => (
                                                <option key={role.value} value={role.value}>
                                                    {role.label}
                                                </option>
                                            ))}
                                        </select>
                                    </td>
                                    <td className="px-4 py-3">
                                        <button
                                            type="button"
                                            onClick={() => toggleActive(user.id, user.is_active)}
                                            disabled={isSelf}
                                            className={statusClass}
                                        >
                                            {user.is_active ? 'Active' : 'Inactive'}
                                        </button>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <p className="mt-4 text-sm text-ink-muted">
                Showing {users.from ?? 0}&ndash;{users.to ?? 0} of {users.total}
            </p>
        </AppLayout>
    );
}
