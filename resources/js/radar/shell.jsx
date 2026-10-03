/* The chrome and the small pieces every tab reuses. */

const LABELS = {
    entered: ['YOU ENTERED', 'bg-[var(--chrome)] text-white'],
    official: ['OFFICIAL DATA', 'bg-[var(--tint)] text-[#1d4f85]'],
    prediction: ['PREDICTION', 'border border-[var(--accent)] text-[var(--accent)]'],
    assumption: ['ASSUMPTION', 'border border-[var(--line)] text-[var(--ink-2)]'],
    calculated: ['CALCULATED', 'bg-[#eceae4] text-[var(--ink-2)]'],
    llm: ['LLM', 'bg-[var(--tint)] text-[#1d4f85]'],
    pipeline: ['Pipeline', 'bg-[#e4efe8] text-[#1f6340]'],
    statistics: ['Statistics', 'bg-[var(--chrome)] text-white'],
    rules: ['Rules', 'bg-[#eceae4] text-[var(--ink-2)]'],
};

export function Pill({ kind, children }) {
    const [text, tone] = LABELS[kind] ?? [children, 'bg-[#eceae4] text-[var(--ink-2)]'];

    return (
        <span className={`meta inline-block rounded px-1.5 py-0.5 align-middle ${tone}`}>{children ?? text}</span>
    );
}

export function Card({ children, className = '' }) {
    return (
        <section className={`rounded-xl border border-[var(--line)] bg-[var(--surface)] ${className}`}>
            {children}
        </section>
    );
}

/** The three headline figures a result panel leads with. */
export function Tile({ tone = 'tint', value, label }) {
    const skin =
        tone === 'accent'
            ? 'bg-[var(--accent)] text-white'
            : tone === 'chrome'
              ? 'bg-[var(--chrome)] text-white'
              : 'bg-[var(--tint)] text-[var(--ink-1)]';

    return (
        <div className={`rounded-lg px-4 py-3.5 ${skin}`}>
            <div className="font-mono text-[26px] leading-none font-semibold tracking-tight">{value}</div>
            <div className="mt-1.5 text-[12.5px] opacity-85">{label}</div>
        </div>
    );
}

export function SectionTitle({ title, sub }) {
    return (
        <div>
            <h2 className="text-[15px] font-semibold text-[var(--ink-1)]">{title}</h2>
            {sub && <p className="mt-0.5 text-[12.5px] text-[var(--ink-2)]">{sub}</p>}
        </div>
    );
}

/** Show as: Simple picture / Detailed — the toggle that appears over every chart. */
export function ShowAs({ value, onChange, options }) {
    return (
        <div className="flex items-center gap-2">
            <span className="text-[12px] text-[var(--ink-2)]">Show as</span>
            <div className="flex overflow-hidden rounded-md border border-[var(--line)]">
                {options.map((o) => (
                    <button
                        key={o}
                        type="button"
                        onClick={() => onChange(o)}
                        aria-pressed={value === o}
                        className={`px-2.5 py-1 text-[12px] ${
                            value === o
                                ? 'bg-[var(--chrome)] text-white'
                                : 'bg-[var(--surface)] text-[var(--ink-2)] hover:text-[var(--ink-1)]'
                        }`}
                    >
                        {o}
                    </button>
                ))}
            </div>
        </div>
    );
}

export const TABS = [
    { id: 'business', label: 'Your business' },
    { id: 'explore', label: 'Ask & explore' },
    { id: 'why', label: 'Ask why' },
    { id: 'sources', label: 'Data sources' },
];

export function Header({ tab, onTab }) {
    return (
        <header className="bg-[var(--chrome)]">
            <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-5 py-3">
                <div className="flex items-center gap-2 text-white">
                    <svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true">
                        <circle cx="10" cy="10" r="8.5" fill="none" stroke="currentColor" strokeWidth="1.5" />
                        <path d="M10 10 L14 6.4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                        <circle cx="10" cy="10" r="1.6" fill="currentColor" />
                    </svg>
                    <span className="text-[15px] font-semibold">Inflation Radar</span>
                </div>

                <nav className="ml-auto flex flex-wrap items-center gap-x-5 gap-y-1">
                    {TABS.map((t) => (
                        <button
                            key={t.id}
                            type="button"
                            onClick={() => onTab(t.id)}
                            aria-current={tab === t.id ? 'page' : undefined}
                            className={`border-b-2 pb-0.5 text-[13px] transition-colors ${
                                tab === t.id
                                    ? 'border-[var(--accent)] text-white'
                                    : 'border-transparent text-white/70 hover:text-white'
                            }`}
                        >
                            {t.label}
                        </button>
                    ))}
                    <span className="meta rounded border border-white/30 px-1.5 py-0.5 text-white/80">DEMO</span>
                </nav>
            </div>
        </header>
    );
}
