# Grôncho API

**Description**: REST API for Grôncho, a money-free clothing swap app (swipe → match → exchange). Swipe on people, match, and exchange garments — no money involved.

<p align="center">
  <img alt="Laravel" src="https://img.shields.io/badge/LARAVEL-13-FF2D78?style=for-the-badge&labelColor=111110">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.3%2B-FF2D78?style=for-the-badge&labelColor=111110">
  <img alt="Passport" src="https://img.shields.io/badge/AUTH-PASSPORT-FF2D78?style=for-the-badge&labelColor=111110">
  <img alt="MySQL" src="https://img.shields.io/badge/DB-MYSQL-FF2D78?style=for-the-badge&labelColor=111110">
  <img alt="Tests" src="https://img.shields.io/badge/TESTS-163%20PASSING-FF2D78?style=for-the-badge&labelColor=111110">
</p>

<table align="center">
  <tr>
    <td align="center"><h3><code>034</code></h3><sub><code>ENDPOINTS</code></sub></td>
    <td align="center"><h3><code>163</code></h3><sub><code>TESTS</code></sub></td>
    <td align="center"><h3><code>002</code></h3><sub><code>ROLES</code></sub></td>
    <td align="center"><h3><code>008</code></h3><sub><code>MODELS</code></sub></td>
    <td align="center"><h3><code>021</code></h3><sub><code>BRANCHES</code></sub></td>
  </tr>
</table>

It is the Sprint 4 MVC project converted into a pure API: token-based authentication with Laravel Passport, two differentiated roles (`user` / `admin`), a full permission layer, business logic that goes well beyond CRUD, and a functional test suite written with TDD.

The front-end is a separate project that consumes this API.

## Index

