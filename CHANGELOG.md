# Changelog

All notable changes to `new-here` will be documented in this file.

## v1.0.0 - 2026-10-08

First release.

- `->isNew()` on any Filament component: actions, action groups, table columns, filters, form fields, infolist entries, sections, tabs.
- `#[IsNew]` attribute on pages, resources and clusters: beacon on the sidebar item and a hint on the page heading.
- Seen once per user, stored in the database; works across browsers and devices.
- Respects permissions: hidden elements are never highlighted nor marked as seen.
- Newcomers don't get the past; announcements expire on their own; scheduling with a future date.
- Auto-registration on every panel; zero configuration.
- Laravel Boost guideline and skill; `new-here:install` writes them for projects without Boost.
- `new-here:list` to audit markers (`--expired`, `--json`).
- Translations: English, Brazilian Portuguese, Spanish. Dark mode and reduced motion.
