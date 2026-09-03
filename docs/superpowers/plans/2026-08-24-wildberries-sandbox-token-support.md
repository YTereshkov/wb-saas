# Wildberries Sandbox Token Support Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow WB test tokens to connect through official sandbox hosts, import sandbox products/orders/sales, and finish synchronization with stocks explicitly unavailable.

**Architecture:** `WildberriesSandboxClient` extends the existing HTTP client so canonical DTOs, pagination, normalization, retries, and import code stay shared. Test tokens are detected from the JWT `t` claim; their stored external account ID is namespaced as `sandbox:<sid>` to prevent collision with a production account using the same seller ID. UI receives only an `environment` marker and the display-safe seller ID, never the token.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia 3, React 19, TypeScript, Laravel HTTP client, PHPUnit.

---

### Task 1: Sandbox token and HTTP contract

**Files:**
- Modify: `app/Modules/Wildberries/Support/WildberriesTokenInspector.php`
- Modify: `app/Modules/Wildberries/Clients/WildberriesHttpClient.php`
- Create: `app/Modules/Wildberries/Clients/WildberriesSandboxClient.php`
- Modify: `app/Modules/Wildberries/Clients/WildberriesHttpClientFactory.php`
- Modify: `config/sellerscope.php`
- Test: `tests/Unit/Wildberries/WildberriesHttpClientTest.php`

- [ ] Add failing tests proving `t: true` selects sandbox behavior, `/ping` uses `content-api-sandbox` and `statistics-api-sandbox`, no production host is contacted, and stocks return a permanent unsupported-resource error.
- [ ] Run the focused unit test and confirm failure because sandbox factory/client methods do not exist.
- [ ] Add `isTest()`, sandbox hosts, protected host/header seams in the shared HTTP client, and `WildberriesSandboxClient`.
- [ ] Run the focused unit test and confirm pass.

### Task 2: Connection and persisted resolution

**Files:**
- Modify: `app/Modules/SellerAccounts/Actions/VerifySellerAccountConnection.php`
- Modify: `app/Modules/SellerAccounts/Actions/UpdateSellerAccountCredential.php`
- Modify: `app/Modules/Wildberries/WildberriesClientResolver.php`
- Test: `tests/Feature/SellerAccounts/CabinetConnectionWizardTest.php`
- Test: `tests/Unit/Wildberries/WildberriesClientResolverTest.php`

- [ ] Add failing tests proving a write-enabled test token connects, stores permissions `products/orders/sales`, creates `sandbox:<sid>`, does not replace a production account with the same `sid`, and later resolves to `WildberriesSandboxClient`.
- [ ] Run focused tests and confirm expected failures.
- [ ] Select sandbox client before connection checks, skip production-only read-only validation for test tokens, namespace sandbox IDs, and resolve stored test credentials through sandbox client.
- [ ] Run focused tests and confirm pass.

### Task 3: Partial synchronization and UI state

**Files:**
- Modify: `app/Http/Controllers/CabinetConnectionController.php`
- Modify: `app/Modules/SellerAccounts/Queries/CabinetListQuery.php`
- Modify: `app/Modules/SellerAccounts/Queries/CabinetDetailsQuery.php`
- Modify: `app/Modules/Synchronization/Queries/InitialSyncStatusQuery.php`
- Modify: `resources/js/pages/settings/cabinets/connect/verification.tsx`
- Modify: `resources/js/pages/settings/cabinets/connect/loading.tsx`
- Modify: `resources/js/pages/settings/cabinets/index.tsx`
- Modify: `resources/js/pages/settings/cabinets/show.tsx`
- Test: `tests/Feature/Synchronization/WildberriesSynchronizationTest.php`

- [ ] Add a failing integration test proving sandbox products/orders/sales import from sandbox hosts, stocks are skipped, the run completes at 100%, and no production/analytics request is sent.
- [ ] Run the focused test and confirm expected failure.
- [ ] Expose `environment: sandbox`, strip the internal ID prefix from props, render a `Песочница WB` badge, and explain that test tokens are read/write while stocks are unavailable.
- [ ] Run feature tests, TypeScript check, and production build.

### Task 4: Restore deleted demo account

**Files:**
- No source changes required; use existing `VerifyDemoSellerAccountConnection` and `RunInitialSync` actions.

- [ ] Inspect the configured demo user and verify `Дом и уют` is absent.
- [ ] Invoke existing application actions in one targeted command without resetting DB or changing the user password.
- [ ] Verify account is active and demo product/order/sale/stock counts are populated.

### Task 5: Final verification

- [ ] Run focused backend tests, full backend test suite, PHPStan, frontend typecheck, and production build.
- [ ] Use in-app Browser to test token page, verification state, loading state, cabinet list, console, and screenshot at desktop viewport.
- [ ] Update `docs/integrations/wildberries-api.md` with sandbox limitations and supported hosts.
