---
name: new-here
description: Announce new features inside a Filament panel with New Here (leonardo-max/new-here). Use whenever you add or change something a panel user will see — a new action or button, table column, filter, form field, infolist entry, section, tab, page, resource or cluster — so it gets a pulsing beacon and a one-time hint for each user. Also use to clean up expired `->isNew()` markers, to configure New Here, or when the user asks to "announce", "highlight", "show what's new" or "onboard users to" a feature.
---

# New Here — announcing new features in the panel

New Here draws a pulsing beacon and a short hint on the exact element that just shipped. Each user sees each hint once (stored in the database, table `new_here_seen`). A marker is shown from its `since` date until it expires (30 days by default), then it does nothing.

## When to mark something as new

Mark it when a panel user will **notice** the change:

- new action/button (header, row, bulk, inside an action group);
- new table column or filter;
- new form field, infolist entry, section or tab;
- new page, resource or cluster;
- an existing element whose behaviour changed in a way the user must learn (use a new `key` if it was marked before).

Do **not** mark refactors, internal fixes, renames with no visible effect, or anything behind a feature the current user cannot reach (permissions are respected automatically: hidden elements are never highlighted).

## How to mark

Use **today's date** for `since`. Write the hint for the end user, in the panel's language, one short sentence about the benefit — not about the implementation.

```php
use LeonardoMax\NewHere\Attributes\IsNew;

// Actions (header, row, bulk, inside ActionGroup — the group trigger gets the beacon)
Action::make('duplicate')
    ->isNew('2026-10-08', 'Duplicate an order with one click.');

// Table columns and filters
TextColumn::make('tracking_code')->isNew('2026-10-08', 'See the tracking code right in the list.');
SelectFilter::make('carrier')->isNew('2026-10-08', 'Filter orders by carrier.');

// Form fields, infolist entries, sections, tabs
TextInput::make('nickname')->isNew('2026-10-08', 'How the customer likes to be called.');
Section::make('Billing')->isNew('2026-10-08', 'All billing data in one place.');
Tab::make('Archived')->isNew('2026-10-08', 'Archived orders now have their own tab.');

// Whole page / resource / cluster
#[IsNew('2026-10-08', 'Upload a document once and publish it to many classes.')]
class DocumentResource extends Resource {}
```

Optional named arguments, for both `->isNew()` and `#[IsNew]`:

| Argument | Default | Use |
|---|---|---|
| `title` | component label | Heading of the hint. |
| `key` | page class + type + name | Stable id. Set it to re-announce a changed element (`key: 'export.v2'`) or to share one announcement across pages. |
| `until` | `since` + `expires_after_days` | Custom end of the announcement. |

A future `since` schedules the announcement: merge today, it appears on release day.

## Checking and cleaning up

```bash
php artisan new-here:list            # every marker: scheduled / active / expired
php artisan new-here:list --expired  # markers that no longer show
php artisan new-here:list --json     # machine-readable
```

Expired markers are harmless. Remove them opportunistically when editing the file; don't open a change only for that unless asked.

## Configuration (rarely needed)

The plugin registers itself on every panel. To customise, add it explicitly — this instance wins:

```php
use LeonardoMax\NewHere\NewHerePlugin;

$panel->plugin(
    NewHerePlugin::make()
        ->expiresAfterDays(45)
        ->maxPerPage(2)
        ->openFirstHintAutomatically(false)
        ->ignoreFeaturesOlderThanUser()   // default: newcomers don't get the past
        ->enabled(fn() => ! session('impersonating')),
);
```

Or publish `config/new-here.php` (`php artisan vendor:publish --tag=new-here-config`): `panels`, `expires_after_days`, `max_per_page`, `open_first_hint_automatically`, `ignore_features_older_than_user`, `table`.

## Testing a marked component

Render the page and assert the attribute is present (it is only rendered inside the announcement window):

```php
use Illuminate\Support\Facades\Date;

Date::setTestNow('2026-10-08');

livewire(ListOrders::class)
    ->assertSeeHtml('data-new-here=');
```
