import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AuthLayout from '@/Layouts/AuthLayout';
import Button from '@/Components/Button';
import Field from '@/Components/Field';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/register', { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <AuthLayout title="Create an account" description="Book and manage your salon appointments online.">
            <form onSubmit={submit} className="space-y-4">
                <Field
                    label="Full name"
                    name="name"
                    autoComplete="name"
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
                    autoComplete="username"
                    required
                    value={data.email}
                    error={errors.email}
                    onChange={(e) => setData('email', e.target.value)}
                />

                <Field
                    label="Phone"
                    name="phone"
                    type="tel"
                    autoComplete="tel"
                    hint="Optional. Used to reach you about your appointment."
                    value={data.phone}
                    error={errors.phone}
                    onChange={(e) => setData('phone', e.target.value)}
                />

                <Field
                    label="Password"
                    name="password"
                    type="password"
                    autoComplete="new-password"
                    required
                    hint="At least 8 characters."
                    value={data.password}
                    error={errors.password}
                    onChange={(e) => setData('password', e.target.value)}
                />

                <Field
                    label="Confirm password"
                    name="password_confirmation"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={data.password_confirmation}
                    error={errors.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                />

                <Button type="submit" disabled={processing} className="w-full">
                    {processing ? 'Creating account…' : 'Create account'}
                </Button>
            </form>

            <p className="mt-6 text-center text-sm text-neutral-600">
                Already registered?{' '}
                <Link href="/login" className="font-medium text-neutral-900 underline">
                    Log in
                </Link>
            </p>
        </AuthLayout>
    );
}
