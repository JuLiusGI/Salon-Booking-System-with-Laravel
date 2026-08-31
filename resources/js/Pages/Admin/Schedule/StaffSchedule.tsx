import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import { Checkbox, Select } from '@/Components/Form';
import type { PageProps } from '@/types';

const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const DAY_OPTIONS = [1, 2, 3, 4, 5, 6, 0].map((value) => ({ value, label: DAY_NAMES[value] }));

interface Block {
    day_of_week: number;
    starts_at: string;
    ends_at: string;
    is_active: boolean;
}

interface Member {
    id: number;
    name: string;
    is_active: boolean;
    is_bookable: boolean;
}

export default function StaffSchedule({
    member,
    blocks,
    timezone,
}: PageProps<{ member: Member; blocks: Block[]; timezone: string }>) {
    const { data, setData, put, processing, errors } = useForm<{ blocks: Block[] }>({ blocks });

    const update = (index: number, changes: Partial<Block>) => {
        setData(
            'blocks',
            data.blocks.map((block, i) => (i === index ? { ...block, ...changes } : block)),
        );
    };

    const addBlock = () => {
        setData('blocks', [
            ...data.blocks,
            { day_of_week: 1, starts_at: '09:00', ends_at: '17:00', is_active: true },
        ]);
    };

    const removeBlock = (index: number) => {
        setData(
            'blocks',
            data.blocks.filter((_, i) => i !== index),
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put(`/admin/staff/${member.id}/schedule`);
    };

    return (
        <AppLayout title={`Working hours: ${member.name}`}>
            <p className="mb-6 max-w-2xl text-sm text-ink-muted">
                Recurring weekly shifts in {timezone}. Add more than one shift on a day for a split shift. Availability
                is the overlap of these hours and the salon&rsquo;s opening hours, so a shift outside opening hours has
                no effect.
            </p>

            {!member.is_bookable && (
                <div
                    role="status"
                    className="mb-6 max-w-2xl rounded-lg border border-line-strong bg-canvas-soft px-4 py-3 text-sm text-ink"
                >
                    {member.name} is not bookable, so these hours will not produce customer-facing availability. They
                    still describe when they are at work.
                </div>
            )}

            <form onSubmit={submit} className="max-w-3xl space-y-6">
                {data.blocks.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-line-strong bg-surface p-10 text-center">
                        <h2 className="text-lg text-ink">No shifts yet</h2>
                        <p className="mx-auto mt-2 max-w-sm text-sm text-ink-muted">
                            Without any shifts, {member.name} has no availability at all.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {data.blocks.map((block, index) => {
                            const startError = errors[`blocks.${index}.starts_at` as keyof typeof errors];
                            const endError = errors[`blocks.${index}.ends_at` as keyof typeof errors];

                            return (
                                <div
                                    key={index}
                                    className="flex flex-wrap items-end gap-4 rounded-2xl border border-line bg-surface p-5"
                                >
                                    <div className="w-40">
                                        <Select
                                            label="Day"
                                            name={`day-${index}`}
                                            options={DAY_OPTIONS}
                                            value={block.day_of_week}
                                            onChange={(e) => update(index, { day_of_week: Number(e.target.value) })}
                                        />
                                    </div>

                                    <div className="space-y-1.5">
                                        <label
                                            htmlFor={`start-${index}`}
                                            className="block text-sm font-medium text-ink"
                                        >
                                            From
                                        </label>
                                        <input
                                            id={`start-${index}`}
                                            type="time"
                                            value={block.starts_at}
                                            onChange={(e) => update(index, { starts_at: e.target.value })}
                                            className="rounded-lg border border-line-strong bg-surface px-3 py-2.5 text-sm text-ink"
                                        />
                                    </div>

                                    <div className="space-y-1.5">
                                        <label htmlFor={`end-${index}`} className="block text-sm font-medium text-ink">
                                            To
                                        </label>
                                        <input
                                            id={`end-${index}`}
                                            type="time"
                                            value={block.ends_at}
                                            onChange={(e) => update(index, { ends_at: e.target.value })}
                                            className="rounded-lg border border-line-strong bg-surface px-3 py-2.5 text-sm text-ink"
                                        />
                                    </div>

                                    <div className="pb-2">
                                        <Checkbox
                                            name={`active-${index}`}
                                            label="In use"
                                            checked={block.is_active}
                                            onChange={(checked) => update(index, { is_active: checked })}
                                        />
                                    </div>

                                    <button
                                        type="button"
                                        onClick={() => removeBlock(index)}
                                        className="pb-2.5 text-sm font-medium text-red-700 underline underline-offset-4"
                                    >
                                        Remove
                                    </button>

                                    {(startError || endError) && (
                                        <p role="alert" className="w-full text-xs font-medium text-red-700">
                                            {startError ?? endError}
                                        </p>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-3">
                    <Button type="button" variant="secondary" onClick={addBlock}>
                        Add a shift
                    </Button>

                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving...' : 'Save working hours'}
                    </Button>

                    <Button type="button" variant="ghost" onClick={() => router.get('/admin/staff')}>
                        Back to team
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
