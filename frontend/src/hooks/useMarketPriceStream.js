import { useEffect, useState } from 'react';

const CHANNEL = 'market.prices';
const PRICE_EVENT = 'market.prices.updated';

function parsePayload(value) {
  if (typeof value !== 'string') return value;
  try {
    return JSON.parse(value);
  } catch {
    return null;
  }
}

/**
 * Subscribe to the public Laravel Reverb market channel with the
 * Pusher-compatible protocol, without adding another frontend dependency.
 */
export function useMarketPriceStream(onPrices) {
  const [status, setStatus] = useState('disabled');

  useEffect(() => {
    const host = String(import.meta.env.VITE_REVERB_HOST || '').trim();
    const key = String(import.meta.env.VITE_REVERB_APP_KEY || '').trim();

    if (!host || !key) {
      setStatus('disabled');
      return undefined;
    }

    const scheme = String(import.meta.env.VITE_REVERB_SCHEME || 'ws').toLowerCase() === 'wss'
      ? 'wss'
      : 'ws';
    const port = String(import.meta.env.VITE_REVERB_PORT || (scheme === 'wss' ? '443' : '8082'));
    const path = String(import.meta.env.VITE_REVERB_PATH || '').replace(/^\/+|\/+$/g, '');
    const endpoint = scheme + '://' + host + (port ? ':' + port : '') + '/'
      + (path ? path + '/' : '') + 'app/' + encodeURIComponent(key)
      + '?protocol=7&client=js&version=8.4.0&flash=false';

    let socket = null;
    let reconnectTimer = null;
    let reconnectAttempts = 0;
    let stopped = false;

    const scheduleReconnect = () => {
      if (stopped || reconnectTimer !== null) return;

      setStatus('reconnecting');
      const delay = Math.min(1000 * (2 ** Math.min(reconnectAttempts, 5)), 30000);
      reconnectAttempts += 1;

      reconnectTimer = window.setTimeout(() => {
        reconnectTimer = null;
        connect();
      }, delay);
    };

    const send = (event, data = {}) => {
      if (socket && socket.readyState === WebSocket.OPEN) {
        socket.send(JSON.stringify({ event, data }));
      }
    };

    const connect = () => {
      if (stopped) return;

      setStatus(reconnectAttempts === 0 ? 'connecting' : 'reconnecting');

      try {
        socket = new WebSocket(endpoint);
      } catch {
        scheduleReconnect();
        return;
      }

      socket.onmessage = (message) => {
        const envelope = parsePayload(message.data);
        if (!envelope || typeof envelope.event !== 'string') return;

        if (envelope.event === 'pusher:connection_established') {
          send('pusher:subscribe', { channel: CHANNEL });
          return;
        }

        if (envelope.event === 'pusher_internal:subscription_succeeded') {
          reconnectAttempts = 0;
          setStatus('connected');
          return;
        }

        if (envelope.event === 'pusher:ping') {
          send('pusher:pong');
          return;
        }

        if (envelope.event === PRICE_EVENT) {
          const payload = parsePayload(envelope.data);
          if (payload && Array.isArray(payload.prices)) {
            onPrices(payload);
          }
        }
      };

      socket.onerror = () => {
        // onclose owns reconnection to avoid creating duplicate sockets.
      };

      socket.onclose = () => {
        if (!stopped) scheduleReconnect();
      };
    };

    connect();

    return () => {
      stopped = true;
      if (reconnectTimer !== null) window.clearTimeout(reconnectTimer);
      reconnectTimer = null;

      if (socket && socket.readyState < WebSocket.CLOSING) {
        socket.close(1000, 'Market page unmounted');
      }
    };
  }, [onPrices]);

  return { status };
}
