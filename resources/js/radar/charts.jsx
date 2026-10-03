import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { formatValue } from './api';

/* Charts are hand-rolled SVG measured in real pixels, so axis text keeps its
 * size instead of shrinking with a scaled viewBox on narrow screens. */

const EUR0 = new Intl.NumberFormat('en-IE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });

function useWidth() {
    const ref = useRef(null);
    const [width, setWidth] = useState(0);

    useLayoutEffect(() => {
        const node = ref.current;
        if (!node) return undefined;

        setWidth(node.clientWidth);
        const ro = new ResizeObserver(([entry]) => setWidth(Math.floor(entry.contentRect.width)));
        ro.observe(node);

        return () => ro.disconnect();
    }, []);

    return [ref, width];
}

/** Read the palette at render time so a theme change repaints the marks. */
function usePalette() {
    const [, force] = useState(0);

    useEffect(() => {
        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => force((n) => n + 1);
        mq.addEventListener('change', onChange);

        return () => mq.removeEventListener('change', onChange);
    }, []);

    const read = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    return {
        s1: read('--series-1'),
        s2: read('--series-2'),
        grid: read('--grid'),
        axis: read('--axis'),
        muted: read('--ink-3'),
        ink: read('--ink-1'),
        surface: read('--surface'),
    };
}

function niceTicks(min, max, want = 4) {
    const span = max - min;
    if (!Number.isFinite(span) || span <= 0) return [min];

    const mag = 10 ** Math.floor(Math.log10(span / want));
    const step = [1, 2, 2.5, 5, 10].map((m) => m * mag).find((c) => c >= span / want) ?? 10 * mag;
    const out = [];
    for (let t = Math.ceil(min / step) * step; t <= max + step * 1e-9; t += step) out.push(t);

    return out;
}

const shortMonth = (ym) => {
    const [y, m] = String(ym).split('-');
    return m ? `${['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][+m - 1]} ${y.slice(2)}` : ym;
};

/**
 * Monthly profit under two scenarios. Each is a p50 line with its p10–p90 band,
 * because a forecast is never shown as a single line here.
 */
export function ProfitChart({ doNothing, withPlan }) {
    const [ref, width] = useWidth();
    const p = usePalette();
    const [cursor, setCursor] = useState(null);

    const months = doNothing?.monthly_profit ?? [];
    if (months.length < 2) return <div ref={ref} />;

    const series = [
        { key: 'plan', name: 'With the plan', colour: p.s1, rows: withPlan?.monthly_profit ?? [] },
        { key: 'nothing', name: 'Do nothing', colour: p.s2, rows: months },
    ].filter((sx) => sx.rows.length >= 2);

    const W = Math.max(300, width || 560);
    const padL = 56;
    const padR = 20;
    const padT = 14;
    const padB = 30;
    const H = 250;
    const iw = W - padL - padR;
    const ih = H - padT - padB;

    const all = series.flatMap((sx) => sx.rows.flatMap((r) => [r.p10, r.p90]));
    const lo = Math.min(...all, 0);
    const hi = Math.max(...all);
    const pad = (hi - lo || 1) * 0.08;
    const d0 = lo - pad;
    const d1 = hi + pad;
    const x = (i) => padL + (i * iw) / (months.length - 1);
    const y = (v) => padT + ih - ((v - d0) / (d1 - d0)) * ih;

    const stride = Math.max(1, Math.ceil((months.length * 42) / iw));
    const ticks = months.map((_, i) => i).filter((i) => i % stride === 0);
    if (ticks.at(-1) !== months.length - 1) {
        if (months.length - 1 - ticks.at(-1) < stride * 0.7) ticks.pop();
        ticks.push(months.length - 1);
    }

    const near = (clientX, rect) => {
        const px = ((clientX - rect.left) / rect.width) * W;
        let best = 0;
        months.forEach((_, i) => {
            if (Math.abs(x(i) - px) < Math.abs(x(best) - px)) best = i;
        });
        return best;
    };

    return (
        <div ref={ref} className="relative">
            <svg
                viewBox={`0 0 ${W} ${H}`}
                width="100%"
                height={H}
                role="img"
                tabIndex={0}
                aria-label={`Monthly profit forecast over ${months.length} months, do nothing versus the recommended plan.`}
                onPointerMove={(e) => setCursor(near(e.clientX, e.currentTarget.getBoundingClientRect()))}
                onPointerLeave={() => setCursor(null)}
                onKeyDown={(e) => {
                    if (e.key === 'ArrowRight') setCursor((c) => Math.min(months.length - 1, (c ?? 0) + 1));
                    else if (e.key === 'ArrowLeft') setCursor((c) => Math.max(0, (c ?? 0) - 1));
                    else if (e.key === 'Escape') setCursor(null);
                    else return;
                    e.preventDefault();
                }}
                className="block focus-visible:outline-2 focus-visible:outline-[var(--ink-1)]"
            >
                {niceTicks(d0, d1).map((t) => (
                    <g key={t}>
                        <line x1={padL} x2={padL + iw} y1={y(t)} y2={y(t)} stroke={p.grid} strokeWidth="1" />
                        <text
                            x={padL - 8}
                            y={y(t) + 3.5}
                            textAnchor="end"
                            fill={p.muted}
                            fontSize="11"
                            style={{ fontVariantNumeric: 'tabular-nums' }}
                        >
                            {/* Guard against a -0 tick rendering as "−€0". */}
                            {EUR0.format(Math.abs(t) < 1e-6 ? 0 : t)}
                        </text>
                    </g>
                ))}

                {ticks.map((i) => (
                    <text key={i} x={x(i)} y={H - 10} textAnchor="middle" fill={p.muted} fontSize="11">
                        {shortMonth(months[i].month)}
                    </text>
                ))}

                <line x1={padL} x2={padL + iw} y1={padT + ih} y2={padT + ih} stroke={p.axis} strokeWidth="1" />

                {series.map((sx) => {
                    const top = sx.rows.map((r, i) => `${i ? 'L' : 'M'}${x(i)} ${y(r.p90)}`).join(' ');
                    const bottom = sx.rows
                        .map((r, i) => `${x(sx.rows.length - 1 - i)} ${y(sx.rows.at(-1 - i).p10)}`)
                        .join(' L');

                    return (
                        <g key={sx.key}>
                            {/* p10–p90 band as a 10% wash, never a saturated block */}
                            <path d={`${top} L${bottom} Z`} fill={sx.colour} fillOpacity="0.1" />
                            <path
                                d={sx.rows.map((r, i) => `${i ? 'L' : 'M'}${x(i)} ${y(r.p50)}`).join(' ')}
                                fill="none"
                                stroke={sx.colour}
                                strokeWidth="2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                            <circle
                                cx={x(sx.rows.length - 1)}
                                cy={y(sx.rows.at(-1).p50)}
                                r="4.5"
                                fill={sx.colour}
                                stroke={p.surface}
                                strokeWidth="2"
                            />
                        </g>
                    );
                })}

                {cursor !== null && (
                    <g>
                        <line x1={x(cursor)} x2={x(cursor)} y1={padT} y2={padT + ih} stroke={p.axis} strokeWidth="1" />
                        {series.map((sx) =>
                            sx.rows[cursor] ? (
                                <circle
                                    key={sx.key}
                                    cx={x(cursor)}
                                    cy={y(sx.rows[cursor].p50)}
                                    r="4.5"
                                    fill={sx.colour}
                                    stroke={p.surface}
                                    strokeWidth="2"
                                />
                            ) : null,
                        )}
                    </g>
                )}
            </svg>

            {cursor !== null && (
                <div
                    className="pointer-events-none absolute top-2 right-2 rounded-md border border-[var(--line)] bg-[var(--surface)] px-2.5 py-1.5 text-xs shadow-lg"
                    role="status"
                >
                    <div className="font-semibold text-[var(--ink-1)]">{shortMonth(months[cursor].month)}</div>
                    {series.map((sx) =>
                        sx.rows[cursor] ? (
                            <div key={sx.key} className="text-[var(--ink-2)]">
                                <span className="inline-block h-0.5 w-3 rounded-full align-middle" style={{ background: sx.colour }} />{' '}
                                {sx.name}: <b className="text-[var(--ink-1)]">{EUR0.format(sx.rows[cursor].p50)}</b>{' '}
                                <span className="text-[var(--ink-3)]">
                                    ({EUR0.format(sx.rows[cursor].p10)}–{EUR0.format(sx.rows[cursor].p90)})
                                </span>
                            </div>
                        ) : null,
                    )}
                </div>
            )}
        </div>
    );
}

/**
 * Extra cost per cost line next quarter. One measure, nominal categories, all the
 * same sign — so it is one hue for every bar, not a ramp.
 */
export function RiskBars({ risks }) {
    const [ref, width] = useWidth();
    const p = usePalette();
    const [hover, setHover] = useState(null);

    const rows = (risks ?? []).filter((r) => r.extra_cost_next_quarter);
    if (!rows.length) return <div ref={ref} />;

    const W = Math.max(300, width || 520);
    const nameW = Math.min(120, Math.max(76, Math.round(W * 0.28)));
    const padL = nameW + 10;
    const padR = 76;
    const padT = 6;
    const band = 34;
    const H = padT + rows.length * band + 6;
    const iw = W - padL - padR;
    const max = Math.max(...rows.map((r) => r.extra_cost_next_quarter.value)) * 1.05 || 1;
    const barH = Math.min(24, band - 10);

    return (
        <div ref={ref} className="relative">
            <svg viewBox={`0 0 ${W} ${H}`} width="100%" height={H} role="img"
                aria-label={`Extra cost next quarter by cost line. ${rows.map((r) => `${r.name} ${formatValue(r.extra_cost_next_quarter)}`).join(', ')}.`}>
                {rows.map((r, i) => {
                    const cy = padT + i * band + band / 2;
                    const w = Math.max(0, (r.extra_cost_next_quarter.value / max) * iw);
                    const top = cy - barH / 2;
                    const r4 = Math.min(4, w);

                    return (
                        <g key={r.cost_line_id ?? r.name}>
                            <text x={padL - 12} y={cy + 4} textAnchor="end" fill={p.muted} fontSize="12">
                                {r.name}
                            </text>
                            {w > 0 && (
                                <path
                                    d={`M${padL} ${top} H${padL + w - r4} a${r4} ${r4} 0 0 1 ${r4} ${r4} V${top + barH - r4} a${r4} ${r4} 0 0 1 ${-r4} ${r4} H${padL} Z`}
                                    fill={p.s1}
                                />
                            )}
                            <text
                                x={padL + w + 8}
                                y={cy + 4}
                                fill={p.ink}
                                fontSize="12"
                                style={{ fontVariantNumeric: 'tabular-nums' }}
                            >
                                {formatValue(r.extra_cost_next_quarter)}
                            </text>
                            <rect
                                x={padL}
                                y={padT + i * band}
                                width={iw}
                                height={band}
                                fill="transparent"
                                onPointerEnter={() => setHover(i)}
                                onPointerLeave={() => setHover(null)}
                            />
                        </g>
                    );
                })}
                <line x1={padL} x2={padL} y1={padT} y2={padT + rows.length * band} stroke={p.axis} strokeWidth="1" />
            </svg>

            {hover !== null && rows[hover] && (
                <div className="pointer-events-none absolute top-1 right-1 rounded-md border border-[var(--line)] bg-[var(--surface)] px-2.5 py-1.5 text-xs shadow-lg">
                    <div className="font-semibold text-[var(--ink-1)]">{rows[hover].name}</div>
                    <div className="text-[var(--ink-2)]">
                        Extra cost: <b className="text-[var(--ink-1)]">{formatValue(rows[hover].extra_cost_next_quarter)}</b>
                    </div>
                    {rows[hover].price_forecast && (
                        <div className="text-[var(--ink-2)]">Price: {formatValue(rows[hover].price_forecast)}</div>
                    )}
                </div>
            )}
        </div>
    );
}
