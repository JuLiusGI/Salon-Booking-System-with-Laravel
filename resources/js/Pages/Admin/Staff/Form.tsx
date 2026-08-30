import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Field from '@/Components/Field';
import { Checkbox, CheckboxGroup, ImageUpload, Select, Textarea } from '@/Components/Form';
import type { PageProps, UserRole } from '@/types';

interface EditableStaff {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: UserRole;
    title: string | null;
    bio: string | null;
    hired_on: string | null;
    is_active: boolean;
    is_bookable: boolean;
    display_order: number;
    photo_url: string | null;
    service_ids: number[];
}

interface StaffFormProps {
    member: EditableStaff | null;
    services: { value: number; label: string }[];
}

export default function Form({ member, services }: PageProps<StaffFormProps>) {
    const editing = member !== null;

    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        email: string;
        phone: string;
        role: string;
        password: string;
        password_confirmation: string;
        title: string;
        bio: string;
        hired_on: string;
        is_active: boolean;
        is_bookable: boolean;
        display_order: number;
        photo: File | null;
        remove_photo: boolean;
        service_ids: number[];
    }>({
        name: member?.name ?? '',
        email: member?.email ?? '',
        phone: member?.phone ?? '',
        role: member?.role ?? 'stylist',
        password: '',
        password_confirmation: '',
        title: member?.title ?? '',
        bio: member?.bio ?? '',
        hired_on: member?.hired_on ?? '',
        is_active: member?.is_active ?? true,
        is_bookable: member?.is_bookable ?? true,
        display_order: member?.display_order ?? 0,
        photo: null,
        remove_photo: false,
        service_ids: member?.service_ids ?? [],
    });

    const isReceptionist = data.role === 'receptionist';

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (editing) {
            router.post(`/admin/staff/${member.id}`, { ...data, _method: 'patch' }, { forceFormData: true });

            return;
        }

        post('/admin/staff', { forceFormData: true });
    };

    return (
        <AppLayout title={editing ? `Edit ${member.name}` : 'Add team member'}>
            <form onSubmit={submit} className="max-w-2xl space-y-7">
                <div className="space-y-6 rounded-2xl border border-line bg-surface p-7">
                    <h2 className="text-lg text-ink">Account</h2>

                    <Field
                        label="Full name"
                        name="name"
                        required
                        autoFocus
                        value={data.name}
                        error={errors.name}
                        onChange={(e) => setData('name', e.target.value)}
                    />

                    <Field
                        label="Email"
                        name="email"
                        type="email"
                        required
                        hint="They sign in with this address."
                        value={data.email}
                        error={errors.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <Field
                        label="Phone"
                        name="phone"
                        type="tel"
                        value={data.phone}
                        error={errors.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                    />

                    <Select
                        label="Role"
                        name="role"
                        required
                        hint="Stylists can be booked. Receptionists manage the diary but are not booked themselves."
                        options={[
                            { value: 'stylist', label: 'Stylist' },
                            { value: 'receptionist', label: 'Receptionist' },
                        ]}
                        value={data.role}
                        error={errors.role}
                        onChange={(e) => {
                            const role = e.target.value;
                            setData('role', role);
                            if (role === 'receptionist') {
                                setData('is_bookable', false);
                            }
                        }}
                    />

                    <Field
                        label={editing ? 'New password' : 'Password'}
                        name="password"
                        type="password"
                        autoComplete="new-password"
                        required={!editing}
                        hint={
                            editing
                                ? 'Leave blank to keep their current password.'
                                : 'At least 8 characters. Ask them to change it after their first sign in.'
                        }
                        value={data.password}
                        error={errors.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <Field
                        label="Confirm password"
                        name="password_confirmation"
                        type="password"
                        autoComplete="new-password"
                        required={!editing}
                        value={data.password_confirmation}
                        error={errors.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                </div>

                <div className="space-y-6 rounded-2xl border border-line bg-surface p-7">
                    <h2 className="text-lg text-ink">Public profile</h2>

                    <Field
                        label="Job title"
                        name="title"
                        hint="Shown under their name on the team page, such as Senior Stylist."
                        value={data.title}
                        error={errors.title}
                        onChange={(e) => setData('title', e.target.value)}
                    />

                    <Textarea
                        label="Bio"
                        name="bio"
                        hint="Optional. A short introduction for the public team page."
                        value={data.bio}
                        error={errors.bio}
                        onChange={(e) => setData('bio', e.target.value)}
                    />

                    <ImageUpload
                        label="Photo"
                        currentUrl={member?.photo_url ?? null}
                        removed={data.remove_photo}
                        error={errors.photo}
                        hint="A square portrait works best. JPEG, PNG, or WebP up to 4 MB."
                        onSelect={(file) => {
                            setData('photo', file);
                            if (file) {
                                setData('remove_photo', false);
                            }
                        }}
                        onRemoveExisting={() => setData('remove_photo', true)}
                    />

                    <Field
                        label="Start date"
                        name="hired_on"
                        type="date"
                        value={data.hired_on}
                        error={errors.hired_on}
                        onChange={(e) => setData('hired_on', e.target.value)}
                    />
                </div>

                <div className="rounded-2xl border border-line bg-surface p-7">
                    <CheckboxGroup
                        legend="Services they can perform"
                        hint="A customer can only choose this person for services listed here."
                        options={services}
                        selected={data.service_ids}
                        error={errors.service_ids}
                        onChange={(values) => setData('service_ids', values)}
                        emptyMessage="No services yet. Create a service first."
                    />
                </div>

                <div className="space-y-6 rounded-2xl border border-line bg-surface p-7">
                    <Field
                        label="Display order"
                        name="display_order"
                        type="number"
                        min={0}
                        required
                        hint="Lower numbers appear first on the team page."
                        value={data.display_order}
                        error={errors.display_order}
                        onChange={(e) => setData('display_order', Number(e.target.value))}
                    />

                    <Checkbox
                        name="is_bookable"
                        label="Can be booked by customers"
                        description={
                            isReceptionist
                                ? 'Receptionists cannot be booked for services.'
                                : 'Appears on the public team page and in the stylist picker.'
                        }
                        checked={data.is_bookable}
                        disabled={isReceptionist}
                        error={errors.is_bookable}
                        onChange={(checked) => setData('is_bookable', checked)}
                    />

                    <Checkbox
                        name="is_active"
                        label="Currently working here"
                        description="Inactive members keep their history but drop out of schedules and booking."
                        checked={data.is_active}
                        error={errors.is_active}
                        onChange={(checked) => setData('is_active', checked)}
                    />
                </div>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving...' : editing ? 'Save changes' : 'Add to team'}
                    </Button>
                    <Button type="button" variant="secondary" onClick={() => router.get('/admin/staff')}>
                        Cancel
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
