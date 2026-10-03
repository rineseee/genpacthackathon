# Margin Shield

**An AI agent that tells a business owner what inflation is doing to *their* costs, margin and cash, in euros, and what each response is worth before they commit.**

Built for the Genpact hackathon. The message is *protect my margin*, not *predict inflation*. We don't forecast the economy. We take official price series from the Kosovo Agency of Statistics and learn how strongly and how fast each one moves one company's coffee, milk, electricity or wage bill. That link is called **pass-through**.

> We cannot stop inflation. But every owner should know how much it costs them, where, and what to do, before they see it in the balance sheet.

## What it does

| Step | What happens | Where in the code |
|---|---|---|
| 1. Classify | Messy invoice lines ("Vaj Luledielli 5L Bimi") are mapped to a price driver (cooking oil → ASK "Oils and fats" price index). The owner confirms each mapping. | `app/Services/Margin/Classification`, `config/margin.php` → `classification_rules` |
| 2. Pass-through | For each cost line it estimates how strongly and with what lag the driver moves the price. It uses the company's own invoices when there are enough, otherwise industry defaults labelled as assumptions. | `PassThroughEstimator` |
| 3. Company inflation | This company's own inflation vs. its selling-price growth, with the gap in €. | `CompanyInflationCalculator` |
| 4. Simulation | 5,000-path Monte Carlo of profit and cash over 6 months, plus a "replay 2022" stress test that replays ASK's actual March–August 2022 price changes. Seeded, so every number can be reproduced. | `Simulation/MonteCarloSimulator`, `ScenarioRunner` |
| 5. Actions | Raise prices, switch supplier, buy ahead, fixed-price contract. Each one is simulated and given a € value. | `Simulation/Actions`, `ActionRecommender` |
| 6. Supplier watch | Flags suppliers whose prices rise faster than the market. It compares against headline CPI when ASK hasn't yet published the subgroup for the invoice months. | `SupplierWatch` |
| 7. Alerts | The owner is emailed **only** when a limit they set is crossed. Delivery runs through n8n (see below). | `Alerts/AlertDispatcher`, `n8n/` |

**Hybrid AI rule:** code produces every number. Language models only handle language (classify, explain, write). `GroundingCheck` blocks any number the engine didn't produce. Every value is labelled *data / forecast / assumption / AI suggestion / needs your validation*, and forecasts are always shown as ranges.

## How the system works

```
ASKdata PxWeb API ──margin:import-ask──▶ database/data/ask-snapshot.json
                                              │ PriceDriverSeeder
                                              ▼
Owner's invoices (CSV) ──InvoiceImporter──▶ SQLite / PostgreSQL ◀── DemoCafeSeeder
                                              │  companies, cost lines, invoices,
                                              │  suppliers, offers, price drivers
                                              ▼
                   app/Services/Margin  (the engine, plain PHP)
   MarginRadar (one cached result per company):
     CompanyProfileBuilder (uses PassThroughEstimator) → CompanyInflationCalculator
       → SimulationInputBuilder → MonteCarloSimulator (baseline + replay 2022)
       → ActionRecommender → SupplierWatch → GroundingCheck
   ScenarioRunner: the owner's own what-if runs (POST /simulations)
                                              │
              ┌───────────────────────────────┼───────────────────────────┐
              ▼                               ▼                           ▼
     /api/v1 JSON (routes/api.php)   margin:analyse (CLI summary)   margin:check-alerts
                                                                         │ hourly
                                                                         ▼
                                                                  n8n → owner's email
```

1. **Data in.** Public prices come only from ASK (HICP groups and annual wages). The import writes a snapshot so seeding and tests work offline. Company data comes from invoice CSV imports, or from `DemoCafeSeeder` for the demo.
2. **Engine.** `MarginRadar` builds the company profile, learns pass-through per cost line, runs the do-nothing baseline, the 2022 replay and every action, ranks the actions by € value, checks suppliers and runs `GroundingCheck` on the summary text. The result is cached as plain arrays, so the API and alerts read the same numbers.
3. **Out.** The API serves the radar, recommendations, simulations and supplier watch. `margin:check-alerts` compares the baseline with each owner's limits and hands crossed ones to n8n, which only formats and sends the email.

