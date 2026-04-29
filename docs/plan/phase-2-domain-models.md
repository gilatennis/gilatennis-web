# Phase 2: Domain Models

Eloquent models, migrations, factories, and seeders. All data is dummy/seeded for now — no admin CRUD.

## 2.1 — Tournaments

Reference: `docs/Gilatennis/register/reg-data.js`, `docs/Gilatennis/tournaments.jsx`

### Migration: `tournaments`
```
id                  bigint PK auto
slug                varchar(255) unique
name                varchar(255)
tagline             text nullable
dates               varchar(100)          -- "July 18 – 20, 2026"
starts_label        varchar(50)           -- "Sat 8:00 AM PT"
city                varchar(100)
venue               varchar(255) nullable
surface             enum('hard','clay','grass','indoor_hard')
draw_size           int unsigned
spots_left          int unsigned
fee_cents           int unsigned          -- store in cents
utr_min             decimal(4,1) nullable
utr_max             decimal(4,1) nullable
format              varchar(100)          -- "Single Elimination, best-of-3"
ball_type           varchar(100) nullable
registration_closes datetime nullable
is_featured         boolean default false
status              enum('draft','open','closed','completed') default 'open'
created_at          datetime
updated_at          datetime
```

### Related Tables

**`tournament_prizes`**
```
id                  bigint PK auto
tournament_id       bigint FK → tournaments
place               varchar(50)           -- "Champion", "Finalist"
value               varchar(255)          -- "$600 + Aero Pro X racket"
sort_order          int unsigned default 0
```

**`tournament_schedule_days`**
```
id                  bigint PK auto
tournament_id       bigint FK → tournaments
day_label           varchar(50)           -- "Fri Jul 17"
sort_order          int unsigned default 0
```

**`tournament_schedule_items`**
```
id                  bigint PK auto
schedule_day_id     bigint FK → tournament_schedule_days
label               varchar(255)          -- "R32 8:00 AM"
sort_order          int unsigned default 0
```

**`tournament_rules`**
```
id                  bigint PK auto
tournament_id       bigint FK → tournaments
body                text
sort_order          int unsigned default 0
```

### Model: `Tournament`
- Relationships: `prizes()`, `scheduleDays()` (with `items()`), `rules()`, `registrations()`
- Scopes: `featured()`, `open()`, `upcoming()`
- Accessors: `fee` (converts cents → dollars), `spotsAvailable`, `isSoldOut`

### Factory & Seeder
- Factory generates realistic tournament data
- Seeder: 4 tournaments matching prototype data (NorCal Spring Open, Sunset Doubles Classic, Red Clay Challenge, Junior Champions Cup) + 1 "Gila Open: Summer Smash" for the registration flow

---

## 2.2 — Articles

Reference: `docs/Gilatennis/articles.jsx`

### Migration: `articles`
```
id                  bigint PK auto
slug                varchar(255) unique
type                enum('review','tip','strategy','fitness')
kicker              varchar(50)           -- "Gear Review", "Tennis Tip"
title               varchar(255)
excerpt             text
body                longtext nullable     -- full content (future)
image_alt           varchar(255) nullable -- placeholder label for now
author              varchar(100)
reading_minutes     int unsigned
is_published        boolean default true
published_at        datetime nullable
created_at          datetime
updated_at          datetime
```

### Related: `article_products` (display-only product references)
```
id                  bigint PK auto
article_id          bigint FK → articles
name                varchar(255)          -- "Pro Staff 97 v14"
price_label         varchar(20)           -- "$259"
original_price      varchar(20) nullable  -- "$289" (strikethrough)
```

### Model: `Article`
- Relationships: `products()`
- Scopes: `published()`, `ofType($type)`, `reviews()`, `tips()`
- Searchable (Phase 5): title, excerpt, author, kicker

### Factory & Seeder
- Seed 6 articles from prototype data

---

## 2.3 — Courts

Reference: `docs/Gilatennis/courts.jsx`

