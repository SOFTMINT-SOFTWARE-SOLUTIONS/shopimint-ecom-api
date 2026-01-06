<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <x-filament::section>
            <div class="text-sm text-gray-500">Total transactions</div>
            <div class="mt-1 text-2xl font-bold">
                {{ number_format($this->summaryStats['total_transactions'] ?? 0) }}
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500">Total paid amount</div>
            <div class="mt-1 text-2xl font-bold">
                {{ number_format($this->summaryStats['total_paid_amount'] ?? 0, 2) }} LKR
            </div>
            <div class="mt-1 text-xs text-gray-500">Status: captured / paid</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500">Unpaid / Failed count</div>
            <div class="mt-1 text-2xl font-bold">
                {{ number_format($this->summaryStats['unpaid_failed_count'] ?? 0) }}
            </div>
            <div class="mt-1 text-xs text-gray-500">All statuses except captured/paid</div>
        </x-filament::section>
    </div>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
