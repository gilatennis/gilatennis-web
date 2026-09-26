# Phase 4: Tournament Registration + Duitku Payment

Full registration flow: tournament detail → multi-step form → Duitku payment → success/failure.

## Routes

```php
// routes/web.php
Route::prefix('tournaments')->group(function () {
    Route::get('{tournament:slug}', [TournamentController::class, 'show'])
        ->name('tournaments.show');

    Route::get('{tournament:slug}/register', [TournamentRegistrationController::class, 'create'])
        ->name('tournaments.register');

    Route::post('{tournament:slug}/register', [TournamentRegistrationController::class, 'store'])
        ->name('tournaments.register.store');

    Route::get('registration/{registration:confirmation_code}/success', [TournamentRegistrationController::class, 'success'])
        ->name('tournaments.register.success');

    Route::get('registration/{registration:confirmation_code}/failed', [TournamentRegistrationController::class, 'failed'])
        ->name('tournaments.register.failed');
});

// Duitku webhook (no CSRF, no Inertia)
Route::post('webhooks/duitku/callback', [DuitkuWebhookController::class, 'handle'])
    ->name('webhooks.duitku')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Duitku return URL (user redirected back after payment)
Route::get('payments/duitku/return', [DuitkuReturnController::class, 'handle'])
    ->name('payments.duitku.return');
```

---

## 4.1 — Tournament Detail Page

Reference: `docs/Gilatennis/register/reg-detail.jsx`

### Controller: `TournamentController@show`

```php
public function show(Tournament $tournament)
{
    return Inertia::render('Tournaments/Show', [
        'tournament' => $tournament->load([
            'prizes',
            'scheduleDays.items',
            'rules',
        ]),
        'registrationCount' => $tournament->registrations()->where('payment_status', 'paid')->count(),
    ]);
}
```

### Page: `resources/js/pages/Tournaments/Show.svelte`

Layout: `PublicLayout`

Sections:
1. **Eyebrow bar**: "Registration Open" badge, closes date, spots left
2. **Hero band**: 8/4 grid
   - Left: image placeholder with chips (surface, USTA-Sanctioned, Singles + Doubles) + title overlay
   - Right: sticky registration card (countdown timer, fee, stats grid, "Register Now" CTA)
3. **Three-col info strip**: Schedule | Rules & Format | Prizes
4. **Players strip**: avatar placeholders showing registered count
5. **FAQ accordion**: 4 items with expand/collapse (use Bits UI `Accordion`)
6. **Sticky mobile CTA**: fixed bottom bar on mobile with register button

### Bits UI Components
- `Accordion` for FAQ

### Countdown Timer
- Svelte reactive countdown using `$effect` interval
- Counts down to `tournament.registration_closes`

---

## 4.2 — Registration Form (Multi-Step)

Reference: `docs/Gilatennis/register/reg-form.jsx`

### Controller: `TournamentRegistrationController`

```php
public function create(Tournament $tournament)
{
    abort_if(! $tournament->divisions()->hasAvailableSpots()->exists(), 403, 'Tournament is sold out.');

    return Inertia::render('Tournaments/Register', [
        'tournament' => $tournament,
    ]);
}

public function store(TournamentRegistrationRequest $request, Tournament $tournament)
{
    // 1. Find or create guest user
    $user = User::firstOrCreate(
        ['email' => $request->email],
        ['name' => $request->name, 'is_guest' => true]
    );

    // 2. Create registration
    $registration = TournamentRegistration::create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'confirmation_code' => TournamentRegistration::generateConfirmationCode(),
        // ... all form fields
        'amount_cents' => $this->calculateAmount($request, $tournament),
        'payment_status' => 'pending',
    ]);

    // 3. Create Duitku invoice
    $duitku = app(DuitkuService::class);
    $invoice = $duitku->createInvoice($registration);

    // 4. Save payment URL + reference
    $registration->update([
        'payment_reference' => $invoice->reference,
        'payment_url' => $invoice->paymentUrl,
        'payment_status' => 'processing',
    ]);

    // 5. Redirect to Duitku payment page
    return Inertia::location($invoice->paymentUrl);
}
```

### Form Request: `TournamentRegistrationRequest`

