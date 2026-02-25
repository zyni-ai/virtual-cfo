<x-filament-panels::page>
    <x-filament::section heading="Company Information">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Company Name</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $company['name'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">GSTIN</dt>
                <dd class="mt-1 text-sm font-mono text-gray-900 dark:text-white">{{ $company['gstin'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Registered State</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $company['state'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">GST Registration Type</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $company['gst_registration_type'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Financial Year</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $company['financial_year'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Currency</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $company['currency'] ?? '—' }}</dd>
            </div>
        </dl>
    </x-filament::section>

    <x-filament::section heading="Configuration">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            These settings are configured via environment variables. To update, edit the <code class="font-mono text-xs">.env</code> file and set the relevant <code class="font-mono text-xs">COMPANY_*</code> variables.
        </p>
    </x-filament::section>
</x-filament-panels::page>
