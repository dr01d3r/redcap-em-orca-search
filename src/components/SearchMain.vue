<script setup>
import {
    ref,
    watch,
    computed,
    nextTick,
    onBeforeMount
} from 'vue'
import {isEmpty, isNotEmpty, isArray} from '@primeuix/utils/object';

// import DTSearchResultsTable from "./DTSearchResultsTable.vue";
import PVSearchResultsTable from './PVSearchResultsTable.vue';

const debug = ref();
const config = ref({});
const errors = ref([]);

const isLoading = ref(false);

const searchSelect = ref();
const searchInput = ref();

// data variables
const items = ref();

const searchField = ref();
const searchValue = ref();
const newRecordValue = ref(null);

const dtSearchField = ref();
const dtSearchValue = ref();

const newRecordLabel = computed(() => {
    return `New ${config.value.new_record_label}`;
});
const searchFields = computed(() => {
    return config.value?.search_fields ?? {};
});
const searchFieldValues = computed(() => {
    if (searchField.value && config.value.search_fields[searchField.value].values) {
        return config.value.search_fields[searchField.value].values;
    }
    return null;
});
const canSearch = computed(() => {
    return searchField.value;
});
const canOpenNewRecord = computed(() => {
    // dev_max_record_limit_reached, dev_max_record_limit_reached_html
    return config.value.dev_max_record_limit_reached !== true
        && (config.value.auto_numbering || (newRecordValue.value !== null && newRecordValue.value !== ''))
        ;
});

watch(searchField, async() => {
    if (isNotEmpty(searchField.value)) {
        searchValue.value = "";
    }
    await nextTick(() => setTimeout(() => {
        if (isNotEmpty(searchFieldValues.value)) {
            searchSelect.value.focus();
        } else {
            searchInput.value.focus();
        }
    }, 100));
}, { flush: 'post' });

const setLoading = async (x) => {
    isLoading.value = x;
}

const init = async () => {
    await setLoading(true);
    OrcaSearch().jsmo.ajax('initialize-search-dashboard', {})
        .then(function(response) {
            config.value = response;
            // auto select first value
            nextTick(() => {
                if (isNotEmpty(searchFields.value)) {
                    searchField.value = Object.keys(searchFields.value)[0];
                }
            });
        })
        .catch(function(err) {
            debug.value = err;
        })
        .finally(async () => {
            await setLoading(false);
        });
};

const search = async () => {
    if (!canSearch) return;
    await setLoading(true);
    items.value = null;
    debug.value = null;
    errors.value = [];
    dtSearchField.value = searchField.value;
    dtSearchValue.value = structuredClone(searchValue.value?.trim());
    OrcaSearch().jsmo.ajax('search', {
        field: dtSearchField.value,
        value: dtSearchValue.value
    })
        .then(function(response) {
            if (response.errors) {
                errors.value = isArray(response.errors) ? response.errors : [ response.errors ];
            } else {
                items.value = response.data;
            }
        })
        .catch(function(err) {
            debug.value = err;
        })
        .finally(async () => {
            await setLoading(false);
        })
    ;
}

function openNewRecord() {
    if (!canOpenNewRecord) return;
    let new_url = config.value.new_record_url;
    if (config.value.auto_numbering === true) {
        new_url = `${new_url}&id=${config.value.new_record_auto_id}`;
    } else {
        new_url = `${new_url}&id=${newRecordValue.value}`;
    }
    window.location.href = new_url;
}

onBeforeMount(async () => {
    await init();
});
</script>

