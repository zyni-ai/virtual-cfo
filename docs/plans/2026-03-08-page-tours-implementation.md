# Page-Specific Onboarding Tours — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the single dashboard-only onboarding tour with per-page tours that auto-trigger on first visit and can be replayed via a header action.

**Architecture:** Tour steps defined in `config/tours.php`, rendered by a page-aware `OnboardingTour` Livewire component that receives a `pageId` prop. Per-page completion tracked via `toured_pages` JSONB column on users table.

**Tech Stack:** Livewire v4, Alpine.js, Driver.js (CDN), Filament v5 header actions, PostgreSQL JSONB.

**Design doc:** `docs/plans/2026-03-08-page-tours-design.md`

---

### Task 1: Migration — Replace `toured_at` with `toured_pages`

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_replace_toured_at_with_toured_pages_on_users_table.php`
- Modify: `app/Models/User.php:30-51`

**Step 1: Create migration**

```bash
php artisan make:migration replace_toured_at_with_toured_pages_on_users_table --table=users --no-interaction
```

**Step 2: Write migration code**

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->jsonb('toured_pages')->nullable();
        $table->dropColumn('toured_at');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->timestampTz('toured_at')->nullable();
        $table->dropColumn('toured_pages');
    });
}
```

**Step 3: Update User model**

In `app/Models/User.php`:
- Replace `'toured_at'` with `'toured_pages'` in `$fillable` (line 35)
- Replace `'toured_at' => 'datetime'` with `'toured_pages' => 'array'` in `casts()` (line 49)

**Step 4: Run migration**

```bash
php artisan migrate
```

**Step 5: Commit**

```
feat(tours): replace toured_at with toured_pages JSONB column (#129)
```

---

### Task 2: Create `config/tours.php` with Phase 1 page definitions

**Files:**
- Create: `config/tours.php`

**Step 1: Create config file**

Before writing tour steps, inspect the actual Filament-rendered HTML to identify stable CSS selectors. Use Laravel Boost `search-docs` to check Filament v5 data attributes. Key selectors to use:

- Dashboard widgets: `.fi-wi-stats-overview` or the widget container
- Header actions: `.fi-header-actions`
- Table filters: `.fi-ta-filters`
- Table: `.fi-ta`
- Sidebar nav items: `[href*="slug"]`

Create `config/tours.php`:

```php
<?php

return [
    'dashboard' => [
        [
            'title' => 'Your Dashboard',
            'description' => 'This is your home base. It shows key stats and recent activity across all your financial data.',
            'element' => null,
        ],
        [
            'title' => 'Quick Stats',
            'description' => 'These cards show your totals at a glance — imports processed, transactions parsed, and mapping progress.',
            'element' => '.fi-wi-stats-overview',
        ],
        [
            'title' => 'Navigation',
            'description' => 'Use the sidebar to move between sections. The workflow flows: Import → Transactions → Map → Export.',
            'element' => '.fi-sidebar-nav',
        ],
    ],

    'imported-files' => [
        [
            'title' => 'Import Statements',
            'description' => 'This is where you upload bank statements, credit card statements, or invoices. The system parses them automatically using AI.',
            'element' => null,
        ],
        [
            'title' => 'Upload Button',
            'description' => 'Click here to upload a new statement (PDF, CSV, or XLSX). Select the statement type and account before uploading.',
            'element' => '.fi-header-actions',
        ],
        [
            'title' => 'Processing Status',
            'description' => 'Track the status of each import — pending, processing, completed, or failed. Click any row to view details.',
            'element' => '.fi-ta',
        ],
    ],

    'transactions' => [
        [
            'title' => 'Your Transactions',
            'description' => 'After importing a statement, parsed transactions appear here. This is where you map them to account heads and export to Tally.',
            'element' => null,
        ],
        [
            'title' => 'Transaction Stats',
            'description' => 'A quick summary of your transactions — total count, mapped vs unmapped, and amounts.',
            'element' => '.fi-wi-stats-overview',
        ],
        [
            'title' => 'Table & Filters',
            'description' => 'Filter transactions by date, bank, status, or mapping type. Use bulk actions to map or export multiple transactions at once.',
            'element' => '.fi-ta',
        ],
    ],

    'account-heads' => [
        [
            'title' => 'Account Heads',
            'description' => 'Your Tally chart of accounts. These are the categories transactions get mapped to before exporting.',
            'element' => null,
        ],
        [
            'title' => 'Import from Tally',
            'description' => 'Use this button to import your chart of accounts from a Tally XML master file. This populates all heads automatically.',
            'element' => '.fi-header-actions',
        ],
        [
            'title' => 'Head Hierarchy',
            'description' => 'Account heads can be nested under parents to match your Tally structure. The table shows the full hierarchy.',
            'element' => '.fi-ta',
        ],
    ],

    'head-mappings' => [
        [
            'title' => 'Mapping Rules',
            'description' => 'Rules automatically map transactions to account heads based on their description. Once set, rules apply to all future imports.',
            'element' => null,
        ],
        [
            'title' => 'Create a Rule',
            'description' => 'Click here to create a new mapping rule. You can also create rules directly from the Transactions page.',
            'element' => '.fi-header-actions',
        ],
        [
            'title' => 'Existing Rules',
            'description' => 'Each rule has a pattern, match type (contains/exact/regex), and target account head. Rules are applied in priority order.',
            'element' => '.fi-ta',
        ],
    ],

    'reconciliation' => [
        [
            'title' => 'Reconciliation',
            'description' => 'Match bank transactions against invoices to enrich your Tally exports with GST breakdowns and vendor details.',
            'element' => null,
        ],
        [
            'title' => 'Reconciliation Stats',
            'description' => 'See how many transactions are matched, unmatched, or pending review.',
            'element' => '.fi-wi-stats-overview',
        ],
        [
            'title' => 'Match Transactions',
            'description' => 'Review and match transactions here. Use the actions to approve matches or manually link entries.',
            'element' => '.fi-ta',
        ],
    ],
];
```

