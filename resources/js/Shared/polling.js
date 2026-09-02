/**
 * Keeps a list screen current without the user reloading.
 *
 * The app has no working websocket transport — broadcasting is set to the log
 * driver and the host blocks the ports Reverb would need — so screens refresh
 * on a timer instead. See the realtime-updates notes for the full background.
 *
 * The point of this mixin is that a refresh should be *invisible*. It:
 *   - pauses while the tab is hidden, so background tabs cost nothing,
 *   - pauses while a modal is open, so data never shifts under a dialog,
 *   - skips a tick if the previous request is still in flight, so a slow
 *     response cannot stack requests up.
 *
 * Usage:
 *   mixins: [pollingMixin],
 *   mounted() { this.startPolling(() => this.fetch(this.currentPageUrl, { quiet: true })); },
 */

export const POLL_INTERVAL_MS = 20000;

/** A modal is open when the app's overlay element is present and active. */
function aModalIsOpen() {
    return document.querySelector('.modal-overlay.active') !== null;
}

export const pollingMixin = {
    data() {
        return {
            pollTimer: null,
            pollInFlight: false,
            lastUpdatedAt: null,
        };
    },
    methods: {
        /**
         * `callback` should return a promise so a slow request delays the next
         * tick rather than overlapping with it.
         */
        startPolling(callback, intervalMs = POLL_INTERVAL_MS) {
            this.stopPolling();

            this.pollTimer = setInterval(async () => {
                if (document.hidden || this.pollInFlight || aModalIsOpen()) {
                    return;
                }

                this.pollInFlight = true;
                try {
                    await callback();
                    this.lastUpdatedAt = new Date();
                } catch {
                    // A failed refresh is not worth interrupting the user for —
                    // the next tick will try again.
                } finally {
                    this.pollInFlight = false;
                }
            }, intervalMs);
        },
        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },
        /** Short, human label for the subtle "kept up to date" indicator. */
        lastUpdatedLabel() {
            if (!this.lastUpdatedAt) return '';
            const seconds = Math.round((Date.now() - this.lastUpdatedAt.getTime()) / 1000);
            if (seconds < 10) return 'Updated just now';
            if (seconds < 60) return `Updated ${seconds}s ago`;
            const minutes = Math.round(seconds / 60);
            return `Updated ${minutes}m ago`;
        },
    },
    beforeUnmount() {
        this.stopPolling();
    },
};
