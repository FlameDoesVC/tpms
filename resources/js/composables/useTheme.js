import { ref, watch } from 'vue';

const isDark = ref(document.documentElement.getAttribute('data-theme') === 'dark');

function applyTheme(dark) {
    const theme = dark ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
}

watch(isDark, (val) => applyTheme(val));

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
    if (!localStorage.getItem('theme')) {
        isDark.value = e.matches;
    }
});

export function useTheme() {
    const toggle = () => { isDark.value = !isDark.value; };
    return { isDark, toggle };
}
