import { createApp } from 'vue';
import SchoolApp from './school/SchoolApp.vue';
import { router } from './school/router';

createApp(SchoolApp).use(router).mount('#app');
