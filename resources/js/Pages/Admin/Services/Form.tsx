import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Field from '@/Components/Field';
import { Checkbox, CheckboxGroup, ImageUpload, Select, Textarea } from '@/Components/Form';
import type { PageProps } from '@/types';

interface EditableService {
    id: number;
    service_category_id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    price: string;
    is_active: boolean;
    display_order: number;
    image_url: string | null;
    staff_ids: number[];
}

interface ServiceFormProps {
    service: EditableService | null;
    categories: { value: number; label: string }[];
    staff: { value: number; label: string }[];
}

export default function Form({ service, categories, staff }: PageProps<ServiceFormProps>) {
    const editing = service !== null;

    const { data, setData, post, processing, errors } = useForm<{
        service_category_id: number | string;
        name: string;
        description: string;
        duration_minutes: number;
        price: string;
        is_active: boolean;
        display_order: number;
        image: File | null;
        remove_image: boolean;
        staff_ids: number[];
    }>({
        service_category_id: service?.service_category_id ?? '',
        name: service?.name ?? '',
        description: service?.description ?? '',
        duration_minutes: service?.duration_minutes ?? 60,
        price: service?.price ?? '',
        is_active: service?.is_active ?? true,
        display_order: service?.display_order ?? 0,
        image: null,
        remove_image: false,
        staff_ids: service?.staff_ids ?? [],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (editing) {
            router.post(`/admin/services/${service.id}`, { ...data, _method: 'patch' }, { forceFormData: true });

            return;
        }

        post('/admin/services', { forceFormData: true });
    };

    return (
        <AppLayout title={editing ? `Edit ${service.name}` : 'New service'}>
            <form onSubmit={submit} className="max-w-2xl space-y-7">
                <div className="space-y-6 rounded-2xl border border-line bg-surface p-7">
                    <h2 className="text-lg text-ink">Details</h2>

                    <Select
                        label="Category"
                        name="service_category_id"
                        required
                        placeholder="Choose a category"
                        options={categories}
                        value={data.service_category_id}
                        error={errors.service_category_id}
                        onChange={(e) => setData('service_category_id', e.target.value)}
                    />

                    <Field
                        label="Name"
                        name="name"
                        required
                        autoFocus
                        value={data.name}
                        error={errors.name}
                        onChange={(e) => setData('name', e.target.value)}
                    />

                    <Textarea
                        label="Description"
                        name="description"
                        hint="Optional. Shown under the service name on the public menu."
                        value={data.description}
                        error={errors.description}
                        onChange={(e) => setData('description', e.target.value)}
                    />

                    <ImageUpload
                        label="Service image"
                        currentUrl={service?.image_url ?? null}
                        removed={data.remove_image}
                        error={errors.image}
                        onSelect={(file) => {
                            setData('image', file);
                            if (file) {
                                setData('remove_image', false);
                            }
                        }}
                        onRemoveExisting={() => setData('remove_image', true)}
                    />
                </div>

                <div className="grid gap-6 rounded-2xl border border-line bg-surface p-7 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        <h2 className="text-lg text-ink">Time and price</h2>
                    </div>

                    <Field
                        label="Duration (minutes)"
                        name="duration_minutes"
                        type="number"
                        min={5}
                        max={600}
                        required
                        hint="How much diary time this blocks."
                        value={data.duration_minutes}
                        error={errors.duration_minutes}
                        onChange={(e) => setData('duration_minutes', Number(e.target.value))}
                    />

                    <Field
                        label="Price"
                        name="price"
                        type="number"
                        step="0.01"
                        min={0}
                        required
                        hint="In pesos."
                        value={data.price}
                        error={errors.price}
                        onChange={(e) => setData('price', e.target.value)}
                    />
                </div>

                <div className="rounded-2xl border border-line bg-surface p-7">
                    <CheckboxGroup
                        legend="Who can perform this service"
                        hint="Only these staff members will be offered when a customer picks this service. A service with nobody assigned cannot be booked."
                        options={staff}
                        selected={data.staff_ids}
                        error={errors.staff_ids}
                        onChange={(values) => setData('staff_ids', values)}
                        emptyMessage="No bookable staff yet. Add a stylist first."
                    />
                </div>

                <div className="space-y-6 rounded-2xl border border-line bg-surface p-7">
                    <Field
                        label="Display order"
                        name="display_order"
                        type="number"
                        min={0}
                        required
                        hint="Lower numbers appear first within the category."
                        value={data.display_order}
                        error={errors.display_order}
                        onChange={(e) => setData('display_order', Number(e.target.value))}
                    />

                    <Checkbox
                        name="is_active"
                        label="Available to book"
                        description="Hidden services disappear from the public menu and cannot be booked."
                        checked={data.is_active}
                        error={errors.is_active}
                        onChange={(checked) => setData('is_active', checked)}
                    />
                </div>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving...' : editing ? 'Save changes' : 'Create service'}
                    </Button>
                    <Button type="button" variant="secondary" onClick={() => router.get('/admin/services')}>
                        Cancel
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
