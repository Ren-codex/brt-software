import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

/**
 * Pusher Channels, not Reverb.
 *
 * Reverb is free software, but it needs a long-running process and an open
 * websocket port — neither of which shared hosting (Hostinger) gives us. Pusher
 * is a hosted broker reached over 443, so it works where Reverb cannot.
 *
 * Echo is only ever a *nicer* path for updates, never the only one. Every screen
 * that listens also polls (see polling.js), because the notification itself is
 * written to the database and therefore survives a dropped socket, a blocked
 * port, or missing credentials. If VITE_PUSHER_APP_KEY is unset we skip Echo
 * entirely rather than throw on boot.
 */
const key = import.meta.env.VITE_PUSHER_APP_KEY;
const cluster = import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'ap1';

if (key) {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key,
        cluster,
        forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
        wsHost: import.meta.env.VITE_PUSHER_HOST || undefined,
        wsPort: import.meta.env.VITE_PUSHER_PORT || undefined,
        enabledTransports: ['ws', 'wss'],
    });
} else {
    // Left undefined on purpose: callers already guard on `window.Echo`, and a
    // missing socket degrades to polling instead of breaking the page.
    window.Echo = undefined;
}
