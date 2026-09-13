<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface SettingItem {
    key: string;
    value: string | boolean | number | null;
    type: string;
}

interface SettingGroup {
    label: string;
    items: SettingItem[];
}

const props = defineProps<{
    groups: Record<string, SettingGroup>;
    activeTab: string;
}>();

const tabOrder = Object.keys(props.groups);
const activeTab = ref(props.activeTab && tabOrder.includes(props.activeTab) ? props.activeTab : tabOrder[0] ?? 'general');

const initialSettings = () => {
    const settings: Record<string, string | boolean | number | null> = {};
    tabOrder.forEach((key) => {
        props.groups[key]?.items.forEach((item) => {
            settings[item.key] = item.value;
        });
    });
    return settings;
};

const form = useForm({ settings: initialSettings() });

const save = () => {
    form.put(route('settings.update'), {
        preserveScroll: true,
        onSuccess: () => form.clearErrors(),
    });
};

const resetDirty = () => {
    form.settings = initialSettings();
};

const dirty = computed(() => tabOrder.some((key) =>
    props.groups[key].items.some((item) => form.settings[item.key] !== item.value),
));

watch(() => props.groups, resetDirty);
</script>

<template>
    <AuthenticatedLayout>
        <div class="w-full">
            <PageHeader title="System Settings" description="Company-wide configuration for localization, numbering, and accounting behavior.">
                <template #actions>
                    <SecondaryButton @click="resetDirty" :disabled="!dirty">Reset</SecondaryButton>
                    <PrimaryButton :disabled="form.processing" @click="save">
                        Save Settings
                    </PrimaryButton>
                </template>
            </PageHeader>

            <div class="mb-5 flex flex-wrap gap-1 rounded-xl border border-gray-200 bg-white p-1 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <button
                    v-for="(group, key) in groups"
                    :key="key"
                    type="button"
                    :class="activeTab === key
                        ? 'bg-indigo-600 text-white shadow-sm'
                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white'"
                    class="flex-1 whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium"
                    @click="activeTab = key"
                >
                    {{ group.label }}
                </button>
            </div>

            <div v-for="(group, key) in groups" :key="key">
                <div v-if="activeTab === key" class="space-y-3">
                    <div
                        v-for="item in group.items"
                        :key="item.key"
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                    >
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ item.key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) }}
                        </label>
                        <p class="mb-2 mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                            Group: {{ key }} · Type: {{ item.type }}
                        </p>

                        <select
                            v-if="item.type === 'bool'"
                            v-model="form.settings[item.key]"
                            :class="form.settings[item.key] !== item.value ? 'border-indigo-400' : 'border-gray-300 dark:border-gray-700'"
                            class="mt-1 block w-full rounded-md border shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-200"
                        >
                            <option :value="true">Enabled</option>
                            <option :value="false">Disabled</option>
                        </select>

                        <select
                            v-else-if="item.type === 'int'"
                            v-model.number="form.settings[item.key]"
                            :class="form.settings[item.key] !== item.value ? 'border-indigo-400' : 'border-gray-300 dark:border-gray-700'"
                            class="mt-1 block w-full rounded-md border shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-200"
                        >
                            <option :value="0">0</option>
                            <option :value="1">1</option>
                            <option :value="2">2</option>
                            <option :value="3">3</option>
                            <option :value="4">4</option>
                            <option :value="5">5</option>
                            <option :value="30">30</option>
                            <option :value="60">60</option>
                            <option :value="90">90</option>
                            <option :value="120">120</option>
                        </select>

                        <input
                            v-else
                            v-model="form.settings[item.key]"
                            type="text"
                            :class="form.settings[item.key] !== item.value ? 'border-indigo-400' : 'border-gray-300 dark:border-gray-700'"
                            class="mt-1 block w-full rounded-md border shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-200"
                        />
                    </div>
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-3">
                <SecondaryButton @click="resetDirty" :disabled="!dirty">Reset</SecondaryButton>
                <PrimaryButton :disabled="form.processing" @click="save">
                    Save Settings
                </PrimaryButton>
            </div>
        </div>
    </AuthenticatedLayout>
</template>