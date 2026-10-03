import { useEffect, useState } from 'react';
import { fetchRadar, formatValue, formatRange } from './api';
import { Card, Empty, Hero, LabelChip, Legend, StatTile } from './ui';
import { ProfitChart, RiskBars } from './charts';

export default function Radar({ companyId = 1 }) {
    const [radar, setRadar] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        const ac = new AbortController();
        setError(null);

        fetchRadar(companyId, { signal: ac.signal })
            .then(setRadar)
            .catch((err) => {
                if (err.name !== 'AbortError') setError(err.message);
            });

        return () => ac.abort();
    }, [companyId]);

    if (error) {
        return (
            <Card title="Could not load the radar">
                <p className="text-sm text-[var(--ink-2)]">
                    {error}. Is the API running? Try <code>composer run dev</code>, then{' '}
                    <code>php artisan migrate:fresh --seed</code>.
                </p>
            </Card>
        );
    }

    if (!radar) {
        return <p className="py-16 text-center text-sm text-[var(--ink-3)]">Loading the radar…</p>;
    }

    const palette = getComputedStyle(document.documentElement);
    const s1 = palette.getPropertyValue('--series-1').trim();
    const s2 = palette.getPropertyValue('--series-2').trim();

    return (
        <div className="mx-auto max-w-6xl px-5 py-8">
            <header className="flex flex-wrap items-baseline justify-between gap-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight text-[var(--ink-1)]">
                        {radar.company?.name ?? 'Margin Shield'}
                    </h1>
                    <p className="mt-1 text-sm text-[var(--ink-2)]">
                        What inflation is doing to this company&rsquo;s costs, margin and cash.
                    </p>
                </div>
                <span className="text-[13px] text-[var(--ink-3)]">As of {radar.as_of}</span>
            </header>

            {radar.summary?.text && (
                <p className="mt-5 rounded-lg border border-[var(--line)] bg-[var(--surface)] p-4 text-sm text-[var(--ink-1)]">
                    {radar.summary.text}
                    <span className="ml-2 align-middle">
                        <LabelChip label={radar.summary.label} />
                    </span>
                </p>
            )}

            <section className="mt-4 grid gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
                <Hero
                    label="Profit lost to inflation this month"
                    value={formatValue(radar.profit_lost_to_inflation_this_month)}
                    note={
                        radar.company_cost_inflation
                            ? `Costs ${formatValue(radar.company_cost_inflation)} against selling prices ${formatValue(radar.selling_price_growth)}.`
                            : null
                    }
                />
                <StatTile label="Margin at risk next quarter" labelled={radar.margin_at_risk_next_quarter} />
                <StatTile
                    label="Protectable with the plan"
                    labelled={radar.protectable_next_quarter}
                    note="Profit the recommended actions win back."
                />
                <StatTile
                    label="Cash-stress probability, do nothing"
                    labelled={radar.do_nothing?.stress_probability}
                    note={
                        radar.with_plan?.stress_probability
                            ? `With the plan: ${formatValue(radar.with_plan.stress_probability)}.`
                            : null
                    }
                />
            </section>

            <div className="mt-3.5 grid gap-3.5 lg:grid-cols-2">
                <Card
                    title="Monthly profit forecast"
                    subtitle="The line is the median path; the band is the 10th to 90th percentile of 5,000 simulated paths."
                    action={
                        <Legend
                            items={[
                                { label: 'With the plan', colour: s1 },
                                { label: 'Do nothing', colour: s2 },
                            ]}
                        />
                    }
                >
                    <ProfitChart doNothing={radar.do_nothing} withPlan={radar.with_plan} />
                </Card>

                <Card
                    title="Where the money goes"
                    subtitle="Extra cost over the next quarter, by cost line. Forecast."
                >
                    {radar.top_risks?.length ? <RiskBars risks={radar.top_risks} /> : <Empty>No cost line is forecast to rise.</Empty>}
                </Card>
            </div>

            <Card title="What to do about it" subtitle="Each action is simulated; the value is the profit it wins back." className="mt-3.5">
                {radar.top_actions?.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr className="border-b border-[var(--axis)] text-left">
                                    <th className="py-2 pr-3 text-xs font-semibold text-[var(--ink-3)]">Action</th>
                                    <th className="py-2 pr-3 text-right text-xs font-semibold text-[var(--ink-3)]">Profit gain</th>
                                    <th className="py-2 pr-3 text-right text-xs font-semibold text-[var(--ink-3)]">Next quarter</th>
                                    <th className="py-2 text-right text-xs font-semibold text-[var(--ink-3)]">Stress change</th>
                                </tr>
                            </thead>
                            <tbody>
                                {radar.top_actions.map((a, i) => (
                                    <tr key={a.title ?? i} className="border-b border-[var(--grid)] last:border-0">
                                        <td className="py-2.5 pr-3">
                                            <div className="font-medium text-[var(--ink-1)]">{a.title}</div>
                                            <LabelChip label={a.label} />
                                        </td>
                                        <td className="py-2.5 pr-3 text-right tabular-nums text-[var(--ink-1)]">
                                            {formatValue(a.expected_profit_gain)}
                                        </td>
                                        <td className="py-2.5 pr-3 text-right tabular-nums text-[var(--ink-1)]">
                                            {formatValue(a.expected_profit_gain_next_quarter)}
                                            <div className="text-[11px] text-[var(--ink-3)]">
                                                {formatRange(a.expected_profit_gain_next_quarter)}
                                            </div>
                                        </td>
                                        <td className="py-2.5 text-right tabular-nums text-[var(--ink-1)]">
                                            {formatValue(a.stress_probability_change)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <Empty>No action is worth taking yet.</Empty>
                )}
            </Card>

            {radar.supplier_flags?.count > 0 && (
                <Card className="mt-3.5">
                    <p className="text-sm text-[var(--ink-1)]">
                        <b>{radar.supplier_flags.count}</b> supplier
                        {radar.supplier_flags.count === 1 ? ' is' : 's are'} raising prices faster than the market, costing{' '}
                        <b>{formatValue(radar.supplier_flags.monthly_overcharge)}</b> a month.
                    </p>
                </Card>
            )}
        </div>
    );
}
