import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import {
    PUBLIC_REVERB_APP_KEY,
    PUBLIC_REVERB_HOST,
    PUBLIC_REVERB_PORT,
    PUBLIC_REVERB_SCHEME,
} from '$env/static/public';

/** @type {import('laravel-echo').default<any> | null} */
let echoInstance = null;

function getEcho() {
    if (echoInstance) {
        return echoInstance;
    }

    window.Pusher = Pusher;

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: PUBLIC_REVERB_APP_KEY,
        wsHost: PUBLIC_REVERB_HOST,
        wsPort: Number(PUBLIC_REVERB_PORT),
        wssPort: Number(PUBLIC_REVERB_PORT),
        forceTLS: PUBLIC_REVERB_SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return echoInstance;
}

/**
 * @param {number} sessionId
 * @param {(event: import('$lib/helpers/api.js').CouncilSessionRealtimeUpdate) => void} onUpdate
 */
export function subscribeToSession(sessionId, onUpdate) {
    const echo = getEcho();
    const channelName = `sessions.${sessionId}`;
    const channel = echo.channel(channelName);

    channel.listen('.council.session.updated', onUpdate);

    return () => {
        channel.stopListening('.council.session.updated');
        echo.leaveChannel(channelName);
    };
}