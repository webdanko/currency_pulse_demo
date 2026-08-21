# FX Pulse

Laravel backend + Vue 3 frontend for tracking live FX rates. A scheduler polls Frankfurter every minute, stores snapshots, and pushes updates to connected browsers over WebSockets. Alert rules get evaluated in a queue worker so the poll command doesn't block.

Stack: PHP 8.4 / Laravel 13 / Reverb / MySQL / Redis / Vue 3 / Tailwind v4 / Docker

## How it works

```
scheduler (every minute)
  -> ExchangeRateProvider (Frankfurter API, cached)
  -> RateSnapshot (MySQL) + RatesUpdated event -> Reverb -> browser
  -> EvaluateAlerts job (queue) -> AlertTriggered event -> Reverb -> toast
```

The Vue SPA subscribes with Laravel Echo. If Reverb isn't configured it falls back to polling every 60s.

---

## Quick start

```bash
docker compose up --build
```

- Frontend: http://localhost:5173
- API: http://localhost:8000/api/rates
- Sockets: ws://localhost:8080

First run migrates and seeds two demo alert rules. Might take a minute or two on first boot while composer does its thing — after that the scheduler kicks in and the table starts updating.

### Without Docker

```bash
cd backend
composer install && cp .env.example .env
php artisan key:generate && php artisan migrate --seed

php artisan serve
php artisan queue:work       # separate terminal
php artisan schedule:work    # separate terminal
php artisan reverb:start     # and another one
```

```bash
cd frontend
npm install && cp .env.example .env
npm run dev
```

Leave `VITE_REVERB_APP_KEY` empty to run withouт sockets.

---

## API

| Method | Endpoint | |
|--------|---|-|
| GET | `/api/rates` | current rates |
| GET | `/api/rates/history?symbol=USD` | snapshots from DB |
| GET | `/api/rates/intraday?symbol=USD` | last 24h from provider |
| GET | `/api/rates/timeseries?symbol=USD&days=30` | daily history |
| GET | `/api/alerts` | list rules |
| POST | `/api/alerts` | create rule |

WebSocket channels: `rates` (`.rates.updated`) and `alerts` (`.alert.triggered`).

## Config

| Var | Default | |
|-----|---------|---|
| `EXCHANGE_BASE_URL` | `https://api.frankfurter.dev/v1` | upstream API |
| `EXCHANGE_SYMBOLS` | `USD,GBP,JPY,CHF,PLN` | currencies to track |
| `EXCHANGE_CACHE_TTL` | `60` | cache TTL in seconds |
| `BROADCAST_CONNECTION` | `reverb` | set to `log` to disable sockets |

## Tests

```bash
cd backend && php artisan test
```

Covers the poll command, alert evaluation (crossing, debounce), history enpoint, and rate caching — Http::fake + Event::fake, nothing external.

Frankfurter doesn't need an API key so it just works. Auth isn't implemented, the alert endpoints are open — fine for a demo, not for prod.
