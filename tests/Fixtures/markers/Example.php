<?php

// Fixture scanned by new-here:list. Not autoloaded.

Action::make('a')->isNew('2026-10-01', 'Active.');
Action::make('b')->isNew(since: '2026-06-01', hint: 'Expired.');
Action::make('c')
    ->isNew(
        '2026-12-24',
        'Scheduled.',
    );

#[IsNew('2026-10-01')]
class Example {}

Action::make('d')->isNew('2026-08-01', 'Long announcement.', until: '2026-12-31');
