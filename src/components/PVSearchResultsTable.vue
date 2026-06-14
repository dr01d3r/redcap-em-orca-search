<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import {isEmpty, isNotEmpty} from '@primeuix/utils/object';
import { FilterMatchMode } from '@primevue/core/api';

const props = defineProps({
    config: {
        type: Object,
        required: true
    },
    items: {
        type: Array,
        default: () => []
    },
    searchField: {
        type: String,
        default: () => null
    },
    searchValue: {
        type: String,
        default: () => null
    }
});

const quickHighlight = (rawString, target) => {
    // first ensure the target column is the searched column
    if (target !== props.searchField) return rawString;

    // only look at text fields that don't have custom validation (i.e. date validation)
    let fi = props.config.display_fields[target];
    if (![ "text", "textarea" ].includes(fi.type) || fi.validation?.startsWith('date')) {
        return rawString;
    }

    // pull search phrase from props
    let searchPhrase = props.searchValue;
    if (!searchPhrase || searchPhrase.trim() === "") return rawString;

    // 1. Check if the phrase even exists in the string before doing anything else
    if (!rawString.toLowerCase().includes(searchPhrase.toLowerCase())) {
        return rawString;
    }

    // 2. Escape special regex characters in the search phrase safely
    const escapedPhrase = searchPhrase.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${escapedPhrase})`, 'gi');

    // 3. FAST PATH: If there are no HTML tags, do a lightning-fast string replacement
    if (!rawString.includes('<') || !rawString.includes('>')) {
        return rawString.replace(regex, '<span class="highlight">$1</span>');
    }

    // 4. SLOW PATH: Fallback only for the rare strings containing HTML
    const container = document.createElement('div');
    container.innerHTML = rawString;

    const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT);
    const nodesToProcess = [];
    let currentNode;

    while ((currentNode = walker.nextNode())) {
        if (regex.test(currentNode.nodeValue)) {
            nodesToProcess.push(currentNode);
        }
    }

    for (let i = 0; i < nodesToProcess.length; i++) {
        const textNode = nodesToProcess[i];
        const spanWrapper = document.createElement('span');
        spanWrapper.innerHTML = textNode.nodeValue.replace(regex, '<span class="highlight">$1</span>');

        while (spanWrapper.firstChild) {
            textNode.parentNode.insertBefore(spanWrapper.firstChild, textNode);
        }
        textNode.parentNode.removeChild(textNode);
    }

    return container.innerHTML;
}

const multiSortMeta = ref([]);
const initialSortFields = computed(() =>
    (props?.config?.display_fields_sort ?? [])
        .map(f => ({ field: `${f.field}.sort`, order: f.order }))
);
watch(initialSortFields, (fields) => {
    if (fields.length) multiSortMeta.value = fields
}, { immediate: true });

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS }
});
const globalFilterFields = computed(() => props?.config?.display_fields ? Object.keys(props.config.display_fields).map(k => `${k}.value`) : []);

const clearGlobalFilter = () => {
    filters.value.global.value = null;
}
const tableSize = ref('small');
const tableSizeOptions = ref([
    { label: 'Small', value: 'small' },
    { label: 'Normal', value: 'null' },
    { label: 'Large', value: 'large' }
]);
const tableRowButtonSize = computed(() => {
    switch (tableSize.value) {
        case 'small': return 'btn-xs';
        case 'large': return '';
        default: return 'btn-sm';
    }
});
</script>

<template>
    <DataTable :value="props.items"
               :size="tableSize"
               v-model:filters="filters" :globalFilterFields="globalFilterFields"
               stripedRows scrollable removableSort sortMode="multiple" :multiSortMeta="multiSortMeta"
               paginator :rows="50" :rowsPerPageOptions="[5, 10, 20, 50]"
               paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
               currentPageReportTemplate="Showing {first} to {last} of {totalRecords} results"
               tableStyle="width: 100%" class="p-datatable-sm">
        <template #header>
            <div class="d-flex justify-content-end align-items-center">
                <div class="input-group w-auto">
                                    <span class="input-group-text">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                    </span>
                    <input type="text" class="form-control" placeholder="Filter Results" v-model="filters['global'].value">
                    <button class="btn btn-outline-danger" type="button" @click="clearGlobalFilter" :disabled="isEmpty(filters['global'].value)">
                        <i class="fa-solid fa-filter-circle-xmark"></i>
                    </button>
                </div>
            </div>
        </template>
        <template #empty>No results found.</template>
        <template #loading>Loading result data. Please wait...</template>
        <Column v-if="props.config.record_home_display === 'first'" header="Record Home">
            <template #body="{ data }">
                <a :href="data['__URL__']" class="btn btn-primary text-nowrap text-white text-decoration-none" role="button" :class="tableRowButtonSize">
                    <i class="fas fa-edit"></i>&nbsp;Open
                </a>
            </template>
        </Column>
        <Column v-for="(col, col_key) of props.config.display_fields" :key="col_key" :field="col_key" :header="col.label"
                :sortable="true" :sortField="`${col_key}.sort`" :filterField="`${col_key}.value`"
        >
            <template #body="{ data }">
                <template v-if="typeof data[col_key]['value'] === 'object'">
                    <ul class="mb-0">
                        <li v-for="(vv) in data[col_key]['value']" v-html="quickHighlight(vv, col_key)"></li>
                    </ul>
                    <div v-if="props.config.display_context_enabled && data[col_key]['scope']"><small class="font-monospace text-nowrap text-black-50">{{ data[col_key]['scope'] }}</small></div>
                </template>
                <template v-else>
                    <template v-if="col_key === props.config.table_pk">
                        <a :href="data['__URL__']" class="btn btn-link text-decoration-underline" :class="tableRowButtonSize" v-html="quickHighlight(data[col_key]['value'], col_key)"></a>
                    </template>
                    <template v-else>
                        <div class="m-0">
                            <div v-html="quickHighlight(data[col_key]['value'], col_key)"></div>
                            <div v-if="props.config.display_context_enabled && data[col_key]['scope']"><small class="font-monospace text-nowrap text-black-50">{{ data[col_key]['scope'] }}</small></div>
                        </div>
                    </template>
                </template>
            </template>
            <template #filter="{ filterModel, filterCallback }">
                <InputText v-model="filterModel.value" type="text" @input="filterCallback()" class="p-column-filter" :placeholder="`Search by ${col.label}`" />
            </template>
        </Column>
        <Column v-if="props.config.record_home_display === 'last'" header="Record Home">
            <template #body="{ data }">
                <a :href="data['__URL__']" class="btn btn-primary text-nowrap text-white text-decoration-none" role="button" :class="tableRowButtonSize">
                    <i class="fas fa-edit"></i>&nbsp;Open
                </a>
            </template>
        </Column>
    </DataTable>
</template>

<style>
/* Style your highlighted text node safely */
.highlight {
    background-color: yellow;
    color: black;
    font-weight: bold;
}
</style>