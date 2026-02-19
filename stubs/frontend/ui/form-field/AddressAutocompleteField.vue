<script setup lang="ts">
import { createEmptyLocation } from '@/components/contacts/contact-form-utils';
import { Button } from '@/components/ui/button';
import { ComboboxField, InputField, SelectField, type ComboboxOption } from '@/components/ui/form-field';
import { SelectContent, SelectItem } from '@/components/ui/select';
import { autocomplete, getPlaceDetails } from '@/lib/api/geocoding';
import statesList from '@/lib/states.utils';
import type { LocationRequest, PlaceSuggestion } from '@/types/generated';
import { MapPin, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    id: string;
    label?: string;
    errors?: Record<string, string>;
    disabled?: boolean;
    debounce?: number;
    errorPath?: string;
    optional?: boolean;
}>();

const modelValue = defineModel<LocationRequest | undefined>();

// Single source of truth for location data
// Can be undefined if no location is set
const location = computed<LocationRequest | undefined>({
    get() {
        return modelValue.value;
    },
    set(value) {
        modelValue.value = value;
    },
});

// UI state
const selectedOption = ref<ComboboxOption | null>(null);
const isManual = ref(false);
const isLoadingDetails = ref(false);

// Initialize manual mode if location already has data (e.g., edit mode)
if (location.value?.line1 || location.value?.locationName) {
    isManual.value = true;
}

// If location becomes undefined while in manual mode, switch back to autocomplete
watch(location, (newLocation) => {
    if (!newLocation && isManual.value) {
        isManual.value = false;
        selectedOption.value = null;
    }
});

// Error handling
const errorPrefix = computed(() => (props.errorPath ? `${props.errorPath}.` : ''));

function buildErrorKey(field: string): string {
    return `${errorPrefix.value}location.${field}`;
}

function getFieldError(field: string): string | undefined {
    return props.errors?.[buildErrorKey(field)];
}

// States dropdown options
const states = computed(() =>
    statesList.map((s) => ({
        value: s.abbreviation,
        label: s.name,
    })),
);

// Combined error for combobox (shows if any address field has an error)
const comboboxError = computed(() => {
    return getFieldError('line1') || getFieldError('city') || getFieldError('state') || getFieldError('postalCode');
});

// Fetch autocomplete suggestions
async function fetchOptions(query: string): Promise<ComboboxOption[]> {
    const suggestions = await autocomplete(query);
    return suggestions.map((s) => ({
        value: s.providerPlaceId,
        label: s.label,
        meta: s,
    }));
}

// When user selects an autocomplete option, fetch details and populate form
watch(selectedOption, async (option) => {
    if (!option) return;

    isLoadingDetails.value = true;
    try {
        const suggestion = option.meta as PlaceSuggestion;
        const details = await getPlaceDetails(suggestion.providerPlaceId);

        // Update location with fetched details
        location.value = {
            locationName: details.name || suggestion.label,
            line1: details.line1,
            line2: details.line2 || '',
            city: details.city,
            state: findStateAbbreviation(details.state),
            postalCode: details.postalCode,
            countryCode: details.countryCode || 'US',
            roomUnit: location.value?.roomUnit || '', // Preserve existing room/unit if any
            latitude: details.latitude ?? undefined,
            longitude: details.longitude ?? undefined,
        };

        // Switch to manual mode so user can verify/edit details
        isManual.value = true;
    } finally {
        isLoadingDetails.value = false;
    }
});

// Helper to find state abbreviation from full name or abbreviation
function findStateAbbreviation(stateInput: string): string {
    const match = statesList.find(
        (s) => s.name.toLowerCase() === stateInput.toLowerCase() || s.abbreviation.toLowerCase() === stateInput.toLowerCase(),
    );
    return match ? match.abbreviation : stateInput;
}

// Toggle between autocomplete search and manual entry modes
function toggleManual() {
    isManual.value = !isManual.value;
    if (!isManual.value) {
        // Clear selection when switching back to autocomplete mode
        selectedOption.value = null;
    } else if (!location.value) {
        // If entering manual mode and no location exists, create an empty one
        location.value = createEmptyLocation();
    }
}

// Clear/remove the location entirely
function clearLocation() {
    location.value = undefined;
    isManual.value = false;
    selectedOption.value = null;
}
</script>

<template>
    <div class="flex w-full flex-col gap-4">
        <div v-if="!isManual" class="flex w-full flex-col gap-2">
            <ComboboxField
                :id="props.id"
                :label="props.label ?? 'Search for a location'"
                :fetch-options="fetchOptions"
                v-model="selectedOption"
                placeholder="Type address or business name..."
                :disabled="props.disabled || isLoadingDetails"
                :loading-message="isLoadingDetails ? 'Loading details...' : 'Searching...'"
                :error="comboboxError"
                :debounce="props.debounce"
                autocomplete="new-password"
            >
                <template #after-options>
                    <div class="p-1">
                        <Button type="button" variant="ghost" size="sm" class="w-full justify-start font-normal" @click="toggleManual">
                            <Pencil class="mr-2 size-4" />
                            Enter address manually
                        </Button>
                    </div>
                </template>
                <template #empty>
                    <div class="flex flex-col items-center gap-2 p-2">
                        <span class="text-sm text-muted-foreground">No results found.</span>
                        <Button type="button" variant="outline" size="sm" @click="toggleManual"> Enter manually </Button>
                    </div>
                </template>
            </ComboboxField>
        </div>

        <div v-else-if="location" class="flex w-full flex-col gap-4">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-medium text-muted-foreground">Location Details</h4>
                <div class="flex gap-2">
                    <Button type="button" variant="ghost" size="sm" @click="toggleManual">
                        <MapPin />
                        Search again
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="clearLocation"
                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                        v-if="props.optional"
                    >
                        <Trash2 />
                        Remove location
                    </Button>
                </div>
            </div>

            <InputField
                label="Location Name"
                :id="`${props.id}-name`"
                v-model="location.locationName"
                :error="getFieldError('locationName')"
                placeholder="Home, Mercy Hospital, etc."
            />

            <div class="grid w-full grid-cols-1 gap-4 md:grid-cols-2">
                <InputField
                    class="md:col-span-2"
                    label="Address line 1"
                    :id="`${props.id}-line1`"
                    v-model="location.line1"
                    :error="getFieldError('line1')"
                    placeholder="123 Main St"
                />

                <InputField
                    class="md:col-span-2"
                    label="Address line 2"
                    optional
                    :id="`${props.id}-line2`"
                    v-model="location.line2"
                    placeholder="Apt 4B"
                />

                <InputField label="City" :id="`${props.id}-city`" v-model="location.city" :error="getFieldError('city')" placeholder="New York" />

                <SelectField
                    :id="`${props.id}-state`"
                    label="State"
                    :name="`${props.id}-state`"
                    v-model="location.state"
                    :error="getFieldError('state')"
                    placeholder="Select state"
                    autocomplete="address-level1"
                >
                    <SelectContent>
                        <SelectItem v-for="s in states" :key="s.value" :value="s.value">{{ s.label }}</SelectItem>
                    </SelectContent>
                </SelectField>

                <InputField
                    label="Zip/Postal"
                    :id="`${props.id}-postal-code`"
                    v-model="location.postalCode"
                    :error="getFieldError('postalCode')"
                    placeholder="10001"
                />

                <InputField label="Room / Unit" optional :id="`${props.id}-room-unit`" v-model="location.roomUnit" placeholder="Room 101" />
            </div>
        </div>
    </div>
</template>
