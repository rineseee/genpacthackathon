import { useMemo } from 'react';
import { companyId, getJson, monthName, useApi } from '../api';
import { Card, Pill, SectionTitle } from '../shell';

export default function DataSources() {
    const drivers = useApi(() => getJson('/api/v1/price-drivers'));
    const lines = useApi(() => getJson(`/api/v1/companies/${companyId()}/cost-lines`));

    const sources = useMemo(() => {
        const list = drivers.data ?? [];
        const latest = list.map((d) => d.latest?.period).filter(Boolean).sort().at(-1);
        const subgroupLatest = list
            .filter((d) => d.code !== 'cpi.headline' && d.kind !== 'wages')
            .map((d) => d.latest?.period)
            .filter(Boolean)
            .sort()
            .at(-1);
        const invoiceMonths = (lines.data ?? []).flatMap((l) => l.unit_price?.history?.map((h) => h.period) ?? []).sort();

        return [
            {
                kind: 'official',
                name: 'Kosovo Agency of Statistics (ASK)',
                what: `${list.length} official series: headline HICP and its food, energy, transport and catering subgroups, plus average wages.`,
                used: 'The national trend each of your cost lines is compared against and forecast from.',
                link: 'https://askdata.rks-gov.net/',
                meta: latest
                    ? `monthly · headline to ${monthName(latest)}, subgroups to ${monthName(subgroupLatest)}`
                    : 'loading…',
            },
            {
                kind: 'entered',
                name: 'Your purchase invoices',
                what: 'What you actually paid, line by line, with supplier and date.',
                used: 'Learns how strongly and how fast your own prices follow each official series.',
                meta: invoiceMonths.length ? `latest invoice month: ${monthName(invoiceMonths.at(-1))}` : 'on every import',
            },
        ];
    }, [drivers.data, lines.data]);

    return (
        <div className="mx-auto max-w-6xl px-5 py-7">
            <span className="meta text-[var(--ink-3)]">PROVENANCE</span>
            <h1 className="mt-1.5 text-[26px] font-semibold tracking-tight">Where every number comes from.</h1>
            <p className="mt-1.5 max-w-2xl text-[13.5px] text-[var(--ink-2)]">
                Public data is cited and refreshed on a schedule. Your own data never leaves your account and is only
                used to learn how your prices follow the market.
            </p>

            {drivers.error && (
                <p className="mt-4 rounded-lg bg-[#fbeaea] p-3 text-[13px] text-[#8a2a2a]">{drivers.error.message}</p>
            )}

            <div className="mt-5 grid gap-3 lg:grid-cols-2">
                {sources.map((s) => (
                    <Card key={s.name} className="p-4">
                        <div className="flex items-start justify-between gap-3">
                            <SectionTitle title={s.name} />
                            <Pill kind={s.kind}>{s.kind === 'entered' ? 'YOUR DATA' : 'OFFICIAL DATA'}</Pill>
                        </div>
                        <p className="mt-2 text-[12.5px] text-[var(--ink-2)]">{s.what}</p>
                        <p className="mt-1.5 text-[12.5px] text-[var(--ink-2)]">
                            <b className="font-medium text-[var(--ink-1)]">How it is used:</b> {s.used}
                        </p>
                        <div className="mt-2.5 flex items-center justify-between">
                            <span className="meta text-[var(--ink-3)]">{s.meta}</span>
                            {s.link && (
                                <a
                                    href={s.link}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-[12px] text-[var(--accent)] underline underline-offset-2"
                                >
                                    Open source
                                </a>
                            )}
                        </div>
                    </Card>
                ))}
            </div>

            {drivers.data && (
                <Card className="mt-4 overflow-x-auto p-4">
                    <SectionTitle title="Official series in use" sub="Latest month published by ASK and the change over 12 months." />
                    <table className="mt-3 w-full border-collapse text-[12.5px]">
                        <tbody>
                            {drivers.data.map((d) => (
                                <tr key={d.code} className="border-b border-[var(--grid)] last:border-0">
                                    <td className="py-2 pr-3">{d.name}</td>
                                    <td className="py-2 pr-3 text-[var(--ink-2)]">{monthName(d.latest?.period)}</td>
                                    <td className="py-2 text-right font-mono tabular-nums">
                                        {d.latest?.change_last_12_months
                                            ? `${d.latest.change_last_12_months.value > 0 ? '+' : ''}${d.latest.change_last_12_months.value.toFixed(1)}%`
                                            : '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Card>
            )}

            <p className="mt-4 text-[12px] text-[var(--ink-2)]">
                Official series are real Kosovo Agency of Statistics data. The demo café&rsquo;s invoices and sales are
                sample data; in the live product they come from the owner&rsquo;s own records.
            </p>
        </div>
    );
}
