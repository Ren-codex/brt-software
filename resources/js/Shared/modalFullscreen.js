/**
 * Puts one modal, not the page, into the browser's full screen: the modal
 * takes the whole monitor with no tabs or address bar, and the page stays
 * behind it. Esc or the button brings it back.
 *
 *   mixins: [modalFullscreenMixin]
 *   <div class="modal-container" ref="fullscreenTarget">
 *       <button @click="toggleModalFullscreen">
 *           <i :class="modalFullscreen ? 'bx bx-exit-fullscreen' : 'bx bx-fullscreen'"></i>
 *       </button>
 *
 * modalFullscreen follows the browser, so leaving with Esc updates the icon.
 */
const fullscreenElement = () => document.fullscreenElement || document.webkitFullscreenElement || null;

export const modalFullscreenMixin = {
    data() {
        return { modalFullscreen: false };
    },
    mounted() {
        this._onFullscreenChange = () => {
            const target = this.$refs.fullscreenTarget;
            this.modalFullscreen = !!target && fullscreenElement() === target;
        };
        document.addEventListener('fullscreenchange', this._onFullscreenChange);
        document.addEventListener('webkitfullscreenchange', this._onFullscreenChange);
    },
    beforeUnmount() {
        this.exitModalFullscreen();
        document.removeEventListener('fullscreenchange', this._onFullscreenChange);
        document.removeEventListener('webkitfullscreenchange', this._onFullscreenChange);
    },
    methods: {
        toggleModalFullscreen() {
            const target = this.$refs.fullscreenTarget;
            if (!target) return;
            if (fullscreenElement() === target) {
                this.exitModalFullscreen();
                return;
            }
            const request = target.requestFullscreen || target.webkitRequestFullscreen;
            // A browser that refuses leaves the modal as it was.
            request?.call(target)?.catch?.(() => {});
        },
        /** For a modal closing while full screen. */
        exitModalFullscreen() {
            const target = this.$refs.fullscreenTarget;
            if (!target || fullscreenElement() !== target) return;
            const exit = document.exitFullscreen || document.webkitExitFullscreen;
            exit?.call(document)?.catch?.(() => {});
        },
    },
};
