# OpenAPI Contract — CI Fail-Closed

- **Sumber**: `backend/config/routes.php` + `config/web_routes.php` → `scripts/generate-route-matrix.php` → `docs/ROUTE_MATRIX.json` → `docs/openapi.yaml` (canonical) + `docs/openapi-petugas.yaml`
- **CI gate** `tests/Contract/RouterOpenApiTest.php`: bandingkan router vs OpenAPI — gagal bila route undocumented, operasi OpenAPI tanpa route, duplicate `method+path`, handler hilang, atau response envelope berubah (`{success,data,error}`).
- **Sync job**: `php scripts/sync-openapi.php` regenerasi `openapi.yaml` dari `ROUTE_MATRIX.json` dengan security scheme `bearerAuth` (JWT), `cookieAuth` (session+CSRF), `pagination` (`page,per_page` max 100), `include_draft` boolean, `Idempotency-Key` header, `upload` `multipart/form-data`, `409` idempotency, `429` RateLimit.
