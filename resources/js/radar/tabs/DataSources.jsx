import { Card, Pill, SectionTitle } from '../shell';

const SOURCES = [
    {
        kind: 'official',
        name: 'Kosovo Agency of Statistics (ASK)',
        what: 'Consumer and producer price indices, by category and month.',
        used: 'The national trend each of your cost lines is compared against.',
        link: 'https://askdata.rks-gov.net/',
        meta: 'monthly · latest Aug 2026',
    },
    {
        kind: 'official',
        name: 'World Bank Pink Sheet',
        what: 'Monthly world commodity prices: grains, oils, energy, metals.',
        used: 'The market index behind flour, cooking oil and fuel.',
        link: 'https://www.worldbank.org/en/research/commodity-markets',
        meta: 'monthly',
    },
    {
        kind: 'official',
        name: 'European Commission milk and dairy prices',
        what: 'Raw milk and dairy product prices across the EU.',
        used: 'The market index behind milk, butter and cheese lines.',
        link: 'https://agridata.ec.europa.eu/',
        meta: 'weekly',
    },
    {
        kind: 'entered',
        name: 'Your purchase invoices',
        what: 'What you actually paid, line by line, with supplier and date.',
        used: 'Learns how strongly your own prices follow each market index.',
        meta: 'on every import',
    },
];

export default function DataSources() {
    return (
        <div className="mx-auto max-w-6xl px-5 py-7">
            <span className="meta text-[var(--ink-3)]">PROVENANCE</span>
            <h1 className="mt-1.5 text-[26px] font-semibold tracking-tight">Where every number comes from.</h1>
            <p className="mt-1.5 max-w-2xl text-[13.5px] text-[var(--ink-2)]">
                Public data is cited and refreshed on a schedule. Your own data never leaves your account and is only
                used to learn how your prices follow the market.
            </p>

            <div className="mt-5 grid gap-3 lg:grid-cols-2">
                {SOURCES.map((s) => (
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

            <p className="mt-4 text-[12px] text-[var(--ink-2)]">
                Demo figures are synthetic but anchored to the ASK August 2026 year-on-year numbers. Commodity, energy and
                wage series shown here are illustrative until the live importers run.
            </p>
        </div>
    );
}
