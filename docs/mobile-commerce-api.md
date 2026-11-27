# DAODES Mobile Commerce API

Base URL: \`/api/mobile/v1\`

All protected endpoints require:

\`\`\`http
Authorization: Bearer <Sanctum token>
Accept: application/json
Content-Type: application/json
\`\`\`

The login token is customer-scoped, has the \`mobile\` ability and expires after 30 days. Logout revokes only the current token.

## Customer endpoints

| Method | Path | Purpose |
|---|---|---|
| GET | \`/health\` | API health |
| POST | \`/auth/login\` | Phone/password login |
| POST | \`/auth/logout\` | Revoke current mobile token |
| GET | \`/me\` | Current customer |
| GET | \`/organizations\` | Active organizations |
| GET | \`/organizations/{id}\` | Public menu and fulfillment options |
| GET | \`/cart\` | Current server-side cart |
| POST | \`/cart/items\` | Add item (\`organization_id\`, \`item_id\`, \`quantity\`) |
| PATCH | \`/cart/items/{menuItem}\` | Set quantity |
| DELETE | \`/cart/items/{menuItem}\` | Remove item |
| DELETE | \`/cart\` | Clear cart |
| GET | \`/addresses\` | Customer address book |
| POST | \`/addresses\` | Create address |
| PUT | \`/addresses/{address}\` | Update address |
| DELETE | \`/addresses/{address}\` | Delete address |
| GET | \`/checkout\` | Recalculate checkout quote and create idempotency key |
| POST | \`/checkout\` | Place order using quote idempotency key |
| GET | \`/orders\` | Paginated order history |
| GET | \`/orders/{order}\` | Order details |
| GET | \`/orders/{order}/updates\` | Current status and status history |
| POST | \`/orders/{order}/pickup/confirm\` | Customer confirms ready pickup |
| GET | \`/notifications\` | Customer notification history |
| POST | \`/notifications/{notification}/read\` | Mark one notification read |
| POST | \`/notifications/read-all\` | Mark all notifications read |
| POST | \`/push/devices\` | Register FCM device token |
| DELETE | \`/push/devices\` | Disable FCM token on this account |

## Checkout lifecycle

1. The customer maintains a database-backed cart via \`CustomerCartService\`.
2. \`GET /checkout\` reloads the menu and settings, checks item availability, working hours, fulfillment options and server-side prices.
3. The resulting quote is stored in \`customer_checkout_snapshots\` for 30 minutes, keyed by customer and UUID idempotency key.
4. \`POST /checkout\` submits that key plus customer-selected fulfillment, address, requested time, payment-at-receipt method and comments.
5. Existing checkout validation, distance/zone checks, row locking, price snapshot and order creation remain in \`CustomerCheckoutController\`. The web checkout continues to use its existing session snapshot.
6. A successful order clears the matching customer cart and the one-time quote.

The app must never calculate authoritative prices or delivery fees. It must refresh the quote before submission and retain the same idempotency key when retrying a timed-out submission.

## Payment status

Cash/card on receipt and dine-in guest account follow the payment methods already supported by the organization settings. Online payment remains disabled until a real payment gateway adapter and merchant credentials are configured. iiko POS credentials are not a payment gateway and must not be used as one.

## Push delivery

The mobile app sends its FCM registration token to \`POST /push/devices\`. The server uses the existing Firebase Messaging service and CustomerDevice model. Production push delivery requires the Firebase service account values already consumed by that service in the server environment. Do not put service-account private keys in the mobile app or Git.

## Deployment

Run the new migration during normal Laravel deployment:

\`\`\`bash
php artisan migrate --force
php artisan route:list --path=api/mobile/v1
\`\`\`

Existing API and web routes are not renamed or replaced.
