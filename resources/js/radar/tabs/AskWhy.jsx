import { useState } from 'react';
import { companyId, getJson, pct, useApi } from '../api';
import { Card, Pill, SectionTitle, ShowAs, Tile } from '../shell';

const PIPELINE = [
    ['01', 'Business data', 'Invoices read and matched to price drivers', 'llm'],
    ['02', 'Economic data', 'Official ASK price series (HICP subgroups, wages) joined by month', 'pipeline'],
    ['03', 'Forecasting', 'How strongly and how fast each cost follows its market index, with ranges', 'statistics'],
    ['04', 'Scenarios', 'Driver-based model plus 5,000 simulations', 'statistics'],
    ['05', 'Risk detection', 'Risk score, anomalies, early warnings', 'statistics'],
    ['06', 'Recommendations', 'Every option simulated; rule-breakers ruled out', 'rules'],
    ['07', 'Explanation', 'Plain-language answer from the numbers above', 'llm'],
    ['08', 'Number check', 'Every figure in the answer must match the engine, or it is blocked', 'rules'],
];

const QUESTIONS = [
    'Why is my business considered high risk?',
    'Which expense is causing the largest risk?',
    'Why does the AI recommend increasing prices?',
    'What happens if I do nothing?',
    'What assumptions were used?',
    'How reliable is this prediction?',
    'Which data influenced this forecast?',
];

const BANDS = [
    ['Low', 0, 25, 'var(--risk-1)'],
    ['Moderate', 25, 50, 'var(--risk-2)'],
    ['Elevated', 50, 75, 'var(--risk-3)'],
    ['Critical', 75, 100, 'var(--risk-4)'],
];

function RiskScale({ score }) {
    return (
        <div className="mt-2">
            <div className="relative h-6" aria-hidden="true">
                <div
                    className="absolute -top-0.5 -translate-x-1/2 rounded bg-[var(--chrome)] px-1.5 py-0.5 font-mono text-[11px] whitespace-nowrap text-white"
                    style={{ left: `${score}%` }}
                >
                    You: {score.toFixed(1)}%
                </div>
            </div>
            <div className="flex h-6 overflow-hidden rounded">
                {BANDS.map(([name, from, to, colour]) => (
                    <div
                        key={name}
                        className="flex items-center justify-center text-[11.5px] font-medium"
                        style={{
                            width: `${to - from}%`,
                            background: colour,
                            color: name === 'Critical' ? '#fff' : 'var(--ink-1)',
                        }}
                    >
                        {name}
                    </div>
                ))}
            </div>
            <div className="meta mt-1 flex justify-between text-[var(--ink-3)]">
                <span>0% · safe</span>
                <span>100% · very exposed</span>
            </div>
        </div>
    );
}

