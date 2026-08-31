import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Field from '@/Components/Field';
import { Select, Textarea } from '@/Components/Form';
import type { BookableCategory, PageProps, SharedProps, SlotOption } from '@/types';

interface CustomerOption {
    id: number;
    name: string;
    email: string;
}

interface CreateProps {
    categories: BookableCategory[];
    stylists: { id: number; name: string; title: string | null }[];
    customers: CustomerOption[];
    selection: {
        service_ids: number[];
        staff_id: number | null;
        date: string | null;
        customer_search: string;
    };
    summary: { duration_minutes: number; total_price: string };
    slots?: { date: string | null; times: SlotOption[] };
    sources: { value: string; label: string }[];
    booking_window: { earliest_date: string; latest_date: string };
    timezone: string;
}

const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

export default function Create({
    categories,
    stylists,
    customers,
    selection,
    summary,
    slots,
    sources,
    booking_window,
    timezone,
}: PageProps<CreateProps>) {
    const { errors } = usePage<SharedProps>().props;

    const [search, setSearch] = useState(selection.customer_search);
    const [customer, setCustomer] = useState<CustomerOption | null>(null);
    const [serviceIds, setServiceIds] = useState<number[]>(selection.service_ids);
    const [staffId, setStaffId] = useState<number | null>(selection.staff_id);
    const [date, setDate] = useState(selection.date ?? '');
    const [slot, setSlot] = useState<SlotOption | null>(null);

    const form = useForm({ source: 'phone', notes: '' });

    const refresh = (next: Record<string, unknown> = {}) => {
        router.get(
            '/manage/appointments/new',
            {
                service_ids: serviceIds,
                staff_id: staffId,
                date,
                customer_search: search,
                ...next,
            } as never,
            {
                only: ['stylists', 'summary', 'slots', 'selection', 'customers'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const findCustomers = (event: FormEvent) => {
        event.preventDefault();
        refresh({ customer_search: search });
    };

    const toggleService = (id: number) => {
        const next = serviceIds.includes(id) ? serviceIds.filter((s) => s !== id) : [...serviceIds, id];
        setServiceIds(next);
        setSlot(null);
        refresh({ service_ids: next });
    };

    const submit = () => {
        if (!customer || !slot || !staffId) return;

        form.transform(() => ({
            customer_id: customer.id,
            service_ids: serviceIds,
            staff_id: staffId,
            starts_at: slot.starts_at,
            source: form.data.source,
            notes: form.data.notes,
        }));

        form.post('/manage/appointments/new');
    };

    const times = slots?.times ?? [];

    return (
        <AppLayout title="New appointment">
            {(errors.starts_at || errors.customer_id || errors.staff_id || errors.service_ids) && (
                <div
                    role="alert"
                    className="mb-6 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900"
                >
                    {errors.starts_at ?? errors.customer_id ?? errors.staff_id ?? errors.service_ids}
                </div>
            )}

            <div className="grid gap-8 lg:grid-cols-[1fr_330px]">
                <div className="space-y-6">
                    <section className="rounded-2xl border border-line bg-surface p-7">
                        <h2 className="text-lg text-ink">
                            <span className="mr-2 text-sm text-ink-muted">1.</span>Find the customer
                        </h2>

                        <form onSubmit={findCustomers} className="mt-4 flex flex-wrap items-end gap-3">
                            <div className="w-72">
                                <Field
                                    label="Search"
                                    name="customer_search"
                                    placeholder="Name, email, or phone"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>
                            <Button type="submit" variant="secondary">
                                Search
                            </Button>
                        </form>

                        {customers.length > 0 && (
                            <ul className="mt-4 divide-y divide-line rounded-xl border border-line">
                                {customers.map((option) => (
                                    <li key={option.id}>
                                        <button
                                            type="button"
                                            onClick={() => setCustomer(option)}
                                            aria-pressed={customer?.id === option.id}
                                            className={`flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm transition-colors ${
                                                customer?.id === option.id ? 'bg-canvas-soft' : 'hover:bg-canvas-soft'
                                            }`}
                                        >
                                            <span className="text-ink">{option.name}</span>
                                            <span className="text-xs text-ink-muted">{option.email}</span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {search.trim() !== '' && customers.length === 0 && (
                            <p className="mt-4 rounded-xl border border-dashed border-line-strong p-5 text-sm text-ink-muted">
                                No customer accounts match that. They need an account before an appointment can be
                                booked for them.
                            </p>
                        )}
                    </section>

                    <section className="rounded-2xl border border-line bg-surface p-7">
                        <h2 className="text-lg text-ink">
                            <span className="mr-2 text-sm text-ink-muted">2.</span>Services
                        </h2>

                        <div className="mt-5 space-y-6">
                            {categories.map((category) => (
                                <fieldset key={category.id}>
                                    <legend className="text-xs font-semibold tracking-wide text-secondary uppercase">
                                        {category.name}
                                    </legend>

                                    <div className="mt-2 grid gap-2 sm:grid-cols-2">
                                        {category.services.map((service) => (
                                            <label
                                                key={service.id}
                                                className={`flex cursor-pointer items-center gap-2.5 rounded-lg border p-3 text-sm transition-colors ${
                                                    serviceIds.includes(service.id)
                                                        ? 'border-primary bg-canvas-soft'
                                                        : 'border-line hover:border-line-strong'
                                                }`}
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={serviceIds.includes(service.id)}
                                                    onChange={() => toggleService(service.id)}
                                                    className="h-4 w-4 rounded border-line-strong accent-primary"
                                                />
                                                <span className="flex-1 text-ink">{service.name}</span>
                                                <span className="text-xs text-ink-muted">
                                                    {service.duration_minutes}m
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                </fieldset>
                            ))}
                        </div>
                    </section>

                    <section className="rounded-2xl border border-line bg-surface p-7">
                        <h2 className="text-lg text-ink">
                            <span className="mr-2 text-sm text-ink-muted">3.</span>Stylist and time
                        </h2>

                        {serviceIds.length === 0 ? (
                            <p className="mt-4 text-sm text-ink-muted">Choose a service first.</p>
                        ) : stylists.length === 0 ? (
                            <p className="mt-4 rounded-xl border border-dashed border-line-strong p-5 text-sm text-ink-muted">
                                No one stylist offers all of these services together.
                            </p>
                        ) : (
                            <>
                                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                    {stylists.map((person) => (
                                        <button
                                            key={person.id}
                                            type="button"
                                            onClick={() => {
                                                setStaffId(person.id);
                                                setSlot(null);
                                                refresh({ staff_id: person.id });
                                            }}
                                            aria-pressed={staffId === person.id}
                                            className={`rounded-xl border p-3.5 text-left text-sm transition-colors ${
                                                staffId === person.id
                                                    ? 'border-primary bg-canvas-soft'
                                                    : 'border-line hover:border-line-strong'
                                            }`}
                                        >
                                            <span className="block font-medium text-ink">{person.name}</span>
                                            {person.title && (
                                                <span className="block text-xs text-ink-muted">{person.title}</span>
                                            )}
                                        </button>
                                    ))}
                                </div>

                                {staffId && (
                                    <div className="mt-5 max-w-xs space-y-1.5">
                                        <label htmlFor="staff-booking-date" className="block text-sm font-medium text-ink">
                                            Date
                                        </label>
                                        <input
                                            id="staff-booking-date"
                                            type="date"
                                            value={date}
                                            min={booking_window.earliest_date}
                                            max={booking_window.latest_date}
                                            onChange={(e) => {
                                                setDate(e.target.value);
                                                setSlot(null);
                                                refresh({ date: e.target.value });
                                            }}
                                            className="w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink"
                                        />
                                        <p className="text-xs text-ink-muted">Times in {timezone}.</p>
                                    </div>
                                )}

                                {date && staffId && (
                                    <div className="mt-5">
                                        {times.length === 0 ? (
                                            <p className="rounded-xl border border-dashed border-line-strong p-5 text-sm text-ink-muted">
                                                Nothing free on this date.
                                            </p>
                                        ) : (
                                            <div role="group" aria-label="Available times" className="flex flex-wrap gap-2">
                                                {times.map((option) => (
                                                    <button
                                                        key={option.starts_at}
                                                        type="button"
                                                        onClick={() => setSlot(option)}
                                                        aria-pressed={slot?.starts_at === option.starts_at}
                                                        className={`rounded-full border px-4 py-2 text-sm transition-colors ${
                                                            slot?.starts_at === option.starts_at
                                                                ? 'border-primary bg-primary text-ink-inverted'
                                                                : 'border-line-strong bg-surface text-ink hover:bg-canvas-soft'
                                                        }`}
                                                    >
                                                        {option.label}
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                )}
                            </>
                        )}
                    </section>
                </div>

                <aside className="lg:sticky lg:top-24 lg:self-start">
                    <div className="rounded-2xl border border-line bg-surface p-6">
                        <h2 className="text-lg text-ink">Summary</h2>

                        <dl className="mt-4 space-y-2.5 text-sm">
                            <div className="flex justify-between gap-3">
                                <dt className="text-ink-muted">Customer</dt>
                                <dd className="text-right text-ink">{customer?.name ?? '—'}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-ink-muted">Services</dt>
                                <dd className="text-ink">{serviceIds.length}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-ink-muted">Total time</dt>
                                <dd className="text-ink">{summary.duration_minutes} min</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-ink-muted">When</dt>
                                <dd className="text-right text-ink">{slot ? `${date} ${slot.label}` : '—'}</dd>
                            </div>
                            <div className="flex justify-between gap-3 border-t border-line pt-3 text-base">
                                <dt className="text-ink">Total</dt>
                                <dd className="font-medium text-ink">
                                    {peso.format(Number(summary.total_price))}
                                </dd>
                            </div>
                        </dl>

                        <div className="mt-5 space-y-4">
                            <Select
                                label="Booked via"
                                name="source"
                                options={sources}
                                value={form.data.source}
                                error={errors.source}
                                onChange={(e) => form.setData('source', e.target.value)}
                            />

                            <Textarea
                                label="Notes"
                                name="notes"
                                rows={3}
                                hint="Optional."
                                value={form.data.notes}
                                error={errors.notes}
                                onChange={(e) => form.setData('notes', e.target.value)}
                            />
                        </div>

                        <Button
                            type="button"
                            onClick={submit}
                            disabled={!customer || !slot || !staffId || form.processing}
                            className="mt-5 w-full"
                        >
                            {form.processing ? 'Booking...' : 'Book appointment'}
                        </Button>
                    </div>
                </aside>
            </div>
        </AppLayout>
    );
}