```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255'],
        'phone' => ['required', 'string', 'max:50'],
        'date_of_birth' => ['required', 'date', 'before:today'],
        'rating_type' => ['required', 'in:UTR,ITN,USTA'],
        'rating_value' => ['required', 'numeric', 'min:1', 'max:16.5'],
        'event_type' => ['required', 'in:singles,doubles,both'],
        'find_partner' => ['boolean'],
        'partner_name' => ['required_if:find_partner,false', 'nullable', 'string'],
        'partner_email' => ['required_if:find_partner,false', 'nullable', 'email'],
        'tshirt_size' => ['required', 'in:XS,S,M,L,XL,XXL'],
        'dietary' => ['nullable', 'string', 'max:500'],
        'waiver_accepted' => ['required', 'accepted'],
    ];
}
```

### Page: `resources/js/pages/Tournaments/Register.svelte`

Layout: `PublicLayout`

Structure:
1. **Back bar**: "← Back to tournament" link
2. **Two-column layout** (8/4 grid on lg):
   - Left (col 4): Sticky order summary card (tournament image, name, venue, price breakdown)
   - Right (col 8): Multi-step form with `ProgressRail`

### Form Steps

**Step 1 — Player Info**:
- Full name, email, phone, date of birth
- Rating type (UTR/ITN/USTA radio) + rating value input
- All fields use Inertia `useForm` for state + validation

**Step 2 — Event Selection**:
- Event type radio cards (Singles $50, Doubles $25 add-on, Both $75)
- Doubles partner section (conditional): toggle "Find me a partner" or enter partner name/email
- T-shirt size selector
- Dietary restrictions textarea

**Step 3 — Review & Pay**:
- Summary table with edit links (clicking "Edit" returns to that step)
- Order summary (entry fee + add-ons + processing fee)
- Waiver checkbox (required)
- Email opt-in checkbox
- "Proceed to Payment" button — submits the full form via Inertia

### Client-Side Validation
Each step validates before allowing "Next":
- Step 1: name, email (format), phone, dob, rating required
- Step 2: event_type required
- Step 3: waiver must be checked

Server-side validation is authoritative (FormRequest).

### Inertia Form

```svelte
<script>
  import { useForm } from '@inertiajs/svelte';

  const form = useForm({
    name: '', email: '', phone: '', date_of_birth: '',
    rating_type: 'UTR', rating_value: '',
    event_type: 'singles', find_partner: false,
    partner_name: '', partner_email: '',
    tshirt_size: 'M', dietary: '',
    waiver_accepted: false,
  });

  function submit() {
    $form.post(route('tournaments.register.store', tournament.slug));
  }
</script>
```

---

## 4.3 — Duitku Payment Integration

### Service: `App\Services\DuitkuService`

Port the Go implementation to PHP:

```php
class DuitkuService
{
    public function __construct(
        private string $merchantCode,
        private string $apiKey,
        private string $apiUrl,
    ) {}

    public function createInvoice(TournamentRegistration $registration): DuitkuInvoiceResponse
    {
        $timestamp = (int)(microtime(true) * 1000);
        $signature = hash('sha256', $this->merchantCode . $timestamp . $this->apiKey);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-duitku-merchantcode' => $this->merchantCode,
            'x-duitku-signature' => $signature,
            'x-duitku-timestamp' => (string)$timestamp,
        ])->post($this->apiUrl . '/api/merchant/createInvoice', [
            'paymentAmount' => $registration->amount_cents, // Duitku uses full amount, not cents for IDR
            'merchantOrderId' => $registration->confirmation_code,
            'productDetails' => "Tournament Registration: {$registration->tournament->name}",
            'customerVaName' => $registration->name,
            'phoneNumber' => $registration->phone,
            'itemDetails' => [
                [
                    'name' => $registration->tournament->name . ' - ' . ucfirst($registration->event_type),
                    'price' => $registration->amount_cents,
                    'quantity' => 1,
                ],
            ],
            'customerDetail' => [
                'firstName' => explode(' ', $registration->name)[0],
                'lastName' => explode(' ', $registration->name, 2)[1] ?? '',
                'email' => $registration->email,
                'phoneNumber' => $registration->phone,
            ],
            'callbackUrl' => route('webhooks.duitku'),
            'returnUrl' => route('payments.duitku.return'),
            'expiryPeriod' => 60, // minutes
        ]);

        // ... handle response, return DTO
    }
}
```

### Config

```env
# .env
DUITKU_MERCHANT_CODE=your_merchant_code
DUITKU_API_KEY=your_api_key
DUITKU_API_URL=https://api-sandbox.duitku.com  # sandbox for dev
```

