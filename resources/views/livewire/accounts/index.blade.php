<x-layouts::app :title="__('Plan comptable')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Plan comptable
                </h1>

                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    @if ($currentCompany && $currentFiscalYear)
                        Comptes de « {{ $currentCompany->name }} » — {{ $currentFiscalYear->name }}.
                    @else
                        Sélectionnez une société et un exercice pour voir le plan comptable.
                    @endif
                </p>
            </div>

            @if ($currentCompany && $currentFiscalYear)
                <div class="flex items-center gap-2">
                    <button
                        wire:click="initializeChart"
                        wire:confirm="Initialiser le plan comptable par défaut pour cet exercice ? Les comptes existants ne seront pas écrasés."
                        class="inline-flex items-center rounded-lg border border-neutral-200 px-3 py-2 text-sm font-medium transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                    >
                        Initialiser le plan
                    </button>

                    <a
                        href="{{ route('accounts.create') }}"
                        wire:navigate
                        class="inline-flex items-center justify-center rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200"
                    >
                        + Nouveau compte
                    </a>
                </div>
            @endif
        </div>

        {{-- Success message --}}
        @if (session()->has('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        @if (! $currentCompany || ! $currentFiscalYear)
            <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                <h2 class="text-lg font-semibold">
                    Aucune société ou exercice sélectionné
                </h2>

                <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                    Sélectionnez une société et un exercice dans le menu latéral.
                </p>
            </div>
        @else
            {{-- Filters --}}
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Rechercher par code ou nom..."
                    class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800 sm:w-72"
                >

                <select
                    wire:model.live="filterType"
                    class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                >
                    <option value="">Tous les types</option>
                    @foreach ($accountTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Account tree --}}
            @php
                $roots = $accounts->whereNull('parent_id');
                $byParent = $accounts->groupBy('parent_id');
            @endphp

            @if ($accounts->isEmpty())
                <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                    <h2 class="text-lg font-semibold">
                        Aucun compte
                    </h2>

                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                        Initialisez le plan comptable par défaut ou créez un compte manuellement.
                    </p>
                </div>
            @else
                <div class="space-y-1 rounded-xl border bg-white p-4 shadow-sm dark:bg-neutral-900 dark:border-neutral-700">
                    @foreach ($roots as $account)
                        @include('livewire.accounts._tree-row', [
                            'account' => $account,
                            'byParent' => $byParent,
                            'depth' => 0,
                        ])
                    @endforeach
                </div>
            @endif
        @endif

    </div>
</x-layouts::app>
