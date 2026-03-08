# Page-Specific Onboarding Tours — Design

## Problem

The current onboarding tour only covers sidebar navigation from the dashboard. Users need guided tours on each page highlighting page-specific UI elements and explaining how the page fits into the overall workflow.

## Design

### Data Model

Replace the `toured_at` timestamp column on `users` with a `toured_pages` JSONB column:

```json
{"dashboard": true, "imported-files": true, "transactions": true}
```

A key's presence means the user completed that page's tour. No new migrations needed when adding pages.

### Tour Definitions

A PHP config file at `config/tours.php` maps page identifiers to their steps:

```php
return [
    'dashboard' => [
        ['title' => 'Your Dashboard', 'description' => '...', 'element' => null],
        ['title' => 'Stats Overview', 'description' => '...', 'element' => '[data-widget]'],
    ],
    'imported-files' => [
        // ...
    ],
];
```

Each page has:
1. An intro step (no element, `side: 'over'`) providing workflow context
2. Element steps highlighting specific UI controls

### Component Architecture

- **`OnboardingTour` Livewire component** — accepts a `pageId` prop, looks up steps from `config('tours')`, checks `toured_pages` for auto-trigger
- **`@script` directive** — registers `Alpine.data('onboardingTour')` once, loads Driver.js from CDN on demand
- **Header action** — each list page adds a "Page Tour" button to `getHeaderActions()` that dispatches a Livewire event to start the tour

### Auto-Trigger Flow

```
Page loads -> OnboardingTour receives pageId
  -> Check user->toured_pages[pageId]
    -> null: auto-start, on complete -> save pageId to toured_pages
    -> exists: do nothing (available via header action)

Header action clicked -> Livewire.dispatch('start-tour')
  -> OnboardingTour starts tour regardless of toured_pages state
```

### UX Decisions

- **No user menu item** — tour is triggered via a contextual header action on each page (discoverable, follows Filament conventions)
- **Tour content style** — each page starts with a "How" intro (workflow context), followed by "What" element steps (feature identification)
- **Auto-trigger once** — each page tour auto-starts on first visit only, tracked per-page in `toured_pages`

## Phase 1 — Core Workflow Pages

| Page ID | Page | Intro Context | Key Elements |
|---------|------|--------------|-------------|
| `dashboard` | Dashboard | Your overview — quick stats and recent activity | Stats widgets, recent imports, sidebar navigation |
| `imported-files` | Imported Files | Upload statements here to start the pipeline | Upload button, status column, file type filter |
| `transactions` | Transactions | Parsed transactions land here for mapping and export | Filters, AI match action, export button, account head column |
| `account-heads` | Account Heads | Your Tally chart of accounts | Tally XML import, hierarchy/parent column, active toggle |
| `head-mappings` | Head Mappings | Rules that auto-map transactions to account heads | Match type, pattern field, create rule action |
| `reconciliation` | Reconciliation | Match bank transactions against invoices | Status widget, match action, reconciliation status |

## Files Changed

| File | Change |
|------|--------|
| `config/tours.php` | New — tour step definitions per page |
| `app/Livewire/OnboardingTour.php` | Refactor — accept `pageId` prop, steps from config |
| `resources/views/livewire/onboarding-tour.blade.php` | Refactor — dynamic steps from config |
| `resources/views/livewire/onboarding-tour-hook.blade.php` | Remove — component rendered per-page instead |
| `app/Providers/Filament/AdminPanelProvider.php` | Remove userMenuItems and BODY_END render hook |
| Migration | Add `toured_pages` JSONB, drop `toured_at` |
| `app/Models/User.php` | Replace `toured_at` cast with `toured_pages` array cast |
| 6 List page files + Dashboard | Add header action + `@livewire('onboarding-tour', ['pageId' => '...'])` |
| `docs/guides/onboarding-tours.md` | New — guide for adding tours to new pages |
| Tests | Update existing, add per-page tour tests |

## Adding New Pages

See `docs/guides/onboarding-tours.md` for the step-by-step guide.
