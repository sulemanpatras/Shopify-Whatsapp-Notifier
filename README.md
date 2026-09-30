# WhatsApp Order Notifier — Shopify app on Laravel

Sends a WhatsApp message to the customer every time a new order is placed on your Shopify store.

- **Shopify integration**: embedded app, managed installation + token exchange (no OAuth redirect code), App Bridge session tokens for the admin panel.
- **Order webhooks**: `orders/create` (plus `app/uninstalled` and the three mandatory privacy webhooks), HMAC-verified, declared in `shopify.app.toml`, deduplicated per order.
- **WhatsApp integration**: WhatsApp Business **Cloud API** (Meta). Approved templates for order confirmations, free text for testing.
- **Message templates**: create several, use variables like `{{customer_name}}`, `{{order_number}}`, `{{order_total}}`, live preview as a chat bubble.
- **Admin panel** (inside the Shopify admin): on/off switch, WhatsApp credentials (token stored encrypted), country code, active message, test send, message log with "Send again".

```
shopify.app.toml          Shopify app config (scopes, webhooks)
package.json              Shopify CLI
scripts/setup.mjs         composer install + .env + key + migrations for web/
web/                      Complete Laravel 12 app (artisan, composer.json, config, public, app code, migrations, views, tests)
  shopify.web.toml        Tells Shopify CLI how to start Laravel
```

## 1. Requirements

PHP 8.2+ (with `pdo_sqlite` or your DB driver, `mbstring`, `openssl`, `curl`), Composer 2, Node 20.10+, a [Shopify Partner account](https://partners.shopify.com) + a development store, and a [Meta developer app](https://developers.facebook.com) with the WhatsApp product.

## 2. Install

```bash
npm install                      # Shopify CLI
npm run setup                    # composer install, .env, app key, migrate (web/ is a complete Laravel 12 app)
npx shopify app config link      # log in and link/create the app in your Partner account (fills client_id)
shopify app dev                  # or: npm run dev
```

`shopify app dev` starts a tunnel, updates the app URLs, injects `SHOPIFY_API_KEY` / `SHOPIFY_API_SECRET`, runs `composer install` + migrations and starts PHP. Press `p` to open the app in your dev store.

## 3. WhatsApp Cloud API setup

1. In your Meta app add **WhatsApp**. Open **WhatsApp → API Setup**: copy the **Phone number ID** and a token. The temporary token expires in ~24h, so for real use create a **System User** in Business Settings with `whatsapp_business_messaging` and `whatsapp_business_management` and generate a permanent token.
2. In **WhatsApp Manager → Message templates** create a **Utility** template, e.g. name `order_confirmation`, language English, body:
   `Hi {{1}}, thanks for your order {{2}} at {{3}}! Your total is {{4}}. We'll message you as soon as it ships.`
   The app's **Messages** tab shows the exact body to paste for each template you design. Wait for approval.
3. In the app: **Settings** → paste Phone number ID + token, pick the message, **Send a test message**, then switch **Order confirmations** on.

Business-initiated messages must use an approved template, which is why "Approved WhatsApp template" is the default type. "Free text" only delivers inside the 24-hour customer window (handy for testing with a number that just messaged you).

## 4. Shopify notes

- **Protected customer data**: `orders/create` payloads only include customer name/phone/address once your app has protected customer data access. In the Partner dashboard: *App → API access → Protected customer data access* and request the fields (name, phone). Development stores work for testing; public distribution needs approval.
- Scopes are `read_orders,read_customers` (see `shopify.app.toml`). Run `shopify app deploy` after changing the toml.
- Checkout must collect a phone number (Settings → Checkout → "Shipping address phone number": Required) or messages are skipped as "no valid phone number".

## 5. Test without placing an order

```bash
cd web
php artisan shopify:simulate-order your-store.myshopify.com --phone="+923001234567"
php artisan test
```

## 6. How it works

1. Order created → Shopify POSTs `/webhooks/orders-create`.
2. `VerifyShopifyWebhook` checks the HMAC; `WebhookController` finds the shop, normalizes the phone (`PhoneNormalizer`), writes one `message_logs` row per order (duplicates ignored).
3. `SendOrderWhatsAppNotification` runs after the 200 is returned, renders the variables (`MessageRenderer`) and calls Graph API `POST /{phone-number-id}/messages` (`WhatsAppClient`). Status, WhatsApp message id or the error text are saved on the log.
4. The admin panel calls `/api/admin/*` with an App Bridge session token (`VerifyShopifySessionToken`), which also installs the shop on first load via token exchange.

## 7. Production checklist

- Host Laravel behind HTTPS (Forge, Laravel Cloud, a VPS, etc.), set `APP_ENV=production`, `APP_DEBUG=false`, a real DB (MySQL/Postgres), `SHOPIFY_API_KEY`, `SHOPIFY_API_SECRET`, `SCOPES`, then `shopify app deploy` with the real `application_url`.
- For high volume, make `SendOrderWhatsAppNotification` implement `ShouldQueue`, set `QUEUE_CONNECTION=database|redis`, run `php artisan queue:work`.
- Get explicit customer opt-in for WhatsApp messages where required by law/WhatsApp policy.
