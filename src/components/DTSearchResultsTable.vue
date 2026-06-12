<script setup>
import { ref, computed } from 'vue';
import {isEmpty, isNotEmpty} from '@primeuix/utils/object';
import DataTable from 'datatables.net-vue3'
import DataTablesLib from 'datatables.net-bs5';

DataTable.use(DataTablesLib);

const props = defineProps({
    config: Object,
    items: Array
});

const columns = computed(() => {
    let c = [];
    // TODO columndef for record home button as first or last col
    if (props.config.record_home_display === 'first') {
        c.push({
            data: '__URL__',
            title: 'Record Home',
            orderable: false,
            render: (data, type) => {
                if (type === 'display') {
                    return `<a href="${data}" class="btn btn-xs btn-primary text-nowrap text-white text-decoration-none" role="button"><i class="fas fa-edit"></i>&nbsp;Open</a>`;
                }
                return data;
            }
        });
    }
    // TODO custom link rendering for tablePk
    if (isNotEmpty(props?.config?.display_fields)) {
        Object.entries(props.config.display_fields).forEach(([k, v]) => {
            let d = {
                data: {
                    _: `${k}.value`
                },
                title: v.label
            };
            if (k === props.config.table_pk) {
                d.orderable = false;
                d.render = (data, type) => {
                    if (type === 'display') {
                        // return `<a href="${data['__URL__']}" class="btn btn-xs btn-link text-decoration-underline">${data.value}</a>`;
                        return `<pre class="mb-0">${data}</pre>`;
                    }
                    return data;
                };
            } else {
                d.data.sort = `${k}.sort`;
            }
            c.push(d);
        });
    }
    return c;
});

const options = computed(() => {
    let opt = {};
    if (isNotEmpty(props?.config?.display_fields_sort)) {
        opt.order = props.config.display_fields_sort;
    }
    return opt;
});

const data = computed(() => props?.items ?? []);

</script>

<template>
    <template v-if="isNotEmpty(columns)">
        <DataTable
            :columns="columns"
            :options="options"
            :data="data"
            class="display"
        />
    </template>
</template>

<style>
@import 'datatables.net-dt';
</style>