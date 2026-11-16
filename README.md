# Weeb: ify

## What are we
*Weebi: ify* is a web app developped to be any otaku's aid for finding the accessories or items needed for their empty spots in their collections.
We aim to be the anime *honey* that doesn't intend to use your persoanl info. 
We'll use a collection of anime that can be saved as favorite in which we'll use real time notifications of collectables of such to add that missing piece to your missing spot on that shelf you want to build as well as notice if current favs are airing or not. 
This is are way of giving back to the community that we grew up in and have had maany good moments with, just another way to connect with such favorites in your life.

## Teamates
- s4mmywils0n : Sever & Stack Setup/ configuring database / API set up

- Cy-EF0510 : Template configuration / Securing Endpoints / Implementing Product Service / User Entity / Spring Boot project skeleton

## Tech Stack
we are using PHP for our web app development, as well as Docker for our vm and PostgreSQL (hosted on Supabase) for our database

## Database: rebuild and reseed

The schema of the live database is in [`database/schema.sql`](database/schema.sql)
(PostgreSQL). It replaces the old MySQL `setup.sql` / `data.sql`, which no longer
matched the real tables.

The connection settings are read from `.env` in the project root (never committed):

```ini
host=...
port=5432
database=postgres
user=...
password=...
```

To rebuild an empty database, run these in order from the project root:

1. **Create the tables:** run `database/schema.sql` in the Supabase SQL editor
   (or `psql "<connection string>" -f database/schema.sql`).
2. **Load the anime:** `php database/db.php` fetches 800 anime from the Kitsu API
   into `anime`. It does nothing if the table already has data.
3. **Fill in the genres:** `php database/Category.php` asks Kitsu for each anime's
   categories and fills `anime.genres` (used by the category filter).
4. **Create a test user:** sign up on the `/register` page.

`database/Favs.php` is no longer needed: `schema.sql` already creates `favorites`.

### Migrations

Changes to an existing database are in [`database/migrations/`](database/migrations/),
numbered in the order to run them. Each has a `.down.sql` that undoes it.
`schema.sql` always includes every migration, so a database built from it needs none.

| # | File | What it does |
|---|---|---|
| 001 | `001_rename_anime_columns.sql` | Renames the `anime` columns with spaces to snake_case: `"age rating"` → `age_rating`, `"created at"` → `created_at`, `"end date"` → `end_date`, `"episode count"` → `episode_count`, `"popularity rank"` → `popularity_rank`, `"show type"` → `show_type`. **The code expects the new names, so run this on the Supabase database before using it.** |

To run one: paste it into the Supabase SQL editor, or
`psql "<connection string>" -f database/migrations/001_rename_anime_columns.sql`.
It is safe to run twice. The URL filters (`?showtype=TV`, `?agerating=PG`) keep their names.

**Known differences**, also noted in `schema.sql`:
- There is no `manga` table yet, so `/api.php/manga` and the `/manga` page return an error.
- `favorites` has no foreign keys to `user` or `anime`.
- `anime` has a Supabase policy named `TEMP FULL ACCESS` that lets anyone with the
  publishable key change anime through Supabase's REST API. Remove it in Build 3.

## API Documentation

Base URL: `http://localhost/Weeb-ify/api.php` (OpenAPI: [`openapi.yaml`](openapi.yaml))

| Route | Methods |
|---|---|
| `/anime`, `/manga` | `GET` (list), `POST` (create) |
| `/anime/{id}`, `/manga/{id}` | `GET`, `PUT`, `PATCH`, `DELETE` |

### Request pipeline (middleware)

Every request goes through the same steps, in this order ([`api.php`](api.php),
classes in [`app/Api/Middleware`](app/Api/Middleware)):

| # | Middleware | What it does | Can answer with |
|---|---|---|---|
| 1 | `RequestIdMiddleware` | Uses the client's `X-Request-Id` or makes one; sends it back in the `X-Request-Id` header and in every error | |
| 2 | `RequestLoggerMiddleware` | Logs method, path, status, time and format to `storage/logs/api.log` | |
| 3 | `CorsMiddleware` | CORS headers only for origins in `app/config/api.php` (or `cors_origins=` in `.env`); answers `OPTIONS` | `204` |
| 4 | `ErrorHandlerMiddleware` | Turns any error below it into a safe JSON error; real details go to the log under the request id | `400` `409` `500` |
| 5 | `ContentNegotiationMiddleware` | Picks the response format from `Accept` | `406` |
| 6 | `RouteMiddleware` | Known resource and id? Method allowed? (`Allow` header on 405) | `404` `405` |
| 7 | `JsonBodyMiddleware` | `POST`/`PUT`/`PATCH`: checks `Content-Type`, size (64 KB) and that the body is a JSON object | `400` `413` `415` |
| → | `ResourceController` | Create / read / update / delete | `200` `201` `400` `404` |

Authentication and authorization middleware are added in Build 3.

### Media types

| Direction | Supported | Header | Otherwise |
|---|---|---|---|
| Response | `application/json` (default), `application/xml` / `text/xml`, `application/yaml` / `application/x-yaml` / `text/yaml` | `Accept` (q-values respected; missing or `*/*` → JSON) | `406` |
| Request body | `application/json` only | `Content-Type` | `415` |

### Errors

Every error has the same shape (in the format that was asked for), and never contains
PHP warnings, SQL or stack traces:

```json
{ "error": "Record not found", "requestId": "3f2a9c1e0b7d4a55" }
```

To find out what went wrong, search `storage/logs/api.log` for the `requestId`.
The `storage/` folder is blocked from the web by `storage/.htaccess`.

