<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4 mb-6">
        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-success-600 dark:text-success-400">{{ $stats['matched'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Matched</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-danger-600 dark:text-danger-400">{{ $stats['flagged'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Flagged</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-600 dark:text-gray-400">{{ $stats['unreconciled'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Unreconciled</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $stats['total_matches'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Matches</div>
            </div>
        </x-filament::section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
