<div class="flex h-full w-full flex-1 flex-col gap-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Balance
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
                        <label for="accountType" class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Type de compte</label>
                        <select
                            id="accountType"
                            wire:model.live="accountType"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            <option value="">Tous les types</option>
                            @foreach ($accountTypes as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="includeZeroBalance"
                            type="checkbox"
                            wire:model.live="includeZeroBalance"
                            value="1"
                            class="h-4 w-4 rounded border-neutral-300"
                        >
                        <label for="includeZeroBalance" class="text-sm">Inclure soldes nuls</label>
                    </div>
                </div>
            </div>

            {{-- PDF export --}}
            <div class="flex justify-end">
                <flux:button
                    variant="outline"
                    href="{{ route('reports.trial-balance.pdf', array_filter([
                        'from_date' => $fromDate,
                        'to_date' => $toDate,
                        'account_type' => $accountType,
                        'include_zero_balance' => $includeZeroBalance,
                    ], fn ($value) => $value !== null && $value !== '')) }}"
                    target="_blank"
                >
                    <flux:icon name="arrow-down-tray" class="size-4" />
                    Exporter PDF
                </flux:button>
            </div>

            {{-- Balance status --}}
            @if ($trialBalance['is_balanced'])
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                    Balance vérifiée — Total débit = Total crédit
                </div>
            @else
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                    Écart détecté — Différence : {{ number_format((float) $trialBalance['difference'], 3, ',', '.') }}
                </div>
            @endif

            {{-- Balance table --}}
            @if ($trialBalance['accounts']->isEmpty())
                <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                    <h2 class="text-lg font-semibold">
                        Aucune écriture
                    </h2>

                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                        Aucune écriture comptable ne correspond aux filtres sélectionnés.
                    </p>
                </div>
            @else
                <div class="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-neutral-900 dark:border-neutral-700">
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                        <thead class="bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Code</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Nom du compte</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Débit</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Crédit</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Solde débit</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Solde crédit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($trialBalance['accounts'] as $account)
                                <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium">
                                        {{ $account->code }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        {{ $account->name }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                        {{ number_format((float) $account->total_debit, 3, ',', '.') }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                        {{ number_format((float) $account->total_credit, 3, ',', '.') }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium">
                                        {{ (float) $account->debit_balance > 0 ? number_format((float) $account->debit_balance, 3, ',', '.') : '' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium">
                                        {{ (float) $account->credit_balance > 0 ? number_format((float) $account->credit_balance, 3, ',', '.') : '' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-neutral-300 bg-neutral-50 font-semibold dark:border-neutral-600 dark:bg-neutral-800">
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-sm">Totaux</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                    {{ number_format((float) $trialBalance['total_debit'], 3, ',', '.') }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                    {{ number_format((float) $trialBalance['total_credit'], 3, ',', '.') }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                    {{ (float) $trialBalance['total_debit_balance'] > 0 ? number_format((float) $trialBalance['total_debit_balance'], 3, ',', '.') : '' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                    {{ (float) $trialBalance['total_credit_balance'] > 0 ? number_format((float) $trialBalance['total_credit_balance'], 3, ',', '.') : '' }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        @endif

</div>
