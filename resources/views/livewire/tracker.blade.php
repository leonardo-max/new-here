<div>
    {{-- `$config` is view data from Tracker::render(), not component state. --}}
    @if (filled($config))
        <div
            wire:ignore
            x-load
            x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('new-here', 'leonardo-max/new-here') }}"
            x-data="newHere(@js($config))"
            class="nh-root"
            hidden
        ></div>
    @endif
</div>
