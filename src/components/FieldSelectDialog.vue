<script setup>
import { ref, useTemplateRef, computed } from "vue";
import Dialog from 'primevue/dialog';
import { isEmpty, isNotEmpty } from '@primeuix/utils/object';

defineExpose({
    show
});
const emit = defineEmits([ 'selected', 'closed']);

const isDialogVisible = ref(false);
const debug = ref({});

const _metadata = ref({});
const _scope = ref();
const _state = ref({});

const filterField = useTemplateRef();
const filterText = ref();

function show(metadata, scope, state) {
    // set local vars
    filterText.value = null;
    _metadata.value = metadata;
    _scope.value = scope;
    let s = {};
    for (let i = 0; i < state.length; i++) {
        s[state[i]['name']] = true;
    }
    _state.value = s;
    // finally show the dialog, if not already shown
    isDialogVisible.value = true;
}

const scopeDisplay = computed(() => {
    let s = _scope.value;
    switch (s) {
        case "search_fields": return "Search Fields";
        case "display_fields": return "Display Fields";
        default: return s;
    }
});

const filteredFields = computed(() => {
    return Object.fromEntries(
        Object.entries(_metadata.value['fields']).filter(([fn, fv]) => {
            return isEmpty(filterText.value)
                || fn.includes(filterText.value)
                || fv['form'].includes(filterText.value)
                || fv['type'].includes(filterText.value)
                || fv['label'].includes(filterText.value)
                ;
        })
    );
});

const accept = () => {
    emit('selected', _scope.value, _state.value);
    isDialogVisible.value = false;
}

const select = (f) => {
    if (_state.value[f]) {
        delete _state.value[f];
    } else {
        _state.value[f] = true;
    }
}

const onShown = () => {
    // need a time delay because something is
    setTimeout(() => {
        filterField.value.focus();
    }, 500);
}

const onHidden = () => {
    // emit close so parent dashboard can update
    emit('closed');
}
</script>

<template>
    <Dialog modal v-model:visible="isDialogVisible" header="Field Selection" :style="{ width: '50rem' }" position="top" class="bg-light"
            :draggable="false" @after-hide="onHidden" @show="onShown" :closable="false">
        <template #header>
            <div class="container g-0 me-3">
                <div class="row">
                    <div class="col-lg-6">
                        <h3 class="mb-2">Search Fields</h3>
                        <div><input type="text" placeholder="Search Fields" class="form-control" ref="filterField" v-model="filterText"></div>
                    </div>
                    <div class="col-lg-6">
                        <div class="well bg-warning-subtle mb-0 mt-2 mt-lg-0">
                            <strong>NOTE:</strong> Select your fields here.  You can put them in your preferred order by dragging and dropping on the left hand side of the main configuration page after you push update.
                        </div>
                    </div>
                </div>
            </div>
        </template>
        <table class="table table-striped table-hover align-middle">
            <thead>
            <tr>
                <th class="text-center">X</th>
                <th>Field Name</th>
                <th>Field Label</th>
                <th>Instrument</th>
            </tr>
            </thead>
            <tbody style="cursor:pointer">
            <template v-if="isNotEmpty(_metadata) && isNotEmpty(_metadata.fields)">
                <tr v-for="(v, k) in filteredFields" @click="select(k)">
                    <td class="text-center text-primary fs-5">
                        <input type="checkbox" class="form-check-input" :checked="_state[k]" />
                    </td>
                    <td>
                        <div class="font-monospace">[<span class="text-danger">{{ k }}</span>]</div>
                    </td>
                    <td>
                        <span class="text-muted" :title="v['label']">{{ v['label'] }}</span>
                    </td>
                    <td><span class="text-muted font-monospace">{{ v['form'] }}</span></td>
                </tr>
            </template>
            </tbody>
        </table>
        <pre v-if="isNotEmpty(debug)" class="mt-3">{{ debug }}</pre>
        <template #footer>
            <div class="mt-3 d-flex gap-2">
                <button type="button" class="btn btn-primary ms-auto" @click="accept"><i class="far fa-circle-check"></i>&nbsp;Update</button>
                <button type="button" class="btn btn-outline-danger" @click="isDialogVisible = false"><i class="fa-solid fa-ban"></i>&nbsp;Cancel</button>
            </div>
        </template>
    </Dialog>
</template>

<style></style>