## Frontend

The dashboard is a React 19 app (design by Olsa), built with Vite and Tailwind CSS 4.

- `/` redirects to `/radar/{company?}` (`routes/web.php`). That route renders `resources/views/radar.blade.php`, which loads `resources/js/app.jsx` into `#radar-root` and passes the company id as `data-company-id` (default `1`).
- `app.jsx` holds the header and switches tabs. The active tab lives in the URL hash, so a demo link can open straight on one tab, e.g. `/radar#why`.
- `resources/js/radar/shell.jsx` has the shared pieces: header, cards, headline tiles, the *Show as* toggle and the label pills (*YOU ENTERED*, *OFFICIAL DATA*, *PREDICTION*, *ASSUMPTION*, *CALCULATED*).

| Tab | Hash | File | What the owner sees |
|---|---|---|---|
| Your business | `#business` | `tabs/YourBusiness.jsx` | A 3-step form: monthly sales, costs, cash, loan and planned rises → main products → results. It shows the extra cost per month, profit after inflation, cash runway and the price rise needed to stay even, with every calculation shown. "Fill with an example café" loads demo values. |
| Ask & explore | `#explore` | `tabs/AskExplore.jsx` | Plain-language questions (will my supplies cost more, is my business safe, what if I raise prices, what will my money be worth) with price history, likely ranges and a short glossary. |
| Ask why | `#why` | `tabs/AskWhy.jsx` | The 8-step pipeline behind every result, a 0–100 risk scale and answers to "why" questions, each showing its numbers. |
| Data sources | `#sources` | `tabs/DataSources.jsx` | Where every number comes from, with links. |

**Current state:** the tabs calculate in the browser from values in the form and sample data in the JSX files. They don't call `/api/v1` yet, so the engine's numbers (the demo results below) show up in the API and CLI, not in the dashboard. Wiring the tabs to `/api/v1/companies/{id}/radar`, `/recommendations` and `/supplier-watch` is the next step.

Run `composer run dev` while working on the frontend, or `npm run build` once. If the page complains about the Vite manifest, the assets haven't been built.

## Run it locally

Requires PHP 8.3+ (Herd's PHP 8.4 works), Composer and Node.

```bash
composer run setup                   # install, .env, key, migrate, build assets
php artisan migrate:fresh --seed     # ASK price drivers + the demo cafe ("Cafe Demo", id 1)
php artisan margin:import-ask --snapshot  # optional: refresh the ASK data from the live API
php artisan margin:analyse           # run the analysis and print each company's summary
composer run dev                     # app + Vite
php artisan test --compact           # test suite
```

### Demo results

"Cafe Demo", a 3-location cafe:

| | Monthly profit in 6 months | Cash-stress probability |
|---|---|---|
| Today | €8,000 | |
| Do nothing | ≈ €5,915 | 18.7% |
| With the recommended plan | ≈ €7,604 | 0.2% |
| Replay 2022 (do nothing) | ≈ €5,244 | 43.4% |

The cafe starts with €20,000 cash. The supplier watch flags the milk supplier *Qumështorja Prishtina* at +9.8% vs +1% official inflation since April.

**Data source:** price drivers are real Kosovo Agency of Statistics (ASK) series from the ASKdata PxWeb API. The seeder loads the committed snapshot `database/data/ask-snapshot.json`. Outlooks are each series' trailing 12-month trend and are labelled as such. They are not official forecasts. The cafe's own invoices and financials are demo data.

### Commands

| Command | What it does |
|---|---|
| `php artisan margin:import-ask [--snapshot]` | Imports price drivers (HICP groups and wages) from ASK; `--snapshot` also writes the snapshot the seeder uses offline |
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
| GET | `/price-drivers` | Public price drivers from ASK: headline and subgroup HICP (food, bread, oils, dairy, sugar, coffee, soft drinks, catering, energy, fuel, transport) and wages |

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


