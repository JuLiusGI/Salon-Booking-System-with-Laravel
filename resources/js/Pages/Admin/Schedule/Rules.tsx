import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Field from '@/Components/Field';
import type { PageProps } from '@/types';

interface Rules {
    min_advance_minutes: number;
    max_advance_days: number;
    cancellation_deadline_hours: number;
    reschedule_deadline_hours: number;
    buffer_minutes: number;
    slot_interval_minutes: number;
    max_duration_minutes: number | null;
}

export default function RulesPage({ rules }: PageProps<{ rules: Rules }>) {
    const { data, setData, put, processing, errors } = useForm<Rules>({ ...rules });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put('/admin/schedule/rules');
    };

    const number = (key: keyof Rules) => (event: { target: { value: string } }) =>
        setData(key, (event.target.value === '' ? null : Number(event.target.value)) as never);

    return (
        <AppLayout title="Booking rules">
            <p className="mb-6 max-w-2xl text-sm text-ink-muted">
                These rules are enforced on the server. They shape which times customers are offered and are checked
                again when a booking is confirmed, so a rule can never be sidestepped from the browser.
            </p>

            <form onSubmit={submit} className="max-w-2xl space-y-7">
                <section className="space-y-6 rounded-2xl border border-line bg-surface p-7">
                    <h2 className="text-lg text-ink">When customers may book</h2>

                    <Field
                        label="Minimum notice (minutes)"
                        name="min_advance_minutes"
                        type="number"
                        min={0}
                        required
                        hint="How far ahead a booking must be made. 60 means nothing within the next hour."
                        value={data.min_advance_minutes}
                        error={errors.min_advance_minutes}
                        onChange={number('min_advance_minutes')}
                    />

                    <Field
                        label="Booking horizon (days)"
                        name="max_advance_days"
                        type="number"
                        min={1}
                        required
                        hint="How far into the future the calendar is open."
                        value={data.max_advance_days}
                        error={errors.max_advance_days}
                        onChange={number('max_advance_days')}
                    />
                </section>

                <section className="space-y-6 rounded-2xl border border-line bg-surface p-7">
                    <h2 className="text-lg text-ink">How appointments are spaced</h2>

                    <Field
                        label="Slot interval (minutes)"
                        name="slot_interval_minutes"
                        type="number"
                        min={5}
                        required
                        hint="The grid offered times sit on. 15 gives 9:00, 9:15, 9:30."
                        value={data.slot_interval_minutes}
                        error={errors.slot_interval_minutes}
                        onChange={number('slot_interval_minutes')}
                    />

                    <Field
                        label="Buffer between appointments (minutes)"
                        name="buffer_minutes"
                        type="number"
                        min={0}
                        required
                        hint="Turnaround time kept clear either side of every booking. It does not delay the first appointment of the day."
                        value={data.buffer_minutes}
                        error={errors.buffer_minutes}
                        onChange={number('buffer_minutes')}
                    />

                    <Field
                        label="Longest appointment (minutes)"
                        name="max_duration_minutes"
                        type="number"
                        min={5}
                        hint="Optional. Leave blank for no limit. Applies to the total of all services chosen together."
                        value={data.max_duration_minutes ?? ''}
                        error={errors.max_duration_minutes}
                        onChange={number('max_duration_minutes')}
                    />
                </section>

                <section className="space-y-6 rounded-2xl border border-line bg-surface p-7">
                    <h2 className="text-lg text-ink">Changing an appointment</h2>

                    <Field
                        label="Cancellation deadline (hours before)"
                        name="cancellation_deadline_hours"
                        type="number"
                        min={0}
                        required
                        hint="After this point a customer can no longer cancel online."
                        value={data.cancellation_deadline_hours}
                        error={errors.cancellation_deadline_hours}
                        onChange={number('cancellation_deadline_hours')}
                    />

                    <Field
                        label="Rescheduling deadline (hours before)"
                        name="reschedule_deadline_hours"
                        type="number"
                        min={0}
                        required
                        hint="After this point a customer can no longer move the appointment themselves."
                        value={data.reschedule_deadline_hours}
                        error={errors.reschedule_deadline_hours}
                        onChange={number('reschedule_deadline_hours')}
                    />
                </section>

                <Button type="submit" disabled={processing}>
                    {processing ? 'Saving...' : 'Save booking rules'}
                </Button>
            </form>
        </AppLayout>
    );
}
