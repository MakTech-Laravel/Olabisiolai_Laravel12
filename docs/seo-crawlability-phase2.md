# Phase 2 — SEO Crawlability (meta injection)

**Status:** Phase 2 + ZBC-pattern resolve/robots/noindex **code-complete**. Prod still needs deploy.  
**Complete plan:** [`seo-crawlability-complete.md`](seo-crawlability-complete.md)  
**Appendix to:** Admin SEO management plan  
**Date:** 2026-08-03  

**Shipped:** C→A→B shell injection; then ZBC-parity (`/seo-pages/resolve`, dynamic `/robots.txt`, noindex/canonical/og_image, www nginx proxy for robots+sitemap). **No** `/news-sitemap.xml`. Decision #0 code-complete for `<head>`; full SSR remains out of scope.

---

## Phase 1 findings (confirmed)

| Check | Finding |
|--------|---------|
| Rendering mode | **CSR only** (Vite + React Router) |
| Raw HTML meta | Generic `<title>Gidira</title>`, empty `#root`, no per-route description/og |
| robots / sitemap | API route exists locally; SPA has no `robots.txt`; prod lag (www 503, api sitemap 404) |
| DocumentHead | Client `useEffect` only |
| Deploy | Separate Coolify services; SPA = nginx static `dist` |

---

## Recommended approach: Server-side meta injection (Option C)

### Why this and not full SSR or headless prerendering

| Option | Verdict | Reason |
|--------|---------|--------|
| **(a) Full SSR/SSG** (Next.js/Remix) | Not for v1 | Full public-SPA rewrite; out of proportion to meta/title/preview gap |
| **(b) Prerender middleware** (Rendertron / prerender.io) | Follow-up | Feasible on server-fronted nginx; heavier ops; acute pain is `<head>`, not body invisibility to Google |
| **(c) Server-side meta injection** | **v1 choice** | Reuses `seo_pages` + by-path lookup; fixes social scrapers + basic crawler `<head>`; lowest effort |

---

## Decision #0 (blocks “SEO-ready”)

| # | Open Question | Recommendation | Owner |
|---|---------------|----------------|-------|
| **0** | SPA is CSR-only; raw HTML has no per-route meta (audit) | Implement server-side meta injection (Option 1 below) — **blocks calling the feature SEO-ready until shipped** | Backend + DevOps |

---

## Implementation detail

### 1. Integration point (split Coolify — critical)

Today: SPA nginx `try_files $uri $uri/ /index.html` → same shell for every path.

Bots hit **`www` (SPA)**, not `api`. Injection must therefore be reachable on the **document request path for www**:

**Option 1 — Laravel-owned HTML shell endpoint (recommended):**

1. API exposes e.g. `GET /spa-shell?path=/about` (or path from `X-Original-URI` / `REQUEST_URI` forwarded by nginx) — internal/public as needed, throttled.
2. Endpoint loads the built SPA `index.html` template (mount `dist/index.html` into API image, or Coolify shared volume / build artifact sync — **decide at implement time**; must stay in sync with SPA deploys).
3. Lookup `seo_pages` by normalized path (reuse public by-path / shared service — **do not duplicate** query rules).
4. Replace placeholder `<title>Gidira</title>`; inject `meta name="description"`, `og:title`, `og:description` (and optionally `og:url` / canonical).
5. Cache per path; on miss/unknown path return unmodified shell (SPA still boots).
6. **SPA nginx change:** for HTML navigations only (not hashed assets), `proxy_pass` to that API shell endpoint instead of raw `index.html`. Assets stay `try_files` / long-cache as today.

**Option 2 — OpenResty/Lua in SPA nginx:** local inject via internal HTTP to by-path API. Defer unless Option 1 latency is measured bad.

### 2. Existing plan pieces

| Piece | Status |
|-------|--------|
| `seo_pages` + seeder | Still source of truth |
| Public by-path API | Still needed; share lookup with shell injector |
| `DocumentHead` | Still needed for **client-side** route changes |
| Admin SEO CRUD | Unchanged — two consumers (CSR + shell) |

### 3. Caching

- Key: `seo:shell:{normalizedPath}` (or similar).
- TTL: long (e.g. 24h); **invalidate on `SeoPageController::update`** (and optionally on seeder/admin generate if needed).
- Closes prior Decision #4 gap for shell cache (JSON by-path may stay uncached in v1).

### 4. Sitemap

Independent of injection. Both read `seo_pages`. No shared write race. Sitemap still lists `FRONTEND_URL` locs; injection makes those URLs’ first HTML response have correct `<head>`.

### 5. Deploy-lag quick-fix (before or with v1)

- Ship API `public/robots.txt` Sitemap line to prod (already in repo: `Sitemap: https://api.gidira.tech/sitemap.xml`).
- Add SPA `public/robots.txt` → same Sitemap URL (Vite copies `public/` into `dist/` on build).
- After API deploy: run `php artisan sitemap:refresh` (or admin Generate) so `GET /sitemap.xml` is warm.
- Investigate Coolify `www` 503 separately (service availability) — not a code change.

---

## v1 vs follow-up

**v1 (SEO-ready bar):**

- Laravel shell injection + SPA nginx proxy for documents
- Shell cache + invalidate on SEO update
- Prod robots / sitemap / uptime fixes

**Follow-up:**

- Prerender (b) or SSR (a) if organic data shows body-content indexing gaps
- OpenResty (Option 2) only if Laravel hop is a measured bottleneck

---

## Stakeholder summary

Right now every URL looks like the same generic “Gidira” page to anything that does not run JavaScript — including Facebook, Twitter, WhatsApp, and LinkedIn previews. A sitemap tells Google that URLs exist; it does not fix what appears when someone shares a link or what title gets stored on first crawl. This plan has the server insert the correct title and description into the HTML before it leaves the server, using the same SEO rows the admin panel already edits. It does not yet make all dynamically loaded catalog/business body text crawlable as static HTML — revisit that only if search data shows it is needed after this ships.

---

## Explicit non-goals (this phase)

- Migrating the SPA to Next.js/Remix
- Headless full-page prerender for all bots in v1
- Changing sitemap URL inventory rules
