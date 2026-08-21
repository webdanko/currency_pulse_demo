import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

/**
 * Laravel Echo wired to a self-hosted Reverb server (Pusher protocol).
 * Returns null when no key is configured so the app still runs on plain
 * polling — the socket layer is an enhancement, not a hard dependency.
 */
export function createEcho() {
  const key = import.meta.env.VITE_REVERB_APP_KEY
  if (!key) return null

  const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http'

  return new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
  })
}
