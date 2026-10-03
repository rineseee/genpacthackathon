/**
 * The one place that talks to the API. Everything else renders what this returns.
 *
 * Shapes come from app/Services/Margin: every number is a LabelledValue
 * ({ value, unit, label, low?, high?, confidence?, source? }), never a bare float,
 * because the product rule is that a forecast is never shown without its range.
 */

export async function fetchRadar(companyId, { signal } = {}) {
    const res = await fetch(`/api/v1/companies/${companyId}/radar`, {
        headers: { accept: 'application/json' },
        signal,
    });

    if (!res.ok) {
        throw new Error(`radar ${res.status} ${res.statusText}`);
    }

    const body = await res.json();

    return body.data;
}

export async function fetchCompanies({ signal } = {}) {
    const res = await fetch('/api/v1/companies', { headers: { accept: 'application/json' }, signal });

    if (!res.ok) {
        throw new Error(`companies ${res.status} ${res.statusText}`);
    }

    return (await res.json()).data ?? [];
}

/* ---- formatting ---- */

const EUR = new Intl.NumberFormat('en-IE', {
    style: 'currency',
    currency: 'EUR',
    maximumFractionDigits: 0,
});

export function formatValue(labelled) {
    if (!labelled) {
        return '—';
    }

    if (labelled.unit === 'percent') {
        return `${labelled.value > 0 ? '+' : labelled.value < 0 ? '−' : ''}${Math.abs(labelled.value).toFixed(1)}%`;
    }

    return EUR.format(labelled.value);
}

/** A forecast must always carry its range; data and assumptions have none to show. */
export function formatRange(labelled) {
    if (!labelled || labelled.low === null || labelled.low === undefined) {
        return null;
    }

    return labelled.unit === 'percent'
        ? `${labelled.low.toFixed(1)}% to ${labelled.high.toFixed(1)}%`
        : `${EUR.format(labelled.low)} to ${EUR.format(labelled.high)}`;
}

export const LABEL_TEXT = {
    data: 'Data',
    forecast: 'Forecast',
    assumption: 'Assumption',
    ai_suggestion: 'AI suggestion',
    needs_validation: 'Needs your validation',
};
