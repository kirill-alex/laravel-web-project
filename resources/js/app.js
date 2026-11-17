import { createApp } from 'vue';
import App from './components/App.vue';
import { createEcho } from './bootstrap';

const root = document.getElementById('app');

if (root) {
    if (root.dataset.broadcastEnabled === 'true') {
        window.Echo = createEcho({
            key: root.dataset.pusherKey,
            cluster: root.dataset.pusherCluster,
        });
    }

    createApp(App).mount(root);
}
