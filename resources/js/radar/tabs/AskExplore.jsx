import { useState } from 'react';
import { Card, Pill, SectionTitle, ShowAs, Tile } from '../shell';

const EUR2 = (n) => `€${n.toFixed(2)}`;

const GLOSSARY = [
    {
        q: 'What is inflation?',
        a: 'Prices slowly going up over time. If inflation is 7%, something that cost €100 a year ago now costs about €107. Your money buys a little less each month.',
    },
    {
        q: 'What is a forecast or "likely range"?',
        a: 'Nobody knows future prices for sure. The shaded area shows where the price will most likely be. A wider area means less certainty.',
    },
    {
        q: 'What is profit margin?',
        a: 'How many cents you keep from each euro you sell, after paying all costs. A 16% margin means you keep 16 cents of every euro.',
    },
    {
        q: 'What is a cash safety buffer?',
        a: 'Money you always keep in the bank for surprises, like a broken fridge. Here it is set to €5,000. Falling below it is an early warning, not yet a crisis.',
    },
];

const QUESTIONS = [
    { title: 'Will my supplies cost more?', sub: 'See price history and what comes next' },
    { title: 'Is my business safe?', sub: 'Your cash for the next 6 months' },
    { title: 'What if I raise my prices?', sub: 'Try a price rise and see your profit' },
    { title: 'What is inflation doing in Kosovo?', sub: 'Real official figures, explained' },
    { title: 'What will my money be worth?', sub: 'A simple calculator for any amount' },
];

const PRODUCTS = {
    'Cooking oil 5L': {
        today: 9.4,
        likely: 10.2,
        fast: 10.43,
        low: 9.96,
        rise: 8.5,
        quarterExtra: 640,
        lastYear: 8,
        driver: 'sunflower oil',
        history: [
            { month: "Oct '25", price: 8.76 },
            { month: 'Nov', price: 8.92 },
            { month: 'Dec', price: 8.93 },
            { month: 'Jan', price: 9.05 },
            { month: 'Feb', price: 9.18 },
            { month: 'Mar', price: 9.4 },
        ],
    },
    'Flour 50kg': {
        today: 28.4,
        likely: 31.1,
        fast: 32.8,
        low: 29.9,
        rise: 9.5,
        quarterExtra: 980,
        lastYear: 12.6,
        driver: 'wheat',
        history: [
            { month: "Oct '25", price: 25.2 },
            { month: 'Nov', price: 25.8 },
            { month: 'Dec', price: 26.4 },
            { month: 'Jan', price: 27.0 },
            { month: 'Feb', price: 27.7 },
            { month: 'Mar', price: 28.4 },
        ],
    },
};

/** Today versus March, as a stacked bar: what you pay now, plus what is added. */
function PriceBars({ p }) {
    const max = p.fast * 1.08;
    const pct = (n) => `${(n / max) * 100}%`;

    const rows = [
        { label: 'Today', total: p.today, extra: 0, note: null },
        {
            label: 'March, most likely',
            total: p.likely,
            extra: p.likely - p.today,
            note: `Could be anywhere from ${EUR2(p.low)} to ${EUR2(p.fast)}.`,
        },
        { label: 'March, if prices rise fast', total: p.fast, extra: p.fast - p.today, note: null },
    ];

    return (
        <div className="mt-4">
            {rows.map((r) => (
                <div key={r.label} className="mb-3 last:mb-0">
                    <div className="flex items-baseline justify-between">
                        <span className="text-[13px] font-medium">{r.label}</span>
                        <span className="font-mono text-[13px] tabular-nums">{EUR2(r.total)}</span>
                    </div>
                    <div className="mt-1 flex h-7 overflow-hidden rounded">
                        <div
                            className="flex items-center bg-[#9aa3ad] px-2 text-[11.5px] whitespace-nowrap text-white"
                            style={{ width: pct(p.today) }}
                        >
                            {r.extra === 0 ? `You pay ${EUR2(p.today)}` : "Today's price"}
                        </div>
                        {r.extra > 0 && (
                            /* 2px of surface keeps the two fills apart without a stroke */
                            <div className="ml-0.5 flex items-center justify-end bg-[var(--accent)] px-2 text-[11.5px] whitespace-nowrap text-white"
                                style={{ width: pct(r.extra) }}>
                                +{EUR2(r.extra)}
                            </div>
                        )}
                    </div>
                    {r.note && <p className="mt-1 text-[11.5px] text-[var(--ink-2)]">{r.note}</p>}
                </div>
            ))}

            <ul className="mt-3 flex gap-4 text-[11.5px] text-[var(--ink-2)]">
                <li className="flex items-center gap-1.5">
                    <span className="inline-block h-2.5 w-2.5 rounded-sm bg-[#9aa3ad]" /> Today&rsquo;s price
                </li>
                <li className="flex items-center gap-1.5">
                    <span className="inline-block h-2.5 w-2.5 rounded-sm bg-[var(--accent)]" /> Extra you would pay
                </li>
            </ul>
        </div>
    );
}

