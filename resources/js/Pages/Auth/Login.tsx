import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AuthLayout from '@/Layouts/AuthLayout';
import Button from '@/Components/Button';
import Field from '@/Components/Field';

export default function Login({ status }: { status?: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/login', { onFinish: () => reset('password') });
    };

    return (
        <AuthLayout title="Log in" description="Welcome back. Sign in to manage your appointments.">
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

                <Field
                    label="Password"
                    name="password"
                    type="password"
                    autoComplete="current-password"
                    required
                    value={data.password}
                    error={errors.password}
                    onChange={(e) => setData('password', e.target.value)}
                />

                <div className="flex items-center justify-between">
                    <label className="flex items-center gap-2 text-sm text-neutral-600">
                        <input
                            type="checkbox"
                            name="remember"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="rounded border-neutral-300"
                        />
                        Remember me
                    </label>

                    <Link href="/forgot-password" className="text-sm text-neutral-600 underline hover:text-neutral-900">
                        Forgot password?
                    </Link>
                </div>

                <Button type="submit" disabled={processing} className="w-full">
                    {processing ? 'Signing in…' : 'Log in'}
                </Button>
            </form>

            <p className="mt-6 text-center text-sm text-neutral-600">
                New here?{' '}
                <Link href="/register" className="font-medium text-neutral-900 underline">
                    Create an account
                </Link>
            </p>
        </AuthLayout>
    );
}
