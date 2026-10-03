# Team context: Margin Shield (Genpact hackathon)

Shared project context for the team. Read it before you start working on the repo.
Status as of 2026-10-03. For setup, commands and the API, see [README.md](README.md).

## The product in one sentence
An AI agent that connects to a business's financial data and tells the owner what inflation is doing, and will do, to **their own** costs, profit, margin and cash, in euros, and what each response is worth before they commit.
The message is **"protect my margin"**, never "we predict inflation".

## Problem
- Owners notice inflation only after margin or cash is gone. Accounting tools look back. Economic dashboards show the economy, not the business. Headline CPI is too general and too late.
- Kosovo, Aug 2026 (Kosovo Agency of Statistics, HICP): headline 7.3%, transport +21.1%, food 2.6%, housing/water/energy/fuel +11.8%.
- SMEs have no CFO.

## Core idea
- We do NOT forecast inflation. We borrow central-bank and market projections.
- The gap we fill is **pass-through**: how strongly and how fast a public price index (CPI category, wheat, diesel, electricity, wages, EUR/USD) turns into one company's flour, electricity or wage cost. We learn it per cost line from the company's own invoices, and fall back to industry defaults (labelled as assumptions) when history is short.

## Hard rules (apply to all code and copy)
1. **Code produces every number. The language model only handles language** (classify expense lines, read news, explain, write messages).
2. A **grounding check** blocks any generated text containing a number the engine didn't produce.
3. Every value carries a label: `data` / `forecast` / `assumption` / `ai_suggestion` / `needs_validation`.
4. Forecasts are **ranges with confidence (p10/p50/p90)**, never a single point.
5. Simulations use a fixed seed, so every number shown can be re-derived.
6. **Dashboard design comes from Olsa. Use it; do not design a new one.**

## Who does what
- **Backend engine and API:** core team (this repo, `app/Services/Margin`, `routes/api.php`).
- **Frontend (React, dashboard design):** Olsa. Talks to the API under `/api/v1`.
- **Real public price data:** Engji (see the task below).

## Pipeline
1. Classify messy expense lines (e.g. "Vaj Luledielli 5L Bimi") to a cost category + price driver. The owner confirms.
2. Estimate pass-through strength and lag per cost line.
3. Company-specific inflation vs. selling-price growth, and the gap in €.
4. 6-month Monte Carlo (5,000 paths) of profit and cash: baseline, "replay 2022" stress test, plan.
5. Simulate each action with a € value: raise prices (in steps), switch supplier (part of volume), buy ahead (cash and storage aware), fixed-price contract.
6. Supplier overcharge detector: "supplier raised flour 15% vs market 5%", with evidence.
7. Email alerts only when an owner-set threshold is crossed (via n8n).

## Headline numbers on the dashboard
- € profit lost to inflation this month
- € margin at risk next quarter, and € we can protect with the plan
- Stress probability (chance of a cash/profit stress event within 6 months)
- Top 3 risky cost lines, top 3 actions, supplier flags

All of these come from `GET /api/v1/companies/{company}/radar`.

## Demo story
"Furra Demo", a 3-location bakery (seeded, company id 1), €8,000/mo profit today:
- Do nothing: ≈ €723/mo in 6 months, 64% cash-stress probability.
- Recommended plan (price steps, part of the flour from a cheaper supplier, fixed energy price): ≈ €6,530/mo, stress ≈ 0%.
- Supplier watch flags the flour supplier *Mulliri Veri* at +15% vs a 5.2% market move.

Numbers come from the current demo seed and change if the seed data changes. Run `php artisan margin:analyse` for the live figures.
Second demo if possible: 6 months of real café/minimarket invoices → "3 products will hurt you this quarter, €X at risk, 3 actions".

## Tech stack
- **Laravel 13, PHP 8.3+** (backend, data, engine, API, alerts). PHPUnit tests, Pint formatting.
- Frontend: React (Olsa).
- Alerts: **n8n** webhook workflow (`n8n/`), Mailpit locally.
- The Monte Carlo engine is written in PHP. A Python FastAPI service for pretrained time-series forecasts is planned, not built.
- SQLite locally, PostgreSQL later.
- Windows note: the `php` on PATH may be 8.0; use Herd's PHP 8.3+.