### Migration: `courts`
```
id                  bigint PK auto
slug                varchar(255) unique
name                varchar(255)
city                varchar(100)
surface             enum('hard','clay','grass','indoor_hard')
rating              decimal(2,1)
review_count        int unsigned default 0
content_type        enum('video','article')
duration            varchar(20)           -- "6:42" or "5 min"
image_alt           varchar(255) nullable
excerpt             text
is_featured         boolean default true
created_at          datetime
updated_at          datetime
```

### Related: `court_amenities`
```
id                  bigint PK auto
court_id            bigint FK → courts
name                varchar(50)           -- "Lights", "Pro Shop", "Stringing"
```

### Model: `Court`
- Relationships: `amenities()`
- Scopes: `featured()`
- Searchable (Phase 5): name, city, excerpt

### Factory & Seeder
- Seed 4 courts from prototype data

---

## 2.4 — Tournament Registrations

### Migration: `tournament_registrations`
```
id                  bigint PK auto
tournament_id       bigint FK → tournaments
user_id             bigint FK → users (nullable for pending)
confirmation_code   varchar(20) unique    -- "GO-2026-XXXX"

-- Player info (captured at registration time)
name                varchar(255)
email               varchar(255)
phone               varchar(50)
date_of_birth       date
rating_type         enum('UTR','ITN','USTA')
rating_value        decimal(4,1)

-- Event selection
event_type          enum('singles','doubles','both')
find_partner        boolean default false
partner_name        varchar(255) nullable
partner_email       varchar(255) nullable
tshirt_size         enum('XS','S','M','L','XL','XXL') default 'M'
dietary             text nullable

-- Payment
payment_status      enum('pending','processing','paid','failed','refunded') default 'pending'
payment_reference   varchar(255) nullable  -- Duitku reference
payment_url         varchar(500) nullable  -- Duitku payment URL
amount_cents        int unsigned
currency            varchar(3) default 'IDR'

-- Timestamps
paid_at             datetime nullable
created_at          datetime
updated_at          datetime
```

### Model: `TournamentRegistration`
- Relationships: `tournament()`, `user()`
- Events: `RegistrationCreated`, `PaymentCompleted`, `PaymentFailed`
- Methods: `markAsPaid()`, `markAsFailed()`, `generateConfirmationCode()`

### Factory
- States: `paid()`, `failed()`, `pending()`

---

## 2.5 — Newsletter Subscribers

### Migration: `newsletter_subscribers`
```
id                  bigint PK auto
email               varchar(255) unique
subscribed_at       datetime
unsubscribed_at     datetime nullable
created_at          datetime
updated_at          datetime
```

### Model: `NewsletterSubscriber`
- Simple model, no relationships
- Scope: `active()`

---

## 2.6 — User Model Extension

The existing `users` table already has Fortify 2FA columns. Extend for guest checkout:

### Migration: `add_guest_fields_to_users`
```
is_guest            boolean default false
```

### Model Changes
- Scope: `guests()`, `registered()`
- When guest checks out: create user with `is_guest=true`, no password. If they later register with same email, upgrade the record.

---

## Artisan Commands

```bash
php artisan make:model Tournament -mf
php artisan make:model TournamentPrize -m
php artisan make:model TournamentScheduleDay -m
php artisan make:model TournamentScheduleItem -m
php artisan make:model TournamentRule -m
php artisan make:model Article -mf
php artisan make:model ArticleProduct -m
php artisan make:model Court -mf
php artisan make:model CourtAmenity -m
php artisan make:model TournamentRegistration -mf
php artisan make:model NewsletterSubscriber -m
```

## Seeder Order

```php
// DatabaseSeeder.php
$this->call([
    TournamentSeeder::class,
    ArticleSeeder::class,
    CourtSeeder::class,
]);
```

## Tests

- Feature test per model: verify factory creates valid records
- Test relationships load correctly
- Test scopes return expected results
- Test `TournamentRegistration::generateConfirmationCode()` produces unique codes