```php
// config/services.php
'duitku' => [
    'merchant_code' => env('DUITKU_MERCHANT_CODE'),
    'api_key' => env('DUITKU_API_KEY'),
    'api_url' => env('DUITKU_API_URL', 'https://api-sandbox.duitku.com'),
],
```

### Service Provider Registration

```php
// AppServiceProvider.php
$this->app->singleton(DuitkuService::class, function ($app) {
    return new DuitkuService(
        config('services.duitku.merchant_code'),
        config('services.duitku.api_key'),
        config('services.duitku.api_url'),
    );
});
```

### DTOs

```php
class DuitkuInvoiceResponse
{
    public function __construct(
        public string $merchantCode,
        public string $reference,
        public string $paymentUrl,
        public string $statusCode,
        public string $statusMessage,
    ) {}
}
```

---

## 4.4 — Webhook Handler

### Controller: `DuitkuWebhookController`

The callback endpoint (`POST /webhooks/duitku/callback`) is CSRF-exempt via
`bootstrap/app.php` → `validateCsrfTokens(except: [...])` (the old
`withoutMiddleware(['csrf'])` never matched in Laravel 11+, which caused Duitku
callbacks to be rejected with HTTP 419).

Verification and settlement:
- signature is accepted under **either** scheme:
  - `HMAC_SHA256(merchantCode . amount . merchantOrderId, merchantKey)` (per the docs), or
  - `md5(merchantCode . amount . merchantOrderId . merchantKey)` (legacy v1).
    Verified offline against a real sandbox callback: our merchant account sends
    **MD5**, despite the docs describing HMAC — so both are accepted, and the
    matched scheme is logged/audited. Comparison uses `hash_equals()`.
- optional callback IP allowlist via `DUITKU_CALLBACK_IPS` (sandbox callback came from `182.23.85.12`).
- callback `amount` must equal `registration.amount_cents / 100` (whole rupiah).
- settlement goes through the single idempotent `TournamentPaymentService`
  (`markPaid` / `markFailed`), which dispatches `PaymentCompleted` / `PaymentFailed`
  once and appends a `payment_events` audit row (incl. `signature_scheme`).
- already-paid registrations short-circuit with `already processed` and are not re-settled.
- returns JSON `{"status": "..."}`; Duitku expects HTTP 200.

> **Production incident root cause:** the webhook was blocked by CSRF (HTTP 419)
> before reaching the controller. The original MD5 signature was actually correct;
> the only fix required for settlement was exempting the callback path from CSRF.


There is no `spots_left` column; availability is derived from division capacity
(`Division::hasAvailableSpots()`), so the webhook does not mutate counts.

---

## 4.5 — Return URL Handler

After Duitku payment, the user is redirected to `returnUrl` (public `GET`).

### Controller: `DuitkuReturnController`

Per the Duitku POP docs the redirect only carries `merchantOrderId`, `reference`
and `resultCode`, has **no signature**, and the URL can be edited by the
customer — so it must never change payment status. The handler is read-only:
it records an audit row and reflects the settled status (success / failed /
pending). Settlement is owned exclusively by the signed webhook.

> **Resolved:** the previous implementation called `markAsPaid()` from this
> unauthenticated redirect (C1 payment-bypass). It was fixed by moving all
> settlement behind the verified webhook; forged `?resultCode=00` hits are inert
> and logged as warnings.

## 4.5b — Payment diagnostics

- Dedicated log channel `payments` → `storage/logs/payments.log` (daily) and stderr
  (`docker compose logs -f app`). Configured in `config/logging.php`.
- Every callback/return/override appends to the `payment_events` table
  (`source`, `raw_payload`, `signature_valid`, `result_code`, `status_before/after`, ip).
- Admin registrations list has a **Stuck** filter: unsettled (`pending`/`processing`)
  registrations older than 30 minutes — these are the "paid but not updated" candidates.
- Manual end-to-end sandbox test:
  `php artisan payments:sandbox-checkout` — creates a throwaway tournament +
  registration, creates a real Duitku sandbox invoice, prints the payment URL,
  then waits for the webhook and verifies the settlement + audit trail.
  Requires the app to be publicly reachable (the `dev.gilatennis.com` tunnel points
  at the local machine) and sandbox credentials in `.env`.


---

## 4.6 — Success / Failed Pages

Reference: `docs/Gilatennis/register/reg-result.jsx`