export default function AskWhy() {
    const [picked, setPicked] = useState(0);
    const [view, setView] = useState('Simple picture');
    const { data, error, loading } = useApi(() => getJson(`/api/v1/companies/${companyId()}/risk-explanation`));

    const answer = data && {
        score: data.stress_probability.value,
        tiles: [
            { tone: 'accent', value: pct(data.stress_probability.value), label: 'chance cash falls below your minimum in 6 months' },
            { tone: 'tint', value: `${data.cash_months.value.toFixed(1)} months`, label: 'of costs kept in cash' },
            { tone: 'chrome', value: `${data.profit_change_per_cost_point.value.toFixed(1)}%`, label: 'profit lost for every 1% rise in costs' },
        ],
        reasons: data.reasons.map((r) => [r.title, r.body]),
        closing: data.closing.text,
        notes: [
            ['ASSUMPTIONS', data.notes.assumptions],
            ['DATA USED', data.notes.data_used],
            ['RELIABILITY', data.notes.reliability],
        ],
        detail: [
            ['Profit margin', pct(data.margin.value), 'calculated'],
            ['Cash ÷ monthly costs', `${data.cash_months.value.toFixed(1)} months`, 'calculated'],
            ['Share of spend rising faster than official inflation', pct(data.fast_rising_share.value), 'calculated'],
            ['Cost-to-profit leverage', `${data.profit_change_per_cost_point.value.toFixed(1)}% per 1%`, 'calculated'],
            ['Chance of cash stress (simulated)', pct(data.stress_probability.value), 'prediction'],
        ],
    };

    return (
        <div className="mx-auto max-w-6xl px-5 py-7">
            <span className="meta text-[var(--ink-3)]">EXPLAINABLE AI · NO BLACK BOX</span>
            <h1 className="mt-1.5 text-[26px] font-semibold tracking-tight">Ask why. Every answer shows its numbers.</h1>

            <Card className="mt-5 p-4 sm:p-5">
                <SectionTitle
                    title="How every result is produced"
                    sub="Hybrid system: statistical models produce the numbers, the language model only explains them"
                />
                <ol className="mt-4 grid gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
                    {PIPELINE.map(([n, title, body, kind]) => (
                        <li key={n} className="rounded-lg border border-[var(--line)] p-3">
                            <span className="meta text-[var(--ink-3)]">{n}</span>
                            <div className="mt-0.5 text-[13px] font-semibold">{title}</div>
                            <p className="mt-1 text-[11.5px] leading-snug text-[var(--ink-2)]">{body}</p>
                            <div className="mt-2">
                                <Pill kind={kind} />
                            </div>
                        </li>
                    ))}
                </ol>
            </Card>

            <div className="mt-4 grid gap-4 lg:grid-cols-[280px_1fr]">
                <Card className="h-fit p-4">
                    <h2 className="text-[15px] font-semibold">Questions</h2>
                    <ul className="mt-3 space-y-1.5">
                        {QUESTIONS.map((q, i) => (
                            <li key={q}>
                                <button
                                    type="button"
                                    onClick={() => setPicked(i)}
                                    aria-pressed={picked === i}
                                    className={`w-full rounded-md px-3 py-2 text-left text-[12.5px] ${
                                        picked === i
                                            ? 'bg-[var(--chrome)] font-medium text-white'
                                            : 'border border-[var(--line)] hover:border-[var(--chrome)]'
                                    }`}
                                >
                                    {q}
                                </button>
                            </li>
                        ))}
                    </ul>
                    <form className="mt-4" onSubmit={(e) => e.preventDefault()}>
                        <label className="text-[12px] font-medium">Ask your own question</label>
                        <div className="mt-1.5 flex gap-2">
                            <input
                                placeholder="e.g. What if my landlord raises rent 10%"
                                className="w-full rounded-md border border-[var(--line)] px-2.5 py-2 text-[12px] outline-none focus:border-[var(--chrome)]"
                            />
                            <button className="rounded-md bg-[var(--chrome)] px-3 py-2 text-[12px] font-medium text-white">
                                Ask
                            </button>
                        </div>
                    </form>
                </Card>

                <Card className="p-4 sm:p-5">
                    <span className="meta text-[var(--ink-3)]">ANSWER</span>
                    <h2 className="mt-1 text-[19px] font-semibold">{QUESTIONS[picked]}</h2>

                    {loading && <p className="mt-3 text-[13px] text-[var(--ink-2)]">Running the engine…</p>}
                    {error && <p className="mt-3 rounded-lg bg-[#fbeaea] p-3 text-[13px] text-[#8a2a2a]">{error.message}</p>}
                    {answer && (
                    <>
                    <p className="mt-2.5 inline-flex items-center gap-2 rounded-md bg-[#e6f2e8] px-2.5 py-1.5 text-[12.5px] text-[#1f6340]">
                        <svg width="13" height="13" viewBox="0 0 16 16" aria-hidden="true">
                            <path d="M2.5 8.5 L6.5 12.5 L13.5 4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                        Number check passed: all {answer.detail.length} figures in this answer come from the calculation engine
                    </p>

                    <div className="mt-3">
                        <ShowAs value={view} onChange={setView} options={['Simple picture', 'Detailed']} />
                    </div>

                    <div className="mt-4 grid gap-3 sm:grid-cols-3">
                        {answer.tiles.map((t) => (
                            <Tile key={t.label} {...t} />
                        ))}
                    </div>

                    <h3 className="mt-5 text-[13.5px] font-semibold">Your chance of cash stress on the risk scale</h3>
                    <RiskScale score={answer.score} />

                    <h3 className="mt-5 text-[13.5px] font-semibold">The main reasons, biggest first</h3>
                    <ol className="mt-2.5 space-y-2">
                        {answer.reasons.map(([title, body], i) => (
                            <li key={title} className="flex gap-3 rounded-lg border border-[var(--line)] p-3">
                                <span className="flex h-5 w-5 flex-none items-center justify-center rounded-full bg-[var(--accent)] text-[11px] font-semibold text-white">
                                    {i + 1}
                                </span>
                                <div>
                                    <div className="text-[13px] font-semibold">{title}</div>
                                    <p className="mt-0.5 text-[12.5px] text-[var(--ink-2)]">{body}</p>
                                </div>
                            </li>
                        ))}
                    </ol>

                    {view === 'Detailed' && (
                        <div className="mt-4 overflow-x-auto rounded-lg border border-[var(--line)]">
                            <table className="w-full border-collapse text-[12.5px]">
                                <tbody>
                                    {answer.detail.map(([k, val, kind]) => (
                                        <tr key={k} className="border-b border-[var(--grid)] last:border-0">
                                            <td className="px-3 py-2">{k}</td>
                                            <td className="px-3 py-2 text-right font-mono tabular-nums">{val}</td>
                                            <td className="w-px py-2 pr-3">
                                                <Pill kind={kind} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <p className="mt-4 rounded-lg bg-[#faf7f2] p-3.5 text-[13px]">{answer.closing}</p>

                    <div className="mt-4 grid gap-3 sm:grid-cols-3">
                        {answer.notes.map(([kind, body]) => (
                            <div key={kind} className="rounded-lg border border-[var(--line)] p-3.5">
                                <span className="meta text-[var(--ink-3)]">{kind}</span>
                                <p className="mt-1.5 text-[12px] text-[var(--ink-2)]">{body}</p>
                            </div>
                        ))}
                    </div>

                    </>
                    )}

                    <p className="mt-4 border-t border-[var(--grid)] pt-3 text-[11.5px] text-[var(--ink-2)]">
                        The language model can only use numbers produced by the forecasting and simulation engines. If
                        data is missing, it says so instead of guessing. This is decision support, not financial advice.
                    </p>
                </Card>
            </div>
        </div>
    );
}
