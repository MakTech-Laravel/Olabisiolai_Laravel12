# SEO Crawlability — Complete Plan (v1 + ZBC-pattern)

**Status:** ZBC-pattern adaptation implemented in code (resolve, robots route, noindex, DocumentHead). Prod needs deploy.  
**Date:** 2026-08-03  
**Related:** [`seo-crawlability-phase2.md`](seo-crawlability-phase2.md), [`olabisi-sitemap-plan.md`](olabisi-sitemap-plan.md)

---

## What “SEO-friendly” means here

| Claim | In scope? |
|-------|-----------|
| Per-URL `<title>` / description / OG / Twitter / robots / JSON-LD in **first HTML** (spa-shell) | **Yes** |
| Same payload via `GET /api/v1/seo-pages/resolve` for CSR `DocumentHead` | **Yes** |
| Sitemap + dynamic `robots.txt` on **www** origin (nginx → API) | **Yes** |
| Admin `noindex` / `canonical_url` / `og_image` | **Yes** |
| Google News `/news-sitemap.xml` | **No** — marketplace, not a news corpus |
| **Full SSR** (React body HTML) | **No** — CSR + spa-shell |

---

## Architecture

```
Browser/Bot → www (SPA nginx)
  ├─ hashed assets → static dist
  ├─ /index.html (file) → static (template sync for API)
  ├─ /robots.txt, /sitemap.xml → proxy → Laravel
  └─ other HTML paths → proxy → GET /spa-shell
                           └─ SeoResolverService (same as /seo-pages/resolve)
CSR DocumentHead → GET /api/v1/seo-pages/resolve
```

**No news-sitemap.** General sitemap only: static paths (minus `noindex`), businesses, catalog.

---

## Ops checklist

1. Deploy API then SPA (or together).
2. `FRONTEND_URL` = real www origin (Sitemap: and `<loc>` depend on it).
3. SPA Coolify: `SPA_SHELL_API_ORIGIN=https://api.gidira.tech`.
4. `php artisan sitemap:refresh` (or admin Generate).
5. Spot-check: `curl https://www…/robots.txt`, `/sitemap.xml`, spa-shell headers.
