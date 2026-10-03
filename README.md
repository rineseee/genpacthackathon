# Margin Shield

**An AI agent that tells a business owner what inflation is doing to *their* costs, margin and cash, in euros, and what each response is worth before they commit.**

Built for the Genpact hackathon. The message is *protect my margin*, not *predict inflation*. We don't forecast the economy. We take public projections (statistics office, central bank, commodity markets) and learn how strongly and how fast each one moves one company's flour, electricity or wage bill. That link is called **pass-through**.

> We cannot stop inflation. But every owner should know how much it costs them, where, and what to do, before they see it in the balance sheet.

## What it does

| Step | What happens | Where in the code |
|---|---|---|
| 1. Classify | Messy invoice lines ("Vaj Luledielli 5L Bimi") are mapped to a price driver (cooking oil → sunflower oil commodity). The owner confirms each mapping. | `app/Services/Margin/Classification`, `config/margin.php` → `classification_rules` |
| 2. Pass-through | For each cost line it estimates how strongly and with what lag the driver moves the price. It uses the company's own invoices when there are enough, otherwise industry defaults labelled as assumptions. | `PassThroughEstimator` |
| 3. Company inflation | This company's own inflation vs. its selling-price growth, with the gap in €. | `CompanyInflationCalculator` |
| 4. Simulation | 5,000-path Monte Carlo of profit and cash over 6 months, plus a "replay 2022" stress test. Seeded, so every number can be reproduced. | `Simulation/MonteCarloSimulator`, `ScenarioRunner` |
| 5. Actions | Raise prices, switch supplier, buy ahead, fixed-price contract. Each one is simulated and given a € value. | `Simulation/Actions`, `ActionRecommender` |
| 6. Supplier watch | Flags suppliers whose prices rise faster than the market. | `SupplierWatch` |
| 7. Alerts | The owner is emailed **only** when a limit they set is crossed. Delivery runs through n8n (see below). | `Alerts/AlertDispatcher`, `n8n/` |

**Hybrid AI rule:** code produces every number. Language models only handle language (classify, explain, write). `GroundingCheck` blocks any number the engine didn't produce. Every value is labelled *data / forecast / assumption / AI suggestion / needs your validation*, and forecasts are always shown as ranges.

## Run it locally

Requires PHP 8.3+ (Herd's PHP 8.4 works), Composer and Node.

```bash
composer run setup                   # install, .env, key, migrate, build assets
php artisan migrate:fresh --seed     # price drivers + the demo bakery ("Furra Demo", id 1)
php artisan margin:analyse           # run the analysis and print each company's summary
composer run dev                     # app + Vite
php artisan test --compact           # test suite
```

### Demo result (current seed)

"Furra Demo", a 3-location bakery:

| | Monthly profit in 6 months | Cash-stress probability |
|---|---|---|
| Today | €8,000 | |
| Do nothing | ≈ €723 | 64% |
| With the recommended plan | ≈ €6,530 | ≈ 0% |

The supplier watch flags the flour supplier *Mulliri Veri* at +15% vs a 5.2% market move.

**Data honesty:** the CPI series are synthetic but anchored to the Kosovo Agency of Statistics Aug 2026 year-on-year figures. Commodity, energy, fuel and wage series, and all projections, are illustrative demo data.

### Commands

| Command | What it does |
|---|---|
| `php artisan margin:analyse {company?}` | Runs the margin analysis, warms its cache (~5 s cold, ~0.2 s warm) and prints the summary |
| `php artisan margin:check-alerts {--company=}` | Sends every crossed owner alert to the n8n email workflow. Scheduled hourly, so keep `php artisan schedule:work` running. |

### API (v1)

All under `/api/v1` (see `routes/api.php`). There is no authentication yet.

| Method | Path | Purpose |
|---|---|---|
| GET | `/companies`, `/companies/{company}` | Companies |
| GET | `/companies/{company}/radar` | Headline numbers: € lost to inflation, margin at risk, stress probability |
| GET | `/companies/{company}/recommendations` | Ranked actions with € value |
| POST / GET | `/companies/{company}/simulations[/{id}]` | Run a what-if simulation, or fetch a stored run |
| GET | `/companies/{company}/supplier-watch` | Suppliers raising prices above market |
| GET / PATCH | `/companies/{company}/cost-lines[/{line}]` | Cost lines; PATCH confirms a driver mapping |
| POST | `/companies/{company}/expense-classifications` | Preview the driver suggested for an expense line |
| POST | `/companies/{company}/invoice-imports` | Import supplier invoices as CSV: `date,supplier,description,quantity,unit,unit_price` |
| CRUD | `/companies/{company}/alert-rules` | The owner's alert limits |
| GET | `/price-drivers` | Public price drivers (CPI, commodities, energy, fuel, wages, FX) |

## Email alerts with n8n

```
Laravel  margin:check-alerts (hourly)
   │  reads the alert metrics from MarginRadar (cached; do-nothing baseline)
   │  compares them with each owner's limits (AlertRule), max one email per limit every 7 days
   ▼
n8n  "Margin Shield – Owner alert email"
   Webhook (POST /webhook/margin-alert)
     → Token valid?  ── no ──→ 401
     → Build email   (formats the numbers it received; computes none)
     → Send email    (SMTP)
     → Reply 200 {"sent": true}
   ▼
Owner's inbox    (locally: Mailpit, http://localhost:8025)
```

- Laravel sends: company, metric, observed value, owner's limit, label (`forecast`), and the simulation it came from (paths, scenario, seed).
- `last_triggered_at` is set **only** when n8n confirms delivery, so a failed send is retried on the next run.
- What-if and stress-test runs never trigger alerts. Only the do-nothing baseline counts.
- Metrics: `stress_probability` (fraction 0–1), `margin_at_risk` (€ next quarter), `supplier_overcharge` (€ per month).

### Set up

1. In `.env`:
   ```
   MARGIN_ALERT_WEBHOOK_URL=http://localhost:5678/webhook/margin-alert
   MARGIN_ALERT_WEBHOOK_TOKEN=<random string>   # generated by install.sh if empty
   ```
2. With n8n running in Docker, run:
   ```bash
   bash n8n/install.sh
   ```
   It starts Mailpit, imports the local SMTP credential and the workflow from `n8n/margin-alert-workflow.json` (the token is filled in from `.env`), publishes it and restarts n8n. Container names default to `inbox-agent-n8n-1` and `inbox-agent-n8n-worker-1`. Override them with `N8N_CONTAINER` and `N8N_WORKER_CONTAINER`.
3. Try it:
   ```bash
   php artisan margin:check-alerts
   ```
   Then open http://localhost:8025 to see the email, and the execution under **Executions** in n8n.

**Real email:** in n8n, open the **Send email** node and swap the credential for real SMTP, e.g. Gmail (`smtp.gmail.com`, port 465, SSL on, an App Password).

## Team
- Rinesa: backend & API
- Olsa: frontend & design
- Engji: price data (ASK)

## Stack

Laravel 13 (accounts, data, alerts, API) · the engine runs in PHP today (`app/Services/Margin`) · SQLite locally / PostgreSQL · n8n (alert delivery) · React frontend (dashboard design by Olsa) · planned Python FastAPI service for pretrained time-series forecasts.

## Roadmap

2-day hackathon demo → ~12-week MVP with 5–10 pilot businesses → accounting and bank integrations, industry benchmarking, a monitoring agent. Kosovo first, then the Western Balkans.

Data source: https://askdata.rks-gov.net/


