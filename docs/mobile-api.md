# DAODES Mobile API v1

Base URL: `https://daodes.space/api/mobile/v1`

All responses are JSON. Send `Accept: application/json` on every request.

## Health

`GET /health`

No authentication required. Returns API status and version.

## Sign in

`POST /auth/login`

JSON body:

```json
{
  "phone": "+79991234567",
  "password": "customer-password",
  "device_name": "DAODES Android"
}
```

The phone must already be verified and the account must have a password, matching the existing customer web sign-in rules. A successful response contains a Sanctum bearer token. Tokens expire after 30 days and are scoped to the `mobile` ability.

Use `Authorization: Bearer <access_token>` for protected endpoints. Store the token only in the operating system secure storage; do not put it in AsyncStorage, logs, analytics, or crash reports.

## Current customer

`GET /me` — bearer token required.

## Sign out

`POST /auth/logout` — bearer token required. Only the current device token is revoked.

## Organizations

`GET /organizations` — public list of active organizations with active menu content.

`GET /organizations/{organizationId}` — public organization catalog. Optional query parameter: `fulfillment=delivery|pickup|dine_in`.

The API returns only explicitly serialized public organization, section, and menu item fields. Fulfillment availability follows the same configuration checks as the existing customer catalog.

## Initial scope

This first API stage intentionally does not expose cart mutation, checkout, order history, addresses, payment processing, or push-device registration. Those operations must be connected through the existing customer cart and checkout domain logic, not reimplemented independently in the mobile client.
