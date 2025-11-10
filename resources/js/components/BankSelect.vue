<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Check, ChevronsUpDown, X } from 'lucide-vue-next';

interface Bank {
    code: string;
    name: string;
}

interface Props {
    banks: Bank[];
    modelValue?: string;
    disabled?: boolean;
    placeholder?: string;
    config?: any;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    disabled: false,
    placeholder: 'Select a bank',
    config: () => ({}),
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const open = ref(false);
const searchQuery = ref('');
const searchInputWrapper = ref<HTMLElement | null>(null);

const selectedBank = computed(() => {
    if (!props.modelValue) return null;
    return props.banks.find(bank => bank.code === props.modelValue);
});

const formatBankName = (name: string): string => {
    const format = props.config?.name_format || 'as-is';
    switch (format) {
        case 'uppercase':
            return name.toUpperCase();
        case 'lowercase':
            return name.toLowerCase();
        case 'title-case':
            // Split by spaces and handle each word, preserving punctuation
            return name.split(/\s+/).map(word => {
                // For words with hyphens, slashes, etc., capitalize each part
                return word.split(/([/-])/).map(part => {
                    if (part.length === 0 || /^[/-]$/.test(part)) return part;
                    return part.charAt(0).toUpperCase() + part.slice(1).toLowerCase();
                }).join('');
            }).join(' ');
        case 'as-is':
        default:
            return name;
    }
};

const formatBankDisplay = (bank: Bank | null, format: string): string => {
    if (!bank) return '';
    
    const name = formatBankName(bank.name);
    const code = bank.code;
    
    switch (format) {
        case 'name':
            return name;
        case 'code':
            return code;
        case 'name-code':
            return `${name} (${code})`;
        case 'code-name':
            return `${code} - ${name}`;
        default:
            return name;
    }
};

const selectedBankDisplay = computed(() => {
    const format = props.config?.selected_format || 'name';
    return formatBankDisplay(selectedBank.value, format);
});

const dropdownMaxHeight = computed(() => {
    // If explicit height is set, use it
    if (props.config?.max_dropdown_height) {
        return props.config.max_dropdown_height;
    }
    
    // Otherwise calculate from max_items_shown (default 10 items, ~40px per item)
    const maxItems = props.config?.max_items_shown || 10;
    if (maxItems === 0) return 'auto';
    return `${maxItems * 40}px`;
});

const showBankCode = computed(() => {
    const show = props.config?.show_bank_code ?? true;
    const position = props.config?.bank_code_position || 'right';
    return show && position !== 'none';
});

const bankCodePosition = computed(() => {
    return props.config?.bank_code_position || 'right';
});

const showSearch = computed(() => {
    return props.config?.show_search ?? true;
});

const showClearButton = computed(() => {
    return props.config?.show_clear_button ?? true;
});

const searchPlaceholder = computed(() => {
    return props.config?.search_placeholder || 'Search banks...';
});

const emptyText = computed(() => {
    return props.config?.empty_text || 'No bank found';
});

const filteredBanks = computed(() => {
    if (!searchQuery.value) return props.banks;
    const query = searchQuery.value.toLowerCase();
    return props.banks.filter(bank => 
        bank.name.toLowerCase().includes(query) ||
        bank.code.toLowerCase().includes(query)
    );
});

const selectBank = (code: string) => {
    emit('update:modelValue', code);
    open.value = false;
    searchQuery.value = '';
};

const clearSelection = () => {
    emit('update:modelValue', '');
};

const toggleOpen = () => {
    if (!props.disabled) {
        open.value = !open.value;
        if (open.value) {
            searchQuery.value = '';
        }
    }
};

// Close dropdown when clicking outside
const dropdown = ref<HTMLElement | null>(null);
const handleClickOutside = (event: MouseEvent) => {
    if (dropdown.value && !dropdown.value.contains(event.target as Node)) {
        open.value = false;
        searchQuery.value = '';
    }
};

watch(open, async (isOpen) => {
    if (isOpen) {
        document.addEventListener('click', handleClickOutside);
        // Focus search input when dropdown opens
        await nextTick();
        const input = searchInputWrapper.value?.querySelector('input');
        input?.focus();
    } else {
        document.removeEventListener('click', handleClickOutside);
    }
});
</script>

<template>
    <div ref="dropdown" class="relative w-full">
        <!-- Trigger Button -->
        <Button
            type="button"
            variant="outline"
            role="combobox"
            :aria-expanded="open"
            :disabled="disabled"
            @click="toggleOpen"
            class="w-full justify-between text-left font-normal"
        >
            <span v-if="selectedBank" class="truncate">{{ selectedBankDisplay }}</span>
            <span v-else class="text-muted-foreground">{{ placeholder }}</span>
            <div class="flex items-center gap-1 ml-2">
                <X
                    v-if="selectedBank && showClearButton"
                    class="h-4 w-4 shrink-0 opacity-50 hover:opacity-100"
                    @click.stop="clearSelection"
                />
                <ChevronsUpDown class="h-4 w-4 shrink-0 opacity-50" />
            </div>
        </Button>

        <!-- Dropdown -->
        <div
            v-if="open"
            class="absolute z-50 mt-1 w-full rounded-md border bg-popover text-popover-foreground shadow-md outline-none animate-in"
        >
            <!-- Search Input -->
            <div v-if="showSearch" ref="searchInputWrapper" class="p-2 border-b">
                <Input
                    v-model="searchQuery"
                    :placeholder="searchPlaceholder"
                    class="h-9"
                    @keydown.escape="open = false"
                />
            </div>

            <!-- Banks List -->
            <div :style="{ maxHeight: dropdownMaxHeight }" class="overflow-y-auto p-1">
                <div v-if="filteredBanks.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                    {{ emptyText }}
                </div>
                <button
                    v-for="bank in filteredBanks"
                    :key="bank.code"
                    type="button"
                    role="option"
                    :aria-selected="modelValue === bank.code"
                    @click="selectBank(bank.code)"
                    class="relative flex w-full cursor-pointer select-none items-center justify-start rounded-sm px-2 py-1.5 text-sm text-left outline-none hover:bg-accent hover:text-accent-foreground"
                    :class="{ 'bg-accent text-accent-foreground': modelValue === bank.code }"
                >
                    <Check
                        class="mr-2 h-4 w-4"
                        :class="modelValue === bank.code ? 'opacity-100' : 'opacity-0'"
                    />
                    <span v-if="bankCodePosition === 'left' && showBankCode" class="text-xs text-muted-foreground mr-2">{{ bank.code }}</span>
                    <span class="flex-1 truncate">{{ formatBankName(bank.name) }}</span>
                    <span v-if="bankCodePosition === 'right' && showBankCode" class="text-xs text-muted-foreground ml-2">{{ bank.code }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