## What already exists
- **Tables / models (with factories):** companies, price_drivers, price_observations, driver_projections, suppliers, cost_lines, invoice_lines, monthly_financials, supplier_offers, alert_rules, simulation_runs.
- **Engine (`app/Services/Margin`):** `MarginRadar::analyse(Company)` is the main entry point (full dashboard payload, cached). Plus `PassThroughEstimator`, `CompanyInflationCalculator`, `SupplierWatch`, `ActionRecommender`, `GroundingCheck`, `LabelledValue`, keyword-based expense `Classification`, `Simulation/MonteCarloSimulator` with actions `RaisePrices`, `SwitchSupplier`, `BuyAhead`, `FixedPriceContract`.
- **API v1 (no auth yet):** companies, radar, recommendations, simulations (what-if), supplier watch, cost lines (confirm mapping), expense classification preview, invoice CSV import, alert rules, price drivers. Full table in the README.
- **Commands:** `margin:analyse {company?}`, `margin:check-alerts` (hourly).
- **Config `config/margin.php`:** simulation settings, pass-through defaults, supplier-watch threshold, alert cooldown, Albanian/English classification keywords, "replay 2022" shock profile.
- **Seeders:** `PriceDriverSeeder` (11 drivers, demo data) and `DemoBakerySeeder`.
- **Tests:** 51 passing (API, services, unit).

## Not built yet
- React frontend with Olsa's design
- Authentication
- Invoice reading from photos/PDF, and the language-model layer for classification and explanations (classification is keyword-based today)
- **Real public data:** CPI series are synthetic but anchored to the Aug 2026 KAS figures; commodity, energy, fuel, wage series and all projections are illustrative demo data

## Task for Engji: replace demo price-driver data with real public data
Goal: the engine reads only three tables, so filling them with real data makes every demo number defensible, with no engine changes.

- `price_drivers`: keep the same 11 codes (cpi.headline, cpi.food, cpi.transport, cpi.housing_energy, commodity.wheat, commodity.sunflower_oil, commodity.sugar, commodity.dairy, energy.electricity, fuel.diesel, wages.kosovo). `source` names the real source.
- `price_observations`: one row per driver per month, `period` = first day of the month, `value` = index or price **level** (not % change). At least 24 months, ideally 36+.
- `driver_projections`: change over `horizon_months` (6) as fractions (`change_low` / `change_mid` / `change_high`), with `source` and `published_on`. Only real institutional projections; if none exists, leave it empty (the engine falls back to the driver's own trend and labels it).

Deliverables: an idempotent `php artisan margin:import-drivers` command; CSVs under `database/data/` (with source + URL) where no API exists; PHPUnit tests with `Http::fake()`; a driver → source → URL → latest period → caveats table.
Source ideas to verify: Kosovo Agency of Statistics (ASKdata) for HICP and wages; World Bank commodity prices (Pink Sheet) for wheat, sunflower oil, sugar; EU Milk Market Observatory for dairy; ERO for electricity tariffs; IMF / Central Bank of Kosovo and the World Bank Commodity Markets Outlook for projections.
Never invent numbers. Don't touch `app/Services/Margin` or the frontend.

## Deck (10 slides)
1 hook ("café costs +18% while official inflation said 7%"), 2 problem, 3 why current tools fail, 4 solution + screenshot, 5 live demo, 6 how it works, 7 proof/backtest as ranges, 8 business model, 9 market/expansion, 10 ask (pilots, funding, data partners).
Jury line: "We cannot stop inflation. But every owner should know how much it costs them, where, and what to do, before they see it in the balance sheet."
Moat: the product-to-price-driver mapping improves with every customer's data.

## Business model
Monthly SaaS per business; per-client plans for accountants; white-label for banks (early warning across loan portfolios). Channels: banks, accountants, POS providers. Kosovo first, then the Western Balkans.
Path: 2-day hackathon demo → ~12-week MVP with 5–10 pilot businesses → scaled product.
