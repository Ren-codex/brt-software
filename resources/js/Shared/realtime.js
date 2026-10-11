/**
 * Live updates, written so that a missing socket is never a broken page.
 *
 * Broadcasting in this app is best-effort by design. Pusher is reached over 443
 * because shared hosting cannot run Reverb, but the credentials may be blank, the
 * network may block it, or the free plan's connection limit may be reached. Every
 * notification is therefore written to the database first and the screens also
 * poll (see polling.js); what Echo adds is *immediacy*, not delivery.
 *
 * So each helper here:
 *   - does nothing at all when window.Echo is undefined,
 *   - returns a teardown function callers run on unmount,
 *   - never throws into the caller's lifecycle hook.
 *
 * Teardown removes only the caller's own handler. The bell and the approvals
 * page listen on the same private channel, so leaving the channel when one of
 * them unmounts would silently disconnect the other.
 */

const NOTIFICATION_EVENT = '.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated';
const RESOLVED_EVENT = '.approval.resolved';

/** Fired in this browser when the user themselves changes what is pending. */
const LOCAL_CHANGE_EVENT = 'approvals:changed';

/** The private channel Laravel notifies a user on. */
function userChannel(vm) {
    const id = vm?.$page?.props?.user?.data?.id;
    return id ? `App.Models.User.${id}` : null;
}

/** Listen for one broadcast event on the signed-in user's channel. */
function listen(vm, event, handler) {
    const channel = userChannel(vm);

    if (!channel || !window.Echo) {
        return () => {};
    }

    try {
        const subscription = window.Echo.private(channel);
        subscription.listen(event, handler);

        return () => {
            try {
                subscription.stopListening(event, handler);
            } catch {
                // Already gone — nothing to undo.
            }
        };
    } catch {
        // A transport that will not start is the same as not having one.
        return () => {};
    }
}

/**
 * Run `handler` whenever a notification arrives for the signed-in user.
 * `types` optionally filters by the notification's `type` field.
 */
export function onUserNotification(vm, handler, types = null) {
    return listen(vm, NOTIFICATION_EVENT, notification => {
        if (types && !types.includes(notification.type)) {
            return;
        }
        handler(notification);
    });
}

/**
 * Run `handler` whenever the set of things awaiting this user's approval may
 * have changed, from any of three directions:
 *
 *   - a new request arrived (broadcast),
 *   - a request was actioned by anyone, on any screen (broadcast),
 *   - this user just actioned one in this browser (local event — instant, and
 *     works with no socket at all).
 *
 * The handler gets no payload; it should simply refetch.
 */
export function onApprovalChange(vm, handler) {
    const stopNew = onUserNotification(vm, () => handler(), ['approval_request']);
    const stopResolved = listen(vm, RESOLVED_EVENT, () => handler());

    const local = () => handler();
    window.addEventListener(LOCAL_CHANGE_EVENT, local);

    return () => {
        stopNew();
        stopResolved();
        window.removeEventListener(LOCAL_CHANGE_EVENT, local);
    };
}

/** Tell every listener in this browser that pending approvals just changed. */
export function announceApprovalChange() {
    window.dispatchEvent(new Event(LOCAL_CHANGE_EVENT));
}