**Step 2: Commit**

```
feat(tours): add tour step definitions for 6 core pages (#129)
```

---

### Task 3: Refactor `OnboardingTour` Livewire component

**Files:**
- Modify: `app/Livewire/OnboardingTour.php`

**Step 1: Write failing tests**

Replace `tests/Feature/Filament/OnboardingTourTest.php` with:

```php
<?php

use App\Livewire\OnboardingTour;
use Livewire\Livewire;

describe('Onboarding Tour', function () {
    it('has toured_pages column on users table', function () {
        $user = asUser();

        expect($user->toured_pages)->toBeNull();
    });

    it('auto-triggers tour for unvisited page', function () {
        $user = asUser();

        Livewire::actingAs($user)
            ->test(OnboardingTour::class, ['pageId' => 'dashboard'])
            ->assertSet('showTour', true)
            ->assertSet('pageId', 'dashboard');
    });

    it('does not auto-trigger for visited page', function () {
        $user = asUser();
        $user->update(['toured_pages' => ['dashboard' => true]]);

        Livewire::actingAs($user)
            ->test(OnboardingTour::class, ['pageId' => 'dashboard'])
            ->assertSet('showTour', false);
    });

    it('marks page as toured on completion', function () {
        $user = asUser();

        Livewire::actingAs($user)
            ->test(OnboardingTour::class, ['pageId' => 'transactions'])
            ->call('completeTour');

        expect($user->fresh()->toured_pages)->toHaveKey('transactions');
    });

    it('preserves other pages when completing a tour', function () {
        $user = asUser();
        $user->update(['toured_pages' => ['dashboard' => true]]);

        Livewire::actingAs($user)
            ->test(OnboardingTour::class, ['pageId' => 'transactions'])
            ->call('completeTour');

        $pages = $user->fresh()->toured_pages;
        expect($pages)->toHaveKey('dashboard')
            ->and($pages)->toHaveKey('transactions');
    });

    it('can start tour on demand via event', function () {
        $user = asUser();
        $user->update(['toured_pages' => ['dashboard' => true]]);

        Livewire::actingAs($user)
            ->test(OnboardingTour::class, ['pageId' => 'dashboard'])
            ->assertSet('showTour', false)
            ->call('startTour')
            ->assertSet('showTour', true);
    });

    it('passes tour steps from config to the view', function () {
        $user = asUser();

        $component = Livewire::actingAs($user)
            ->test(OnboardingTour::class, ['pageId' => 'dashboard']);

        $steps = $component->get('steps');
        expect($steps)->toBeArray()
            ->and($steps)->not->toBeEmpty()
            ->and($steps[0])->toHaveKeys(['title', 'description', 'element']);
    });
});
```

