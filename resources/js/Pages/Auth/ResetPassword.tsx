import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AuthLayout from '@/Layouts/AuthLayout';
import Button from '@/Components/Button';
import Field from '@/Components/Field';

interface ResetPasswordProps {
    token: string;
    email: string;
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/reset-password', { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <AuthLayout title="Choose a new password">
            <form onSubmit={submit} className="space-y-4">
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
                    label="New password"
                    name="password"
                    type="password"
                    autoComplete="new-password"
                    required
                    autoFocus
                    hint="At least 8 characters."
                    value={data.password}
                    error={errors.password}
                    onChange={(e) => setData('password', e.target.value)}
                />

                <Field
                    label="Confirm new password"
                    name="password_confirmation"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={data.password_confirmation}
                    error={errors.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                />

                <Button type="submit" disabled={processing} className="w-full">
                    {processing ? 'Saving…' : 'Reset password'}
                </Button>
            </form>
        </AuthLayout>
    );
}
