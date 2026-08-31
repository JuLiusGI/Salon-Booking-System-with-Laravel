import type { ReactNode } from 'react';

/**
 * Small hand-rolled charts.
 *
 * Colour rules, from the visualization guidance:
 *
 * - Single-series marks use the brand primary. Length carries the magnitude, so
 *   colour is constant and only has to clear contrast against the surface.
 * - The one two-series chart uses SERIES, a pair validated for colourblind
 *   separation rather than chosen by eye. The raw brand colours failed that
 *   check (Dark Green reads too dark and both read near-grey at mark size), so
 *   these are the nearest passing steps in the same hue families.
 * - Every chart carries a visually hidden table of the same numbers, so the data
 *   is never available only as colour and shape.
 */

/** Validated for CVD separation against a white surface. Do not eyeball-edit. */
export const SERIES: readonly string[] = ['#17739B', '#CC7A35'];

interface FigureProps {
    title: string;
    subtitle?: string;
    summary: string;
    children: ReactNode;
    table: ReactNode;
    legend?: { label: string; color: string }[];
}

function Figure({ title, subtitle, summary, children, table, legend }: FigureProps) {
    return (
        <figure className="rounded-2xl border border-line bg-surface p-6">
            <figcaption className="mb-4">
                <h3 className="text-base text-ink">{title}</h3>
                {subtitle && <p className="mt-0.5 text-xs text-ink-muted">{subtitle}</p>}
            </figcaption>

            {legend && legend.length > 1 && (
                <ul className="mb-3 flex flex-wrap gap-4">
                    {legend.map((entry) => (
                        <li key={entry.label} className="flex items-center gap-2 text-xs text-ink-muted">
                            <span
                                aria-hidden="true"
                                className="inline-block h-2.5 w-2.5 rounded-full"
                                style={{ backgroundColor: entry.color }}
                            />
                            {entry.label}
                        </li>
                    ))}
                </ul>
            )}

            <div role="img" aria-label={summary}>
                {children}
            </div>

            {/* The same numbers, for screen readers and anyone who cannot use
                the chart. Hidden visually, never from assistive technology. */}
            <div className="sr-only">{table}</div>
        </figure>
    );
}

/* -------------------------------------------------------------------------- */

interface BarPoint {
    label: string;
    total: number;
}

interface BarChartProps {
    title: string;
    subtitle?: string;
    data: BarPoint[];
    unit?: string;
    height?: number;
}

/**
 * Vertical bars for magnitude across ordered categories: hours of the day,
 * days of the week.
 */
