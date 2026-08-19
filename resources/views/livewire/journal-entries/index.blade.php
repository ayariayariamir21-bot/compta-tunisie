<x-layouts::app :title="__('Écritures comptables')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Écritures comptables
                </h1>

                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    @if ($currentCompany && $currentFiscalYear)
                        Écritures de « {{ $currentCompany->name }} » — {{ $currentFiscalYear->name }}.
                    @else
                        Sélectionnez une société et un exercice pour voir les écritures.
                    @endif
                </p>
            </div>

            @if ($currentCompany && $currentFiscalYear)
                <a
                    href="{{ route('journal-entries.create') }}"
                    wire:navigate
                    class="inline-flex items-center justify-center rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200"
                >
                    + Nouvelle écriture
                </a>
            @endif
        </div>

        @if (session()->has('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                {{ session('error') }}
            </div>
        @endif

        @if (! $currentCompany || ! $currentFiscalYear)
            <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                <h2 class="text-lg font-semibold">Aucune société ou exercice sélectionné</h2>
                <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">Sélectionnez une société et un exercice dans le menu latéral.</p>
            </div>
        @else
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Rechercher par numéro, référence ou description..."
                    class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800 sm:w-72"
                >

                <select wire:model.live="filterJournal" class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                    <option value="">Tous les journaux</option>
                    @foreach ($journals as $journal)
                        <option value="{{ $journal->id }}">{{ $journal->code }} — {{ $journal->name }}</option>
                    @endforeach
                </select>

                <select wire:model.live="filterStatus" class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                    <option value="">Tous les statuts</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>

            @if ($entries->isEmpty())
                <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                    <h2 class="text-lg font-semibold">Aucune écriture</h2>
                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">Créez une nouvelle écriture comptable.</p>
                </div>
            @else
                <div class="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-neutral-900 dark:border-neutral-700">
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                        <thead class="bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Numéro</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Journal</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Référence</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Débit</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Crédit</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Statut</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($entries as $entry)
                                <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium">
                                        <a href="{{ route('journal-entries.show', $entry->id) }}" wire:navigate class="text-blue-600 hover:underline dark:text-blue-400">
                                            {{ $entry->entry_number }}
                                        </a>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">{{ $entry->entry_date->format('d/m/Y') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">{{ $entry->journal->code }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $entry->reference ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm">{{ number_format((float) $entry->lines_sum_debit, 3, '.', ' ') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm">{{ number_format((float) $entry->lines_sum_credit, 3, '.', ' ') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        @php $color = $entry->status->color(); @endphp
                                        <span class="inline-flex items-center rounded-full bg-{{ $color }}-100 px-2.5 py-0.5 text-xs font-medium text-{{ $color }}-800 dark:bg-{{ $color }}-900 dark:text-{{ $color }}-200">
                                            {{ $entry->status->label() }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                        <div class="flex items-center justify-end gap-2">
                                            @if ($entry->isDraft() && Auth::user()->can('update', $entry))
                                                <a href="{{ route('journal-entries.edit', $entry->id) }}" wire:navigate class="rounded-md px-2 py-1 text-sm text-neutral-600 transition hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800">Modifier</a>
                                            @endif

                                            @if ($entry->isDraft() && Auth::user()->can('post', $entry))
                                                <button wire:click="post({{ $entry->id }})" wire:confirm="Comptabiliser cette écriture ?" class="rounded-md px-2 py-1 text-sm text-green-600 transition hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-950">Comptabiliser</button>
                                            @endif

                                            @if ($entry->isDraft() && Auth::user()->can('cancel', $entry))
                                                <button wire:click="cancel({{ $entry->id }})" wire:confirm="Annuler cette écriture ?" class="rounded-md px-2 py-1 text-sm text-amber-600 transition hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950">Annuler</button>
                                            @endif

                                            @if ($entry->isDraft() && Auth::user()->can('delete', $entry))
                                                <button wire:click="delete({{ $entry->id }})" wire:confirm="Supprimer cette écriture ?" class="rounded-md px-2 py-1 text-sm text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950">Supprimer</button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $entries->links() }}
                </div>
            @endif
        @endif

    </div>
</x-layouts::app>
