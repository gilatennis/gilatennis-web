# Duitku POP API — Fix Plan

## Problems Found

### 1. Wrong Endpoint

| | Current | Correct |
|---|---|---|
| URL | `/webapi/api/merchant/v2/inquiry` | `/api/merchant/createInvoice` |
| Sandbox base | (same base) | `https://api-sandbox.duitku.com` |
| Production base | (same base) | `https://api-prod.duitku.com` |

### 2. Wrong Authentication (Create Invoice)

Current code puts `signature` (MD5) and `merchantCode` in the **request body**. Docs require authentication via **HTTP headers**:

```
x-duitku-signature: SHA256(<MERCHANT_CODE> + <TIMESTAMP_JAKARTA_MS> + <API_KEY>)
x-duitku-timestamp: <UNIX_TIMESTAMP_MILLISECONDS_JAKARTA>
x-duitku-merchantcode: <MERCHANT_CODE>
```

- Hash algorithm: **SHA256**, not MD5
- Input: `merchantCode . timestamp . apiKey` (no order ID, no amount)
- Timestamp: Jakarta timezone (WIB / UTC+7), in **milliseconds**

### 3. Stale Body Fields

These fields must be **removed** from the request body:
- `signature` — goes in header only
- `merchantCode` — goes in header only

### 4. Callback Signature — Already Correct

Webhook (`DuitkuWebhookController`) uses `MD5(merchantCode + amount + merchantOrderId + apiKey)`. This matches the docs. No change needed there.

---

## Files to Change

### `app/Services/DuitkuService.php`

1. Generate `$timestamp` = current time in WIB milliseconds:
   ```php
   $timestamp = (string) now('Asia/Jakarta')->getTimestampMs();
   // or: (string) (int) (microtime(true) * 1000)  — UTC ms, WIB offset not needed for Unix ms
   // Docs say "Jakarta timezone" but UNIX ms is timezone-agnostic. Use: round(microtime(true) * 1000)
   ```
2. Compute signature:
   ```php
   $signature = hash('sha256', $this->merchantCode . $timestamp . $this->apiKey);
   ```
3. Set headers on the HTTP request:
   ```php
   ->withHeaders([
       'Content-Type'          => 'application/json',
       'Accept'                => 'application/json',
       'x-duitku-signature'    => $signature,
       'x-duitku-timestamp'    => $timestamp,
       'x-duitku-merchantcode' => $this->merchantCode,
   ])
   ```
4. Remove `signature` and `merchantCode` keys from `$payload`.
5. Change endpoint path from `/webapi/api/merchant/v2/inquiry` → `/api/merchant/createInvoice`.

### `config/services.php` / `.env`

Verify `DUITKU_API_URL` is set to the correct base:
- Sandbox: `https://api-sandbox.duitku.com`
- Production: `https://api-prod.duitku.com`

(No path suffix — service appends `/api/merchant/createInvoice`.)

### `app/Http/Controllers/DuitkuWebhookController.php`

No auth changes needed. But consider:
- Store `reference` (Duitku's transaction ID) on the registration record when paid — already done via `markAsPaid($request->reference)`. Good.
- Add `publisherOrderId` storage if needed for reconciliation (optional).

---

## Implementation Order

1. Fix `DuitkuService`: timestamp generation → SHA256 signature → headers → remove body fields → endpoint path
2. Update `.env` / `config/services.php` base URL if needed
3. Run existing tests: `php artisan test --compact --filter=Duitku`
4. Update / add feature tests covering the new header-based auth flow
5. Manual sandbox test end-to-end

---

## Notes

- The `DuitkuInvoiceResponse` DTO needs no changes.
- The `DuitkuReturnController` (redirect handler) is not covered by the docs fix — review separately if needed.
- Callback is `application/x-www-form-urlencoded` POST — keep current CSRF exception in `routes/web.php`.
