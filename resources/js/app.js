import './bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp, Link, Head } from '@inertiajs/vue3';
import BootstrapVueNext from 'bootstrap-vue-next';
import VueApexCharts from "vue3-apexcharts";
import Layout from '@/Shared/Layouts/Main.vue'
import store from "@/Shared/State/store";
import AOS from 'aos';

AOS.init({
    easing: 'ease-out-back',
    duration: 1000
});

const appName = import.meta.env.VITE_APP_NAME || 'BRT Accounting Software';

if (window.history.state === null) {
  window.history.replaceState({}, '');
}

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        const key = `./Pages/${name}.vue`;
        const module = pages[key];
        if (!module) {
            // helpful debug output when a page isn't found
            console.error('Inertia page not found:', name, Object.keys(pages));
            throw new Error(`Page not found: ${name}`);
        }
        const page = module.default;
        if (page.layout === undefined) {
            page.layout = Layout;
        }
        return page;
    },
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(store)
            .use(BootstrapVueNext)
            .use(VueApexCharts)
            .component("Link", Link)
            .component("Head", Head)
            .mount(el);
    },
    title: title => title ? `${appName} | ${title}` : appName,
});
