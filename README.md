# E-COMMERCE-TRIAL

## Storefront API (v1)

Run `php artisan migrate:fresh --seed && php artisan storage:link` to load the apparel catalog and placeholder images
(served from `/storage`). Demo buyer: `client@example.com` / `password`. Staff: `manager@system.local` / `password`.

| Endpoint | Auth | Notes |
| --- | --- | --- |
| `GET /api/v1/catalog/products` | public | `category`, `search`, `gender` (`men`/`women` include `unisex`; 422 if invalid), `featured`, `in_stock`, `on_sale` (bool), `min_discount` (1-90, implies `on_sale`; 422 if invalid), `per_page` → `{data, meta}`; `meta.max_discount` = top active product-sale % (null if none). Products carry `onSale`, `discountPercent`, `compareAtPriceEUR`, `saleEndsAt`; `price`/`priceEUR` are the effective (discounted) price, also used by checkout |
| `GET /api/v1/catalog/products/{slug}` | public | `{data: Product}` |
| `GET /api/v1/catalog/categories` | public | `{data: [{id,name,slug}]}` |
| `GET /api/v1/catalog/flash-sales` | public | `tier` (`all`/`40`/`50`/`60` min discount %), `category`, `size`, `sort` (`discount`/`depletion`/`price_asc`/`price_desc`), `page`, `per_page` (max 48) → `{data:[FlashSale], meta:{..., nextEndsAt, serverTime, tiers, categories}}`; active sales first, then upcoming |
| `POST /api/v1/client/flash-sales/{id}/reserve` | client | 202 `{status:'processing'}` or 422 `{status:'failed'}` (sold out / 1 per client); only for currently active sales |
| `POST /api/v1/newsletter/flash-drops` | public, throttle 5/min | `{email}` → 201 new / 200 duplicate `{message, subscribed:true}` |
| `GET/POST /api/v1/staff/clients`, `GET/PUT/DELETE /api/v1/staff/clients/{client}`, `POST .../{client}/password`, `POST .../{client}/revoke-sessions`, `GET .../{client}/orders` | Sanctum staff token; `clients.view` for reads, `clients.manage` for writes | Customer management mirroring the CMS Customers screen. List supports `search`, `has_orders`, `per_page`. Delete returns 422 for customers with orders; password change revokes their sessions |
| `GET /api/v1/content/home` | public, throttle 120/min | `{data:{heroEyebrow, heroTitle, heroSubtitle, lookbook:[{id,title,caption,link,imageUrl}], editorialTitle, editorialBody, editorialImageUrl}}`; image URLs absolute or `null`; empty strings/arrays when unseeded |
| `GET /api/v1/content/site-settings` | public, throttle 120/min | `{data:{announcement, footerTagline, socialLinks:[{label,icon,href}], storeLocations:[{name,address,hours}]}}`; only active rows, by `sort_order` |
| `GET /api/v1/content/articles?page=&per_page=&category=` | public, throttle 120/min | `{data:[article], meta:{current_page,last_page,per_page,total}, categories:[string]}`; published (not draft/scheduled) only, `published_at` desc, `per_page` default 9 / max 24; list items have no `body` |
| `GET /api/v1/content/articles/{slug}` | public, throttle 120/min | `{data:article+body (Markdown), related:[article]}` (up to 3, same category first); 404 JSON for draft, scheduled or unknown. Article: `{id,slug,title,excerpt,coverImage,coverImageAlt,author,category,isFeatured,publishedAt,updatedAt,readingMinutes,seoTitle,seoDescription}` |
| `PUT /api/v1/staff/products/{product}` | staff `products.manage` | partial update; accepts editorial fields `headline`, `long_description`, `care_instructions`, `seo_title`, `seo_description` (also on create) |
| `POST /api/v1/client/register`, `/login` | public | `{token, client:{id,name,email}}` (Bearer) |
| `GET /api/v1/client/me`, `POST /client/logout` | client | |
| `POST /api/v1/client/orders` | client | multi-item checkout, transactional, decrements stock; 422 on insufficient stock / invalid size or color |
| `GET /api/v1/client/orders[/{orderNumber}]` | client | order history |
| `POST /api/v1/staff/products/{product}/images` | staff `products.manage` | multipart `image`, optional `is_primary` |
| `DELETE /api/v1/staff/products/{product}/images/{image}` | staff `products.manage` | |

Flash sale stock is held in Redis: the seeder and `php artisan flash-sales:warm` load it (re-run the command after flushing Redis). Reservations dispatch a job to RabbitMQ (`order.reservations`).

CORS is configured in `config/cors.php` (`CORS_ALLOWED_ORIGINS`, default `http://localhost:5173`). Postman collections live in `postman/`.

### SEO endpoints

Public, unauthenticated: `GET /sitemap.xml` (storefront URLs: `/`, `/shop`, `/shop/men`, `/shop/women`, `/flash-sale`, every `/products/{slug}`; checkout URLs are never listed) and `GET /robots.txt` (disallows `/checkout/` and points to the API-origin sitemap). URLs use `FRONTEND_URL` (`config('shop.frontend_url')`, default `http://localhost:5173`).

## CMS admin panel (Filament)