function HistoryTable({ p }) {
    return (
        <div className="mt-4 overflow-x-auto">
            <table className="w-full border-collapse text-[13px]">
                <thead>
                    <tr className="border-b border-[var(--axis)] text-left">
                        <th className="py-2 pr-3 text-[11.5px] font-semibold text-[var(--ink-3)]">Month</th>
                        <th className="py-2 pr-3 text-right text-[11.5px] font-semibold text-[var(--ink-3)]">Price</th>
                        <th className="py-2 text-[11.5px] font-semibold text-[var(--ink-3)]">Likely range</th>
                    </tr>
                </thead>
                <tbody>
                    {p.history.map((h) => (
                        <tr key={h.month} className="border-b border-[var(--grid)]">
                            <td className="py-2 pr-3">{h.month}</td>
                            <td className="py-2 pr-3 text-right font-mono tabular-nums">{EUR2(h.price)}</td>
                            <td className="py-2 text-[var(--ink-3)]">paid</td>
                        </tr>
                    ))}
                    <tr>
                        <td className="py-2 pr-3">March</td>
                        <td className="py-2 pr-3 text-right font-mono tabular-nums">{EUR2(p.likely)}</td>
                        <td className="py-2 font-mono text-[12px] text-[var(--ink-2)] tabular-nums">
                            {EUR2(p.low)} – {EUR2(p.fast)}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    );
}

const SOURCES = [
    { kind: 'YOUR DATA', title: 'Your purchase invoices', body: 'Every price you paid for this item in the last 12 months.', meta: 'Last invoice: 28 Sep 2026' },
    { kind: 'OFFICIAL STATISTICS', title: 'Kosovo Agency of Statistics', body: 'Price index for the Food category, to see the national trend.', meta: 'Latest: Aug 2026 · monthly' },
    { kind: 'MARKET DATA', title: 'World market prices', body: 'Prices that move before your supplier prices do.', meta: 'Updated daily' },
    { kind: 'AI ESTIMATE', title: 'Forecast model', body: 'Combines all of the above to estimate the likely range. It is an estimate, not a promise.', meta: 'Re-run on every new invoice' },
];

