<script setup>
import {onBeforeMount, onBeforeUnmount, computed, ref, watch} from "vue";
import {isEmpty, isNotEmpty} from '@primeuix/utils/object';
import {useToast} from 'primevue/usetoast';
import FieldSelectDialog from './FieldSelectDialog.vue';

import { useDragAndDrop } from '@formkit/drag-and-drop/vue';

const toast = useToast();
const showToast = (type, summary, detail) => {
    toast.add({
        group: 'general',
        severity: type,
        summary: summary,
        detail: detail,
        life: 3000
    });
};

const debug = ref();
const config = ref({});
const metadata = ref({});
const searchFields = ref([]);
const displayFields = ref([]);

const isLoading = ref(false);
const isModified = ref(false);

const isFieldDialogVisible = ref(false);
const fieldSelectModal = ref();

const toggleIcon = (v) => {
    if (v === true) {
        return 'fas fa-toggle-on text-success';
    }
    return 'fas fa-toggle-off text-dark';
};
const toggleConfigOption = (f) => {
    config.value[f] = !config.value[f];
};

const toggleDisplaySort = (i, oldVal) => {
    let newVal = !oldVal;
    displayFields.value[i]['sort'] = newVal;
    if (newVal !== true) {
        displayFields.value[i]['direction'] = null;
        displayFields.value[i]['priority'] = null;
    }
};

const configModified = async () => {
    if (!isLoading.value && !isModified.value) {
        isModified.value = true;
        toast.add({
            group: 'modified',
            severity: 'warn',
            summary: 'Configuration Modified',
            detail: 'Changes have been made to the configuration.  Be sure to save prior to leaving this page so changes are not lost!'
        });
    }
}

// watchers for config modification
watch(config, configModified, { deep: true });
watch(searchFields, configModified, { deep: true });
watch(displayFields, configModified, { deep: true });

const editSearchFields = () => {
    if (searchFields.value !== null && isNotEmpty(metadata.value)) {
        fieldSelectModal.value.show(metadata.value, 'search_fields', searchFields.value);
    }
};
const editDisplayFields = () => {
    if (displayFields.value !== null && isNotEmpty(metadata.value)) {
        fieldSelectModal.value.show(metadata.value, 'display_fields', displayFields.value);
    }
};

const onFieldsSelected = (scope, fields) => {
    let f = [];
    let _ = [];
    switch (scope) {
        case 'search_fields': _ = searchFields.value; break;
        case 'display_fields': _ = displayFields.value; break;
    }
    // first preserve fields that are still selected from before
    for (let i = 0; i < _.length; i++) {
        if (fields[_[i]['name']]) {
            f.push(_[i]);
            delete fields[_[i]['name']];
        }
    }
    for (const key of Object.keys(fields)) {
        f.push({
            name: key
        });
    }
    switch (scope) {
        case 'search_fields': searchFields.value = f; break;
        case 'display_fields': displayFields.value = f; break;
    }
}

const saveButtonText = computed(() => {
    return OrcaSearch().cmdKey + '+S';
});

const saveModuleConfig = async () => {
    await setLoading(true);
    OrcaSearch().jsmo.ajax('save-module-config', {
        ...config.value,
        search_fields: searchFields.value,
        display_fields: displayFields.value
    })
        .then(response => {
            // look for possible errors during save result
            if (isNotEmpty(response.errors)) {
                if (Array.isArray(response.errors)) {
                    console.log(...response.errors);
                } else {
                    console.log(response.errors);
                }
            } else {
                // success
                toast.removeGroup('modified');
                showToast(
                    'success',
                    'Success',
                    'Module Configuration Saved!'
                );
                isModified.value = false;
            }
        })
        .catch(err => {
            debug.value = err;
        })
        .finally(async () => {
            await setLoading(false);
        });
}

/*
    Dran-n-Drop Functionality for Display and Search Fields
 */
const [searchFieldsTableBody, searchFieldsRows] = useDragAndDrop(searchFields, {
    dragHandle: ".handle",
    onSort: (event) => {
        searchFields.value = [ ...event.values ];
    },
});
const [displayFieldsTableBody, displayFieldsRows] = useDragAndDrop(displayFields, {
    dragHandle: ".handle",
    onSort: (event) => {
        displayFields.value = [ ...event.values ];
    },
});

const setLoading = async (x) => {
    isLoading.value = x;
}

