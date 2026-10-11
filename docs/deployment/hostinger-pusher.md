# Real-time notifications on shared hosting — Pusher Channels

**Audience:** whoever deploys and configures this app on Hostinger shared
hosting. Assumes access to hPanel, the app's `.env`, and the ability to run
`npm run build`.

This is the alternative to [`forge-setup.md`](forge-setup.md). That runbook uses
Laravel Reverb and needs three long-running processes; shared hosting gives you
none of them. Pick **one** of the two — they are not meant to be combined.

---

## Why Pusher here

Reverb is free software, and nothing about its licensing is the problem. The
problem is what it needs to run: a persistent process holding an open WebSocket
port. Hostinger shared hosting does not let you run daemons or open arbitrary
ports, so Reverb has nowhere to live.

Pusher Channels is a hosted broker reached over ordinary HTTPS on 443, so it
works from shared hosting without any process of your own.

### What the free plan covers

Pusher's free ("Sandbox") plan is, at time of writing, **200,000 messages/day,
100 concurrent connections, and 20 messages/second**. For an internal accounting
system with a few dozen staff that is comfortable — one approval notification to
five approvers is five messages. Confirm the current limits on Pusher's pricing
page before relying on these numbers; if you exceed concurrent connections, new
browsers simply fall back to polling (see below), they do not error.

---

## Step 1 — Create the Pusher app

1. Sign up at [pusher.com](https://pusher.com) → **Channels** → Create app.
2. Choose the cluster physically closest to your users — **`ap1` (Singapore)**
   for the Philippines. The cluster is part of the connection address, so
   getting it wrong means nothing connects.
3. Open **App Keys** and copy `app_id`, `key`, `secret`, `cluster`.

## Step 2 — Fill in `.env`

```dotenv
BROADCAST_CONNECTION=pusher

PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_key
PUSHER_APP_SECRET=your_secret
PUSHER_APP_CLUSTER=ap1
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
```

The `VITE_PUSHER_*` lines already in `.env.example` read from these, so you do
not set them separately.

> **The secret is server-side only.** `PUSHER_APP_SECRET` must never reach the
> browser — do not add a `VITE_` copy of it. Only the key and cluster are public.

## Step 3 — Rebuild the front end

The Pusher key is compiled into the JavaScript bundle, so a `.env` change alone
changes nothing in the browser:

```bash
npm run build
```

Then hard-refresh (Ctrl+Shift+R). Skipping this step is the single most common
reason "I set the keys and nothing happened".

## Step 4 — Confirm it works

1. Sign in as a user who holds an **approver** level somewhere.
2. In Pusher's dashboard open **Debug Console**.
3. Have someone submit something that needs approval — a loan, a purchase order.
4. You should see a `broadcast` event on `private-App.Models.User.<id>` within a
   second, and the bell badge should move without a page reload.

If the Debug Console stays empty, work through *Troubleshooting* below.

---

## Why there is no queue worker

Broadcasting normally goes through the queue, and `QUEUE_CONNECTION=database`
with no worker would leave every broadcast sitting in the `jobs` table forever.
Shared hosting cannot run `queue:work` as a daemon.

So the notification classes implement **`ShouldBroadcastNow`** instead of
`ShouldBroadcast` — they send inside the request that raised them. The trade-off
is deliberate: the request pays roughly 100–300 ms to talk to Pusher, and in
exchange the notification actually arrives.

Note that this cost is **per recipient**, because each approver is notified
individually. Three approvers on a module is unremarkable; if you ever grant
`approver` on one submodule to twenty people, the request that submits something
for approval will visibly slow down. That is the point at which a VPS with a real
queue worker is the right answer rather than a tuning exercise.

**Do not change these back to `ShouldBroadcast` unless you have also set up a
worker.** If you later move to a VPS, switching back plus a Supervisor-managed
worker is the better arrangement.

### Failures cannot break a save

Because broadcasting is synchronous, a Pusher outage happens *inside* someone's
request. `ApprovalRequestObserver` therefore wraps the whole notification step in
a try/catch and only logs on failure. A dead socket must never turn a failed
notification into a failed approval.

---

## Notifications still arrive without a socket

This matters for understanding what Pusher is actually buying you.

Every notification is written to the `notifications` table **first**, and the
bell and the approvals page both poll (`resources/js/Shared/polling.js`, 20 s,
paused while the tab is hidden or a modal is open). Pusher only makes them arrive
*sooner*.

So the system degrades in stages rather than breaking:

| State | Result |
|---|---|
| Pusher configured and reachable | Notifications appear within about a second |
| Keys blank, or Pusher unreachable | Notifications appear on the next 20 s poll |
| `BROADCAST_CONNECTION=log` | Same as above; broadcasts are written to the log |

`resources/js/echo.js` skips Echo entirely when `VITE_PUSHER_APP_KEY` is empty,
so a half-configured install does not throw on page load. Running with
`BROADCAST_CONNECTION=log` and no keys is a perfectly valid, if less immediate,
way to run this app.

### What is *not* live

Only notifications broadcast. The list screens (sales orders, receipts,
remittances, inventory stock, approvals) refresh on the 20 s poll, not instantly —
there are no broadcast events for ordinary record edits. Making those live would
mean adding per-module broadcast events, which is separate work.

---

## The scheduler

Four scheduled commands raise notifications (overdue invoices, unpaid same-day
orders, low stock, outstanding deliveries). Without the scheduler those never
run, Pusher or not. In hPanel → **Cron Jobs**, add:

```
* * * * * cd /home/<user>/domains/<domain>/public_html && php artisan schedule:run >> /dev/null 2>&1
```

Use the PHP binary path hPanel shows if a bare `php` is the wrong version.

---

## Troubleshooting

**Nothing in the Pusher Debug Console.** The server is not reaching Pusher.
Check `BROADCAST_CONNECTION=pusher`, then `php artisan config:clear`. Look in
`storage/logs/laravel.log` for a line from `ApprovalRequestObserver` —
"Approval notification failed" names the real error, because it is logged rather
than shown.

**Events in the console, but the browser ignores them.** The browser half is
misconfigured. Confirm `npm run build` ran *after* the `.env` change, then check
the browser console for a failed `/broadcasting/auth` request — a 403 there means
the session cookie is not reaching it, and a 404 means channel routes are not
registered.

**Wrong cluster.** A key from an `ap1` app with `PUSHER_APP_CLUSTER=mt1` fails to
connect with no useful error. They must match.

**Approval notifications reach the wrong people.** Recipients are derived from
`role_permissions`, not from a role name: everyone holding `approver` (or `admin`)
on that module/submodule, plus Super Admins. Fix the grant in
**Libraries → Roles** rather than looking for a recipient list in code — there
isn't one.

**The bell shows nothing but the approvals page has rows.** Expected, and not a
bug. The page reads live pending records; the bell reads notification history. A
record that became pending before this feature was deployed has no notification.
