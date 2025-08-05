<script setup lang="ts">
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
    ComboboxTrigger,
} from '@/components/ui/combobox';
import { TagsInput, TagsInputInput, TagsInputItem, TagsInputItemDelete, TagsInputItemText } from '@/components/ui/tags-input';
import { ChevronsUpDown } from 'lucide-vue-next';
import { useFilter } from 'reka-ui';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

interface SelectItem {
    value: string;
    label: string;
}

interface Props {
    items: SelectItem[];
    modelValue?: string[];
    placeholder?: string;
    className?: string;
    minInputWidth?: string;
}

interface Emits {
    (e: 'update:modelValue', value: string[]): void;
    (e: 'change', value: string[]): void;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: () => [],
    placeholder: 'Select items...',
    className: 'px-2 gap-2 w-full',
    minInputWidth: 'min-w-[200px]',
});

const emit = defineEmits<Emits>();

const localModelValue = ref<string[]>([...props.modelValue]);
const open = ref(false);
const searchTerm = ref('');
const componentId = ref(`multiselect-${Date.now()}-${Math.random()}`);
const isClosing = ref(false);

const { contains } = useFilter({ sensitivity: 'base' });

const filteredItems = computed(() => {
    const options = props.items.filter((item) => !localModelValue.value.includes(item.label));
    return searchTerm.value ? options.filter((option) => contains(option.label, searchTerm.value)) : options;
});

// Watch for external modelValue changes
watch(
    () => props.modelValue,
    (newValue) => {
        if (JSON.stringify(newValue) !== JSON.stringify(localModelValue.value)) {
            localModelValue.value = [...newValue];
        }
    },
    { deep: true },
);

// Watch for local changes and emit
watch(
    localModelValue,
    (newValue, oldValue) => {
        if (JSON.stringify(newValue) !== JSON.stringify(oldValue)) {
            emit('update:modelValue', [...newValue]);
            emit('change', [...newValue]);
        }
    },
    { deep: true },
);

const handleSelect = async (ev: any) => {
    if (typeof ev.detail.value === 'string' && !isClosing.value) {
        searchTerm.value = '';
        localModelValue.value = [...localModelValue.value, ev.detail.value];
        await nextTick();
        closeDropdown();
    }
};

const closeDropdown = () => {
    if (!isClosing.value) {
        isClosing.value = true;
        open.value = false;
        // Reset closing flag after a short delay
        setTimeout(() => {
            isClosing.value = false;
        }, 50);
    }
};

const openDropdown = () => {
    if (!isClosing.value) {
        // Close all other dropdowns first
        document.dispatchEvent(
            new CustomEvent('close-all-multiselect', {
                detail: { except: componentId.value },
            }),
        );
        open.value = true;
    }
};

const handleInputClick = (event: Event) => {
    event.stopPropagation();
    if (open.value) {
        closeDropdown();
    } else {
        openDropdown();
    }
};

const handleTriggerClick = (event: Event) => {
    event.stopPropagation();
    if (open.value) {
        closeDropdown();
    } else {
        openDropdown();
    }
};

// Global event listener to close other dropdowns
const handleCloseAllMultiselect = (event: CustomEvent) => {
    if (event.detail.except !== componentId.value && open.value) {
        closeDropdown();
    }
};

// Handle clicks outside the component
const handleDocumentClick = (event: Event) => {
    const target = event.target as Element;
    const componentElement = document.getElementById(componentId.value);

    if (componentElement && !componentElement.contains(target) && open.value) {
        closeDropdown();
    }
};

// Prevent default focusout behavior that might conflict
const handleFocusOut = (event: FocusEvent) => {
    // Only close if focus is moving completely outside the component
    const relatedTarget = event.relatedTarget as Element;
    const componentElement = document.getElementById(componentId.value);

    if (componentElement && relatedTarget && !componentElement.contains(relatedTarget)) {
        // Add a small delay to allow for click events to process
        setTimeout(() => {
            if (open.value && !isClosing.value) {
                closeDropdown();
            }
        }, 150);
    }
};

onMounted(() => {
    document.addEventListener('close-all-multiselect', handleCloseAllMultiselect as EventListener);
    document.addEventListener('click', handleDocumentClick);
});

onUnmounted(() => {
    document.removeEventListener('close-all-multiselect', handleCloseAllMultiselect as EventListener);
    document.removeEventListener('click', handleDocumentClick);
});
</script>

<template>
    <div :id="componentId" class="relative">
        <Combobox v-model="localModelValue" v-model:open="open" :ignore-filter="true">
            <ComboboxAnchor as-child class="w-full">
                <ComboboxTrigger as-child class="w-full">
                    <TagsInput v-model="localModelValue" :class="className" @click="handleTriggerClick" @focusout="handleFocusOut">
                        <div class="flex flex-wrap items-center gap-2">
                            <TagsInputItem v-for="item in localModelValue" :key="item" :value="item">
                                <TagsInputItemText />
                                <TagsInputItemDelete />
                            </TagsInputItem>
                        </div>
                        <div class="flex w-full items-center">
                            <ComboboxInput v-model="searchTerm" as-child>
                                <TagsInputInput
                                    :placeholder="placeholder"
                                    :class="`${minInputWidth} h-auto w-full border-none p-0 focus-visible:ring-0`"
                                    @keydown.enter.prevent
                                    @click="handleInputClick"
                                />
                            </ComboboxInput>
                            <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                        </div>
                    </TagsInput>
                </ComboboxTrigger>
            </ComboboxAnchor>
            <div @pointerdownoutside="closeDropdown">
                <ComboboxList class="w-[var(--reka-popper-anchor-width)] max-w-[var(--reka-popper-anchor-width)]">
                    <ComboboxEmpty />
                    <ComboboxGroup>
                        <ComboboxItem
                            v-for="item in filteredItems"
                            :key="item.value"
                            :value="item.label"
                            @select.prevent="handleSelect"
                            class="w-full"
                        >
                            {{ item.label }}
                        </ComboboxItem>
                    </ComboboxGroup>
                </ComboboxList>
            </div>
        </Combobox>
    </div>
</template>