Open `http://localhost:8000/admin` and sign in with a staff account (web guard + session; the Sanctum API guards are untouched, and storefront clients cannot sign in).

| Demo user | Password | Role |
|---|---|---|
| `admin@system.local` | `password` | admin (everything) |
| `manager@system.local` | `password` | products, flash sales, orders (read) |
| `support@system.local` | `password` | orders (read + refund) |

- Entering the panel requires the `cms.access` permission (migration `2026_10_11_000001` adds it and grants it to admin, manager and support; the seeder does the same).
- Resources are authorised with the existing RBAC (`User::hasPermission()`): Products/Categories → `products.manage`; Orders → `orders.view` (Refund action → `orders.refund`, reuses `OrderCmsService`); Flash sales → `flash-sales.view|create|update|delete`.
- **Access tokens** (nav group *Security*, `/admin/access-tokens`): **admin role only** (others get 403 and no nav entry). Lists Sanctum tokens (staff and client; hashes never shown) with filters, per-row and bulk **Revoke**, and a **Create staff token** action whose plain-text token is shown once.
- **Customers** (nav group *Sales*, `/admin/clients`): storefront accounts with order/session counts, search, and verified/has-orders filters; an orders relation manager links to each order. `clients.view` (admin, manager, support; migration `2026_10_12_000002`) reads; `clients.manage` (admin, support) edits details, **Set password** (also revokes sessions), **Revoke sessions**, and deletes only customers with no orders (orders cascade from the client).
- **Staff users / Roles / Permissions** (nav group *Security*, `/admin/users|roles|permissions`, requires `users.manage`, held by admin): create/edit staff, assign roles and extra direct permissions, edit role permissions, and relabel permissions (names are read-only since code checks them). Safeguards: you can't delete yourself or the last admin, the last admin can't lose the admin role, built-in roles (`admin`, `manager`, `support`) can't be renamed or deleted, roles still assigned to users can't be deleted, and deleting a user revokes their tokens.
- **Content screens** (nav group *Content*, `app/Filament/Pages`): **Home Page** (`/admin/content/home-page`: hero, editorial copy + image, drag-reorderable lookbook items with title/caption/link/image) and **Site Settings** (`/admin/content/site-settings`: announcement, footer tagline, reorderable social links and store locations with active toggles). They edit the single `HomePage::current()` / `SiteSetting::current()` rows and the public `GET /api/v1/content/home` and `/content/site-settings` reflect saves immediately (no caching). Uploads go to the `public` disk under `content/` (max 4 MB, images only); files that are replaced or removed are deleted. Lookbook links accept `/relative` paths or http(s) URLs; social link URLs must be valid URLs.
- **Editorial articles** (nav group *Content*, `/admin/articles`, `content.manage`): Markdown articles with cover upload (`public` disk, `content/articles`, max 4 MB; replaced/deleted covers are removed), slug (changing it changes the public URL), category, featured flag, draft/published status with optional scheduling (publishing with no date uses now), SEO fields, filters and a **View on site** action (list and edit page). Live articles open the public URL; draft/scheduled ones open **Preview on site**, a 1-hour signed link (`/editorial/{slug}?preview=1&expires=..&signature=..`) that the storefront forwards to `GET /api/v1/content/articles/{slug}/preview` (`signed:relative`, any status, `noindex`, `no-store`; 403 if invalid/expired).
- **Media library** (nav group *Content*, `/admin/content/media`, `content.manage`): browses every file on the `public` disk (product, article and home/lookbook images) with search, folder and in-use/unused filters, upload (images, max 4 MB, stored in `media/`), copy URL and delete. Each file shows which products/articles/home blocks reference it; files in use cannot be deleted.
- Content screens require the `content.manage` permission **and** `cms.access` (migration `2026_10_12_000001` adds it and grants it to admin and manager; the seeder does the same; support does not get it). Run `php artisan migrate` on existing databases.
- Filament code lives in `app/Filament` (panel provider: `app/Providers/Filament/AdminPanelProvider.php`) and targets **Filament v5** (Schemas API: `form(Schema $schema)`, `Filament\Schemas\Components\*`, unified `Filament\Actions\*`); new resources should use `App\Filament\Concerns\AuthorizesByPermission`.
- Filament needs PHP `ext-intl` (added to `.devcontainer/Dockerfile`; rebuild the container). After upgrading Filament run `php artisan filament:upgrade`; published assets live in `public/css|js/filament`.

## Product sales (regular discounts)

Separate from flash sales: `products.sale_discount_percent` (1-90) with optional `sale_starts_at`/`sale_ends_at`. In the Filament **Products** resource use the *Sale* form section (toggle off clears it), the "On sale" / "Discount tier" table filters, and the bulk actions *Put on sale* / *End sale* (require `products.manage`). Demo data: `php artisan db:seed --class=ProductSaleSeeder`.

Demo orders: `php artisan db:seed --class=OrderSeeder` creates 36 orders (`KFT-SEED-0001`…) with line items across the seeded customers, mixed statuses, delivery methods and promo use over the last 90 days. Idempotent (re-running updates the same orders; stock is untouched). Included in `DatabaseSeeder`.
