import { createApp } from "vue";
import App from "./App.vue";

import PrimeVue from "primevue/config";
import Lara from '@primevue/themes/lara';

import Dialog from 'primevue/dialog';
import ProgressSpinner from 'primevue/progressspinner';
import Tooltip from 'primevue/tooltip';
import Toast from "primevue/toast";
import ToastService from 'primevue/toastservice';

const app = createApp(App);

app.use(PrimeVue, {
    theme: {
        preset: Lara,
        options: {
            darkModeSelector: false || 'none',
        }
    }
});
app.use(ToastService);

app.directive('tooltip', Tooltip);

app.component("Dialog", Dialog);
app.component("ProgressSpinner", ProgressSpinner);
app.component("Toast", Toast);

app.mount("#ORCA_SEARCH_CONFIG");