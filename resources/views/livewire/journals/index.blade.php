<x-layouts::app :title="__('Journaux')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Journaux
                </h1>

                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    @if ($currentCompany && $currentFiscalYear)
                        Journaux de « {{ $currentCompany->name }} » — {{ $currentFiscalYear->name }}.
                    @else
                        Sélectionnez une société et un exercice pour voir les journaux.
                    @endif
                </p>
            </div>

            @if ($currentCompany && $currentFiscalYear)
            @can('create', [App\Models\Journal::class, $currentCompany])
                <div class="flex items-center gap-2">
                    <button
                        wire:click="initializeJournals"
                        wire:confirm="Initialiser les journaux par défaut pour cet exercice ? Les journaux existants ne seront pas écrasés."
                        class="inline-flex items-center rounded-lg border border-neutral-200 px-3 py-2 text-sm font-medium transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                    >
                        Initialiser les journaux
                    </button>

                    <a
                        href="{{ route('journals.create') }}"
                        wire:navigate
                        class="inline-flex items-center justify-center rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200"
                    >
                        + Nouveau journal
                    </a>
                </div>
            @endcan
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
                    @foreach ($journalTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Journals list --}}
            @if ($journals->isEmpty())
                <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                    <h2 class="text-lg font-semibold">
                        Aucun journal
                    </h2>

                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                        Initialisez les journaux par défaut ou créez un journal manuellement.
                    </p>
                </div>
            @else
                <div class="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-neutral-900 dark:border-neutral-700">
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                        <thead class="bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Code</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Nom</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">État</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($journals as $journal)
                                <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium">
                                        {{ $journal->code }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        {{ $journal->name }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        {{ $journal->type->label() }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        @if ($journal->is_active)
                                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200">
                                                Actif
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">
                                                Inactif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                        <div class="flex items-center justify-end gap-2">
                                            @if (Auth::user()->can('update', $journal))
                                                <a
                                                    href="{{ route('journals.edit', $journal->id) }}"
                                                    wire:navigate
                                                    class="rounded-md px-2 py-1 text-sm text-neutral-600 transition hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800"
                                                >
                                                    Modifier
                                                </a>
                                            @endif

                                            @if (Auth::user()->can('activate', $journal))
                                                <button
                                                    wire:click="toggleActive({{ $journal->id }})"
                                                    wire:confirm="{{ $journal->is_active ? 'Désactiver ce journal ?' : 'Activer ce journal ?' }}"
                                                    class="rounded-md px-2 py-1 text-sm transition hover:bg-neutral-100 dark:hover:bg-neutral-800 {{ $journal->is_active ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}"
                                                >
                                                    {{ $journal->is_active ? 'Désactiver' : 'Activer' }}
                                                </button>
                                            @endif

                                            @if (Auth::user()->can('delete', $journal))
                                                <button
                                                    wire:click="delete({{ $journal->id }})"
                                                    wire:confirm="Supprimer le journal « {{ $journal->name }} » ?"
                                                    class="rounded-md px-2 py-1 text-sm text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950"
                                                >
                                                    Supprimer
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

    </div>
</x-layouts::app>