**Step 2: Run tests — expect failures**

```bash
php artisan test --compact --filter=OnboardingTour
```

Expected: FAIL (component doesn't accept `pageId` yet, `toured_pages` column doesn't exist yet — migration from Task 1 must run first)

**Step 3: Refactor the Livewire component**

Rewrite `app/Livewire/OnboardingTour.php`:

```php
<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class OnboardingTour extends Component
{
    public string $pageId;

    public bool $showTour = false;

    /** @var array<int, array{title: string, description: string, element: string|null}> */
    public array $steps = [];

    public function mount(string $pageId): void
    {
        $this->pageId = $pageId;
        $this->steps = config("tours.{$pageId}", []);

        $touredPages = $this->user()->toured_pages ?? [];
        $this->showTour = ! isset($touredPages[$pageId]);
    }

    public function completeTour(): void
    {
        $user = $this->user();
        $touredPages = $user->toured_pages ?? [];
        $touredPages[$this->pageId] = true;
        $user->update(['toured_pages' => $touredPages]);
        $this->showTour = false;
    }

    #[On('start-tour')]
    public function startTour(): void
    {
        $this->showTour = true;
    }

    public function render(): View
    {
        return view('livewire.onboarding-tour');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
```

Key changes:
- Accepts `pageId` prop instead of global tour
- Reads steps from `config('tours.{pageId}')`
- Checks `toured_pages[pageId]` instead of `toured_at`
- `completeTour()` merges into existing `toured_pages` (preserves other pages)
- Renamed `restartTour` → `startTour` with `#[On('start-tour')]` event

**Step 4: Run tests — expect pass**

```bash
php artisan test --compact --filter=OnboardingTour
```

**Step 5: Commit**

```
feat(tours): refactor OnboardingTour to accept pageId and per-page tracking (#129)
```

---

### Task 4: Refactor Blade template for dynamic steps

**Files:**
- Modify: `resources/views/livewire/onboarding-tour.blade.php`

**Step 1: Rewrite the template**

Replace the hardcoded steps with a dynamic approach that reads from the `$steps` property:

```blade
<div>
    @if($showTour)
        <div
            x-data="onboardingTour($wire)"
            x-init="$nextTick(() => startTour())"
        ></div>
    @endif
</div>

@script
<script>
    Alpine.data('onboardingTour', (wire) => ({
        async startTour() {
            if (window.driver?.js?.driver) {
                this.runTour();
                return;
            }

            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css';
            document.head.appendChild(link);

            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.iife.js';
            script.onload = () => this.runTour();
            document.head.appendChild(script);
        },
        runTour() {
            const driverFn = window.driver.js.driver;
            const rawSteps = wire.$get('steps');

            const steps = rawSteps.map(step => ({
                element: step.element || undefined,
                popover: {
                    title: step.title,
                    description: step.description,
                    side: step.element ? 'bottom' : 'over',
                    align: step.element ? 'start' : 'center',
                },
            }));

            const tourDriver = driverFn({
                showProgress: true,
                animate: true,
                allowClose: true,
                overlayColor: 'rgba(0, 0, 0, 0.6)',
                steps: steps,
                onDestroyStarted: () => {
                    tourDriver.destroy();
                    wire.completeTour();
                },
            });

            tourDriver.drive();
        },
    }));
</script>
@endscript
```

Key changes:
- Steps read from `wire.$get('steps')` (Livewire property) instead of hardcoded JS
- Maps the PHP array structure to Driver.js format
- Uses `side: 'bottom'` for element steps, `side: 'over'` for intro steps

**Step 2: Run tests**

```bash
php artisan test --compact --filter=OnboardingTour
```

**Step 3: Commit**

```
feat(tours): render dynamic tour steps from config (#129)
```

---

