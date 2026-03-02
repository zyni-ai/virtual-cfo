<x-filament-panels::layout.base :livewire="null">
    <div class="fi-simple-layout flex min-h-screen flex-col items-center">
        <div class="fi-simple-main-ctn flex w-full flex-grow items-center justify-center">
            <main class="fi-simple-main my-16 w-full max-w-lg px-6">
                <div class="fi-simple-page rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h1 class="fi-simple-header-heading text-center text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                        {{ $heading }}
                    </h1>
                    @if(isset($subheading))
                        <p class="fi-simple-header-subheading mt-2 text-center text-sm text-gray-500 dark:text-gray-400">
                            {{ $subheading }}
                        </p>
                    @endif

                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</x-filament-panels::layout.base>
