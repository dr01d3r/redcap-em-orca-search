import { createApp } from 'vue';
import App from './App.vue';

import PrimeVue from 'primevue/config';
import Lara from '@primevue/themes/lara';

import Dialog from 'primevue/dialog';
import ProgressSpinner from 'primevue/progressspinner';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
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

app.component("Button", Button);
app.component("InputText", InputText);
app.component("DataTable", DataTable);
app.component("Column", Column);
app.component("Toast", Toast);

app.component("Dialog", Dialog);
app.component("ProgressSpinner", ProgressSpinner);

app.mount('#ORCA_SEARCH');