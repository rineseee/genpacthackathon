import { useEffect, useState } from 'react';

/**
 * The one place that talks to the backend. Every number the tabs show comes from /api/v1,
 * where the engine produced it (as { value, unit, label, low?, high?, confidence? }).
 */

export function companyId() {
    return document.getElementById('radar-root')?.dataset.companyId ?? '1';
}

async function request(url, options = {}) {
    const res = await fetch(url, {
        ...options,
        headers: { accept: 'application/json', 'content-type': 'application/json', ...(options.headers ?? {}) },
    });
    const body = await res.json().catch(() => ({}));

    if (!res.ok) {
        const firstError = body.errors ? Object.values(body.errors)[0]?.[0] : null;
        throw new Error(firstError ?? body.message ?? `Request failed (${res.status})`);
    }

    return body.data;
}

export const getJson = (url) => request(url);

export const postJson = (url, payload) => request(url, { method: 'POST', body: JSON.stringify(payload) });

/** Load once per dependency change; returns { data, error, loading }. */
export function useApi(load, deps = []) {
    const [state, setState] = useState({ data: null, error: null, loading: true });

    useEffect(() => {
        let alive = true;
        setState((s) => ({ ...s, loading: true, error: null }));
        load()
            .then((data) => alive && setState({ data, error: null, loading: false }))
            .catch((error) => alive && setState({ data: null, error, loading: false }));

        return () => {
            alive = false;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, deps);

    return state;
}

/* ---- formatting ---- */

const EUR0 = new Intl.NumberFormat('en-IE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
const EUR2F = new Intl.NumberFormat('en-IE', { style: 'currency', currency: 'EUR', minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const eur = (n) => (n === null || n === undefined ? '—' : EUR0.format(n));
export const eur2 = (n) => (n === null || n === undefined ? '—' : EUR2F.format(n));
export const pct = (n, signed = false) =>
    n === null || n === undefined ? '—' : `${signed && n > 0 ? '+' : ''}${Number(n).toFixed(1)}%`;

/** "2026-12" becomes "December 2026". */
export const monthName = (period) =>
    period ? new Date(`${period}-01T00:00:00`).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }) : '';

export const shortMonth = (period) =>
    period ? new Date(`${period}-01T00:00:00`).toLocaleDateString('en-GB', { month: 'short', year: '2-digit' }) : '';

/** Map an engine label to the pill the design uses. */
export const pillFor = (label, entered = false) =>
    ({ data: entered ? 'entered' : 'calculated', forecast: 'prediction', assumption: 'assumption', ai_suggestion: 'llm' })[label] ??
    'calculated';
