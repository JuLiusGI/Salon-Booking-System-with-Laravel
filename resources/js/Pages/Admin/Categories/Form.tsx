import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Field from '@/Components/Field';
import { Checkbox, ImageUpload, Textarea } from '@/Components/Form';
import type { PageProps } from '@/types';

interface EditableCategory {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    display_order: number;
    image_url: string | null;
}

export default function Form({ category }: PageProps<{ category: EditableCategory | null }>) {
    const editing = category !== null;

    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        description: string;
        is_active: boolean;
        display_order: number;
        image: File | null;
        remove_image: boolean;
    }>({
        name: category?.name ?? '',
        description: category?.description ?? '',
        is_active: category?.is_active ?? true,
        display_order: category?.display_order ?? 0,
        image: null,
        remove_image: false,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        // A file upload has to go over POST, so an update is sent as a spoofed
        // PATCH rather than a real one.
        if (editing) {
            router.post(
                `/admin/categories/${category.id}`,
                { ...data, _method: 'patch' },
                { forceFormData: true },
            );

            return;
        }

        post('/admin/categories', { forceFormData: true });
    };

    return (
        <AppLayout title={editing ? `Edit ${category.name}` : 'New category'}>
            <form onSubmit={submit} className="max-w-2xl space-y-7">
                <div className="space-y-6 rounded-2xl border border-line bg-surface p-7">
                    <Field
                        label="Name"
                        name="name"
                        required
                        autoFocus
                        hint="Shown as a heading on the public service menu."
                        value={data.name}
                        error={errors.name}
                        onChange={(e) => setData('name', e.target.value)}
                    />

                    <Textarea
                        label="Description"
                        name="description"
                        hint="Optional. A sentence introducing this group of services."
                        value={data.description}
                        error={errors.description}
                        onChange={(e) => setData('description', e.target.value)}
                    />

                    <ImageUpload
                        label="Category image"
                        currentUrl={category?.image_url ?? null}
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

                <div className="space-y-6 rounded-2xl border border-line bg-surface p-7">
                    <Field
                        label="Display order"
                        name="display_order"
                        type="number"
                        min={0}
                        required
                        hint="Lower numbers appear first. Categories with the same number fall back to alphabetical order."
                        value={data.display_order}
                        error={errors.display_order}
                        onChange={(e) => setData('display_order', Number(e.target.value))}
                    />

                    <Checkbox
                        name="is_active"
                        label="Visible to customers"
                        description="Hidden categories stay in the admin area but disappear from the public site."
                        checked={data.is_active}
                        error={errors.is_active}
                        onChange={(checked) => setData('is_active', checked)}
                    />
                </div>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving...' : editing ? 'Save changes' : 'Create category'}
                    </Button>
                    <Button type="button" variant="secondary" onClick={() => router.get('/admin/categories')}>
                        Cancel
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
