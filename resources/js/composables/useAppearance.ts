import { onMounted, ref } from 'vue';

type Appearance = 'dark';

export function updateTheme(): void {
    document.documentElement.classList.add('dark');
}

export function initializeTheme(): void {
    localStorage.removeItem('appearance');
    updateTheme();
}

export function useAppearance() {
    const appearance = ref<Appearance>('dark');

    onMounted(() => {
        initializeTheme();
    });

    function updateAppearance(value: Appearance): void {
        appearance.value = value;
        updateTheme();
    }

    return {
        appearance,
        updateAppearance,
    };
}