### Task 5: Remove global tour from AdminPanelProvider

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php:70-81`
- Delete: `resources/views/livewire/onboarding-tour-hook.blade.php`

**Step 1: Remove userMenuItems and renderHook**

In `AdminPanelProvider.php`, remove lines 70-81:
- Remove `->userMenuItems([...])` block
- Remove `->renderHook(PanelsRenderHook::BODY_END, ...)` block
- Remove unused imports: `Filament\Actions\Action`, `Filament\View\PanelsRenderHook`, `Illuminate\Contracts\View\View`

**Step 2: Delete the hook blade file**

```bash
rm resources/views/livewire/onboarding-tour-hook.blade.php
```

**Step 3: Run tests to verify nothing breaks**

```bash
php artisan test --compact --filter="OnboardingTour|ContextualHelp"
```

**Step 4: Commit**

```
refactor(tours): remove global tour from AdminPanelProvider (#129)
```

---

### Task 6: Add OnboardingTour to Dashboard page

**Files:**
- Modify: `app/Filament/Pages/Dashboard.php`

**Step 1: Write a failing test**

Add to `tests/Feature/Filament/OnboardingTourTest.php`:

```php
describe('Page Tour Integration', function () {
    it('renders tour component on dashboard', function () {
        $user = asUser();

        $this->actingAs($user)
            ->get(filament()->getUrl())
            ->assertSeeLivewire(OnboardingTour::class);
    });

    it('shows page tour header action on dashboard', function () {
        $user = asUser();

        $this->actingAs($user)
            ->get(filament()->getUrl())
            ->assertSeeText('Page Tour');
    });
});
```

Note: For the Dashboard page (which extends Filament's `BaseDashboard`), the Livewire component and header action are added differently than resource list pages. The Dashboard needs:
- Override `getHeaderActions()` to add the tour action
- A footer or content method to inject `@livewire('onboarding-tour', ['pageId' => 'dashboard'])`

**Step 2: Run tests — expect failure**

```bash
php artisan test --compact --filter="Page Tour Integration"
```

**Step 3: Implement — modify Dashboard.php**

```php
<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    public function getHeading(): string
    {
        return 'Welcome, '.Auth::user()->name.'!';
    }

    public function getSubheading(): ?string
    {
        return 'Here\'s an overview of your financial data.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('page_tour')
                ->label('Page Tour')
                ->icon('heroicon-o-academic-cap')
                ->color('gray')
                ->extraAttributes([
                    'x-on:click.prevent' => "Livewire.dispatch('start-tour')",
                ]),
        ];
    }

    public function getFooter(): ?View
    {
        return view('livewire.page-tour-embed', ['pageId' => 'dashboard']);
    }
}
```

Create `resources/views/livewire/page-tour-embed.blade.php`:

```blade
@livewire('onboarding-tour', ['pageId' => $pageId])
```

This view is reusable — all pages will use it with different `$pageId` values.

**Step 4: Run tests — expect pass**

```bash
php artisan test --compact --filter="Page Tour Integration"
```

**Step 5: Commit**

```
feat(tours): add page-specific tour to Dashboard (#129)
```

---

### Task 7: Add OnboardingTour to resource list pages

**Files:**
- Modify: `app/Filament/Resources/ImportedFileResource/Pages/ListImportedFiles.php`
- Modify: `app/Filament/Resources/TransactionResource/Pages/ListTransactions.php`
- Modify: `app/Filament/Resources/AccountHeadResource/Pages/ListAccountHeads.php`
- Modify: `app/Filament/Resources/HeadMappingResource/Pages/ListHeadMappings.php`
- Modify: `app/Filament/Resources/ReconciliationResource/Pages/ListReconciliation.php`

**Step 1: Write failing tests**

Add to the `Page Tour Integration` describe block:

```php
use App\Filament\Resources\ImportedFileResource;
use App\Filament\Resources\TransactionResource;
use App\Filament\Resources\AccountHeadResource;
use App\Filament\Resources\HeadMappingResource;
use App\Filament\Resources\ReconciliationResource;

it('renders tour on imported files page', function () {
    asUser();

    livewire(ImportedFileResource\Pages\ListImportedFiles::class)
        ->assertSeeLivewire(OnboardingTour::class);
});

it('renders tour on transactions page', function () {
    asUser();

    livewire(TransactionResource\Pages\ListTransactions::class)
        ->assertSeeLivewire(OnboardingTour::class);
});

it('renders tour on account heads page', function () {
    asUser();

    livewire(AccountHeadResource\Pages\ListAccountHeads::class)
        ->assertSeeLivewire(OnboardingTour::class);
});

it('renders tour on head mappings page', function () {
    asUser();

    livewire(HeadMappingResource\Pages\ListHeadMappings::class)
        ->assertSeeLivewire(OnboardingTour::class);
});

it('renders tour on reconciliation page', function () {
    asUser();

    livewire(ReconciliationResource\Pages\ListReconciliation::class)
        ->assertSeeLivewire(OnboardingTour::class);
});
```

**Step 2: Run tests — expect failure**

```bash
php artisan test --compact --filter="Page Tour Integration"
```

**Step 3: Implement — add to each list page**

For Filament resource list pages, add the header action and a footer view. The pattern is identical for each page — only `$pageId` changes.

Example for `ListImportedFiles.php`:

```php
use Filament\Actions;
use Filament\Actions\Action;
use Illuminate\Contracts\View\View;

protected function getHeaderActions(): array
{
    return [
        Action::make('page_tour')
            ->label('Page Tour')
            ->icon('heroicon-o-academic-cap')
            ->color('gray')
            ->extraAttributes([
                'x-on:click.prevent' => "Livewire.dispatch('start-tour')",
            ]),
    ];
}

public function getFooter(): ?View
{
    return view('livewire.page-tour-embed', ['pageId' => 'imported-files']);
}
```

Repeat for each page with the appropriate `pageId`:
- `ListImportedFiles` → `'imported-files'`
- `ListTransactions` → `'transactions'`
- `ListAccountHeads` → `'account-heads'`
- `ListHeadMappings` → `'head-mappings'`
- `ListReconciliation` → `'reconciliation'`

**Important:** Pages that already have `getHeaderActions()` (ListAccountHeads, ListHeadMappings, ListImportedFiles) — ADD the tour action to the existing array. Do not replace existing actions.

**Step 4: Run tests — expect pass**

```bash
php artisan test --compact --filter="Page Tour Integration"
```

**Step 5: Commit**

```
feat(tours): add page-specific tours to 5 resource list pages (#129)
```

---

### Task 8: Update ContextualHelpTest and clean up old migration

**Files:**
- Modify: `tests/Feature/Filament/ContextualHelpTest.php` (if any tests reference `toured_at`)
- Delete: `database/migrations/2026_03_08_024931_add_toured_at_to_users_table.php`

**Step 1: Check for references to `toured_at` in tests**

Search for `toured_at` across all test files. The old `OnboardingTourTest.php` was already replaced in Task 3. Check `ContextualHelpTest.php` — it should be unaffected (uses `asUser()` only).

**Step 2: Delete old migration**

```bash
rm database/migrations/2026_03_08_024931_add_toured_at_to_users_table.php
```

**Step 3: Run full test suite**

```bash
php artisan test --compact --filter="OnboardingTour|ContextualHelp"
```

**Step 4: Run PHPStan**

```bash
vendor/bin/phpstan analyse
```

Fix any type errors (e.g., `toured_at` references in PHPStan baseline or other files).

**Step 5: Commit**

```
chore(tours): remove old toured_at migration and clean up references (#129)
```

---

### Task 9: Run `/simplify` and final verification

**Step 1: Run the laravel-simplifier**

Run `/simplify` on all modified files to check for code reuse, quality, and efficiency issues.

**Step 2: Run full test suite**

```bash
php artisan test --compact
```

Ensure all tests pass (ignore pre-existing failures documented in #133).

**Step 3: Run PHPStan**

```bash
vendor/bin/phpstan analyse
```

**Step 4: Commit any simplification fixes**

```
refactor(tours): simplify per code review (#129)
```

---

## Summary

| Task | Description | Commit Type |
|------|-------------|-------------|
| 1 | Migration: `toured_at` → `toured_pages` JSONB | `feat` |
| 2 | Create `config/tours.php` with 6 page definitions | `feat` |
| 3 | Refactor OnboardingTour component + tests | `feat` |
| 4 | Refactor Blade template for dynamic steps | `feat` |
| 5 | Remove global tour from AdminPanelProvider | `refactor` |
| 6 | Add tour to Dashboard page | `feat` |
| 7 | Add tour to 5 resource list pages | `feat` |
| 8 | Clean up old migration and references | `chore` |
| 9 | `/simplify` and final verification | `refactor` |

**Total: 9 tasks, ~9 commits**
