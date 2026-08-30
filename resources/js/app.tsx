import './bootstrap';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ComponentType } from 'react';

const appName = import.meta.env.VITE_APP_NAME || 'Salon Booking';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),

    resolve: (name) => {
        const pages = import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx', {
            eager: true,
        });

        const page = pages[`./Pages/${name}.tsx`];

        if (!page) {
            throw new Error(`Inertia page not found: ./Pages/${name}.tsx`);
        }

        return page;
    },

    setup({ el, App, props }) {
        if (!el) {
            throw new Error('Inertia root element was not found in the root template.');
        }

        createRoot(el).render(<App {...props} />);
    },

    progress: {
        // Read from the design tokens so the loading bar cannot drift away from
        // the palette. Falls back only if the stylesheet has not applied yet.
        color: getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim() || '#0a3323',
    },
});
