<template>
    <div v-if="s.open" class="modal-overlay active approval-success-overlay" @click.self="close">
        <div class="modal-container approval-success-modal" @click.stop>
            <div class="modal-body approval-success-body">
                <div class="approval-success-icon">
                    <svg class="approval-check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                        <circle class="approval-check-circle" cx="26" cy="26" r="25" fill="none" />
                        <path class="approval-check-mark" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
                    </svg>
                </div>
                <h4 class="approval-success-title">Approved</h4>
                <p class="approval-success-text">{{ s.label }} has been approved.</p>
            </div>
        </div>
    </div>
</template>

<script>
import { approvedState, closeApproved } from '@/Shared/approved';

export default {
    name: 'ApprovalSuccess',
    computed: {
        s() { return approvedState; },
    },
    methods: {
        close() { closeApproved(); },
    },
};
</script>

<style scoped>
/* Content-specific only — modal chrome comes from _library-modal.scss. */
.approval-success-modal { max-width: 380px; }
.approval-success-body {
    padding: 2rem 1.75rem 1.75rem;
    display: flex; flex-direction: column; align-items: center; text-align: center;
}
.approval-success-icon { width: 104px; height: 104px; margin: 0 auto 1rem; display: grid; place-items: center; }
.approval-check {
    width: 104px; height: 104px; border-radius: 50%; display: block;
    stroke-width: 3; stroke: var(--brt-primary, #3d8d7a); stroke-miterlimit: 10;
    box-shadow: inset 0 0 0 var(--brt-primary, #3d8d7a);
    animation: approvalCheckFill 0.4s ease-in-out 0.4s forwards, approvalCheckScale 0.3s ease-in-out 0.9s both;
}
.approval-check-circle {
    stroke-dasharray: 166; stroke-dashoffset: 166; stroke-width: 3; stroke-miterlimit: 10;
    stroke: var(--brt-primary, #3d8d7a); fill: none;
    animation: approvalCheckStroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
}
.approval-check-mark {
    transform-origin: 50% 50%; stroke: #fff; stroke-width: 4;
    stroke-linecap: round; stroke-linejoin: round;
    stroke-dasharray: 48; stroke-dashoffset: 48;
    animation: approvalCheckStroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
}
@keyframes approvalCheckStroke { 100% { stroke-dashoffset: 0; } }
@keyframes approvalCheckScale { 0%, 100% { transform: none; } 50% { transform: scale3d(1.1, 1.1, 1); } }
@keyframes approvalCheckFill { 100% { box-shadow: inset 0 0 0 52px var(--brt-primary, #3d8d7a); } }
.approval-success-title { margin: 0; font-weight: 800; color: #16322e; font-size: 1.3rem; }
.approval-success-text { margin: 0.35rem 0 0; color: #6b8c85; font-size: 0.9rem; }
</style>
