# Buyer catalog cart API (web + mobile)

Passport Bearer auth (`Authorization: Bearer <token>`). Envelope: `{ success, message, data }` (and `errors` on validation failures). Wire fields are **snake_case**.

Base: `/api/v1`

## Catalog discovery (public)

| Method | Path | Notes |
|--------|------|--------|
| GET | `/catalog` | Feed with filters + pagination (`per_page` for home strips) |
| GET | `/catalog/items/{id}` | Single item |

## Cart (authenticated, verified user/vendor)

| Method | Path | Body / query | Response `data` |
|--------|------|--------------|-----------------|
| GET | `/cart` | optional `business_info_id` | `{ carts, count }` or `{ cart }` |
| POST | `/cart/items` | `{ catalog_item_id, quantity? }` | `{ cart }` |
| PATCH | `/cart/items/{id}` | `{ quantity }` (`0` removes) | `{ cart }` |
| DELETE | `/cart/items/{id}` | — | `{ cart }` |
| POST | `/cart/send` | `{ cart_id }` or `{ business_info_id }` | `{ cart, conversation_uuid, message }` |
| GET | `/messages/carts/{cartMessageId}` | sent cart id | `{ cart }` |

OpenAPI UI: `/api/documentation` (tag **Buyer Cart**).

Sending creates a direct conversation message whose body contains `[GIDIRA_CART]…[/GIDIRA_CART]` including `cart_id` for later snapshot fetch.
