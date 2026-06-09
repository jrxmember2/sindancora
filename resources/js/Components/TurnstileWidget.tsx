import { useEffect, useRef } from 'react';

declare global {
    interface Window {
        turnstile?: {
            render: (el: HTMLElement, opts: Record<string, unknown>) => string;
            reset: (id?: string) => void;
        };
        onTurnstileLoad?: () => void;
    }
}

const SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

function ensureScript(): Promise<void> {
    return new Promise((resolve) => {
        if (window.turnstile) {
            resolve();
            return;
        }
        const existing = document.querySelector(`script[src="${SCRIPT_SRC}"]`);
        if (existing) {
            existing.addEventListener('load', () => resolve());
            return;
        }
        const script = document.createElement('script');
        script.src = SCRIPT_SRC;
        script.async = true;
        script.defer = true;
        script.addEventListener('load', () => resolve());
        document.head.appendChild(script);
    });
}

/**
 * Widget Cloudflare Turnstile (anti-bot). Só renderiza quando `siteKey` está presente; chama
 * `onVerify` com o token resolvido para o formulário enviar em `cf-turnstile-response`.
 */
export default function TurnstileWidget({ siteKey, onVerify }: { siteKey: string; onVerify: (token: string) => void }) {
    const ref = useRef<HTMLDivElement>(null);
    const rendered = useRef(false);

    useEffect(() => {
        let cancelled = false;
        ensureScript().then(() => {
            if (cancelled || rendered.current || !ref.current || !window.turnstile) return;
            rendered.current = true;
            window.turnstile.render(ref.current, {
                sitekey: siteKey,
                callback: (token: string) => onVerify(token),
                'error-callback': () => onVerify(''),
                'expired-callback': () => onVerify(''),
            });
        });
        return () => {
            cancelled = true;
        };
    }, [siteKey, onVerify]);

    return <div ref={ref} className="mt-1" />;
}
