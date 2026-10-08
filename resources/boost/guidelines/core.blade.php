## New Here (leonardo-max/new-here)

This Filament panel uses New Here to show each user what is new right on the screen: a pulsing beacon and a short hint on the exact button, column, field or page that just shipped. Each user sees it once.

### Rule: announce what users will notice

Whenever you add something a panel user will see or use — a new action/button, table column, filter, form field, infolist entry, section, tab, or a whole new page/resource — mark it as new in the same change. Use today's date as `since` and write the hint for the end user, in the panel's language, describing the benefit in one short sentence.

Do not mark refactors, renames with no visible effect, internal fixes or anything the user cannot see.

### How

- Any Filament component: `->isNew(since, hint)`. Optional named arguments: `title` (defaults to the component label), `key` (stable id; defaults to page class + component type + name), `until` (custom end date).
- Whole page, resource or cluster: the `#[IsNew(since, hint)]` attribute on the class. The sidebar item gets a beacon and the page heading a hint on the first visit.
- A marker shows from `since` until it expires (30 days by default) and then does nothing. A future `since` schedules the announcement.

@verbatim
<code-snippet name="Marking components and pages as new" lang="php">
use LeonardoMax\NewHere\Attributes\IsNew;

Action::make('duplicate')
    ->isNew('2026-10-08', 'Duplicate an order with one click.');

TextColumn::make('tracking_code')
    ->isNew('2026-10-08', 'See the tracking code without opening the order.');

SelectFilter::make('carrier')->isNew('2026-10-08', 'Filter orders by carrier.');

#[IsNew('2026-10-08', 'Upload a document once and publish it to many classes.')]
class DocumentResource extends Resource {}
</code-snippet>
@endverbatim

### Housekeeping

- `php artisan new-here:list` lists every marker with its status (`scheduled`, `active`, `expired`).
- `php artisan new-here:list --expired` lists markers that no longer show. Remove them when touching those files; they are harmless if left.
