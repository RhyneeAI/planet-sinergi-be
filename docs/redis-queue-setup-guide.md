# Redis Queue Setup Guide

## Storage Structure

```
storage/app/public/reports/
├── absence/
│   ├── attendance/          # attendance-20260621.xlsx
│   ├── bonus/               # bonus-20260621.xlsx
│   ├── deduction/           # deduction-20260621.xlsx
│   ├── employee/            # employee-20260621.xlsx
│   └── payroll/             # payroll-20260621.xlsx
├── operational/             # income-expense-20260621.pdf / .xlsx
└── pos/
    ├── marketing-commission/ # marketing-commission-20260621.pdf
    └── revenue/             # revenue-20260621.pdf
```

## Development

### 1. Install Redis locally

```bash
# Ubuntu / Debian
sudo apt update && sudo apt install redis-server php8.3-redis

# macOS
brew install redis && brew services start redis
```

### 2. Update .env

```env
# Redis config
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

# Queue
QUEUE_CONNECTION=redis
```

### 3. Run queue worker

```bash
# Terminal 1 — jalankan worker (harus selalu jalan)
php artisan queue:work redis --queue=exports,default --tries=3

# Terminal 2 — test export (jalan seperti biasa)
```

### 4. Test export

Buka endpoint export (misal absensi), pastikan job terkirim ke Redis:

```bash
php artisan tinker
> \App\Jobs\GenerateReportExport::dispatch(
    \App\Models\Company::first(),
    'absence/attendance',
    ['date_from' => '2026-01-01', 'date_to' => '2026-06-21'],
    'xlsx'
  );
```

Cek Redis:

```bash
redis-cli
> KEYS *
> LLIST queues:exports
```

---

## Server (Shared Hosting dengan Redis Socket)

### 1. Cek PHP extension Redis

```bash
php -m | grep redis
```

Jika tidak muncul, minta hosting enable `phpredis` extension.

### 2. Konfigurasi .env

```env
QUEUE_CONNECTION=redis

# Socket Redis dari hosting
REDIS_CLIENT=phpredis
REDIS_SCHEME=unix
REDIS_PATH=/home/plaq5446/tmp/redis.sock
REDIS_HOST=
REDIS_PORT=0
REDIS_PASSWORD=null
```

### 3. Buat queue worker sebagai background process

Buat file `worker.sh` di root project:

```bash
#!/bin/bash
while true; do
    php artisan queue:work redis --queue=exports,default --tries=3 --timeout=300 --sleep=3
    sleep 5
done
```

Jalankan via **screen** atau **nohup**:

```bash
# Via screen (disarankan)
screen -S queue-worker
php artisan queue:work redis --queue=exports,default --tries=3 --timeout=300 --sleep=3
# Ctrl+A, D untuk detach

# Via nohup
chmod +x worker.sh
nohup ./worker.sh > storage/logs/queue-worker.log 2>&1 &
```

Cek status:

```bash
ps aux | grep queue:work
```

> **Catatan:** Jika hosting menyediakan cron job, kamu bisa bikin cron setiap menit yang menjalankan queue worker. Tapi ini tidak ideal untuk processing berat. Idealnya worker jalan terus via screen / supervisor.

### 4. Migrasi tabel queue

```bash
php artisan queue:table
php artisan queue:failed-table
php artisan migrate
```

### 5. Test

```bash
curl https://domain.com/api/v1/abs/reports/attendance?mode=export \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

---

## Catatan Penting

| Issue | Solusi |
|---|---|
| Worker mati setelah logout SSH | Pakai **screen** atau **tmux** |
| Hosting restart server | Pasang cron job `@reboot` atau lapor hosting |
| Queue jalan tapi export lama | Naikin `--timeout` (default 60, bisa 300 untuk report besar) |
| Redis penuh | Redis auto-clear dengan `maxmemory-policy allkeys-lru` |

## Monitoring

```bash
# Cek failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Lihat log worker
tail -f storage/logs/queue-worker.log

# Flush Redis (reset) — hati-hati!
# redis-cli FLUSHALL
```
