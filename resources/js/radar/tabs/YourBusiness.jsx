import { useMemo, useState } from 'react';
import { Card, Pill, SectionTitle, Tile } from '../shell';

const EUR = new Intl.NumberFormat('en-IE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });

const FIELDS = {
    sales: { label: 'Sales per month', hint: 'Total money customers pay you in an average month.', required: true, demo: 24000 },
    goods: { label: 'Goods and raw materials you buy', hint: 'Food, drinks, products to resell, packaging.', required: true, demo: 8400 },
    salaries: { label: 'Salaries', hint: 'All wages including contributions.', required: true, demo: 6000 },
    rent: { label: 'Rent', hint: 'For your shop or premises.', required: true, demo: 2200 },
    utilities: { label: 'Electricity, water, heating', hint: 'Your utility bills.', required: true, demo: 800 },
    fuel: { label: 'Fuel and delivery', hint: 'Petrol, diesel, delivery fees.', demo: 400 },
    other: { label: 'Other costs', hint: 'Accounting, internet, repairs, insurance.', demo: 2200 },
    cash: { label: 'Money in the bank today', hint: 'Business account balance.', required: true, demo: 9000 },
    drawings: { label: 'Money you take home each month', hint: 'What you pay yourself.', demo: 3500 },
    minCash: { label: 'Minimum cash you want to keep', hint: 'For emergencies. We warn you before you go below it.', demo: 5000 },
    loan: { label: 'Loan still owed', hint: 'Leave empty if none.', demo: 40000 },
    loanRate: { label: 'Loan interest rate', hint: 'Per year.', unit: '%', demo: 6.5 },
    salaryRise: { label: 'Planned salary increase', hint: 'Leave empty if none.', unit: '%', demo: 0 },
    rentRise: { label: 'Rent increase in your contract', hint: 'Leave empty if none.', unit: '%', demo: 0 },
};

const REQUIRED = Object.entries(FIELDS).filter(([, f]) => f.required).map(([k]) => k);

/** Driver mix per industry: how much of the goods bill follows which market index. */
const INDUSTRY_INFLATION = {
    cafe: 7.3,
    retail: 5.9,
    bakery: 8.1,
    manufacturing: 6.4,
    services: 4.2,
};

function Money({ id, field, value, onChange }) {
    return (
        <label className="block">
            <span className="text-[13px] font-medium text-[var(--ink-1)]">
                {field.label}{' '}
                <span className="text-[11px] font-normal text-[var(--ink-3)]">
                    {field.required ? 'required' : 'optional'}
                </span>
            </span>
            <div
                className={`mt-1.5 flex items-center rounded-md border bg-[var(--surface)] ${
                    field.required && !value ? 'border-[#c9b89a]' : 'border-[var(--line)]'
                } focus-within:border-[var(--chrome)]`}
            >
                <span className="pl-2.5 text-[13px] text-[var(--ink-3)]">{field.unit === '%' ? '' : '€'}</span>
                <input
                    id={id}
                    inputMode="decimal"
                    value={value}
                    onChange={(e) => onChange(e.target.value.replace(/[^\d.]/g, ''))}
                    placeholder={`e.g. ${field.demo}`}
                    className="w-full bg-transparent px-2 py-2 font-mono text-[13px] outline-none placeholder:text-[var(--ink-3)]"
                />
                {field.unit === '%' && <span className="pr-2.5 text-[13px] text-[var(--ink-3)]">%</span>}
            </div>
            <p className="mt-1 text-[11.5px] text-[var(--ink-2)]">{field.hint}</p>
        </label>
    );
}

function Group({ title, sub, children, cols = 3 }) {
    return (
        <Card className="mt-3.5 p-4 sm:p-5">
            <SectionTitle title={title} sub={sub} />
            <div className={`mt-4 grid gap-x-5 gap-y-4 ${cols === 3 ? 'sm:grid-cols-2 lg:grid-cols-3' : 'sm:grid-cols-2'}`}>
                {children}
            </div>
        </Card>
    );
}

export default function YourBusiness() {
    const [step, setStep] = useState(1);
    const [industry, setIndustry] = useState('cafe');
    const [v, setV] = useState(Object.fromEntries(Object.keys(FIELDS).map((k) => [k, ''])));
    const [products, setProducts] = useState([
        { name: 'Espresso', price: '1.50', share: '30' },
        { name: 'Sandwich', price: '3.20', share: '25' },
    ]);

    const set = (k) => (val) => setV((prev) => ({ ...prev, [k]: val }));
    const num = (k) => Number(v[k] || 0);

    const missing = REQUIRED.filter((k) => !v[k]);

    const result = useMemo(() => {
        const costs = num('goods') + num('salaries') + num('rent') + num('utilities') + num('fuel') + num('other');
        const profit = num('sales') - costs;
        const margin = num('sales') ? (profit / num('sales')) * 100 : 0;
        const inflation = INDUSTRY_INFLATION[industry];
        // Only the bought-in goods, fuel and utilities follow market prices; wages and
        // rent move by what the owner already told us is in their contracts.
        const exposed = num('goods') + num('utilities') + num('fuel');
        const extraPerMonth =
            (exposed * inflation) / 100 +
            (num('salaries') * Number(v.salaryRise || 0)) / 100 +
            (num('rent') * Number(v.rentRise || 0)) / 100;
        const profitAfter = profit - extraPerMonth;
        const runway = extraPerMonth > 0 && profitAfter < 0 ? num('cash') / Math.abs(profitAfter) : null;
        const priceRiseNeeded = num('sales') ? (extraPerMonth / num('sales')) * 100 : 0;

        return { costs, profit, margin, inflation, exposed, extraPerMonth, profitAfter, runway, priceRiseNeeded };
    }, [v, industry]);

    return (
        <div className="mx-auto max-w-6xl px-5 py-7">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-[26px] leading-tight font-semibold tracking-tight">
                        How will rising prices affect your business?
                    </h1>
                    <p className="mt-1.5 max-w-2xl text-[13.5px] text-[var(--ink-2)]">
                        You enter your numbers. We add official price data, make the forecast and show every
                        calculation. Nothing is hidden.
                    </p>
                </div>
                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={() => setV(Object.fromEntries(Object.entries(FIELDS).map(([k, f]) => [k, String(f.demo)])))}
                        className="rounded-md border border-[var(--line)] bg-[var(--surface)] px-3 py-2 text-[13px] font-medium hover:border-[var(--chrome)]"
                    >
                        Fill with an example café
                    </button>
                    <button
                        type="button"
                        onClick={() => setV(Object.fromEntries(Object.keys(FIELDS).map((k) => [k, ''])))}
                        className="rounded-md px-3 py-2 text-[13px] text-[var(--ink-2)] hover:text-[var(--ink-1)]"
                    >
                        Clear
                    </button>
                </div>
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-x-2.5 gap-y-1.5 text-[12px] text-[var(--ink-2)]">
                <span>Every number is labelled:</span>
                <Pill kind="entered" /> <span>your own numbers</span>
                <Pill kind="official" /> <span>Kosovo Agency of Statistics</span>
                <Pill kind="prediction" /> <span>our forecast</span>
                <Pill kind="assumption" /> <span>you can change it</span>
                <Pill kind="calculated" /> <span>simple maths from the above</span>
            </div>

            <ol className="mt-5 flex flex-wrap gap-2">
                {['Your business numbers', 'Your main products', 'Results and calculations'].map((label, i) => (
                    <li key={label}>
                        <button
                            type="button"
                            onClick={() => setStep(i + 1)}
                            className={`flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-[13px] ${
                                step === i + 1
                                    ? 'border-[var(--chrome)] bg-[var(--chrome)] text-white'
                                    : 'border-[var(--line)] bg-[var(--surface)] text-[var(--ink-2)]'
                            }`}
                        >
                            <span
                                className={`flex h-5 w-5 items-center justify-center rounded-full text-[11px] ${
                                    step === i + 1 ? 'bg-white text-[var(--chrome)]' : 'bg-[#eceae4] text-[var(--ink-2)]'
                                }`}
                            >
                                {i + 1}
                            </span>
                            {label}
                        </button>
                    </li>
                ))}
            </ol>

            {step === 1 && (
                <>
                    <Card className="mt-5 p-4 sm:p-5">
                        <label className="block">
                            <span className="text-[13px] font-medium">What kind of business is it?</span>
                            <select
                                value={industry}
                                onChange={(e) => setIndustry(e.target.value)}
                                className="mt-1.5 block w-full max-w-xs rounded-md border border-[var(--line)] bg-[var(--surface)] px-2.5 py-2 text-[13px]"
                            >
                                <option value="cafe">Café or restaurant</option>
                                <option value="bakery">Bakery</option>
                                <option value="retail">Shop or retail</option>
                                <option value="manufacturing">Small manufacturing</option>
                                <option value="services">Services</option>
                            </select>
                            <p className="mt-1.5 text-[11.5px] text-[var(--ink-2)]">
                                Used to pick which official price trend fits the goods you buy.
                            </p>
                        </label>
                    </Card>

                    <Group title="Money coming in" sub="Your average month." cols={2}>
                        <Money id="sales" field={FIELDS.sales} value={v.sales} onChange={set('sales')} />
                    </Group>

                    <Group
                        title="Money going out every month"
                        sub="Use an average month. Leave a field empty if it does not apply."
                    >
                        {['goods', 'salaries', 'rent', 'utilities', 'fuel', 'other'].map((k) => (
                            <Money key={k} id={k} field={FIELDS[k]} value={v[k]} onChange={set(k)} />
                        ))}
                    </Group>

                    <Group title="Cash and loans" sub="So we can tell you if you might run short of money.">
                        {['cash', 'drawings', 'minCash', 'loan', 'loanRate'].map((k) => (
                            <Money key={k} id={k} field={FIELDS[k]} value={v[k]} onChange={set(k)} />
                        ))}
                    </Group>

                    <Group title="Changes you already know about" sub="Things only you know, for the next 6 months." cols={2}>
                        {['salaryRise', 'rentRise'].map((k) => (
                            <Money key={k} id={k} field={FIELDS[k]} value={v[k]} onChange={set(k)} />
                        ))}
                    </Group>

                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <p className="text-[12.5px] text-[#8a6d3b]">
                            {missing.length
                                ? `Still needed: ${missing.map((k) => FIELDS[k].label.toLowerCase()).join(', ')}.`
                                : 'All required numbers are in.'}
                        </p>
                        <button
                            type="button"
                            onClick={() => setStep(2)}
                            className="rounded-md bg-[var(--chrome)] px-4 py-2.5 text-[13px] font-medium text-white"
                        >
                            Next: your main products
                        </button>
                    </div>
                </>
            )}

            {step === 2 && (
                <Card className="mt-5 p-4 sm:p-5">
                    <SectionTitle
                        title="Your main products"
                        sub="The few things you sell most. We use them to show what a price rise would do."
                    />
                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full border-collapse text-[13px]">
                            <thead>
                                <tr className="border-b border-[var(--axis)] text-left">
                                    <th className="py-2 pr-3 text-[11.5px] font-semibold text-[var(--ink-3)]">Product</th>
                                    <th className="py-2 pr-3 text-[11.5px] font-semibold text-[var(--ink-3)]">Price you charge</th>
                                    <th className="py-2 text-[11.5px] font-semibold text-[var(--ink-3)]">Share of sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                {products.map((p, i) => (
                                    <tr key={i} className="border-b border-[var(--grid)] last:border-0">
                                        {['name', 'price', 'share'].map((key) => (
                                            <td key={key} className="py-2 pr-3">
                                                <input
                                                    value={p[key]}
                                                    onChange={(e) =>
                                                        setProducts((list) =>
                                                            list.map((row, j) => (j === i ? { ...row, [key]: e.target.value } : row)),
                                                        )
                                                    }
                                                    className={`w-full rounded-md border border-[var(--line)] px-2 py-1.5 text-[13px] outline-none focus:border-[var(--chrome)] ${key === 'name' ? '' : 'font-mono'}`}
                                                />
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <button
                            type="button"
                            onClick={() => setProducts((l) => [...l, { name: '', price: '', share: '' }])}
                            className="rounded-md border border-[var(--line)] px-3 py-2 text-[13px]"
                        >
                            Add a product
                        </button>
                        <button
                            type="button"
                            onClick={() => setStep(3)}
                            className="rounded-md bg-[var(--chrome)] px-4 py-2.5 text-[13px] font-medium text-white"
                        >
                            Next: results and calculations
                        </button>
                    </div>
                </Card>
            )}

            {step === 3 && (
                <>
                    <Card className="mt-5 p-4 sm:p-5">
                        <SectionTitle
                            title="What this means for you"
                            sub="Every figure below is built only from the numbers you entered and the official price trend."
                        />
                        <div className="mt-4 grid gap-3 sm:grid-cols-3">
                            <Tile tone="accent" value={EUR.format(Math.round(result.extraPerMonth))} label="extra cost every month" />
                            <Tile value={`${result.margin.toFixed(1)}%`} label="profit margin today" />
                            <Tile
                                tone="chrome"
                                value={`+${result.priceRiseNeeded.toFixed(1)}%`}
                                label="price rise that holds your profit"
                            />
                        </div>

                        <div className="mt-5 overflow-x-auto">
                            <table className="w-full border-collapse text-[13px]">
                                <tbody>
                                    {[
                                        ['Sales per month', EUR.format(num('sales')), 'entered'],
                                        ['All costs per month', EUR.format(result.costs), 'calculated'],
                                        ['Profit per month today', EUR.format(result.profit), 'calculated'],
                                        [
                                            'Costs that follow market prices',
                                            EUR.format(result.exposed),
                                            'calculated',
                                        ],
                                        [
                                            `Price trend for ${industry === 'cafe' ? 'cafés and restaurants' : industry}`,
                                            `+${result.inflation}% a year`,
                                            'official',
                                        ],
                                        ['Extra cost every month', EUR.format(Math.round(result.extraPerMonth)), 'prediction'],
                                        [
                                            'Profit per month after that',
                                            EUR.format(Math.round(result.profitAfter)),
                                            'prediction',
                                        ],
                                    ].map(([label, value, kind]) => (
                                        <tr key={label} className="border-b border-[var(--grid)] last:border-0">
                                            <td className="py-2.5 pr-3">{label}</td>
                                            <td className="py-2.5 pr-3 text-right font-mono tabular-nums">{value}</td>
                                            <td className="w-px py-2.5 pl-2">
                                                <Pill kind={kind} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <p className="mt-4 rounded-lg bg-[#faf7f2] p-3.5 text-[13px] text-[var(--ink-1)]">
                            {result.profitAfter >= 0 ? (
                                <>
                                    Your costs rise about{' '}
                                    <b>{EUR.format(Math.round(result.extraPerMonth))}</b> a month. You stay profitable, at{' '}
                                    <b>{EUR.format(Math.round(result.profitAfter))}</b> a month, but you keep{' '}
                                    {result.margin.toFixed(0)} cents of every euro, so there is not much room. Raising
                                    prices by <b>{result.priceRiseNeeded.toFixed(1)}%</b> puts you back where you started.
                                </>
                            ) : (
                                <>
                                    Your costs rise about <b>{EUR.format(Math.round(result.extraPerMonth))}</b> a month,
                                    which turns your profit into a loss of{' '}
                                    <b>{EUR.format(Math.abs(Math.round(result.profitAfter)))}</b> a month.
                                    {result.runway !== null && (
                                        <>
                                            {' '}
                                            At that rate the money in your bank lasts about{' '}
                                            <b>{result.runway.toFixed(1)} months</b>.
                                        </>
                                    )}{' '}
                                    Raising prices by <b>{result.priceRiseNeeded.toFixed(1)}%</b> would cover it.
                                </>
                            )}
                        </p>
                    </Card>

                    <p className="mt-3 text-[12px] text-[var(--ink-2)]">
                        This is decision support, not financial advice. The price trend is an official index for your
                        industry, not a promise about your own suppliers.
                    </p>
                </>
            )}
        </div>
    );
}
