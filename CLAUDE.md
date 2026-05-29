# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## About This System

BRT Accounting System — an internal business management platform covering:
- **Sales** (sales orders, AR invoices, receipts, sales returns)
- **Inventory** (purchase orders, receiving, stock management, accounts payable, stock returns)
- **Payroll** (payroll processing, employee loans, payroll templates, deductions/earnings)
- **Employee Profiling** (employee records, positions, salaries)
- **Customers & Contacts**
- **Accounting** (journal entries, revenue reports, expenses)

## Commands

```bash
# Frontend
npm run dev          # Start Vite dev server (hot module replacement)
npm run build        # Build production assets to public/build/

# Backend
php artisan serve    # Start Laravel development server
php artisan migrate  # Run database migrations
php artisan tinker   # Laravel REPL

# Testing
php artisan test                          # Run all tests
php artisan test --filter=TestClassName   # Run a single test class
./vendor/bin/pest                         # Run Pest directly
./vendor/bin/pint                         # Run Laravel Pint (code formatter)
```

**Important:** The app typically runs with `npm run dev` + `php artisan serve` simultaneously. When only `npm run build` is run without the dev server, a browser hard refresh (Cmd+Shift+R) is needed. If styles don't update while the dev server is running, restart it — Vite HMR can go stale with SCSS changes.

## Architecture

### Stack
Laravel 11 + Vue 3 + **Inertia.js** + Bootstrap 5 + SCSS. Real-time features via Laravel Reverb (WebSockets). PDF generation via DomPDF/FPDI. Exports via Maatwebsite Excel.

**This system uses Inertia.js** as the full-stack bridge — there is no separate API. Controllers return `Inertia::render('Modules/PageName', [...props])` instead of JSON. Vue pages in `resources/js/Pages/` receive server data as props. Navigation uses Inertia's `<Link>` component (no full page reloads). Runtime data fetching (filters, table rows) is done via `axios.get('?option=lists')` to avoid triggering a full Inertia visit.

### Request → Response Flow

```
Route (routes/web.php)
  → Controller (app/Http/Controllers/Modules/)
    → Service Class (app/Services/Modules/*Class.php)
      → Model + Eloquent
        → Inertia::render() or JSON response
```

Controllers are thin — all business logic lives in Service classes. The `HandlesTransaction` trait wraps service calls in `DB::transaction()` and standardizes the response shape: `{ data, message, info, status, errors }`.

Controllers use a `?option=` query parameter pattern to multiplex list/dashboard/detail calls through a single `index()` method rather than creating separate endpoints.

### Frontend Structure

```
resources/js/
  app.js                    # Entry point — registers Inertia, Vuex, global components
  Pages/Modules/            # One folder per business module
    Sales/
      Index.vue             # Page component (receives Inertia props)
      Components/           # Sub-components (tabs, sidebars, modals)
        SalesOrders/
          Modals/
          Tab/
  Shared/
    Layouts/Main.vue        # Default layout wrapping all pages
    State/store.js          # Vuex store (layout, notifications, todo)
    Components/             # Globally shared components
```

Pages use a **vertical sidebar tab** pattern (see `Inventory/Index.vue`, `Sales/Index.vue`): the page renders a collapsible left sidebar with tab buttons; clicking a tab conditionally renders the matching `*Tab.vue` component.

Data fetching is done via `axios.get('/<resource>?option=lists', ...)` inside Vue components (not via Inertia navigation). Initial dropdown data is passed as Inertia props on page load.

### SCSS / Styling

```
resources/scss/
  config/default/app.scss   # Main entry — imports Bootstrap then all component partials
  components/
    _library-modal.scss     # Global styles for ALL custom modals (.modal-overlay, .modal-container, .modal-header, .modal-body, .modal-footer, .close-btn)
    _library-index.scss     # Shared card header for module index pages (.library-card, .library-card-header)
    _inventory-index.scss   # Inventory-specific sidebar/tab layout
    _modal.scss             # Bootstrap modal overrides (imported AFTER library-modal — watch for cascade conflicts)
```

**Modal header design standard:**
- Background: `linear-gradient(to right, #cfe0d9 0%, #edf6f2 100%)`
- Border-bottom: `1px solid #c4d9d2`
- Heading color: `#16322e`; kicker/subtitle: `#6b8c85`
- `.close-btn`: white bg, `border: 1px solid #c4d9d2`, `border-radius: 10px`, `color: #16322e`
- `.modal-header-icon`: `rgba(61,141,122,0.12)` bg, `border-radius: 10px`, `color: #3d8d7a`

**CSS cascade rule — critical:** `_modal.scss` is imported at line 63 of `app.scss`, which is AFTER `_library-modal.scss` at line 45. Any `.modal-header` rule in `_modal.scss` or in a Vue `<style scoped>` block will silently override the library styles. If a modal header looks wrong (solid color instead of gradient, circle instead of rounded button), the cause is always a scoped or later-imported override winning the cascade.

**No scoped modal CSS rule:** Do not add `<style scoped>` blocks that redefine `.modal-header`, `.modal-body`, `.modal-footer`, or `.close-btn` in modal components. All modal chrome styling lives exclusively in `_library-modal.scss`. Only add scoped styles for content-specific elements inside the modal body.

Most modals use `.modal-overlay > .modal-container > .modal-header` and rely entirely on `_library-modal.scss`. A few custom-class modals (e.g., `.payable-modal-header`, `.receipts-header`) use non-standard class names and need direct per-file updates when the design changes.

**Sticky footer rule:** Action buttons MUST live in `.modal-footer`, never inside `.modal-body`. The correct modal structure requires `display:flex; flex-direction:column` on `.modal-container`, `flex:1 1 auto; min-height:0; overflow-y:auto` on `.modal-body`, and `flex-shrink:0` on `.modal-footer`. Putting buttons inside `.modal-body` causes them to scroll out of view and creates a dark gap at the bottom.

### Service & Utility Patterns

- **`DropdownClass`** — central service for populating all `<select>` / Multiselect options passed as Inertia props
- **`SeriesService`** — auto-increments document numbers (SO-, PO-, etc.) keyed by slug from a `series` DB table
- **`HandlesTransaction` trait** — use for any controller action that writes to the DB; returns a standard response envelope
- **`PrintClass`** — handles PDF generation and print views; routes accept `?option=print` to return a PDF response

### Key Business Modules

| Module | Controller | Service |
|---|---|---|
| Sales Orders | `SalesOrderController` | `SalesOrderClass` |
| Inventory / Receiving | `ReceivedStockController` | `ReceivedStockService` |
| AR Invoices | `ArInvoiceController` | `ArInvoiceClass` |
| Payroll | `PayrollController` | `PayrollClass` |
| Employees | `EmployeeController` | `EmployeeClass` |
| Customers | `CustomerController` | `CustomerClass` |

### Authentication & Authorization

Routes use the `auth`, `verified`, `is_active`, and `2fa` middleware stack. Role-based access is enforced via a `role:` middleware (e.g., `role:Administrator`). The `ListRole` model drives available roles; `ListStatus` drives status dropdowns across all modules.
