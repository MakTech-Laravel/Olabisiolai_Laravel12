# Coolify + Reverb (split architecture)

Gidira runs **three separate Coolify applications**:

| Service | Domain | Container port | Repo |
|---------|--------|----------------|------|
| API | `api.gidira.tech` | 80 | `Olabisiolai_Laravel12` |
| SPA | `www.gidira.tech` | 80 | `olabisiolai_frontend_react` |
| WebSocket | `ws.gidira.tech` | 8080 | `olabisiolai_websocket` |

Browsers connect with `wss://ws.gidira.tech/app/{REVERB_APP_KEY}`. The API container does **not** run Reverb or proxy `/app`.

## Data flow

```
Browser (www.gidira.tech)
  │ REST  → api.gidira.tech/api/v1/...
  │ wss   → ws.gidira.tech/app/{key}
  │ auth  → api.gidira.tech/api/broadcasting/auth  (Bearer token)
  ▼
api.gidira.tech publishes events → ws.gidira.tech:443 (HTTP, HMAC-signed)
```

## Backend checklist (api.gidira.tech)

1. **Build pack:** Dockerfile (not Nixpacks — see `nixpacks.toml`).
2. **Port:** Map public HTTP(S) to container **80** only.
3. **Health:** `/up`
4. **Environment:** Start from [`docs/env.coolify.example`](env.coolify.example). Critical vars:
   - `BROADCAST_CONNECTION=reverb`
   - `REVERB_HOST=ws.gidira.tech`, `REVERB_PORT=443`, `REVERB_SCHEME=https`
   - `REVERB_APP_*` must match websocket + frontend build
   - `FRONTEND_URL=https://www.gidira.tech`
   - `CORS_ALLOWED_ORIGINS=https://www.gidira.tech,https://gidira.tech`
   - `APP_DEBUG=false`
5. **Entrypoint:** `docker/entrypoint.sh` merges Coolify env into `.env`, runs migrations, Passport, then supervisord (php-fpm, nginx, queue, scheduler).

### Publishing to Reverb from the API container

On Coolify, separate apps do not resolve each other by Docker service name by default. Use the **public** WS hostname so Traefik loops back on the same VPS:

```dotenv
REVERB_HOST=ws.gidira.tech
REVERB_PORT=443
REVERB_SCHEME=https
```

**Alternative** (internal network): enable “Connect To Predefined Network” on both API and websocket services, then:

```dotenv
REVERB_HOST=<websocket-container-hostname>
REVERB_PORT=8080
REVERB_SCHEME=http
```

Do **not** set `REVERB_BROADCAST_*` or `REVERB_SERVER_*` in split deployment — those are for the old colocated model.

## Frontend checklist (www.gidira.tech)

Vite inlines `VITE_*` at **build time**. Set these as Docker **build arguments** in Coolify:

```dotenv
VITE_ENVIRONMENT_MODE=production
VITE_API_BASE_URL=https://api.gidira.tech/api/v1
VITE_REVERB_APP_KEY=<same as REVERB_APP_KEY>
VITE_REVERB_HOST=ws.gidira.tech
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

Rebuild the frontend image after any `VITE_*` change.

## WebSocket checklist (ws.gidira.tech)

1. **Port:** Expose container **8080** (Traefik terminates TLS on 443).
2. **Health:** `/health`
3. **Environment:** See `olabisiolai_websocket/.env.example`
4. **Origins:** `REVERB_ALLOWED_ORIGINS` must include `www.gidira.tech` and `gidira.tech` (bare hostnames, no scheme).

## Verify end-to-end

1. Open `https://www.gidira.tech/ws-test`
2. Status should be **Connected** without logging in
3. **Trigger public ping** → event on `reverb-ping`
4. **Check backend config** → `host: ws.gidira.tech`, `port: 443`
5. DevTools → Network → WS → `wss://ws.gidira.tech/app/...` with status **101**
6. Log in → **Trigger private notification** (requires `REALTIME_ALLOW_TEST_BROADCAST=true` on API)
7. API logs: no `Broadcast skipped:` after ping

## Troubleshooting

| Symptom | Likely cause |
|---------|----------------|
| No WS in Network tab | Frontend built without `VITE_REVERB_*` |
| WS 403 / origin rejected | `REVERB_ALLOWED_ORIGINS` missing SPA hostname |
| Ping API OK, no WS event | API cannot reach `ws.gidira.tech` — check `REVERB_HOST` / firewall |
| Private channel fails | Wrong token, or `/api/broadcasting/auth` CORS |
| `Broadcast skipped:` in logs | Publish URL unreachable — fix `REVERB_HOST` |

See also: [`docs/COOLIFY_GIDIRA_DEPLOYMENT.md`](../../docs/COOLIFY_GIDIRA_DEPLOYMENT.md) (repo root).
