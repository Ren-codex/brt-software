import { createApp, h } from 'vue';
import ConfirmDialog from '@/Shared/Components/ConfirmDialog.vue';

function showConfirm(options = {}) {
    return new Promise((resolve) => {
        const el = document.createElement('div');
        document.body.appendChild(el);

        const app = createApp({
            render() {
                return h(ConfirmDialog, {
                    ...options,
                    onConfirm() { resolve(true);  cleanup(); },
                    onCancel()  { resolve(false); cleanup(); },
                });
            },
        });

        app.mount(el);

        function cleanup() {
            app.unmount();
            el.remove();
        }
    });
}

export default {
    install(app) {
        app.config.globalProperties.$confirm = showConfirm;
    },
};
