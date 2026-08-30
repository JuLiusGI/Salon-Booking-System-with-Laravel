import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Field from '@/Components/Field';
import type { PageProps } from '@/types';

interface CustomerProfile {
    birthday: string | null;
    gender: string | null;
    address: string | null;
    preferences: string | null;
    allergies: string | null;
}

export default function Edit({ auth, profile }: PageProps<{ profile: CustomerProfile | null }>) {
    const user = auth.user!;
    const isCustomer = user.role === 'customer';

    const details = useForm({
        name: user.name,
        email: user.email,
        phone: '',
        birthday: profile?.birthday ?? '',
        gender: profile?.gender ?? '',
        address: profile?.address ?? '',
        preferences: profile?.preferences ?? '',
        allergies: profile?.allergies ?? '',
    });

    const password = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submitDetails = (event: FormEvent) => {
        event.preventDefault();
        details.patch('/profile', { preserveScroll: true });
    };

    const submitPassword = (event: FormEvent) => {
        event.preventDefault();
        password.put('/profile/password', {
            preserveScroll: true,
            onSuccess: () => password.reset(),
        });
    };

    return (
        <AppLayout title="Profile">
            <div className="space-y-6">
                <section className="rounded-lg border border-neutral-200 bg-white p-6">
                    <h2 className="text-base font-semibold text-neutral-900">Account details</h2>

                    <form onSubmit={submitDetails} className="mt-4 max-w-lg space-y-4">
                        <Field
                            label="Full name"
                            name="name"
                            required
                            value={details.data.name}
                            error={details.errors.name}
                            onChange={(e) => details.setData('name', e.target.value)}
                        />

                        <Field
                            label="Email"
                            name="email"
                            type="email"
                            required
                            value={details.data.email}
                            error={details.errors.email}
                            onChange={(e) => details.setData('email', e.target.value)}
                        />

                        <Field
                            label="Phone"
                            name="phone"
                            type="tel"
                            value={details.data.phone}
                            error={details.errors.phone}
                            onChange={(e) => details.setData('phone', e.target.value)}
                        />

                        {isCustomer && (
                            <>
                                <Field
                                    label="Birthday"
                                    name="birthday"
                                    type="date"
                                    value={details.data.birthday}
                                    error={details.errors.birthday}
                                    onChange={(e) => details.setData('birthday', e.target.value)}
                                />

                                <Field
                                    label="Address"
                                    name="address"
                                    value={details.data.address}
                                    error={details.errors.address}
                                    onChange={(e) => details.setData('address', e.target.value)}
                                />

                                <Field
                                    label="Allergies or sensitivities"
                                    name="allergies"
                                    hint="Shared with your stylist so treatments can be adjusted."
                                    value={details.data.allergies}
                                    error={details.errors.allergies}
                                    onChange={(e) => details.setData('allergies', e.target.value)}
                                />
                            </>
                        )}

                        <Button type="submit" disabled={details.processing}>
                            {details.processing ? 'Saving...' : 'Save changes'}
                        </Button>
                    </form>
                </section>

                <section className="rounded-lg border border-neutral-200 bg-white p-6">
                    <h2 className="text-base font-semibold text-neutral-900">Change password</h2>

                    <form onSubmit={submitPassword} className="mt-4 max-w-lg space-y-4">
                        <Field
                            label="Current password"
                            name="current_password"
                            type="password"
                            autoComplete="current-password"
                            required
                            value={password.data.current_password}
                            error={password.errors.current_password}
                            onChange={(e) => password.setData('current_password', e.target.value)}
                        />

                        <Field
                            label="New password"
                            name="password"
                            type="password"
                            autoComplete="new-password"
                            required
                            value={password.data.password}
                            error={password.errors.password}
                            onChange={(e) => password.setData('password', e.target.value)}
                        />

                        <Field
                            label="Confirm new password"
                            name="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            required
                            value={password.data.password_confirmation}
                            error={password.errors.password_confirmation}
                            onChange={(e) => password.setData('password_confirmation', e.target.value)}
                        />

                        <Button type="submit" disabled={password.processing}>
                            {password.processing ? 'Updating...' : 'Update password'}
                        </Button>
                    </form>
                </section>
            </div>
        </AppLayout>
    );
}
