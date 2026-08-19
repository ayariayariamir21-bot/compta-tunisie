<x-layouts::app :title="__('Détail de l\'écriture')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Écriture {{ $journalEntry->entry_number }}</h1>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    @php $color = $journalEntry->status->color(); @endphp
                    <span class="inline-flex items-center rounded-full bg-{{ $color }}-100 px-2.5 py-0.5 text-xs font-medium text-{{ $color }}-800 dark:bg-{{ $color }}-900 dark:text-{{ $color }}-200">
                        {{ $journalEntry->status->label() }}
                    </span>
                </p>
            </div>

            <div class="flex items-center gap-2">
                @if ($journalEntry->isDraft() && Auth::user()->can('update', $journalEntry))
                    <a href="{{ route('journal-entries.edit', $journalEntry->id) }}" wire:navigate class="rounded-lg border border-neutral-200 px-3 py-2 text-sm font-medium transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800">Modifier</a>
                @endif
                <a href="{{ route('journal-entries.index') }}" wire:navigate class="rounded-lg border border-neutral-300 px-4 py-2.5 text-sm font-medium hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800">Retour</a>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <h2 class="text-lg font-semibold">Informations</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-medium text-neutral-500">Numéro</p>
                            <p class="mt-1 text-sm font-medium">{{ $journalEntry->entry_number }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-neutral-500">Date</p>
                            <p class="mt-1 text-sm">{{ $journalEntry->entry_date->format('d/m/Y') }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-neutral-500">Journal</p>
                            <p class="mt-1 text-sm">{{ $journalEntry->journal->code }} — {{ $journalEntry->journal->name }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-neutral-500">Période</p>
                            <p class="mt-1 text-sm">{{ $journalEntry->accountingPeriod->name }}</p>
                        </div>
                        @if ($journalEntry->reference)
                            <div>
                                <p class="text-xs font-medium text-neutral-500">Référence</p>
                                <p class="mt-1 text-sm">{{ $journalEntry->reference }}</p>
                            </div>
                        @endif
                        @if ($journalEntry->description)
                            <div class="sm:col-span-2">
                                <p class="text-xs font-medium text-neutral-500">Description</p>
                                <p class="mt-1 text-sm">{{ $journalEntry->description }}</p>
                            </div>
                        @endif
                        <div>
                            <p class="text-xs font-medium text-neutral-500">Créé par</p>
                            <p class="mt-1 text-sm">{{ $journalEntry->creator->name }}</p>
                        </div>
                        @if ($journalEntry->posted_at)
                            <div>
                                <p class="text-xs font-medium text-neutral-500">Comptabilisé le</p>
                                <p class="mt-1 text-sm">{{ $journalEntry->posted_at->format('d/m/Y H:i') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div>
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <h2 class="text-lg font-semibold">Totaux</h2>
                    <div class="mt-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-neutral-500">Total Débit</span>
                            <span class="text-sm font-medium">{{ number_format((float) $journalEntry->totalDebit(), 3, '.', ' ') }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-neutral-500">Total Crédit</span>
                            <span class="text-sm font-medium">{{ number_format((float) $journalEntry->totalCredit(), 3, '.', ' ') }}</span>
                        </div>
                        <hr class="dark:border-neutral-700">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">Équilibrée</span>
                            @if ($journalEntry->isBalanced())
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Oui</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Non</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
            <h2 class="text-lg font-semibold">Lignes</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                    <thead class="bg-neutral-50 dark:bg-neutral-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Compte</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Description</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Débit</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Crédit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach ($journalEntry->lines as $line)
                            <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800">
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium">{{ $line->account->code }} — {{ $line->account->name }}</td>
                                <td class="px-4 py-3 text-sm">{{ $line->description ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm">{{ $line->debit > 0 ? number_format((float) $line->debit, 3, '.', ' ') : '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm">{{ $line->credit > 0 ? number_format((float) $line->credit, 3, '.', ' ') : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-neutral-50 dark:bg-neutral-800">
                        <tr>
                            <td colspan="2" class="px-4 py-3 text-sm font-medium text-right">Totaux</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium">{{ number_format((float) $journalEntry->totalDebit(), 3, '.', ' ') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium">{{ number_format((float) $journalEntry->totalCredit(), 3, '.', ' ') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </div>
</x-layouts::app>