const init = async () => {
    await setLoading(true);
    OrcaSearch().jsmo.ajax('initialize-config-dashboard', {})
        .then(function(response) {
            config.value = response.config ?? {};
            metadata.value = response.metadata ?? {};
            searchFields.value = response.search_fields ?? [];
            displayFields.value = response.display_fields ?? [];
        })
        .catch(function(err) {
            debug.value = err;
        })
        .finally(async () => {
            await setLoading(false);
        });
}

const onKeyDown = (event) => {
    if (event.key === 's' && (event.ctrlKey || event.metaKey)) {
        event.preventDefault();
        saveModuleConfig();
    }
};

onBeforeMount(async () => {
    await init();
    // event handler for global saves
    document.addEventListener('keydown', onKeyDown);
});
onBeforeUnmount(() => {
    // event handler for global saves
    document.removeEventListener('keydown', onKeyDown);
})
</script>

<template>
    <div class="projhdr">
        <i class="fa-solid fa-gears">&ZeroWidthSpace;</i>&nbsp;Orca Search Configuration
    </div>
    <div class="card module-config" :class="{ modified: isModified }">
        <div class="card-body">
            <template v-if="isEmpty(config)">
                <!-- LOADING PLACEHOLDER -->
            </template>
            <template v-else>
                <!-- GENERAL CONFIGURATION -->
                <h4 class="mb-2 d-flex gap-2 align-items-center pb-1 border-dark border-bottom border-3">
                    <span>General Configuration</span>
                </h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle">
                        <thead class="table-dark">
                        <tr>
                            <th>Name</th>
                            <th class="text-center">Enabled</th>
                            <th>Description</th>
                        </tr>
                        </thead>
                        <tbody>
                        <!-- replace_add_edit -->
                        <tr>
                            <td>Replace Add/Edit</td>
                            <td class="p-0">
                                <button class="fs-3 btn m-0 border-0 rounded-0 w-100" type="button" @click="toggleConfigOption('replace_add_edit')" data-config-name="replace_add_edit">
                                    <i :class="toggleIcon(config['replace_add_edit'])"></i>
                                </button>
                            </td>
                            <td>
                                <div>Replace the <span style="color: rgb(128, 0, 0);">Add/Edit Records</span> link with a link to this module's search page</div>
                                <small class="mt-1">
                                    <b>NOTE:</b> a <i class="fas fa-info-circle" style="display: inline; color: rgb(128, 0, 0);"></i> tooltip icon will appear next to the link when this is enabled
                                </small>
                            </td>
                        </tr>
                        <!-- include_dag_if_exists -->
                        <tr>
                            <td>Enable DAG Display</td>
                            <td class="p-0">
                                <button class="fs-3 btn m-0 border-0 rounded-0 w-100" type="button" @click="toggleConfigOption('include_dag_if_exists')" data-config-name="include_dag_if_exists">
                                    <i :class="toggleIcon(config['include_dag_if_exists'])"></i>
                                </button>
                            </td>
                            <td>Include a DAG column in display fields (only if project uses DAGs)</td>
                        </tr>
                        <!-- empty_search_disabled -->
                        <tr>
                            <td>Disable Empty Search</td>
                            <td class="p-0">
                                <button class="fs-3 btn m-0 border-0 rounded-0 w-100" type="button" @click="toggleConfigOption('empty_search_disabled')" data-config-name="empty_search_disabled">
                                    <i :class="toggleIcon(config['empty_search_disabled'])"></i>
                                </button>
                            </td>
                            <td>Prevent an empty search from yielding results</td>
                        </tr>
                        <!-- search_limit -->
                        <tr>
                            <td>Search Limit</td>
                            <td>
                                <select class="form-select" v-model="config['search_limit']" data-config-name="search_limit">
                                    <option value="0">No Limit</option>
                                    <option value="500">500</option>
                                    <option value="1000">1000</option>
                                    <option value="1500">1500</option>
                                    <option value="2000">2000</option>
                                    <option value="5000">5000</option>
                                </select>
                            </td>
                            <td>If a search attempt yields more results than what is specified, an error message will be returned and no results will display.</td>
                        </tr>
                        <!-- instance_search -->
                        <tr>
                            <td>Repeat Instance Search Method</td>
                            <td>
                                <select class="form-select" v-model="config['instance_search']" data-config-name="instance_search">
                                    <option value="LATEST">Latest</option>
                                    <option value="ALL">All</option>
                                </select>
                            </td>
                            <td>If a field is on a repeating instrument, which instances should be searched.</td>
                        </tr>
                        <!-- record_home_display -->
                        <tr>
                            <td>Record Home Display</td>
                            <td>
                                <select class="form-select" v-model="config['record_home_display']" data-config-name="record_home_display">
                                    <option value="none">None</option>
                                    <option value="first">First Column</option>
                                    <option value="last">Last Column</option>
                                </select>
                            </td>
                            <td>Where to position Record Home button in the display fields table (default=Last Column, use None to hide it).</td>
                        </tr>
                        <!-- display_context_enabled -->
                        <tr>
                            <td>Enable Data Context Display</td>
                            <td class="p-0">
                                <button class="fs-3 btn m-0 border-0 rounded-0 w-100" type="button" @click="toggleConfigOption('display_context_enabled')" data-config-name="display_context_enabled">
                                    <i :class="toggleIcon(config['display_context_enabled'])"></i>
                                </button>
                            </td>
                            <td>Show the event/instance context of search results</td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <!-- SEARCH FIELDS -->
                <h4 class="mb-2 d-flex gap-2 align-items-center pb-1 border-dark border-bottom border-3">
                    <span>Search Fields</span>
                    <button class="btn btn-link btn-lg py-0 px-2" @click="editSearchFields"><i class="fa-solid fa-edit"></i></button>
                </h4>
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-dark">
                    <tr>
                        <th style="width: 4rem" class="text-center">
                            <span v-tooltip.bottom="{
                                value: 'Drag and Drop the fields in the order you want them to show up in the search dropdown.',
                                escape: false
                            }"><i class="fa-solid fa-up-down"></i>&nbsp;<i class="fas fa-info-circle text-primary">&ZeroWidthSpace;</i></span>
                        </th>
                        <th style="width: 5rem" class="text-center">X</th>
                        <th>Field Name</th>
                        <th>Field Label</th>
                        <th>Field Type</th>
                        <th>Custom Label</th>
                        <th class="text-center">
                            <span v-tooltip.bottom="{
                                value: 'Users can provide only part of the field value to get results.<br/><br/>Example: searching \'<strong>deer</strong>\' instead of \'Leila <strong>Deer</strong>ing\'.',
                                escape: false
                            }">Partial Matches?&nbsp;<i class="fas fa-info-circle text-primary">&ZeroWidthSpace;</i></span></th>
                    </tr>
                    </thead>
                    <tbody ref="searchFieldsTableBody">
                    <tr v-for="(v, i) in searchFieldsRows" :key="v['name']">
                        <td class="handle text-center fs-6" style="cursor: grab;">
                            <i class="fa-solid fa-grip-vertical"></i>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-danger" type="button" @click="searchFields.splice(i, 1)">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                        <th class="font-monospace"><div>[<span class="text-danger">{{ v['name'] }}</span>]</div></th>
                        <td>{{ metadata.fields[v['name']]['label'] }}</td>
                        <td>{{ metadata.fields[v['name']]['type'] }}</td>
                        <td>
                            <input type="text" class="form-control" v-model="v['label']" />
                        </td>
                        <td class="p-0">
                            <button class="fs-3 btn m-0 border-0 rounded-0 w-100" type="button" @click="v['wildcard'] = !v['wildcard']" :disabled="[ 'select', 'radio', 'sql', 'checkbox' ].includes(metadata.fields[v['name']]['type'])">
                                <i :class="toggleIcon(v['wildcard'])"></i>
                            </button>
                        </td>
                    </tr>
                    </tbody>
                </table>

                <!-- DISPLAY FIELDS -->
                <h4 class="mb-2 d-flex gap-2 align-items-center pb-1 border-dark border-bottom border-3">
                    <span>Display Fields</span>
                    <button class="btn btn-link btn-lg py-0 px-2" @click="editDisplayFields"><i class="fa-solid fa-edit"></i></button>
                </h4>
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-dark">
                    <tr>
                        <th style="width: 4rem" class="text-center">
                            <span v-tooltip.bottom="{
                                value: 'Drag and Drop the fields in the order you want them to display in the results table.<br/><br/>Top to bottom will display left to right.',
                                escape: false
                            }"><i class="fa-solid fa-up-down"></i>&nbsp;<i class="fas fa-info-circle text-primary">&ZeroWidthSpace;</i></span>
                        </th>
                        <th style="width: 5rem" class="text-center">X</th>
                        <th>Field Name</th>
                        <th>Field Label</th>
                        <th>Custom Header</th>
                        <th class="text-center">Sort Enabled</th>
                        <th>Sort Direction</th>
                        <th>Sort Priority</th>
                    </tr>
                    </thead>
                    <tbody ref="displayFieldsTableBody">
                    <tr v-for="(v, i) in displayFieldsRows" :key="v['name']">
                        <td class="handle text-center fs-6" style="cursor: grab;">
                            <i class="fa-solid fa-grip-vertical"></i>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-danger" type="button" @click="displayFields.splice(i, 1)">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                        <th class="font-monospace"><div>[<span class="text-danger">{{ v['name'] }}</span>]</div></th>
                        <td>{{ metadata.fields[v['name']]['label'] }}</td>
                        <td>
                            <input type="text" class="form-control" v-model="v['header']" />
                        </td>
                        <td class="p-0">
                            <button class="fs-3 btn m-0 border-0 rounded-0 w-100" type="button" @click="toggleDisplaySort(i, v['sort'])">
                                <i :class="toggleIcon(v['sort'])"></i>
                            </button>
                        </td>
                        <td>
                            <select class="form-select" v-model="v['direction']" :disabled="!v['sort']">
                                <option value="NONE">None</option>
                                <option value="asc">Ascending</option>
                                <option value="desc">Descending</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" class="form-control" v-model="v['priority']" :disabled="!v['sort']" />
                        </td>
                    </tr>
                    </tbody>
                </table>
            </template>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-end">
            <button class="btn btn-primary" @click="saveModuleConfig"><i class="fas fa-save">&nbsp;</i>Save (<span class="small font-monospace">{{ saveButtonText }}</span>)</button>
        </div>
    </div>
    <pre v-if="debug" class="mt-3">{{ debug }}</pre>
    <Dialog modal v-model:visible="isFieldDialogVisible" header="Field Selection" :style="{ width: '40rem' }" position="top" class="bg-light">
        <div v-if="config && metadata && metadata.forms" class="d-flex flex-column">
            <div v-for="(v, k) in metadata.forms" class="fs-6">
                <div class="pb-1 border-3 border-bottom border-secondary bg-dark text-light fs-5 mb-2 p-2">{{ v.menu }}</div>
                <div class="d-flex gap-2 flex-column">
                    <template v-for="(fv, fk) in v.fields">
                        <label class="d-flex flex-row gap-2 mb-0 align-items-center" :for="`fd_${fk}`" style="cursor: pointer">
                            <input type="checkbox" class="form-check-input fs-5" :id="`fd_${fk}`" />
                            <div class="font-monospace">[<span class="text-danger">{{ fk }}</span>]</div>
                            <span class="text-muted">({{ fv }})</span>
                        </label>
                        <hr class="my-0" />
                    </template>
                </div>
            </div>
        </div>
        <template #footer>
            <div class="mt-3 d-flex gap-2">
                <button type="button" class="btn btn-primary ms-auto" @click=""><i class="fa-solid fa-floppy-disk"></i>&nbsp;Save</button>
                <button type="button" class="btn btn-outline-danger" @click="isFieldDialogVisible = false"><i class="fa-solid fa-ban"></i>&nbsp;Cancel</button>
            </div>
        </template>
    </Dialog>
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
    <field-select-dialog ref="fieldSelectModal" @selected="onFieldsSelected"></field-select-dialog>

    <Toast position="bottom-right" group="general" />
    <Toast position="top-right" group="modified" />
</template>

<style lang="scss">
*[data-pd-tooltip] {
    cursor: pointer;
}
.module-config.card {
    background-color: var(--bs-light);
}
.module-config.card .card-footer {
    background-color: var(--bs-secondary-bg-subtle);
}
.module-config.card.modified {
    background-color: var(--bs-warning-bg-subtle);
}
.module-config.card.modified .card-footer {
    background-color: var(--bs-warning);
}
.btn.btn-outline-primary {
    text-decoration: none;
    color: var(--bs-primary);
}
.btn.btn-outline-primary:hover {
    color: var(--bs-light);
}
</style>