### Success Page: `resources/js/pages/Tournaments/RegistrationSuccess.svelte`
- Black background, white text
- Check circle icon in coral
- Confirmation code display
- Headline: "You're in. See you on the clay."
- Email confirmation notice
- "Add to Calendar" dropdown (Google, Apple, Outlook, .ics)
- "Share registration" button
- "Browse more tournaments" link
- What's next strip: Check-in time, Bracket release date, Stringing service

### Failed Page: `resources/js/pages/Tournaments/RegistrationFailed.svelte`
- Light gray background
- Alert icon in coral
- Error code display
- Headline: "Your card was declined."
- Diagnostic info block (monospace)
- "Try Again" button → redirects back to registration form
- "Use different payment method" button
- "Cancel registration" button
- Support email link

### Pending Page: `resources/js/pages/Tournaments/PaymentPending.svelte`
- Spinner + "Processing payment..." message
- Auto-polls registration status every 3 seconds using Inertia polling
- Redirects to success/failed when status resolves

---

## 4.7 — Guest User Flow

1. User fills out registration form (no login required)
2. On submit, `User::firstOrCreate` by email with `is_guest=true`
3. If email already exists as registered user → link registration to that user
4. If email already exists as guest → reuse same user record
5. Guest users can later "upgrade" by registering with password (Fortify registration checks for existing guest record)

### Fortify Customization

```php
// Actions/Fortify/CreateNewUser.php
// Check for existing guest user and upgrade
$existingGuest = User::where('email', $input['email'])
    ->where('is_guest', true)
    ->first();

if ($existingGuest) {
    $existingGuest->update([
        'name' => $input['name'],
        'password' => Hash::make($input['password']),
        'is_guest' => false,
    ]);
    return $existingGuest;
}
```

---

## Files Created

| File | Purpose |
|------|---------|
| `app/Http/Controllers/TournamentController.php` | Tournament detail page |
| `app/Http/Controllers/TournamentRegistrationController.php` | Registration create/store/success/failed |
| `app/Http/Controllers/DuitkuWebhookController.php` | Payment webhook |
| `app/Http/Controllers/DuitkuReturnController.php` | Post-payment redirect |
| `app/Http/Requests/TournamentRegistrationRequest.php` | Form validation |
| `app/Services/DuitkuService.php` | Duitku API client |
| `app/DTOs/DuitkuInvoiceResponse.php` | Response DTO |
| `app/Events/PaymentCompleted.php` | Event |
| `app/Events/PaymentFailed.php` | Event |
| `resources/js/pages/Tournaments/Show.svelte` | Detail page |
| `resources/js/pages/Tournaments/Register.svelte` | Registration form |
| `resources/js/pages/Tournaments/RegistrationSuccess.svelte` | Success page |
| `resources/js/pages/Tournaments/RegistrationFailed.svelte` | Failed page |
| `resources/js/pages/Tournaments/PaymentPending.svelte` | Pending page |

## Tests

### Feature Tests
```
tests/Feature/TournamentRegistrationTest.php
```

1. `test_tournament_detail_page_loads` — GET `/tournaments/{slug}` returns 200
2. `test_registration_form_loads_for_open_tournament` — GET `/tournaments/{slug}/register` returns 200
3. `test_registration_form_blocked_for_sold_out_tournament` — returns 403
4. `test_registration_creates_guest_user` — POST creates user with `is_guest=true`
5. `test_registration_reuses_existing_guest_user` — POST with same email reuses user
6. `test_registration_links_to_existing_registered_user` — POST with registered email links correctly
7. `test_registration_validates_required_fields` — POST with empty data returns validation errors
8. `test_registration_validates_email_format` — invalid email returns error
9. `test_registration_validates_rating_range` — out-of-range rating
10. `test_duitku_webhook_marks_payment_as_paid` — POST webhook with resultCode=00
11. `test_duitku_webhook_marks_payment_as_failed` — POST webhook with error code
12. `test_duitku_webhook_is_idempotent` — replayed webhook does not double-process
13. `test_success_page_loads_with_valid_code` — GET success page
14. `test_failed_page_loads_with_valid_code` — GET failed page
15. `test_guest_user_can_upgrade_to_registered` — Fortify registration upgrades guest

### Unit Tests
```
tests/Unit/DuitkuServiceTest.php
```

1. `test_signature_generation` — verify SHA-256 matches expected format
2. `test_invoice_payload_structure` — verify JSON payload matches Duitku spec
