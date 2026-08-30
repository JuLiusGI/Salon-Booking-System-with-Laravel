import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AuthLayout from '@/Layouts/AuthLayout';
import Button from '@/Components/Button';
import Field from '@/Components/Field';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/forgot-password');
    };

    return (
        <AuthLayout
            title="Reset your password"
            description="Enter your email and we'll send you a link to choose a new password."
        >
            {status && (
                <div role="status" className="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <Field
                    label="Email"
                    name="email"
                    type="email"
                    autoComplete="username"
                    required
                    autoFocus
                    value={data.email}
                    error={errors.email}
                    onChange={(e) => setData('email', e.target.value)}
                />

                <Button type="submit" disabled={processing} className="w-full">
                    {processing ? 'Sending…' : 'Email password reset link'}
                </Button>
            </form>

            <p className="mt-6 text-center text-sm text-neutral-600">
                <Link href="/login" className="underline hover:text-neutral-900">
                    Back to log in
                </Link>
            </p>
        </AuthLayout>
    );
}
