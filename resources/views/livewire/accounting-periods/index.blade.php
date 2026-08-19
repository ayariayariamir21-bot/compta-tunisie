<x-layouts::app :title="__('Périodes comptables')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Périodes comptables
                </h1>

                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    @if ($currentCompany && $currentFiscalYear)
                        Périodes de « {{ $currentCompany->name }} » — {{ $currentFiscalYear->name }}.
                    @else
                        Sélectionnez une société et un exercice pour gérer les périodes.
                    @endif
                </p>
            </div>
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
                    Sélectionnez une société et un exercice dans le menu latéral pour voir les périodes.
                </p>
            </div>
        @else
            <div class="space-y-3">

                @forelse ($periods as $period)
                    <div class="rounded-xl border bg-white p-5 shadow-sm dark:bg-neutral-900 {{ $period->is_open ? 'border-emerald-200 dark:border-emerald-800' : ($period->is_closed ? 'border-amber-200 dark:border-amber-800' : 'border-neutral-200 dark:border-neutral-700') }}">

                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h2 class="text-lg font-semibold">
                                            {{ $period->name }}
                                        </h2>

                                        @if ($period->is_open)
                                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                                Ouverte
                                            </span>
                                        @elseif ($period->is_closed)
                                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                                Clôturée
                                            </span>
                                        @else
                                            <span class="rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">
                                                Fermée
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mt-1 flex items-center gap-4 text-sm text-neutral-500 dark:text-neutral-400">
                                        <span>Code: {{ $period->code }}</span>
                                        <span>•</span>
                                        <span>{{ $period->start_date->format('d/m/Y') }} — {{ $period->end_date->format('d/m/Y') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                @if ($period->is_open && ! $period->is_closed)
                                    <button
                                        wire:click="close({{ $period->id }})"
                                        wire:confirm="Voulez-vous clôturer cette période ? Cette action est irréversible."
                                        class="inline-flex items-center rounded-lg border border-amber-200 px-3 py-2 text-sm font-medium text-amber-600 transition hover:bg-amber-50 dark:border-amber-900 dark:text-amber-400 dark:hover:bg-amber-950"
                                    >
                                        Clôturer
                                    </button>
                                @endif
                            </div>
                        </div>

                    </div>
                @empty

                    <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                        <h2 class="text-lg font-semibold">
                            Aucune période
                        </h2>

                        <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                            Les périodes sont automatiquement générées lors de la création d'un exercice.
                        </p>
                    </div>

                @endforelse

            </div>
        @endif

    </div>
</x-layouts::app>
