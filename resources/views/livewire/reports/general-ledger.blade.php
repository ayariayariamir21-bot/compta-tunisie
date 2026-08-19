<x-layouts::app :title="__('Grand Livre')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Grand Livre
                </h1>

                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    @if ($currentCompany && $currentFiscalYear)
                        {{ $currentCompany->name }} — Exercice {{ $currentFiscalYear->code }}
                    @else
                        Sélectionnez une société et un exercice comptable.
                    @endif
                </p>
            </div>
        </div>

        @if (! $currentCompany || ! $currentFiscalYear)
            <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                <h2 class="text-lg font-semibold">
                    Aucun contexte sélectionné
                </h2>

                <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                    Sélectionnez une société et un exercice dans le menu latéral.
                </p>
            </div>
        @else
            {{-- Filters --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label for="accountId" class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Compte</label>
                        <select
                            id="accountId"
                            wire:model.live="accountId"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            <option value="">Tous les comptes</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex-1">
                        <label for="journalId" class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Journal</label>
                        <select
                            id="journalId"
                            wire:model.live="journalId"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            <option value="">Tous les journaux</option>
                            @foreach ($journals as $journal)
                                <option value="{{ $journal->id }}">{{ $journal->code }} — {{ $journal->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="fromDate" class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Date début</label>
                        <input
                            id="fromDate"
                            type="date"
                            wire:model.live="fromDate"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                    </div>

                    <div>
                        <label for="toDate" class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Date fin</label>
                        <input
                            id="toDate"
                            type="date"
                            wire:model.live="toDate"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                    </div>

                    <div class="flex-1">
                        <label for="search" class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Recherche</label>
                        <input
                            id="search"
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Référence ou description..."
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                    </div>
                </div>
            </div>

            {{-- Ledger content --}}
            @if (empty($ledgerData))
                <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                    <h2 class="text-lg font-semibold">
                        Aucune écriture trouvée
                    </h2>

                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                        Aucun mouvement comptable ne correspond aux filtres sélectionnés.
                    </p>
                </div>
            @else
                @foreach ($ledgerData as $ledger)
                    <div class="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-neutral-900 dark:border-neutral-700">
                        {{-- Account header --}}
                        <div class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold">
                                    {{ $ledger['account']->code }} — {{ $ledger['account']->name }}
                                </h3>
                                <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ $ledger['account']->account_type->label() }}
                                </span>
                            </div>
                        </div>

                        <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                            <thead class="bg-neutral-50 dark:bg-neutral-800">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Date</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Journal</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">N° Écriture</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Réf.</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Description</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Débit</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Crédit</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Solde</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                                {{-- Opening balance --}}
                                @if ($ledger['opening_balance'] !== '0.000')
                                    <tr class="bg-neutral-50 dark:bg-neutral-800/50">
                                        <td colspan="5" class="px-4 py-2 text-sm font-medium text-neutral-500 dark:text-neutral-400">
                                            Solde d'ouverture
                                        </td>
                                        <td class="px-4 py-2 text-right text-sm font-medium">
                                            @php
                                                $ob = (float) $ledger['opening_balance'];
                                            @endphp
                                            {{ $ob > 0 ? number_format($ob, 3, ',', '.') : '' }}
                                        </td>
                                        <td class="px-4 py-2 text-right text-sm font-medium">
                                            {{ $ob < 0 ? number_format(abs($ob), 3, ',', '.') : '' }}
                                        </td>
                                        <td class="px-4 py-2 text-right text-sm font-semibold">
                                            {{ number_format((float) $ledger['opening_balance'], 3, ',', '.') }}
                                        </td>
                                    </tr>
                                @endif

                                {{-- Ledger lines --}}
                                @php
                                    $runningBalance = (float) $ledger['opening_balance'];
                                @endphp

                                @foreach ($ledger['lines'] as $line)
                                    @php
                                        $runningBalance += (float) $line->debit;
                                        $runningBalance -= (float) $line->credit;
                                    @endphp
                                    <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800">
                                        <td class="whitespace-nowrap px-4 py-2 text-sm">
                                            {{ $line->entry_date->format('d/m/Y') }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2 text-sm">
                                            {{ $line->journal_code }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2 text-sm font-mono">
                                            {{ $line->entry_number }}
                                        </td>
                                        <td class="px-4 py-2 text-sm text-neutral-500 dark:text-neutral-400">
                                            {{ $line->reference ?? '—' }}
                                        </td>
                                        <td class="px-4 py-2 text-sm">
                                            {{ $line->entry_description ?? $line->line_description ?? '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2 text-right text-sm">
                                            {{ (float) $line->debit > 0 ? number_format((float) $line->debit, 3, ',', '.') : '' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2 text-right text-sm">
                                            {{ (float) $line->credit > 0 ? number_format((float) $line->credit, 3, ',', '.') : '' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2 text-right text-sm font-medium">
                                            {{ number_format($runningBalance, 3, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach

                                {{-- Closing balance --}}
                                <tr class="border-t-2 border-neutral-300 bg-neutral-50 font-semibold dark:border-neutral-600 dark:bg-neutral-800">
                                    <td colspan="5" class="px-4 py-2 text-sm">
                                        Solde de clôture
                                    </td>
                                    <td class="px-4 py-2 text-right text-sm">
                                        @php
                                            $cb = (float) $ledger['closing_balance'];
                                        @endphp
                                        {{ $cb > 0 ? number_format($cb, 3, ',', '.') : '' }}
                                    </td>
                                    <td class="px-4 py-2 text-right text-sm">
                                        {{ $cb < 0 ? number_format(abs($cb), 3, ',', '.') : '' }}
                                    </td>
                                    <td class="px-4 py-2 text-right text-sm">
                                        {{ number_format($cb, 3, ',', '.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endforeach
            @endif
        @endif

    </div>
</x-layouts::app>
