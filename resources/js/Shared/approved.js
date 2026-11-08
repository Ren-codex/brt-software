import { reactive } from 'vue';

/**
 * One shared "Approved" confirmation — a big-check modal plus a short chime —
 * triggered from anywhere with `this.$approved('<what was approved>')`.
 *
 * The modal is rendered once by <ApprovalSuccess> in the main layout, reading
 * this shared reactive state; the chime is the "Major Chord Climb" tone.
 */
const state = reactive({ open: false, label: '', timer: null });

let audioCtx = null;
let unlockArmed = false;

/** Create/resume the audio context. Safe to call repeatedly. */
function prime() {
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        if (!audioCtx) audioCtx = new Ctx();
        if (audioCtx.state === 'suspended') audioCtx.resume();
    } catch (e) {
        /* audio unavailable — the modal still shows */
    }
}

/**
 * Browsers only allow audio after a user gesture. The chime usually fires in an
 * async success handler, outside the gesture, so unlock the context on the
 * first interaction anywhere; after that it is free to play.
 */
function armAudioUnlock() {
    if (unlockArmed || typeof window === 'undefined') return;
    unlockArmed = true;
    window.addEventListener('pointerdown', prime, { capture: true });
    window.addEventListener('keydown', prime, { capture: true });
}

function playTone() {
    prime();
    const ctx = audioCtx;
    if (!ctx) return;
    try {
        const now = ctx.currentTime;
        // "Major Chord Climb": C5 → E5 → G5, a small ascending "ta-da".
        [[523.25, 0, 0.14], [659.25, 0.11, 0.14], [783.99, 0.22, 0.22]].forEach(([freq, offset, dur]) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            const t = now + offset;
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, t);
            gain.gain.setValueAtTime(0.0001, t);
            gain.gain.exponentialRampToValueAtTime(0.16, t + 0.015);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + dur);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(t);
            osc.stop(t + dur + 0.03);
        });
    } catch (e) {
        /* ignore */
    }
}

/** Show the big-check modal (auto-dismissing after 2s) and play the chime. */
export function showApproved(label) {
    state.label = label || 'The request';
    state.open = true;
    playTone();
    if (state.timer) clearTimeout(state.timer);
    state.timer = setTimeout(() => { state.open = false; }, 2000);
}

export function closeApproved() {
    if (state.timer) { clearTimeout(state.timer); state.timer = null; }
    state.open = false;
}

export const approvedState = state;

export const ApprovedPlugin = {
    install(app) {
        armAudioUnlock();
        app.config.globalProperties.$approved = showApproved;
    },
};
