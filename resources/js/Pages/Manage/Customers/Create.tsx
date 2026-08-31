import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Field from '@/Components/Field';

export default function Create() {
    const form = useForm({ name: '', email: '', phone: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/manage/customers/new');
    };

    return (
        <AppLayout title="Add a customer">
            <form onSubmit={submit} className="max-w-xl space-y-6">
                <div className="space-y-5 rounded-2xl border border-line bg-surface p-7">
                    <p className="text-sm text-ink-muted">
                        For someone who walked in without an account. They set their own password later through
                        &ldquo;forgot password&rdquo;, so the desk never handles it.
                    </p>

                    <Field
                        label="Full name"
                        name="name"
                        required
                        autoFocus
                        value={form.data.name}
                        error={form.errors.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                    />

                    <Field
                        label="Email"
                        name="email"
                        type="email"
                        required
                        hint="They will sign in with this, and it is where a reset link goes."
                        value={form.data.email}
                        error={form.errors.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                    />

                    <Field
                        label="Phone"
                        name="phone"
                        type="tel"
                        value={form.data.phone}
                        error={form.errors.phone}
                        onChange={(e) => form.setData('phone', e.target.value)}
                    />
                </div>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Adding...' : 'Add customer'}
                    </Button>
                    <Button type="button" variant="secondary" onClick={() => router.get('/manage/customers')}>
                        Cancel
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