| | | | |
|---|---|---|---|
| `001` [Requirements](#001--exercise-requirements) | `002` [Technologies](#002--technologies) | `003` [Data model](#003--data-model) | `004` [Roles](#004--roles-and-permissions) |
| `005` [Installation](#005--installation) | `006` [Demo](#006--demo) | `007` [Authentication](#007--authentication) | `008` [Conventions](#008--api-conventions) |
| `009` [Endpoints](#009--endpoints) | `010` [Business rules](#010--business-rules) | `011` [Demo data](#011--demo-data) | `012` [Testing](#012--testing) |
| `013` [Structure](#013--project-structure) | `014` [Git workflow](#014--git-workflow) | `015` [Design](#015--design-decisions-and-good-practices) | |

## 001 — Exercise requirements

| Requirement | How it is met |
|---|---|
| At least 2 maintainable resources | Universes, Items and Exchanges have full CRUD (plus Items/Universes image management, Swipes and Matches) |
| User management with Passport | Register, login and logout issue and revoke Passport personal access tokens; every other route requires one |
| At least 2 differentiated roles | `user` and `admin`, enforced with an `admin` middleware and Laravel Policies per resource |
| Logic more complex than a simple CRUD | Mutual-like matching, user and item rankings, give/receive balance rule, exchange state machine, item locking and ownership transfer |
| Functional tests | 163 tests developed with TDD (test written and seen failing before the code that makes it pass) |
| Access control on every URL | All routes except `register` and `login` sit behind `auth:api`; ownership and role rules are covered by tests |

## 002 — Technologies

- **Backend**: Laravel 13 (PHP 8.3+, developed on PHP 8.5)
- **Database**: MySQL
- **Authentication**: Laravel Passport (personal access tokens)
- **Authorization**: Laravel Policies (owner / admin per resource) and an `admin` middleware
- **Validation**: one FormRequest per endpoint
- **Business logic**: dedicated services (`MatchService`, `ExchangeService`, `RankingService`)
- **Fixed-state fields**: native PHP enums (`ItemStatus`, `MatchStatus`, `ExchangeType`, `ExchangeStatus`)
- **Testing**: PHPUnit 12, TDD
- **Code style**: Laravel Pint

## 003 — Data model

What the application registers:

All tables also carry `created_at` / `updated_at`; `users` additionally has `email_verified_at` and `remember_token`.

| Table | What it stores | Key constraints |
|---|---|---|
| `users` | Accounts with a role | `email` unique, `role` defaults to `user` |
| `universes` | A user's aesthetic profile (moodboard) | One per user (`user_id` unique) |
| `universe_images` / `item_images` | Photo paths | Deleted in cascade with their parent |
| `items` | Garments offered for swap | Owner changes when an exchange finishes |
| `swipes` | Like / dislike given to another user | Unique per (swiper, target) pair |
| `matches` | Mutual likes between two users | Unique pair; the code stores the smaller user id first |
| `exchanges` | A request for an item inside a match | Items are protected from deletion while referenced |
| `oauth_*` | Passport tokens and clients | Managed by Passport |

## 004 — Roles and permissions

| Action | Not logged in | `user` (owner / participant) | `user` (anyone else) | `admin` |
|---|---|---|---|---|
| Register / log in | yes | | | |
| See universes | | yes | yes | yes |
| Create universe (one per user) | | yes | | yes |
| Edit / delete a universe | | owner | 403 | yes |
| Upload universe or item images | | owner | 403 | 403 (admins only moderate) |
| Delete an image | | owner | 403 | yes |
| List items | | own only | own only | all |
| See / edit / delete an item | | owner | 403 | yes |
| Swipe | | yes | | yes |
| List matches | | own only | own only | own only |
| Close a match | | participant | 403 | yes |
| Create an exchange | | match participant | 422 | match participant |
| See an exchange | | requester or receiver | 403 | yes |
| Accept / reject / finish an exchange | | receiver only | 403 | yes |
| Cancel an exchange | | requester only | 403 | yes |
| User and item rankings | | yes | yes | yes |
| `/users` endpoints | 401 | 403 | 403 | yes |

An administrator can never be created through the API. The only way to get one is the seeder.

## 005 — Installation

Requirements: PHP 8.3+, Composer, MySQL.

1. Clone the repository:
   ```bash
   git clone https://github.com/DCdiegocortes/sprint05_api_Lv01_groncho.git
   cd sprint05_api_Lv01_groncho
   ```
2. Install dependencies:
   ```bash
   composer install
   ```
3. Create the environment file and the application key:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. Create an empty MySQL database named `groncho_api` and set your credentials in `.env`:

   | Variable | Default in `.env.example` | Notes |
   |---|---|---|
   | `APP_ENV` | `local` | Demo data is only seeded in `local` and `testing` |
   | `APP_URL` | `http://localhost:8000` | Used to build public image URLs |
   | `DB_CONNECTION` | `mysql` | |
   | `DB_HOST` / `DB_PORT` | `127.0.0.1` / `3306` | |
   | `DB_DATABASE` | `groncho_api` | Create it before migrating |
   | `DB_USERNAME` / `DB_PASSWORD` | `root` / *(empty)* | Change to your local credentials |

5. Run the migrations:
   ```bash
   php artisan migrate
   ```
6. Generate Passport's encryption keys and create its personal access client (required to issue tokens):
   ```bash
   php artisan passport:keys
   php artisan passport:client --personal --name="Groncho Personal Access Client" --no-interaction
   ```
7. Seed the administrator and the demo data (see [Demo data](#011--demo-data)):
   ```bash
   php artisan db:seed
   ```
8. (Optional) Expose uploaded images through a public URL:
   ```bash
   php artisan storage:link
   ```
9. Start the server. The API is served at `http://127.0.0.1:8000/api`:
   ```bash
   php artisan serve
   ```

## 006 — Demo

The API has no interface of its own, so the demo is a quick request flow with the seeded accounts.

**Quick flow** (after following the installation steps):

```bash
# 1. Log in as a demo user, then copy the token from the response
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"ana@groncho.com","password":"password"}'

export TOKEN="paste-the-token-here"

# 2. List her matches
curl http://127.0.0.1:8000/api/matches \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"

# 3. See the user ranking
curl http://127.0.0.1:8000/api/users/ranking \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

## 007 — Authentication

The API uses **Laravel Passport personal access tokens**.

1. `POST /api/register` creates an account (the role is always `user`).
2. `POST /api/login` with email and password returns `{ "token": "..." }`.
3. Send the token on every other request:
   ```
   Authorization: Bearer {token}
   ```
4. `POST /api/logout` revokes the token; using it again returns `401`.

Tokens last about one year and there is no refresh endpoint: when a token expires or is revoked, log in again.

```bash
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"ana@groncho.com","password":"password"}'
```
```json
{ "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..." }
```

## 008 — API conventions

- **Always send `Accept: application/json`.** Without it, an unauthenticated request answers `500` instead of `401`.
- Bodies are JSON (`Content-Type: application/json`), except image uploads, which use `multipart/form-data` with an `images[]` field (max 5 MB per image).
- Fields are `snake_case`; dates are ISO 8601 in UTC; enums are uppercase strings.
- Lists are plain JSON arrays and have no pagination yet.

**Error responses** always carry a `message`:

| Status | Meaning | Example body |
|---|---|---|
| `401` | Missing, invalid or revoked token | `{"message": "Unauthenticated."}` |
| `401` | Wrong email or password on login | `{"message": "Invalid credentials"}` |
| `403` | Authenticated but not allowed | `{"message": "This action is unauthorized."}` (`{"message": "Forbidden"}` on `/users` routes for non-admins) |
| `404` | The record does not exist | `{"message": "No query results for model [...]"}` |
| `422` | Validation or business rule failed | see below |

```json
{
  "message": "This item does not belong to your match partner.",
  "errors": { "requested_item_id": ["This item does not belong to your match partner."] }
}
```

## 009 — Endpoints

34 endpoints. All of them require a token except `register` and `login`.

### Auth
| Method | Route | Who |
|---|---|---|
| POST | `/register` | Public |
| POST | `/login` | Public |
| POST | `/logout` | Authenticated |

### Profile
| Method | Route | Who |
|---|---|---|
| GET | `/profile` | Authenticated (own) |
| PUT | `/profile` | Authenticated (own) |

### Users (admin)
| Method | Route | Who |
|---|---|---|
| GET | `/users` | Admin |
| GET | `/users/{id}` | Admin |
| DELETE | `/users/{id}` | Admin |

### Universes
| Method | Route | Who |
|---|---|---|
| GET | `/universes` | Authenticated |
| GET | `/universes/{id}` | Authenticated |
| POST | `/universes` | Authenticated (creates their own) |
| PUT | `/universes/{id}` | Owner or admin |
| DELETE | `/universes/{id}` | Owner or admin |
| POST | `/universes/{id}/images` | Owner |
| DELETE | `/universes/{id}/images/{image}` | Owner or admin |

### Items
| Method | Route | Who |
|---|---|---|
| GET | `/items` | Own (user) / all (admin) |
| GET | `/items/{id}` | Owner or admin |
| POST | `/items` | Authenticated (creates their own) |
| PUT | `/items/{id}` | Owner or admin |
| DELETE | `/items/{id}` | Owner or admin |
| POST | `/items/{id}/images` | Owner |
| DELETE | `/items/{id}/images/{image}` | Owner or admin |

### Swipes and matches
| Method | Route | Who |
|---|---|---|
| POST | `/swipes` | Authenticated |
| GET | `/matches` | Authenticated (own) |
| DELETE | `/matches/{id}` | Participant or admin |

### Exchanges
| Method | Route | Who |
|---|---|---|
| GET | `/exchanges` | Participant (requester or item owner) / all (admin) |
| GET | `/exchanges/{id}` | Participant or admin |
| POST | `/exchanges` | Match participant |
| PUT | `/exchanges/{id}` | Whoever received the request, or admin |
| DELETE | `/exchanges/{id}` | Requester or admin (not once `FINISHED`) |

### Rankings
| Method | Route | Who |
|---|---|---|
| GET | `/users/ranking` | Authenticated |
| GET | `/users/ranking/best` | Authenticated |
| GET | `/users/ranking/worst` | Authenticated |
| GET | `/items/ranking` | Authenticated |

### Example: request an item

```bash
curl -X POST http://127.0.0.1:8000/api/exchanges \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"match_id": 1, "requested_item_id": 2, "type": "GIFT", "message": "Is it still available?"}'
```
```json
{
  "match_id": 1, "requester_id": 2, "requested_item_id": 2, "offered_item_id": null,
  "type": "GIFT", "status": "PENDING", "message": "Is it still available?", "id": 7
}
```

### Example: user ranking

```bash
curl http://127.0.0.1:8000/api/users/ranking \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```
```json
[
  { "user": { "id": 2, "name": "Ana", "email": "ana@groncho.com", "role": "user" }, "success_rate": 0.5 },
  { "user": { "id": 5, "name": "Dani", "email": "dani@groncho.com", "role": "user" }, "success_rate": 0.5 }
]
```

## 010 — Business rules

**Swipes and matches**
- You cannot swipe yourself, and you can swipe each person only once.
- When two users like each other, a match is created automatically with status `ACTIVE`. The two user ids are stored smaller-first, so a pair can never produce two matches, even if both likes arrive at the same time.
- Closing a match sets it to `CLOSED`; it is never deleted.

**Exchanges**
- It can only be created inside an `ACTIVE` match, for an `AVAILABLE` item that belongs to **your match partner**.
- `GIFT`: the requester receives the item for free (no offered item). `TRADE`: the requester must offer one of their own `AVAILABLE` items.
- **Give/receive balance**: if a user has received more than 2 items than they have given, creating a new exchange is blocked with `403` until they balance it out.
- **State machine**: `PENDING → ACCEPTED / REJECTED`, and `ACCEPTED → FINISHED`. Only the receiver (or an admin) can change the status; `REJECTED` and `FINISHED` are final.
- **Item lock**: accepting an exchange marks its items `UNAVAILABLE` and rejects every other pending request that targets or offers them. Cancelling an accepted exchange releases the items.
- **Ownership transfer**: when an exchange reaches `FINISHED`, a `TRADE` swaps both items and a `GIFT` moves only the requested one. The items become `AVAILABLE` again in their new owner's closet.

**Rankings**
- *User ranking*: percentage of the exchanges a user requested that reached `FINISHED`. Users who never requested anything are excluded.
- *Item ranking*: how many times each item was requested, whatever the outcome. An "acceptance rate" for items was rejected on purpose: an item can only be finished once, so the percentage would be inversely related to its demand.

**Fields controlled by the server**: `user_id`, `role` and `status` are never read from the request body.

## 011 — Demo data

For development and evaluation, one command loads accounts, universes, items, matches and exchanges in every status:

```bash
php artisan migrate:fresh --seed
php artisan passport:client --personal --name="Groncho Personal Access Client" --no-interaction
```

The second command is needed because `migrate:fresh` also wipes the Passport client (the encryption keys stay in `storage/`).

All demo accounts use the password **`password`**:

| Email | Role |
|---|---|
| `admin@groncho.com` | admin |
| `ana@groncho.com`, `beto@groncho.com`, `carla@groncho.com`, `dani@groncho.com`, `eli@groncho.com` | user |

What you will find:
- 4 matches (one of them `CLOSED`) and 6 exchanges: 2 `PENDING`, 1 `ACCEPTED`, 1 `REJECTED` and 2 `FINISHED` (a gift and a trade).
- Ana and Dani both request Beto's *Vintage denim jacket*: when Beto accepts one, the other is rejected automatically.
- The accepted exchange has its two items locked, and the finished ones have already changed owner.

The seeders are idempotent (running them twice does not duplicate anything). Demo data is only created when `APP_ENV` is `local` or `testing`; in any other environment, `php artisan db:seed` creates only the administrator.

> The demo accounts have a publicly known password. They are meant for local development and evaluation only.

## 012 — Testing

```bash
php artisan test
```

163 functional tests. Every endpoint is covered for the happy path, authentication (`401`), authorization (`403`) and its business rules. The suite runs on an in-memory SQLite database, so it never touches your MySQL data.

| Area | Tests |
|---|---|
| Auth (register, login/logout, admin seeder) | 13 |
| Profile | 7 |
| Users (admin) | 7 |
| Universes (create, view, edit, images) | 26 |
| Items (create, view, edit, images) | 30 |
| Swipes | 8 |
| Matches | 6 |
| Exchanges (create, view, status, delete, item lock) | 44 |
| Rankings | 8 |
| Demo data seeders | 12 |
| Laravel example tests | 2 |

**TDD workflow used in every branch**: write the test, run it and confirm it fails (red), write the minimum code to make it pass (green), then run the whole suite to confirm nothing else broke.

Code style is checked with Laravel Pint:

```bash
./vendor/bin/pint --test
```

## 013 — Project structure

```
app/
├── Enums/                        # Fixed values as native PHP enums
│   ├── ExchangeStatus.php        # PENDING / ACCEPTED / REJECTED / FINISHED
│   ├── ExchangeType.php          # TRADE / GIFT
│   ├── ItemStatus.php            # AVAILABLE / UNAVAILABLE
│   └── MatchStatus.php           # ACTIVE / CLOSED
├── Http/
│   ├── Controllers/              # One controller per resource, kept thin
│   │   ├── AuthController.php
│   │   ├── ProfileController.php
│   │   ├── UserController.php
│   │   ├── UniverseController.php / UniverseImageController.php
│   │   ├── ItemController.php / ItemImageController.php
│   │   ├── SwipeController.php
│   │   ├── MatchController.php
│   │   ├── ExchangeController.php
│   │   └── RankingController.php
│   ├── Middleware/
│   │   └── EnsureUserIsAdmin.php # 'admin' middleware
│   └── Requests/                 # Validation (and some authorization) per endpoint
│       └── Auth/  Profile/  Universes/  Items/  Swipes/  Exchanges/
├── Models/
│   ├── User.php
│   ├── Universe.php / UniverseImage.php
│   ├── Item.php / ItemImage.php
│   ├── Swipe.php
│   ├── MatchModel.php            # Not called "Match": `match` is a reserved word in PHP
│   └── Exchange.php
├── Policies/                     # Owner / participant / admin rules per resource
│   ├── UniversePolicy.php  ItemPolicy.php
│   └── MatchModelPolicy.php  ExchangePolicy.php
└── Services/                     # Business logic outside the controllers
    ├── MatchService.php          # Mutual-like detection and match creation
    ├── ExchangeService.php       # Balance rule, item lock / release, ownership transfer
    └── RankingService.php        # Both rankings

database/
├── migrations/                   # One table per resource, created in the branch that needs it
├── factories/                    # One per model, used by the tests
└── seeders/
    ├── DatabaseSeeder.php        # Admin always; demo data only in local / testing
    ├── AdminSeeder.php           # The only way an admin gets created
    └── UserSeeder.php  UniverseSeeder.php  ItemSeeder.php
        SwipeSeeder.php  MatchSeeder.php  ExchangeSeeder.php

routes/
└── api.php                       # All the API routes

tests/Feature/
├── Auth/  Profile/  Users/  Swipes/  Matches/  Rankings/  Seeders/
├── Universes/                    # Create, View, Edit, Images
├── Items/                        # Create, View, Edit, Images
└── Exchanges/                    # Create, View, Status, Delete, ItemLock
```

## 014 — Git workflow

The project follows **Gitflow**:

- `main`: initial project scaffold.
- `develop`: integration branch with all the delivered work. Every finished branch is merged here through a pull request.
- `feature/*`: one branch per capability a client would recognize ("a user can create their universe"), never per technical layer. Each carries its own migrations next to the logic that uses them.
- `fix/*`: corrections to behavior that already exists, named after the behavior being fixed.

| PR | Branch | What it adds |
|---|---|---|
| #1 | `feature/register` | `POST /register`, `role` column |
| #2 | `feature/login-logout` | Passport, login, logout, admin seeder |
| #3 | `feature/profile` | `GET/PUT /profile` |
| #4 | `feature/users-admin` | Admin user management, `admin` middleware |
| #5 | `feature/universe-create` | Create a universe |
| #6 | `feature/universe-view` | List and view universes |
| #7 | `feature/universe-edit` | Edit and delete a universe, `UniversePolicy` |
| #8 | `feature/universe-images` | Upload and moderate universe photos |
| #9 | `feature/item-create` | Publish an item, `ItemStatus` enum |
| #10 | `feature/item-view` | List and view items, `ItemPolicy` |
| #11 | `feature/item-edit` | Edit and delete an item |
| #12 | `feature/item-images` | Upload and moderate item photos |
| #13 | `feature/swipe` | Like / dislike, automatic match on mutual like |
| #14 | `feature/matches` | List and close matches |
| #15 | `feature/exchanges` | Exchange CRUD, balance rule, state machine, ownership transfer |
| #16 | `feature/rankings` | User and item rankings |
| #17 | `fix/exchange-request-limited-to-match-partner` | A requested item must belong to your match partner |
| #18 | `fix/no-exchanges-on-closed-match` | No exchange can be created on a closed match |
| #19 | `feature/item-locked-during-exchange` | Items are locked while committed to an accepted exchange |
| #20 | `fix/mutual-like-race-condition` | Two simultaneous likes never produce an error or a duplicate match |
| #21 | `feature/demo-data` | Demo seeders for local development and evaluation |

## 015 — Design decisions and good practices

- **Pure API**: no views. The front-end is a separate project.
- **Single responsibility**: controllers only coordinate. Validation lives in FormRequests, permissions in Policies and business rules in Services (SOLID / KISS).
- **DRY**: a policy rule is written once and reused; factories build test data for every model.
- **No magic values**: statuses and types are native PHP enums cast on the models.
- **Security**: every route except `register` and `login` requires a Passport token; `user_id`, `role` and `status` are decided by the server; administrators can only be created by a seeder; foreign keys and unique indexes protect the data in the database itself.

## Author

<p align="center"><code>GRÔNCHÔ</code> — Diego Cortes ·</p>
