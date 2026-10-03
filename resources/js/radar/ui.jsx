import { formatValue, formatRange, LABEL_TEXT } from './api';

/**
 * Every number on screen carries its label, and a forecast carries its range.
 * That rule is the product's honesty guarantee, so it lives in one component
 * rather than being re-decided per card.
 */

export function LabelChip({ label }) {
    if (!label) {
        return null;
    }

    const tone =
        label === 'data'
            ? 'text-[var(--ink-2)]'
            : label === 'needs_validation'
              ? 'text-[var(--status-serious)]'
              : 'text-[var(--ink-3)]';

    return (
        <span className={`text-[11px] uppercase tracking-wide ${tone}`}>
            {LABEL_TEXT[label] ?? label}
        </span>
    );
}

export function Card({ title, action, subtitle, children, className = '' }) {
    return (
        <section
            className={`rounded-lg border border-[var(--line)] bg-[var(--surface)] p-4 sm:p-5 ${className}`}
        >
            {(title || action) && (
                <div className="flex items-baseline justify-between gap-3">
                    {title && <h2 className="text-sm font-semibold text-[var(--ink-1)]">{title}</h2>}
                    {action}
                </div>
            )}
            {subtitle && <p className="mt-1 text-xs text-[var(--ink-2)]">{subtitle}</p>}
            <div className={title ? 'mt-3' : ''}>{children}</div>
        </section>
    );
}

export function Hero({ label, value, note, children }) {
    return (
        <div className="flex flex-col justify-center rounded-lg border border-[var(--line)] bg-[var(--surface)] p-5">
            <div className="text-xs text-[var(--ink-3)]">{label}</div>
            {/* Proportional figures: tabular-nums makes a display number look loose. */}
            <div className="my-1 text-[46px] leading-none font-semibold tracking-tight text-[var(--ink-1)]">
                {value}
            </div>
            {note && <div className="text-[13px] text-[var(--ink-2)]">{note}</div>}
            {children}
        </div>
    );
}

export function StatTile({ label, labelled, note }) {
    const range = formatRange(labelled);

    return (
        <div className="rounded-lg border border-[var(--line)] bg-[var(--surface)] p-4">
            <div className="text-xs text-[var(--ink-3)]">{label}</div>
            <div className="mt-1 text-2xl leading-tight font-semibold tracking-tight text-[var(--ink-1)]">
                {formatValue(labelled)}
            </div>
            <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                <LabelChip label={labelled?.label} />
                {range && <span className="text-[11px] text-[var(--ink-3)]">{range}</span>}
            </div>
            {note && <div className="mt-1.5 text-xs text-[var(--ink-2)]">{note}</div>}
        </div>
    );
}

export function Legend({ items }) {
    return (
        <ul className="flex flex-wrap items-center gap-x-4 gap-y-1">
            {items.map((item) => (
                <li key={item.label} className="flex items-center gap-1.5 text-xs text-[var(--ink-2)]">
                    <span
                        aria-hidden="true"
                        className="inline-block h-0.5 w-4 rounded-full"
                        style={{ background: item.colour }}
                    />
                    {item.label}
                </li>
            ))}
        </ul>
    );
}

export function Empty({ children }) {
    return <p className="py-6 text-center text-sm text-[var(--ink-3)]">{children}</p>;
}
