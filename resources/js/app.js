import './bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp, Link, Head } from '@inertiajs/vue3';
import ConfirmPlugin from '@/Shared/confirm-plugin';
import BootstrapVueNext from 'bootstrap-vue-next';
import VueApexCharts from "vue3-apexcharts";
import Toast from "vue-toastification";
import "vue-toastification/dist/index.css";
import Layout from '@/Shared/Layouts/Main.vue'
import store from "@/Shared/State/store";
import { applyInitialTheme } from "@/Shared/theme";
import SortSelect from "@/Shared/Components/SortSelect.vue";
import { permissionsMixin } from '@/Shared/permissions';
import AOS from 'aos';

AOS.init({
    easing: 'ease-out-back',
    duration: 1000
});

// Before anything is drawn: apply the signed-in user's theme, read from the
// page data the server sent, so the page does not flash green and then change.
applyInitialTheme();

const appName = import.meta.env.VITE_APP_NAME || 'BRT Accounting Software';

if (window.history.state === null) {
  window.history.replaceState({}, '');
}

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
        let page = pages[`./Pages/${name}.vue`].default
        if (page.layout === undefined) {
            page.layout = Layout;
        }
        return pages[`./Pages/${name}.vue`]
    },
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(store)
            .mixin(permissionsMixin)
            .use(BootstrapVueNext)
            .use(VueApexCharts)
            .use(Toast)
            .use(ConfirmPlugin)
            .component("Link", Link)
            .component("Head", Head)
            // On every list page, so it is registered once here.
            .component("SortSelect", SortSelect)
            .mount(el);
    },
    title: title => title ? `${appName} | ${title}` : appName,
});
