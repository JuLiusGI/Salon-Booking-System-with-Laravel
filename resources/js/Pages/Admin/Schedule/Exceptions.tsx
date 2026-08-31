import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import ConfirmDelete from '@/Components/ConfirmDelete';
import Field from '@/Components/Field';
import { Select } from '@/Components/Form';
import type { PageProps, Paginated } from '@/types';

interface ExceptionRow {
    id: number;
    type: string;
    type_label: string;
    staff_name: string | null;
    starts_at: string;
    ends_at: string;
    override_opens_at: string | null;
    override_closes_at: string | null;
    reason: string | null;
    is_past: boolean;
}

interface TypeOption {
    value: string;
    label: string;
    salon_wide: boolean;
}

interface ExceptionsProps {
    exceptions: Paginated<ExceptionRow>;
    filters: { staff?: string; type?: string };
    staff: { value: number; label: string }[];
    types: TypeOption[];
    timezone: string;
}

export default function Exceptions({ exceptions, filters, staff, types, timezone }: PageProps<ExceptionsProps>) {
    const { data, setData, post, processing, errors, reset } = useForm({
        staff_id: '',
        type: 'break',
        starts_at: '',
        ends_at: '',
        override_opens_at: '',
        override_closes_at: '',
        reason: '',
    });

    const selectedType = types.find((type) => type.value === data.type);
    const isSalonWide = selectedType?.salon_wide ?? false;
    const isSpecialHours = data.type === 'special_hours';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/admin/schedule/exceptions', { onSuccess: () => reset() });
    };

    const filter = (overrides: Record<string, string | undefined>) => {
        router.get('/admin/schedule/exceptions', { ...filters, ...overrides }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout title="Schedule exceptions">
            <p className="mb-6 max-w-2xl text-sm text-ink-muted">
                One-off changes to the schedule: leave, days off, breaks, holidays, closures, and special opening
                hours. All times are {timezone}.
            </p>

            <div className="grid gap-8 lg:grid-cols-[380px_1fr]">
                <form onSubmit={submit} className="space-y-6 self-start rounded-2xl border border-line bg-surface p-6">
                    <h2 className="text-lg text-ink">Add an exception</h2>

                    <Select
                        label="Type"
                        name="type"
                        required
                        options={types.map((type) => ({ value: type.value, label: type.label }))}
                        value={data.type}
                        error={errors.type}
                        onChange={(e) => {
                            setData('type', e.target.value);
                            const next = types.find((type) => type.value === e.target.value);
                            if (next?.salon_wide) {
                                setData('staff_id', '');
                            }
                        }}
                    />

                    <Select
                        label="Staff member"
                        name="staff_id"
                        placeholder={isSalonWide ? 'Whole salon' : 'Choose a staff member'}
                        options={staff}
                        value={data.staff_id}
                        error={errors.staff_id}
                        disabled={isSalonWide}
                        hint={
                            isSalonWide
                                ? 'This type applies to the whole salon and affects everyone.'
                                : undefined
                        }
                        onChange={(e) => setData('staff_id', e.target.value)}
                    />

                    <Field
                        label="From"
                        name="starts_at"
                        type="datetime-local"
                        required
                        value={data.starts_at}
                        error={errors.starts_at}
                        onChange={(e) => setData('starts_at', e.target.value)}
                    />

                    <Field
                        label="To"
                        name="ends_at"
                        type="datetime-local"
                        required
                        value={data.ends_at}
                        error={errors.ends_at}
                        onChange={(e) => setData('ends_at', e.target.value)}
                    />

                    {isSpecialHours && (
                        <div className="space-y-4 rounded-xl border border-line bg-canvas-soft p-4">
                            <p className="text-xs text-ink-muted">
                                Special hours replace the salon&rsquo;s normal opening times for this period rather
                                than blocking time out.
                            </p>

                            <Field
                                label="Opens at"
                                name="override_opens_at"
                                type="time"
                                required
                                value={data.override_opens_at}
                                error={errors.override_opens_at}
                                onChange={(e) => setData('override_opens_at', e.target.value)}
                            />

                            <Field
                                label="Closes at"
                                name="override_closes_at"
                                type="time"
                                required
                                value={data.override_closes_at}
                                error={errors.override_closes_at}
                                onChange={(e) => setData('override_closes_at', e.target.value)}
                            />
                        </div>
                    )}

                    <Field
                        label="Reason"
                        name="reason"
                        hint="Optional. Shown to staff, never to customers."
                        value={data.reason}
                        error={errors.reason}
                        onChange={(e) => setData('reason', e.target.value)}
                    />

                    <Button type="submit" disabled={processing} className="w-full">
                        {processing ? 'Adding...' : 'Add exception'}
                    </Button>
                </form>

                <div>
                    <div className="mb-4 flex flex-wrap items-end gap-3">
                        <div className="w-48">
                            <Select
                                label="Filter by staff"
                                name="filter_staff"
                                placeholder="Everyone"
                                options={staff}
                                value={filters.staff ?? ''}
                                onChange={(e) => filter({ staff: e.target.value || undefined })}
                            />
                        </div>

                        <div className="w-48">
                            <Select
                                label="Filter by type"
                                name="filter_type"
                                placeholder="All types"
                                options={types.map((type) => ({ value: type.value, label: type.label }))}
                                value={filters.type ?? ''}
                                onChange={(e) => filter({ type: e.target.value || undefined })}
                            />
                        </div>
                    </div>

                    <div className="overflow-x-auto rounded-2xl border border-line bg-surface">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-line bg-canvas-soft text-xs tracking-wide text-ink-muted uppercase">
                                <tr>
                                    <th scope="col" className="px-5 py-3 font-medium">
                                        Type
                                    </th>
                                    <th scope="col" className="px-5 py-3 font-medium">
                                        Applies to
                                    </th>
                                    <th scope="col" className="px-5 py-3 font-medium">
                                        Period
                                    </th>
                                    <th scope="col" className="px-5 py-3 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {exceptions.data.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="px-5 py-12 text-center text-ink-muted">
                                            No exceptions recorded.
                                        </td>
                                    </tr>
                                )}

                                {exceptions.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4">
                                            <span className="font-medium text-ink">{row.type_label}</span>
                                            {row.is_past && (
                                                <span className="ml-2 text-xs text-ink-muted">(past)</span>
                                            )}
                                            {row.reason && (
                                                <p className="mt-0.5 text-xs text-ink-muted">{row.reason}</p>
                                            )}
                                        </td>
                                        <td className="px-5 py-4 text-ink-muted">
                                            {row.staff_name ?? 'Whole salon'}
                                        </td>
                                        <td className="px-5 py-4 text-ink-muted">
                                            {row.starts_at} &ndash; {row.ends_at}
                                            {row.override_opens_at && (
                                                <p className="mt-0.5 text-xs">
                                                    Open {row.override_opens_at}&ndash;{row.override_closes_at}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-5 py-4 text-right">
                                            <ConfirmDelete
                                                url={`/admin/schedule/exceptions/${row.id}`}
                                                subject={`this ${row.type_label.toLowerCase()}`}
                                                triggerLabel="Remove"
                                                consequence="The time it was blocking becomes bookable again. Existing appointments are not affected."
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <p className="mt-4 text-sm text-ink-muted">
                        Showing {exceptions.from ?? 0}&ndash;{exceptions.to ?? 0} of {exceptions.total}
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
