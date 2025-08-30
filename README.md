# OpenWishlist

**OpenWishlist** is a transparent, self-hostable wishlist application.

- **No** hidden affiliate links or tracking
- **Simple** and user-friendly
- **Modern** stack: PHP 8.2+, SSR + Vanilla JS, REST API

## Status
🧭 Planning & early development. See `docs/adr` for architecture decisions.

## Goals (MVP)
- Session-based auth (browser)
- Multiple wishlists per user (public/private)
- Wishes with title, URL, price, notes
- Images per wish: either external **link** or **local download**
- Admin area to edit configuration (no `.env`)
- REST API for integration

## License
OpenWishlist is free software, licensed under **GNU AGPLv3** (or later).
If you run a modified version as a network service, you must provide users
with the corresponding source code (Affero clause). See [LICENSE](LICENSE).

## API Preview (Swagger UI)
We use [Swagger UI](https://swagger.io/tools/swagger-ui/) to preview the API described in `api/openapi.yml`.

**Requirements:** Docker.

**Run locally (recommended):**
~~~
./scripts/swagger-ui.sh
# or with a custom port:
./scripts/swagger-ui.sh 9090
~~~
This starts Swagger UI at **http://localhost:8081** (or your chosen port).

**Alternative (without the script):**
~~~
docker run --rm -p 8081:8080 \
  -e SWAGGER_JSON=/openapi.yml \
  -v "$(pwd)/api/openapi.yml":/openapi.yml \
  swaggerapi/swagger-ui
~~~

**Troubleshooting:**  
If you see the Petstore example, your spec wasn’t mounted. Verify the path and filename (`.yml` vs `.yaml`) and try again.

---

## OpenAPI Specification

- **File:** `api/openapi.yml`  
- **Standard:** OpenAPI 3.0.3  
- **Covers:** endpoints, parameters, request/response schemas, authentication (session cookie + CSRF header), error format (Problem+JSON), pagination/sorting.

**Contract-first workflow:**  
Propose API changes by updating `api/openapi.yml` in a PR first. Once agreed, implement the backend/SSR changes to match the spec.

---

## Job System

OpenWishlist uses a robust database-backed job queue for background tasks like image processing.

### Job Worker
```bash
php bin/worker --verbose
# Process background jobs (default: max 10 jobs, 30 seconds)

php bin/worker --max-jobs=20 --max-seconds=60 --type=image.fetch
# Custom limits and job type
```

### Job Lifecycle
- **queued** → **processing** → **completed** (success)
- **queued** → **processing** → **failed** (after max attempts)
- **Zombie recovery**: Processing jobs older than 5 minutes are automatically reclaimed

### Features
- **Exponential backoff**: Failed jobs retry with increasing delays (2min, 4min, 8min, etc.)
- **Dead letter queue**: Jobs failing after max attempts are marked as `failed` (not deleted)
- **Zombie detection**: Crashed worker jobs are automatically recovered
- **Admin interface**: Monitor and manually trigger jobs at `/admin/jobs`

### Job Types
- `image.fetch`: Downloads and processes local images for wishes

---

## Development Workflow (short)

1. Create a feature branch.  
2. Update `api/openapi.yml` (and code as needed).  
3. Preview with Swagger UI (`./scripts/swagger-ui.sh`).  
4. Open a PR; CI must pass; merge to `main`.
