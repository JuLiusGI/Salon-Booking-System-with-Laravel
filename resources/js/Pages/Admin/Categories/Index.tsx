import { Link, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ButtonLink } from '@/Components/Button';
import ConfirmDelete from '@/Components/ConfirmDelete';
import { StatusBadge } from '@/Components/Form';
import type { PageProps, SharedProps } from '@/types';

interface AdminCategory {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    display_order: number;
    services_count: number;
    image_url: string | null;
}

export default function Index({ categories }: PageProps<{ categories: AdminCategory[] }>) {
    const { errors } = usePage<SharedProps>().props;

    return (
        <AppLayout title="Service categories">
            {errors.category && (
                <div
                    role="alert"
                    className="mb-6 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900"
                >
                    {errors.category}
                </div>
            )}

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <p className="text-sm text-ink-muted">
                    Categories group the service menu on the public site. Only active categories with at least one
                    active service are shown to customers.
                </p>
                <ButtonLink href="/admin/categories/create">New category</ButtonLink>
            </div>

            {categories.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-line-strong bg-surface p-12 text-center">
                    <h2 className="text-lg text-ink">No categories yet</h2>
                    <p className="mx-auto mt-2 max-w-sm text-sm text-ink-muted">
                        Create your first category, such as Hair or Nails, then add services to it.
                    </p>
                    <div className="mt-6">
                        <ButtonLink href="/admin/categories/create">New category</ButtonLink>
                    </div>
                </div>
            ) : (
                <div className="overflow-x-auto rounded-2xl border border-line bg-surface">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-line bg-canvas-soft text-xs tracking-wide text-ink-muted uppercase">
                            <tr>
                                <th scope="col" className="px-5 py-3 font-medium">
                                    Category
                                </th>
                                <th scope="col" className="px-5 py-3 font-medium">
                                    Services
                                </th>
                                <th scope="col" className="px-5 py-3 font-medium">
                                    Order
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
                            {categories.map((category) => (
                                <tr key={category.id}>
                                    <td className="px-5 py-4">
                                        <div className="flex items-center gap-3">
                                            <div className="h-11 w-11 shrink-0 overflow-hidden rounded-lg border border-line bg-canvas-soft">
                                                {category.image_url && (
                                                    <img
                                                        src={category.image_url}
                                                        alt=""
                                                        className="h-full w-full object-cover"
                                                    />
                                                )}
                                            </div>
                                            <div>
                                                <p className="font-medium text-ink">{category.name}</p>
                                                {category.description && (
                                                    <p className="mt-0.5 max-w-md truncate text-xs text-ink-muted">
                                                        {category.description}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-5 py-4 text-ink-muted">{category.services_count}</td>
                                    <td className="px-5 py-4 text-ink-muted">{category.display_order}</td>
                                    <td className="px-5 py-4">
                                        <StatusBadge active={category.is_active} />
                                    </td>
                                    <td className="px-5 py-4">
                                        <div className="flex items-center justify-end gap-4">
                                            <Link
                                                href={`/admin/categories/${category.id}/edit`}
                                                className="text-sm font-medium text-secondary underline underline-offset-4"
                                            >
                                                Edit
                                            </Link>
                                            <ConfirmDelete
                                                url={`/admin/categories/${category.id}`}
                                                subject={category.name}
                                                consequence={
                                                    category.services_count > 0
                                                        ? `This category still holds ${category.services_count} service${
                                                              category.services_count === 1 ? '' : 's'
                                                          }. You will need to move or delete them first.`
                                                        : 'The category will be removed from the public service menu. This can be undone by an administrator with database access.'
                                                }
                                            />
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AppLayout>
    );
}