<template>
    <div class="projhdr">
        <i class="fa-solid fa-magnifying-glass"></i>&nbsp;Search Dashboard
    </div>
    <template v-if="config">
        <div class="card">
            <!-- Default panel contents -->
            <div class="card-header">
                <div class="row">
                    <div class="form-group col-lg">
                        <label for="search-field">Search Field</label><br/>
                        <select v-model="searchField" placeholder="Select a search field" class="form-select">
                            <option v-for="(v, k) in searchFields" :value="k">{{ v.label }}</option>
                        </select>
                    </div>
                    <!-- This condition is copied below for responsiveness support -->
                    <template v-if="config && config.auto_numbering">
                        <div class="form-group col-lg d-none d-lg-block">
                            <label>New Record</label><br/>
                            <button type="button" class="btn btn-secondary w-100" @click="openNewRecord" :disabled="!canOpenNewRecord">{{ config.new_record_text }}</button>
                        </div>
                    </template>
                    <template v-else>
                        <div class="col-lg d-none d-lg-block">
                            <label>New Record</label><br/>
                            <div class="input-group">
                                <input type="text" autocomplete="new-password" class="form-control" @keyup.enter="openNewRecord" v-model="newRecordValue" :placeholder="newRecordLabel" />
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-secondary" @click="openNewRecord" :disabled="!canOpenNewRecord">{{ config.new_record_text }}</button>
                                </span>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="row">
                    <div class="form-group col-lg">
                        <label for="search-field" class="w-100">Search Text<span class="text-danger" v-if="config.empty_search_disabled"> *</span></label>
                        <select v-show="searchFieldValues" v-model="searchValue" placeholder="Select a value" class="form-select" ref="searchSelect">
                            <option v-for="(v, k) in searchFieldValues" :value="k">{{ v }}</option>
                        </select>
                        <input v-show="!searchFieldValues" type="text" class="form-control" @keyup.enter="search" v-model="searchValue" ref="searchInput" />
                    </div>
                    <!-- This is copied below for responsiveness support -->
                    <div class="form-group col-lg d-none d-lg-block">
                        <label class="w-100">&nbsp;</label>
                        <button type="button" class="btn btn-primary w-100" @click="search" :disabled="!canSearch">Search</button>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-12 d-lg-none">
                        <button type="button" class="btn btn-primary w-100" @click="search" :disabled="!canSearch">Search</button>
                    </div>
                    <template v-if="config.auto_numbering">
                        <div class="form-group col-12 d-lg-none">
                            <label>New Record</label><br/>
                            <button type="button" class="btn btn-secondary w-100" @click="openNewRecord" :disabled="!canOpenNewRecord">{{ config.new_record_text }}</button>
                        </div>
                    </template>
                    <template v-else>
                        <div class="col-12 d-lg-none">
                            <label>New Record</label><br/>
                            <div class="input-group">
                                <input type="text" class="form-control" v-model="newRecordValue" :placeholder="newRecordLabel" />
                                <span class="input-group-btn">
                            <button type="button" class="btn btn-secondary" @click="openNewRecord" :disabled="!canOpenNewRecord">{{ config.new_record_text }}</button>
                        </span>
                            </div>
                        </div>
                    </template>
                </div>
                <template v-if="config && config.dev_max_record_limit_reached_html">
                    <div v-html="config.dev_max_record_limit_reached_html"></div>
                </template>
            </div>
            <div class="card-body">
                <PVSearchResultsTable :config="config" :items="items" :searchField="dtSearchField" :searchValue="dtSearchValue" />
                <div class="vstack">
                    <template v-if="config.has_repeating_forms">
                        <template v-if="config.instance_search === 'LATEST'">
                            <i class="mt-2 text-muted">* Search will only return matches that occur within the <b>latest</b> instance of a form.</i>
                        </template>
                        <template v-else>
                            <i class="mt-2 text-muted">* Search will return matches that occur in <b>any</b> instance of a form.</i>
                        </template>
                    </template>
                    <template v-if="config.user_dag">
                        <i class="mt-2 text-muted">* Search results will be limited to the <b>{{ config.groups[config.user_dag] }}</b> Data Access Group.</i>
                    </template>
                    <template v-if="config.search_limit">
                        <i class="mt-2 text-muted">* If search results exceed <b>{{ config.search_limit }}</b> records, no results will be returned.</i>
                    </template>
                </div>
            </div>
        </div>
    </template>
    <div class="mt-3 alert alert-danger" v-for="e in errors"><strong>ERROR:</strong>&nbsp;{{ e }}</div>
    <pre class="mt-3" v-if="debug">{{ debug }}</pre>

    <Dialog v-model:visible="isLoading"
            modal
            :dismissable-mask="false"
            :closable="false"
            pt:root:class="border-0 bg-transparent shadow-none"
            pt:mask:class="bg-dark bg-opacity-50 backdrop-blur"
    >
        <template #container>
            <div class="d-flex flex-column align-items-center justify-content-center p-3">
                <ProgressSpinner
                    stroke-width="4"
                    animation-duration=".8s"
                    aria-label="Loading content"
                />
            </div>
        </template>
    </Dialog>
</template>

<style lang="scss">
#os-search-table > thead > tr > th {
    text-overflow: ellipsis;
}
</style>