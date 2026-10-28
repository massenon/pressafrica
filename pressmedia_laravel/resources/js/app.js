/**
 * Vue islands: Blade renders the page (SEO, fast first paint); interactive widgets are mounted on
 * elements like <div data-island="search" data-props='{"q":""}'></div>. One small component per island,
 * loaded on demand, so pages without islands ship no Vue.
 */
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createI18n } from 'vue-i18n';
import fr from './i18n/fr.json';
import en from './i18n/en.json';

const islands = import.meta.glob('./islands/*.vue');
const locale = document.documentElement.lang === 'en' ? 'en' : 'fr';
const i18n = createI18n({ legacy: false, locale, fallbackLocale: 'fr', messages: { fr, en } });
const pinia = createPinia();

document.querySelectorAll('[data-island]').forEach(async (el) => {
    const load = islands[`./islands/${el.dataset.island}.vue`];
    if (!load) return;
    const { default: component } = await load();
    createApp(component, JSON.parse(el.dataset.props || '{}')).use(pinia).use(i18n).mount(el);
});
