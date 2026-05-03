window._ = require('lodash');

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

window.axios = require('axios');

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.head.querySelector('meta[name="csrf-token"]');

if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.content;
}

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

const realtimeConfig = window.AppRealtimeNotifications || {};

if (realtimeConfig.enabled && realtimeConfig.pusherKey) {
    const EchoModule = require('laravel-echo');
    const Echo = EchoModule.default || EchoModule;

    window.Pusher = require('pusher-js');

    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: realtimeConfig.pusherKey,
        cluster: realtimeConfig.pusherCluster || 'mt1',
        forceTLS: Boolean(realtimeConfig.forceTLS),
        encrypted: Boolean(realtimeConfig.forceTLS),
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: realtimeConfig.authEndpoint || '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrfToken ? csrfToken.content : '',
            },
        },
    });
}
