import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export function createEcho({ key, cluster }) {
    if (!key) {
        return null;
    }

    window.Pusher = Pusher;

    return new Echo({
        broadcaster: 'pusher',
        key,
        cluster: cluster || 'eu',
        forceTLS: true,
    });
}
