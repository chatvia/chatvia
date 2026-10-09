<script setup lang="ts">
// Country picker for billing addresses. Only offers the countries Stripe
// supports, sorted by name, with their flag. Emits the ISO 3166-1 alpha-2 code.
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface Country {
    code: string;
    name: string;
    flag: string;
}

interface Props {
    modelValue?: string;
    placeholder?: string;
    disabled?: boolean;
    required?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    required: false,
});

const { t } = useI18n();

const displayPlaceholder = computed(() => props.placeholder ?? t('billing.payment_methods.placeholders.country'));

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

// Countries Stripe supports for billing addresses.
// prettier-ignore
const allowedCountryCodes = [
    'US', 'AE', 'AG', 'AL', 'AM', 'AR', 'AT', 'AU', 'BA', 'BE', 'BG', 'BH', 'BO', 'CA', 'CH',
    'CI', 'CL', 'CO', 'CR', 'CY', 'CZ', 'DE', 'DK', 'DO', 'EC', 'EE', 'EG', 'ES', 'ET', 'FI',
    'FR', 'GB', 'GH', 'GM', 'GR', 'GT', 'GY', 'HK', 'HR', 'HU', 'ID', 'IE', 'IL', 'IS', 'IT',
    'JM', 'JO', 'JP', 'KE', 'KH', 'KR', 'KW', 'LC', 'LI', 'LK', 'LT', 'LU', 'LV', 'MA', 'MD',
    'MG', 'MK', 'MN', 'MO', 'MT', 'MU', 'MX', 'MY', 'NA', 'NG', 'NL', 'NO', 'NZ', 'OM', 'PA',
    'PE', 'PH', 'PL', 'PT', 'PY', 'QA', 'RO', 'RS', 'RW', 'SA', 'SE', 'SG', 'SI', 'SK', 'SN',
    'SV', 'TH', 'TN', 'TR', 'TT', 'TZ', 'UY', 'UZ', 'VN', 'ZA', 'BD', 'BJ', 'MC', 'NE', 'SM',
    'AZ', 'BN', 'BT', 'AO', 'DZ', 'TW', 'BS', 'BW', 'GA', 'LA', 'MZ', 'KZ', 'PK'
];