export function BarChart({ title, subtitle, data, unit = 'appointments', height = 160 }: BarChartProps) {
    const max = Math.max(1, ...data.map((d) => d.total));
    const busiest = data.reduce((a, b) => (b.total > a.total ? b : a), data[0]);

    return (
        <Figure
            title={title}
            subtitle={subtitle}
            summary={`${title}. Busiest: ${busiest?.label ?? 'none'} with ${busiest?.total ?? 0} ${unit}.`}
            table={
                <table>
                    <caption>{title}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Period</th>
                            <th scope="col">{unit}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.map((d) => (
                            <tr key={d.label}>
                                <th scope="row">{d.label}</th>
                                <td>{d.total}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            }
        >
            {/* Each column fills the plot height so the bar's percentage has
                something definite to resolve against. Ending the columns with
                items-end instead would shrink them to content, and a percentage
                of nothing is nothing. */}
            <div className="flex gap-[2px]" style={{ height }}>
                {data.map((d) => (
                    <div
                        key={d.label}
                        className="flex h-full flex-1 flex-col justify-end"
                        title={`${d.label}: ${d.total}`}
                    >
                        <div
                            className="rounded-t bg-primary transition-opacity hover:opacity-75"
                            style={{ height: `${Math.max((d.total / max) * 100, d.total > 0 ? 3 : 0)}%` }}
                        />
                    </div>
                ))}
            </div>

            <div className="mt-2 flex gap-[2px]">
                {data.map((d, i) => (
                    <div key={d.label} className="flex-1 text-center text-[10px] text-ink-muted">
                        {/* Every other label on dense axes, so they never collide. */}
                        {data.length > 10 ? (i % 2 === 0 ? d.label : '') : d.label}
                    </div>
                ))}
            </div>
        </Figure>
    );
}

/* -------------------------------------------------------------------------- */

interface RankedPoint {
    name: string;
    bookings: number;
    value?: string;
}

/**
 * Horizontal bars for ranked magnitude, where the labels are long enough that
 * vertical bars would force them sideways.
 */
export function RankedBars({
    title,
    subtitle,
    data,
    emptyMessage = 'Nothing in this period.',
}: {
    title: string;
    subtitle?: string;
    data: RankedPoint[];
    emptyMessage?: string;
}) {
    const max = Math.max(1, ...data.map((d) => d.bookings));

    if (data.length === 0) {
        return (
            <figure className="rounded-2xl border border-line bg-surface p-6">
                <figcaption className="mb-3 text-base text-ink">{title}</figcaption>
                <p className="text-sm text-ink-muted">{emptyMessage}</p>
            </figure>
        );
    }

    return (
        <Figure
            title={title}
            subtitle={subtitle}
            summary={`${title}. Top: ${data[0].name} with ${data[0].bookings} bookings.`}
            table={
                <table>
                    <caption>{title}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Bookings</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.map((d) => (
                            <tr key={d.name}>
                                <th scope="row">{d.name}</th>
                                <td>{d.bookings}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            }
        >
            <ul className="space-y-2.5">
                {data.map((d) => (
                    <li key={d.name}>
                        <div className="flex items-baseline justify-between gap-3 text-sm">
                            <span className="truncate text-ink">{d.name}</span>
                            {/* Direct label, so the value never depends on
                                reading a bar against an axis. */}
                            <span className="shrink-0 text-ink-muted">{d.bookings}</span>
                        </div>
                        <div className="mt-1 h-2 w-full rounded-full bg-canvas">
                            <div
                                className="h-2 rounded-full bg-primary"
                                style={{ width: `${(d.bookings / max) * 100}%` }}
                                title={`${d.name}: ${d.bookings}`}
                            />
                        </div>
                    </li>
                ))}
            </ul>
        </Figure>
    );
}

/* -------------------------------------------------------------------------- */

interface TrendPoint {
    label: string;
    total: number;
    completed?: number;
}

/**
 * A line for change over time. Two series at most, and both are named in the
 * legend as well as coloured.
 */
export function TrendChart({
    title,
    subtitle,
    data,
    showCompleted = true,
}: {
    title: string;
    subtitle?: string;
    data: TrendPoint[];
    showCompleted?: boolean;
}) {
    const width = 640;
    const height = 160;
    const max = Math.max(1, ...data.map((d) => Math.max(d.total, d.completed ?? 0)));

    const x = (i: number) => (data.length <= 1 ? 0 : (i / (data.length - 1)) * width);
    const y = (v: number) => height - (v / max) * height;

    const path = (key: 'total' | 'completed') =>
        data
            .map((d, i) => `${i === 0 ? 'M' : 'L'} ${x(i).toFixed(1)} ${y(d[key] ?? 0).toFixed(1)}`)
            .join(' ');

    const busiest = data.reduce((a, b) => (b.total > a.total ? b : a), data[0]);
    const legend = [{ label: 'Booked', color: SERIES[0] }];

    if (showCompleted) {
        legend.push({ label: 'Completed', color: SERIES[1] });
    }

    return (
        <Figure
            title={title}
            subtitle={subtitle}
            summary={`${title}. Peak: ${busiest?.label ?? 'none'} with ${busiest?.total ?? 0}.`}
            legend={legend}
            table={
                <table>
                    <caption>{title}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Day</th>
                            <th scope="col">Booked</th>
                            {showCompleted && <th scope="col">Completed</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {data.map((d) => (
                            <tr key={d.label}>
                                <th scope="row">{d.label}</th>
                                <td>{d.total}</td>
                                {showCompleted && <td>{d.completed ?? 0}</td>}
                            </tr>
                        ))}
                    </tbody>
                </table>
            }
        >
            <svg
                viewBox={`0 0 ${width} ${height}`}
                preserveAspectRatio="none"
                className="h-40 w-full"
                aria-hidden="true"
            >
                {/* Recessive gridlines: present enough to read against, quiet
                    enough not to compete with the data. */}
                {[0.25, 0.5, 0.75].map((f) => (
                    <line
                        key={f}
                        x1={0}
                        x2={width}
                        y1={height * f}
                        y2={height * f}
                        stroke="currentColor"
                        strokeWidth={1}
                        className="text-line"
                    />
                ))}

                {showCompleted && (
                    <path d={path('completed')} fill="none" stroke={SERIES[1]} strokeWidth={2} vectorEffect="non-scaling-stroke" />
                )}
                <path d={path('total')} fill="none" stroke={SERIES[0]} strokeWidth={2} vectorEffect="non-scaling-stroke" />
            </svg>

            <div className="mt-2 flex justify-between text-[10px] text-ink-muted">
                <span>{data[0]?.label}</span>
                <span>{data[data.length - 1]?.label}</span>
            </div>
        </Figure>
    );
}

/* -------------------------------------------------------------------------- */

/**
 * A single number. Often the right answer instead of a chart.
 */
export function StatTile({
    label,
    value,
    note,
    tone = 'default',
}: {
    label: string;
    value: string | number;
    note?: string;
    tone?: 'default' | 'attention';
}) {
    return (
        <div
            className={`rounded-2xl border p-5 ${
                tone === 'attention' ? 'border-accent/50 bg-accent/10' : 'border-line bg-surface'
            }`}
        >
            <p className="text-xs tracking-wide text-ink-muted uppercase">{label}</p>
            <p className="mt-1.5 font-display text-3xl text-ink">{value}</p>
            {note && <p className="mt-1 text-xs text-ink-muted">{note}</p>}
        </div>
    );
}
