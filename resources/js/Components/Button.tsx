import type { ButtonHTMLAttributes } from 'react';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'primary' | 'secondary' | 'danger';
}

const VARIANTS = {
    primary: 'bg-neutral-900 text-white hover:bg-neutral-800 focus:ring-neutral-400',
    secondary: 'border border-neutral-300 bg-white text-neutral-800 hover:bg-neutral-50 focus:ring-neutral-300',
    danger: 'bg-red-600 text-white hover:bg-red-500 focus:ring-red-300',
} as const;

export default function Button({ variant = 'primary', className = '', ...props }: ButtonProps) {
    return (
        <button
            className={`inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-medium transition outline-none focus:ring-2 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-60 ${VARIANTS[variant]} ${className}`}
            {...props}
        />
    );
}
