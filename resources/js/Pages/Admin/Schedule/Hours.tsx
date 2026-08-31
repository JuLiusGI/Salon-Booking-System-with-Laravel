import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import { Checkbox } from '@/Components/Form';
import type { PageProps } from '@/types';

const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const ORDER = [1, 2, 3, 4, 5, 6, 0];

interface DayRow {
    day_of_week: number;
    is_closed: boolean;
    opens_at: string;
    closes_at: string;
}

export default function Hours({ days, timezone }: PageProps<{ days: DayRow[]; timezone: string }>) {
    const { data, setData, put, processing, errors } = useForm<{ days: DayRow[] }>({ days });

    const update = (dayOfWeek: number, changes: Partial<DayRow>) => {
        setData(
            'days',
            data.days.map((row) => (row.day_of_week === dayOfWeek ? { ...row, ...changes } : row)),
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put('/admin/schedule/hours');
    };

    const rowFor = (dayOfWeek: number) => data.days.find((row) => row.day_of_week === dayOfWeek)!;
    const indexFor = (dayOfWeek: number) => data.days.findIndex((row) => row.day_of_week === dayOfWeek);

    return (
        <AppLayout title="Opening hours">
            <p className="mb-6 max-w-2xl text-sm text-ink-muted">
                These are the hours the salon trades, in {timezone}. Availability never falls outside them, whatever a
                stylist&rsquo;s own working hours say. For a one-off change, add special hours on the exceptions page
                instead of editing the weekly pattern.
            </p>

            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <div className="divide-y divide-line rounded-2xl border border-line bg-surface">
                    {ORDER.map((dayOfWeek) => {
                        const row = rowFor(dayOfWeek);
                        const index = indexFor(dayOfWeek);
                        const opensError = errors[`days.${index}.opens_at` as keyof typeof errors];
                        const closesError = errors[`days.${index}.closes_at` as keyof typeof errors];

                        return (
                            <div key={dayOfWeek} className="flex flex-wrap items-center gap-5 px-6 py-5">
                                <p className="w-28 font-medium text-ink">{DAY_NAMES[dayOfWeek]}</p>

                                <div className="w-40">
                                    <Checkbox
                                        name={`closed-${dayOfWeek}`}
                                        label="Closed"
                                        checked={row.is_closed}
                                        onChange={(checked) => update(dayOfWeek, { is_closed: checked })}
                                    />
                                </div>

                                {!row.is_closed && (
                                    <div className="flex flex-wrap items-center gap-3">
                                        <label className="sr-only" htmlFor={`opens-${dayOfWeek}`}>
                                            {DAY_NAMES[dayOfWeek]} opening time
                                        </label>
                                        <input
                                            id={`opens-${dayOfWeek}`}
                                            type="time"
                                            value={row.opens_at}
                                            onChange={(e) => update(dayOfWeek, { opens_at: e.target.value })}
                                            className="rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink"
                                        />

                                        <span aria-hidden="true" className="text-ink-muted">
                                            to
                                        </span>

                                        <label className="sr-only" htmlFor={`closes-${dayOfWeek}`}>
                                            {DAY_NAMES[dayOfWeek]} closing time
                                        </label>
                                        <input
                                            id={`closes-${dayOfWeek}`}
                                            type="time"
                                            value={row.closes_at}
                                            onChange={(e) => update(dayOfWeek, { closes_at: e.target.value })}
                                            className="rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink"
                                        />
                                    </div>
                                )}

                                {(opensError || closesError) && (
                                    <p role="alert" className="w-full text-xs font-medium text-red-700">
                                        {opensError ?? closesError}
                                    </p>
                                )}
                            </div>
                        );
                    })}
                </div>

                <Button type="submit" disabled={processing}>
                    {processing ? 'Saving...' : 'Save opening hours'}
                </Button>
            </form>
        </AppLayout>
    );
}
