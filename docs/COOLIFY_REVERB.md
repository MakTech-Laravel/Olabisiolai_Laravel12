# Coolify + Reverb (WebSockets)

## Correct mental model (this is not a bug)

| Layer | Port | Role |
|--------|------|------|
| Browser | **443** (`wss://`) | Same public host as your REST API. Port 443 is default for HTTPS — you do **not** open Reverb’s internal port in the browser. |
| Coolify / Traefik | 443 → **80** | TLS termination; forwards HTTP to the **container** port you expose (must be **80** for this image). |
| **nginx** inside the container | **80** | Serves Laravel (`/`, `/api`, …) and **proxies** `/app`, `/apps`, `/reverb` → Reverb. |
| **Reverb** (supervisor) | **8089** | **Internal only.** Listens on `127.0.0.1:8089` (or `0.0.0.0:8089`); nginx talks to it. **Do not** publish 8089 on Coolify. |

So: **`wss://olabisiolai.maktechlaravel.cloud:443/...` is correct.** Reverb on 8089 is reached **only** from nginx inside the container, not from the browser.

## What is already in this repo

The WebSocket proxy is **already** defined in `docker/nginx.conf`:

- `location ^~ /app` → `http://127.0.0.1:8089`
- `location ^~ /apps` → same
- `location ^~ /reverb` → same  
- `Upgrade` / `Connection` use `$connection_upgrade` (required for WebSockets).

You **do not** add blocks under the VPS host `/etc/nginx/sites-available/` **unless** you run Laravel **without** this Docker image. With the provided **Dockerfile**, all of that lives **inside** the image.

## Coolify checklist

1. **Build pack:** Dockerfile (not Nixpacks).
2. **Ports:** Map public HTTP(S) to container **80** only. Do **not** map or expose **8089** publicly.
3. **Start command:** Leave default (supervisord); do not replace with `php artisan serve` or raw `reverb:start` only.
4. **Laravel `.env`:** See `.env.production.example` — especially `REVERB_HOST`, `REVERB_PORT=443`, `REVERB_SCHEME=https`, and **loopback** publish: `REVERB_BROADCAST_HOST=127.0.0.1`, `REVERB_BROADCAST_PORT=8089`, `REVERB_BROADCAST_SCHEME=http`.
5. **React build:** `VITE_REVERB_HOST` = **API hostname only** (same host where `/app` exists). `VITE_REVERB_APP_KEY` must equal `REVERB_APP_KEY`. Rebuild the frontend after any change.

### Frontend (why DevTools shows no WebSocket)

The SPA only starts Echo when **both** `VITE_REVERB_APP_KEY` and `VITE_REVERB_HOST` exist in the **built** bundle (`messagingEnv.isReverbConfigured()` in `src/config/messagingEnv.ts`). Vite inlines `VITE_*` at **build time**, not from the browser’s runtime `.env`.

If those variables were missing in the Coolify **frontend** Docker build, you will see:

- Successful `POST …/messages` (REST works)
- **No** `WS` / `wss://…/app/…` entries in Network — Echo never connects

**Fix:** In the **frontend** service on Coolify, set build arguments / build-time environment to match the backend `REVERB_APP_KEY` and API hostname, then **redeploy / rebuild** the frontend image. See `olabisiolai_frontend_react/Dockerfile` (`ARG VITE_REVERB_*`) and `olabisiolai_frontend_react/.env.example`.

After a correct build, open the Console: you should **not** see `[Realtime] Laravel Echo is disabled…`. In Network → filter **WS**, you should see `101` on `wss://<API-host>/app/<key>`.

**Note:** This app does not set `window.Echo`; use DevTools → WS filter, not `console.log(window.Echo)`.

## Verify inside the running container

```bash
# Reverb listening (supervisor)
ss -tlnp | grep 8089

# nginx proxies /app to Reverb (expect non-connection-refused; exact status may vary)
curl -sS -o /dev/null -w "%{http_code}\n" -H "Connection: Upgrade" -H "Upgrade: websocket" http://127.0.0.1/app/

# Laravel can publish to Reverb
php artisan tinker --execute="broadcast(new \\Illuminate\\Notifications\\Events\\BroadcastNotificationCreated(...));"
# (optional — or trigger a real message and watch storage/logs)
```

Check **`storage/logs/laravel.log`** for `Broadcast skipped:` — that means PHP could not reach Reverb (usually wrong `REVERB_BROADCAST_*`).

## If WebSocket still fails in the browser

1. **Network → WS:** URL should be `wss://<API-host>/app/<REVERB_APP_KEY>` (port 443 is implicit; `:443` in the UI is normal).
2. **Status 101** = upgrade succeeded.
3. If it never connects: edge proxy not forwarding `Upgrade` (rare on Coolify’s Traefik), wrong service port (not hitting container nginx), or **SPA** `VITE_REVERB_*` / key mismatch.

## Common mistake

Believing the browser must connect **directly** to **8089**. That would bypass nginx and usually break TLS and routing. **Always** use the public API URL on **443** (or **80** without TLS); nginx inside the container bridges to Reverb.
