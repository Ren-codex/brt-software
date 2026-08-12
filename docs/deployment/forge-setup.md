# Production Deployment — Laravel Forge

This runbook connects the GitHub repository **`Ren-codex/brt-software`** to a
production server managed by [Laravel Forge](https://forge.laravel.com), with
auto-deploy on push.

> **Why Forge for this app:** BRT runs three long-running processes besides the
> web app — **Laravel Reverb** (WebSockets), a **database queue worker** (the
> `jobs` table, used for low-stock and other notifications), and the **scheduler**
> (4 scheduled commands). Forge manages each as a first-class daemon/cron. A
> generic "git pull + build" pipeline would silently drop these.

The deploy steps themselves live in [`deploy.sh`](../../deploy.sh) at the repo
root — that file is the source of truth; paste its body into Forge's Deploy
Script field (Step 4).

---

## What you need before starting

- A **Laravel Forge** account (subscription).
- A **server provider** connected to Forge (DigitalOcean, Hetzner, AWS, Vultr…).
  Forge provisions the VPS; the provider bills you for it.
- Access to the **GitHub repo** `Ren-codex/brt-software` (Forge needs GitHub
  authorization to read it and set up the deploy webhook).
- A **domain name** you can point at the server (for the app and, ideally, a
  subdomain for Reverb such as `ws.yourdomain.com`).

Everything below is done in the Forge dashboard + a few `.env` values. No app
code changes are required.

---

## Step 1 — Provision the server

1. Forge → **Create Server** → pick your provider.
2. **PHP version: 8.3** (the app requires `^8.2`; 8.3 is the safe current choice).
3. Server type: **App Server** (includes Nginx + MySQL). Size: start at 2 GB RAM
   or more — Reverb + a queue worker + PHP-FPM need headroom.
4. Note the **database root password** and **`forge` DB user password** Forge
   shows you once.

## Step 2 — Create the site

1. Server → **New Site**. Root domain: e.g. `app.yourdomain.com`.
2. **Web Directory:** `/public` (Laravel default).
3. **PHP Version:** 8.3 (match the server).

## Step 3 — Connect the GitHub repository

1. Site → **Apps** tab → **Git Repository**.
2. Provider: **GitHub**. Repository: `Ren-codex/brt-software`.
3. **Branch:** the branch you deploy to production from — typically `main`.
   (Merge your feature branch into `main` first; see "Release flow" below.)
4. Leave **Install Composer Dependencies** unchecked here — the deploy script
   handles it with production flags.
5. Click **Install Repository**.

## Step 4 — Set the deploy script

1. Site → **Apps** → **Deploy Script**.
2. Replace the contents with the body of [`deploy.sh`](../../deploy.sh).
   Forge provides `$FORGE_SITE_PATH`, `$FORGE_SITE_BRANCH`, `$FORGE_PHP`, and
   `$FORGE_PHP_FPM` automatically, which the script uses.
3. **Enable Quick Deploy** so every push to the deploy branch triggers it.

## Step 5 — Configure the environment (`.env`)

Site → **Environment**. Set at minimum:

```dotenv
APP_NAME="BRT Accounting"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.yourdomain.com

# Generate once and paste (see note below), then never change it
APP_KEY=

# Database — use the credentials Forge created in Step 1
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=brtsoftware_db
DB_USERNAME=forge
DB_PASSWORD=your-forge-db-password

# Queue runs on the database driver (the jobs table)
QUEUE_CONNECTION=database

# Broadcasting via Reverb (WebSockets)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=ws.yourdomain.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# Front-end reads these at build time (Vite)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# Mail, session, cache — set to your production values
SESSION_DRIVER=database
CACHE_STORE=database
```

**Generate `APP_KEY`:** after the first deploy, open Forge → Server →
**Commands** (or SSH) and run `php artisan key:generate --show`, then paste the
value into the `APP_KEY` line above and redeploy. Keep it stable forever —
changing it invalidates all encrypted data and sessions.

> `DB_DATABASE`: create the `brtsoftware_db` database under Server → **Database**
> if Forge didn't already, and make sure the user has access.

## Step 6 — Queue worker daemon

Site → **Queue** → **New Worker**:

- **Connection:** `database`
- **Queue:** `default`
- **Tries:** `3`, **Timeout:** `60`, **Sleep:** `3`

Forge runs this under Supervisor and restarts it after each deploy (the deploy
script's `queue:restart` signals it to reload code).

## Step 7 — Scheduler

Server → **Scheduler** → **Add Scheduled Job**:

- **Command:** `php /home/forge/app.yourdomain.com/artisan schedule:run`
- **Frequency:** Every Minute (`* * * * *`)

This drives the app's scheduled commands: `credit:monthly`, `credit:annual`,
`invoices:mark-overdue`, `expense:carry-budget`.

## Step 8 — Reverb WebSocket daemon

1. Server → **Daemons** → **New Daemon**:
   - **Command:** `php /home/forge/app.yourdomain.com/artisan reverb:start --host=0.0.0.0 --port=8080`
   - **Directory:** the site root (`/home/forge/app.yourdomain.com`)
   - **User:** `forge`
2. **Expose it over `wss://`** — Reverb listens on `8080` internally; browsers
   connect over TLS on `443`. Create a Forge **subdomain site** `ws.yourdomain.com`
   and, under its **Nginx Configuration**, proxy to the Reverb port:

   ```nginx
   location / {
       proxy_pass http://127.0.0.1:8080;
       proxy_http_version 1.1;
       proxy_set_header Host $host;
       proxy_set_header Upgrade $http_upgrade;
       proxy_set_header Connection "Upgrade";
       proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
       proxy_set_header X-Forwarded-Proto $scheme;
   }
   ```

   Then issue SSL for `ws.yourdomain.com` too (Step 9). These are the values the
   `REVERB_HOST=ws.yourdomain.com` / `REVERB_PORT=443` lines in `.env` point at.

   > Alternative: skip the subdomain and open port `8080` in the firewall with
   > its own TLS. The subdomain-proxy approach above is cleaner and avoids
   > mixed-content/firewall issues.

## Step 9 — SSL

Site → **SSL** → **Let's Encrypt** for both `app.yourdomain.com` and
`ws.yourdomain.com`. Forge auto-renews.

## Step 10 — First deploy

1. Site → **Apps** → **Deploy Now**.
2. Watch the deploy output. On success, migrations have run, assets are built,
   caches are warm.
3. Set `APP_KEY` if you haven't (Step 5), then deploy once more.
4. Confirm the queue worker, scheduler, and Reverb daemon are **green** in their
   respective tabs.

---

## Release flow (day to day)

1. Merge your work into the deploy branch (`main`) via a GitHub PR.
2. With **Quick Deploy** on, the merge/push triggers `deploy.sh` automatically.
3. Watch Forge → Site → **Deployments** for the result.

## Rollback

Forge keeps a deployment history — use **Deployments → Redeploy** on a previous
commit. Because migrations run with `--force`, avoid destructive migrations; add
a reversing migration rather than editing history if a schema change must be
undone.

## Health checklist after a deploy

- App loads over `https://app.yourdomain.com`, no 500s.
- Real-time features work (Reverb daemon green, `wss://ws.yourdomain.com` connects).
- Queue worker green — notifications process (no stuck rows in `jobs`,
  nothing piling up in `failed_jobs`).
- Scheduler green — `schedule:run` fires every minute.
