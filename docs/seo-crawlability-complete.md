# SEO Crawlability — Complete Plan (v1)

**Status:** Implementation target for Decision #0 “SEO-ready” (meta injection).  
**Date:** 2026-08-03  
**Related:** [`seo-crawlability-phase2.md`](seo-crawlability-phase2.md), [`olabisi-sitemap-plan.md`](olabisi-sitemap-plan.md)

---

## What “fully SEO-friendly” means here

| Claim | In scope for v1? |
|-------|------------------|
| Per-URL `<title>` / description / Open Graph / Twitter in **first HTML** (no JS) | **Yes** |
| Sitemap + robots discoverability | **Yes** (already shipped) |
| Admin-editable static page meta | **Yes** |
| Business + catalog share/crawl `<head>` from live DB | **Yes** (this completion pack) |
| Real Vite `index.html` (hashed JS/CSS) served with injected meta | **Yes** (template sync) |
| **Full SSR** (React body HTML from server) | **No** — CSR stays |
| Headless prerender of full page body | **No** — follow-up only if Search Console shows body gaps |

Bots and social scrapers that do **not** run JS still get correct **head**. Page body still hydrates in the browser.

---

## Architecture (final)

```
Browser/Bot → www (SPA nginx)
  ├─ *.js/css/img → static dist (long cache)
  ├─ /index.html, /robots.txt → static files on disk
  └─ other paths → proxy → API GET /spa-shell?path=$uri
                      ├─ load SPA index template (file mount OR fetch FRONTEND_URL/index.html)
                      ├─ resolve meta (seo_pages → business → catalog)
                      └─ inject <head> tags + cache per path
```

`DocumentHead` remains for **in-SPA** navigations after hydration.

---

## Completion checklist

### Already shipped (C → A → B)

- [x] SPA + API `robots.txt` Sitemap line
- [x] `GET /spa-shell` + `seo_pages` inject + cache invalidate on admin update
- [x] SPA nginx document proxy + API-down fallback to local `index.html`

### This pack (closes Decision #0 in code)

- [x] **Template sync** — prefer `SPA_SHELL_INDEX_PATH`; else HTTP fetch `{FRONTEND_URL}/index.html` (cached); else `resources/spa/index.html`
- [x] **Dynamic meta** — `/businesses/{encryptId}` and `/catalog/items/{id}` from marketplace-visible rows
- [x] **Richer tags** — `og:type`, `og:site_name`, `twitter:card|title|description`
- [x] **Artisan** — `seo:refresh-spa-shell-template`
- [x] **Tests** + docs status → Decision #0 **code-complete** (prod still needs deploy)

### Deploy (ops — not code)

1. Deploy **API** then **SPA** (or together).
2. Coolify SPA: `SPA_SHELL_API_ORIGIN=https://api.gidira.tech` (no trailing slash).
3. Coolify API (optional): mount SPA `dist/index.html` → set `SPA_SHELL_INDEX_PATH`; otherwise leave unset so API fetches `FRONTEND_URL/index.html`.
4. Ensure `FRONTEND_URL=https://www…` and `/index.html` is reachable **without** going through `/spa-shell` (nginx `try_files $uri` serves the file).
5. `php artisan sitemap:generate` (or admin Generate).
6. Spot-check: `curl -sI https://www…/about` → `X-Gidira-Spa-Shell: 1`; `curl -s https://www…/about | head` shows route title.

---

## Explicit non-goals (unchanged)

- Next.js / Remix migration
- Rendertron / prerender.io for all bots in v1
- Changing sitemap URL inventory rules