const allCountries: Country[] = [
    { code: 'AE', name: 'United Arab Emirates', flag: '🇦🇪' },
    { code: 'AG', name: 'Antigua and Barbuda', flag: '🇦🇬' },
    { code: 'AL', name: 'Albania', flag: '🇦🇱' },
    { code: 'AM', name: 'Armenia', flag: '🇦🇲' },
    { code: 'AO', name: 'Angola', flag: '🇦🇴' },
    { code: 'AR', name: 'Argentina', flag: '🇦🇷' },
    { code: 'AT', name: 'Austria', flag: '🇦🇹' },
    { code: 'AU', name: 'Australia', flag: '🇦🇺' },
    { code: 'AZ', name: 'Azerbaijan', flag: '🇦🇿' },
    { code: 'BA', name: 'Bosnia and Herzegovina', flag: '🇧🇦' },
    { code: 'BD', name: 'Bangladesh', flag: '🇧🇩' },
    { code: 'BE', name: 'Belgium', flag: '🇧🇪' },
    { code: 'BG', name: 'Bulgaria', flag: '🇧🇬' },
    { code: 'BH', name: 'Bahrain', flag: '🇧🇭' },
    { code: 'BJ', name: 'Benin', flag: '🇧🇯' },
    { code: 'BN', name: 'Brunei Darussalam', flag: '🇧🇳' },
    { code: 'BO', name: 'Bolivia', flag: '🇧🇴' },
    { code: 'BS', name: 'Bahamas', flag: '🇧🇸' },
    { code: 'BT', name: 'Bhutan', flag: '🇧🇹' },
    { code: 'BW', name: 'Botswana', flag: '🇧🇼' },
    { code: 'CA', name: 'Canada', flag: '🇨🇦' },
    { code: 'CH', name: 'Switzerland', flag: '🇨🇭' },
    { code: 'CI', name: "Côte d'Ivoire", flag: '🇨🇮' },
    { code: 'CL', name: 'Chile', flag: '🇨🇱' },
    { code: 'CO', name: 'Colombia', flag: '🇨🇴' },
    { code: 'CR', name: 'Costa Rica', flag: '🇨🇷' },
    { code: 'CY', name: 'Cyprus', flag: '🇨🇾' },
    { code: 'CZ', name: 'Czech Republic', flag: '🇨🇿' },
    { code: 'DE', name: 'Germany', flag: '🇩🇪' },
    { code: 'DK', name: 'Denmark', flag: '🇩🇰' },
    { code: 'DO', name: 'Dominican Republic', flag: '🇩🇴' },
    { code: 'DZ', name: 'Algeria', flag: '🇩🇿' },
    { code: 'EC', name: 'Ecuador', flag: '🇪🇨' },
    { code: 'EE', name: 'Estonia', flag: '🇪🇪' },
    { code: 'EG', name: 'Egypt', flag: '🇪🇬' },
    { code: 'ES', name: 'Spain', flag: '🇪🇸' },
    { code: 'ET', name: 'Ethiopia', flag: '🇪🇹' },
    { code: 'FI', name: 'Finland', flag: '🇫🇮' },
    { code: 'FR', name: 'France', flag: '🇫🇷' },
    { code: 'GA', name: 'Gabon', flag: '🇬🇦' },
    { code: 'GB', name: 'United Kingdom', flag: '🇬🇧' },
    { code: 'GH', name: 'Ghana', flag: '🇬🇭' },
    { code: 'GM', name: 'Gambia', flag: '🇬🇲' },
    { code: 'GR', name: 'Greece', flag: '🇬🇷' },
    { code: 'GT', name: 'Guatemala', flag: '🇬🇹' },
    { code: 'GY', name: 'Guyana', flag: '🇬🇾' },
    { code: 'HK', name: 'Hong Kong', flag: '🇭🇰' },
    { code: 'HR', name: 'Croatia', flag: '🇭🇷' },
    { code: 'HU', name: 'Hungary', flag: '🇭🇺' },
    { code: 'ID', name: 'Indonesia', flag: '🇮🇩' },
    { code: 'IE', name: 'Ireland', flag: '🇮🇪' },
    { code: 'IL', name: 'Israel', flag: '🇮🇱' },
    { code: 'IS', name: 'Iceland', flag: '🇮🇸' },
    { code: 'IT', name: 'Italy', flag: '🇮🇹' },
    { code: 'JM', name: 'Jamaica', flag: '🇯🇲' },
    { code: 'JO', name: 'Jordan', flag: '🇯🇴' },
    { code: 'JP', name: 'Japan', flag: '🇯🇵' },
    { code: 'KE', name: 'Kenya', flag: '🇰🇪' },
    { code: 'KH', name: 'Cambodia', flag: '🇰🇭' },
    { code: 'KR', name: 'Korea, Republic of', flag: '🇰🇷' },
    { code: 'KW', name: 'Kuwait', flag: '🇰🇼' },
    { code: 'KZ', name: 'Kazakhstan', flag: '🇰🇿' },
    { code: 'LA', name: "Lao People's Democratic Republic", flag: '🇱🇦' },
    { code: 'LC', name: 'Saint Lucia', flag: '🇱🇨' },
    { code: 'LI', name: 'Liechtenstein', flag: '🇱🇮' },
    { code: 'LK', name: 'Sri Lanka', flag: '🇱🇰' },
    { code: 'LT', name: 'Lithuania', flag: '🇱🇹' },
    { code: 'LU', name: 'Luxembourg', flag: '🇱🇺' },
    { code: 'LV', name: 'Latvia', flag: '🇱🇻' },
    { code: 'MA', name: 'Morocco', flag: '🇲🇦' },
    { code: 'MC', name: 'Monaco', flag: '🇲🇨' },
    { code: 'MD', name: 'Moldova, Republic of', flag: '🇲🇩' },
    { code: 'MG', name: 'Madagascar', flag: '🇲🇬' },
    { code: 'MK', name: 'Macedonia, the former Yugoslav Republic of', flag: '🇲🇰' },
    { code: 'MN', name: 'Mongolia', flag: '🇲🇳' },
    { code: 'MO', name: 'Macao', flag: '🇲🇴' },
    { code: 'MT', name: 'Malta', flag: '🇲🇹' },
    { code: 'MU', name: 'Mauritius', flag: '🇲🇺' },
    { code: 'MX', name: 'Mexico', flag: '🇲🇽' },
    { code: 'MY', name: 'Malaysia', flag: '🇲🇾' },
    { code: 'MZ', name: 'Mozambique', flag: '🇲🇿' },
    { code: 'NA', name: 'Namibia', flag: '🇳🇦' },
    { code: 'NE', name: 'Niger', flag: '🇳🇪' },
    { code: 'NG', name: 'Nigeria', flag: '🇳🇬' },
    { code: 'NL', name: 'Netherlands', flag: '🇳🇱' },
    { code: 'NO', name: 'Norway', flag: '🇳🇴' },
    { code: 'NZ', name: 'New Zealand', flag: '🇳🇿' },
    { code: 'OM', name: 'Oman', flag: '🇴🇲' },
    { code: 'PA', name: 'Panama', flag: '🇵🇦' },
    { code: 'PE', name: 'Peru', flag: '🇵🇪' },
    { code: 'PH', name: 'Philippines', flag: '🇵🇭' },
    { code: 'PK', name: 'Pakistan', flag: '🇵🇰' },
    { code: 'PL', name: 'Poland', flag: '🇵🇱' },
    { code: 'PT', name: 'Portugal', flag: '🇵🇹' },
    { code: 'PY', name: 'Paraguay', flag: '🇵🇾' },
    { code: 'QA', name: 'Qatar', flag: '🇶🇦' },
    { code: 'RO', name: 'Romania', flag: '🇷🇴' },
    { code: 'RS', name: 'Serbia', flag: '🇷🇸' },
    { code: 'RW', name: 'Rwanda', flag: '🇷🇼' },
    { code: 'SA', name: 'Saudi Arabia', flag: '🇸🇦' },
    { code: 'SE', name: 'Sweden', flag: '🇸🇪' },
    { code: 'SG', name: 'Singapore', flag: '🇸🇬' },
    { code: 'SI', name: 'Slovenia', flag: '🇸🇮' },
    { code: 'SK', name: 'Slovakia', flag: '🇸🇰' },
    { code: 'SM', name: 'San Marino', flag: '🇸🇲' },
    { code: 'SN', name: 'Senegal', flag: '🇸🇳' },
    { code: 'SV', name: 'El Salvador', flag: '🇸🇻' },
    { code: 'TH', name: 'Thailand', flag: '🇹🇭' },
    { code: 'TN', name: 'Tunisia', flag: '🇹🇳' },
    { code: 'TR', name: 'Turkey', flag: '🇹🇷' },
    { code: 'TT', name: 'Trinidad and Tobago', flag: '🇹🇹' },
    { code: 'TW', name: 'Taiwan, Province of China', flag: '🇹🇼' },
    { code: 'TZ', name: 'Tanzania, United Republic of', flag: '🇹🇿' },
    { code: 'US', name: 'United States', flag: '🇺🇸' },
    { code: 'UY', name: 'Uruguay', flag: '🇺🇾' },
    { code: 'UZ', name: 'Uzbekistan', flag: '🇺🇿' },
    { code: 'VN', name: 'Viet Nam', flag: '🇻🇳' },
    { code: 'ZA', name: 'South Africa', flag: '🇿🇦' },
];

// Filter countries to only show Stripe supported ones
const countries: Country[] = allCountries
    .filter((country) => allowedCountryCodes.includes(country.code))
    .sort((a, b) => a.name.localeCompare(b.name));

const selectedCountry = computed(() => {
    return countries.find((country) => country.code === props.modelValue);
});

const updateValue = (value: unknown) => {
    if (value && typeof value === 'string') {
        emit('update:modelValue', value);
    }
};
</script>

<template>
    <Select :model-value="modelValue" @update:model-value="updateValue" :disabled="disabled">
        <SelectTrigger class="w-full">
            <SelectValue :placeholder="displayPlaceholder">
                <template v-if="selectedCountry">
                    <div class="flex items-center gap-2">
                        <span>{{ selectedCountry.flag }}</span>
                        <span>{{ selectedCountry.name }}</span>
                    </div>
                </template>
            </SelectValue>
        </SelectTrigger>
        <SelectContent class="max-h-[300px]">
            <SelectItem v-for="country in countries" :key="country.code" :value="country.code">
                <div class="flex items-center gap-2">
                    <span>{{ country.flag }}</span>
                    <span>{{ country.name }}</span>
                </div>
            </SelectItem>
        </SelectContent>
    </Select>
</template>
