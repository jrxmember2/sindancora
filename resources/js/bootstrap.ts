import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Tempo real via Laravel Reverb (Fase 5). Só inicializa quando configurado (VITE_REVERB_APP_KEY);
// sem ele, a inbox/sino seguem funcionando por polling/refresh.
window.Pusher = Pusher;

if (import.meta.env.VITE_REVERB_APP_KEY) {
    // Defaults seguros: sem VITE_REVERB_HOST, usa o domínio atual via /app do nginx (porta 443),
    // evitando cair em localhost:8080 quando o build sai sem as variáveis.
    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? window.location.protocol.replace(':', '');
    const forceTLS = scheme === 'https';
    const host = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
    const port = Number(import.meta.env.VITE_REVERB_PORT ?? (forceTLS ? 443 : 80));

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS,
        enabledTransports: ['ws', 'wss'],
    });
}