export default function AskExplore() {
    const [open, setOpen] = useState(null);
    const [picked, setPicked] = useState(0);
    const [product, setProduct] = useState('Cooking oil 5L');
    const [view, setView] = useState('Simple picture');

    const p = PRODUCTS[product];

    return (
        <div className="mx-auto max-w-6xl px-5 py-7">
            <Card className="p-4 sm:p-5">
                <SectionTitle
                    title="New to inflation? Start with these 4 words"
                    sub="Tap a word to see what it means. You will see them in every answer below."
                />
                <ul className="mt-3">
                    {GLOSSARY.map((g, i) => (
                        <li key={g.q} className="border-t border-[var(--grid)] first:border-0">
                            <button
                                type="button"
                                onClick={() => setOpen(open === i ? null : i)}
                                aria-expanded={open === i}
                                className="flex w-full items-center justify-between gap-3 py-2.5 text-left text-[13.5px] font-medium"
                            >
                                {g.q}
                                <span className="text-[var(--ink-3)]">{open === i ? '−' : '+'}</span>
                            </button>
                            {open === i && <p className="pb-3 text-[13px] text-[var(--ink-2)]">{g.a}</p>}
                        </li>
                    ))}
                </ul>
            </Card>

            <h1 className="mt-7 text-[26px] font-semibold tracking-tight">What would you like to know?</h1>
            <p className="mt-1.5 max-w-2xl text-[13.5px] text-[var(--ink-2)]">
                Pick a question or type your own. No finance knowledge needed: every answer comes with a chart, a plain
                explanation and where the data came from.
            </p>

            <form className="mt-4 flex max-w-xl gap-2" onSubmit={(e) => e.preventDefault()}>
                <input
                    placeholder="e.g. Will coffee get more expensive?"
                    className="w-full rounded-md border border-[var(--line)] bg-[var(--surface)] px-3 py-2.5 text-[13px] outline-none focus:border-[var(--chrome)]"
                />
                <button type="submit" className="rounded-md bg-[var(--chrome)] px-4 py-2.5 text-[13px] font-medium text-white">
                    Show me
                </button>
            </form>

            <ul className="mt-4 grid gap-2.5 sm:grid-cols-3 lg:grid-cols-5">
                {QUESTIONS.map((q, i) => (
                    <li key={q.title}>
                        <button
                            type="button"
                            onClick={() => setPicked(i)}
                            aria-pressed={picked === i}
                            className={`h-full w-full rounded-lg border p-3 text-left ${
                                picked === i
                                    ? 'border-[var(--chrome)] bg-[var(--chrome)] text-white'
                                    : 'border-[var(--line)] bg-[var(--surface)]'
                            }`}
                        >
                            <span className="block text-[13px] font-semibold">{q.title}</span>
                            <span className={`mt-1 block text-[11.5px] ${picked === i ? 'text-white/75' : 'text-[var(--ink-2)]'}`}>
                                {q.sub}
                            </span>
                        </button>
                    </li>
                ))}
            </ul>

            <Card className="mt-5 p-4 sm:p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <span className="meta text-[var(--ink-3)]">YOUR SUPPLIES · NEXT 6 MONTHS</span>
                        <h2 className="mt-1 text-[19px] leading-snug font-semibold">
                            {product} will probably cost {EUR2(p.low)}–{EUR2(p.fast)} by March. Today: {EUR2(p.today)}.
                        </h2>
                    </div>
                    <span className="rounded-full bg-[var(--accent)] px-2.5 py-1 text-[12px] font-medium text-white">
                        Rising fast
                    </span>
                </div>

                <div className="mt-4 flex flex-wrap items-end justify-between gap-3">
                    <label className="text-[12px] text-[var(--ink-2)]">
                        Which product?
                        <select
                            value={product}
                            onChange={(e) => setProduct(e.target.value)}
                            className="mt-1 block rounded-md border border-[var(--line)] bg-[var(--surface)] px-2.5 py-2 text-[13px] text-[var(--ink-1)]"
                        >
                            {Object.keys(PRODUCTS).map((k) => (
                                <option key={k}>{k}</option>
                            ))}
                        </select>
                    </label>
                    <ShowAs value={view} onChange={setView} options={['Simple picture', 'Detailed chart']} />
                </div>

                <div className="mt-5 grid gap-5 lg:grid-cols-[1.6fr_1fr]">
                    <div>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <Tile tone="accent" value={`+${EUR2(p.likely - p.today)}`} label="more per unit by March (most likely)" />
                            <Tile value={`+${p.rise}%`} label="most likely price rise in 6 months" />
                            <Tile tone="chrome" value={`€${p.quarterExtra}`} label="extra for your business in the next 3 months" />
                        </div>

                        {view === 'Simple picture' ? <PriceBars p={p} /> : <HistoryTable p={p} />}

                        <p className="mt-4 rounded-lg bg-[#faf7f2] p-3.5 text-[13px]">
                            Today you pay <b>{EUR2(p.today)}</b> per unit. By March it will most likely cost{' '}
                            <b>{EUR2(p.likely)}</b>, which is <b>+{EUR2(p.likely - p.today)}</b> every time you buy it. If
                            prices rise fast, it could reach <b>{EUR2(p.fast)}</b>.
                        </p>
                    </div>

                    <div className="space-y-3">
                        <div className="rounded-lg border border-[var(--line)] p-3.5">
                            <span className="meta text-[var(--ink-3)]">IN SIMPLE WORDS</span>
                            <p className="mt-1.5 text-[13px] text-[var(--ink-2)]">
                                Over the last year you paid about {p.lastYear}% more for this. The price is likely to keep
                                climbing because {p.driver} prices are going up. For your business that means roughly €
                                {p.quarterExtra} extra in the next 3 months.
                            </p>
                        </div>
                        <div className="rounded-lg border border-[var(--line)] p-3.5">
                            <span className="meta text-[var(--ink-3)]">WHAT YOU CAN DO</span>
                            <p className="mt-1.5 text-[13px] text-[var(--ink-2)]">
                                Consider buying ahead while prices are lower, or lock a fixed price with your supplier.
                            </p>
                        </div>
                    </div>
                </div>

                <h3 className="mt-6 text-[14px] font-semibold">Where this data comes from</h3>
                <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {SOURCES.map((s) => (
                        <div key={s.title} className="rounded-lg border border-[var(--line)] p-3.5">
                            <Pill kind={s.kind === 'AI ESTIMATE' ? 'prediction' : s.kind === 'OFFICIAL STATISTICS' ? 'official' : s.kind === 'YOUR DATA' ? 'entered' : 'calculated'}>
                                {s.kind}
                            </Pill>
                            <div className="mt-2 text-[13px] font-semibold">{s.title}</div>
                            <p className="mt-1 text-[12px] text-[var(--ink-2)]">{s.body}</p>
                            <p className="meta mt-2 text-[var(--ink-3)]">{s.meta}</p>
                        </div>
                    ))}
                </div>

                <p className="mt-4 text-[12px] text-[var(--ink-2)]">
                    Demo: business figures are sample data for a café. In the live product they come from the owner&rsquo;s
                    own invoices and sales.
                </p>
            </Card>
        </div>
    );
}
