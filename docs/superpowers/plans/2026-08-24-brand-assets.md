# SellerScope Brand Assets Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace text-based SellerScope branding and obsolete favicon references with the approved PNG assets from `public/images/brand`.

**Architecture:** Keep one shared React `BrandLogo` component for all layouts. Serve immutable public PNG assets directly from Laravel's public directory and declare explicit browser icon sizes in the root Blade template.

**Tech Stack:** Laravel 13, Inertia.js 3, React 19, TypeScript, Tailwind CSS 4, PHPUnit.

---

### Task 1: Browser icon metadata

**Files:**
- Modify: `resources/views/app.blade.php`
- Modify: `tests/Feature/Frontend/DemoPagesTest.php`

- [x] **Step 1: Add a failing favicon metadata assertion**

Add assertions to the existing page response test for:

```php
$response->assertSee('/images/brand/sellerscope-favicon-16.png', false);
$response->assertSee('/images/brand/sellerscope-favicon-32.png', false);
$response->assertSee('/images/brand/sellerscope-mark.png', false);
```

- [x] **Step 2: Run the focused test and verify it fails**

Run: `php artisan test tests/Feature/Frontend/DemoPagesTest.php`

Expected: failure because the Blade template still references `/favicon.ico`, `/favicon.svg`, and `/apple-touch-icon.png`.

- [x] **Step 3: Replace favicon links**

Use explicit PNG metadata:

```blade
<link rel="icon" type="image/png" sizes="16x16" href="/images/brand/sellerscope-favicon-16.png">
<link rel="icon" type="image/png" sizes="32x32" href="/images/brand/sellerscope-favicon-32.png">
<link rel="apple-touch-icon" href="/images/brand/sellerscope-mark.png">
```

- [x] **Step 4: Run the focused test and verify it passes**

Run: `php artisan test tests/Feature/Frontend/DemoPagesTest.php`

Expected: all tests pass.

### Task 2: Shared application logo

**Files:**
- Modify: `resources/js/components/brand-logo.tsx`

- [x] **Step 1: Replace generated text branding**

Preserve the existing Inertia `Link`, destination, accessible name, and focus behavior. Render:

```tsx
<img
    src={compact
        ? '/images/brand/sellerscope-mark.png'
        : '/images/brand/sellerscope-logo-horizontal.png'}
    alt=""
    width={compact ? 36 : 154}
    height={compact ? 36 : 36}
    className={compact ? 'size-9 object-contain' : 'h-9 w-auto object-contain'}
/>
```

- [x] **Step 2: Run static frontend checks**

Run: `npm run types:check && npm run lint:check && npm run format:check`

Expected: every command exits with code 0.

- [x] **Step 3: Run production build**

Run: `npm run build`

Expected: Vite production build exits with code 0.

### Task 3: Rendered browser verification

**Files:**
- No source file changes expected.

- [x] **Step 1: Rebuild local application containers**

Run: `docker compose up -d --build app queue scheduler`

Expected: application, queue, and scheduler containers start healthy.

- [x] **Step 2: Verify application shell**

Open `http://localhost:8000/demo/app` at desktop and compact-sidebar widths. Confirm the horizontal logo and compact mark load without distortion, clipping, or layout shift.

- [x] **Step 3: Verify authentication shell**

Open `http://localhost:8000/login` at desktop and mobile widths. Confirm the horizontal logo is visible and correctly sized.

- [x] **Step 4: Verify favicon and console health**

Confirm favicon asset responses succeed, the page is not blank, no framework overlay appears, and browser console has no relevant errors or warnings.
