<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Relevé client
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
                    <label for="customerId" class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Client</label>
                    <select
                        id="customerId"
                        wire:model.live="customerId"
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    >
                        <option value="">Sélectionnez un client</option>
                        @foreach ($customers as $statementCustomer)
                            <option value="{{ $statementCustomer->id }}">
                                {{ $statementCustomer->code }} — {{ $statementCustomer->name }}@if(! $statementCustomer->is_active) (inactif) @endif
                            </option>
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
                    @error('fromDate') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="toDate" class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Date fin</label>
                    <input
                        id="toDate"
                        type="date"
                        wire:model.live="toDate"
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    >
                    @error('toDate') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        @if (! $customer)
            <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                <h2 class="text-lg font-semibold">
                    Aucun client sélectionné
                </h2>

                <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                    Choisissez un client pour afficher son relevé.
                </p>
            </div>
        @elseif ($statement === null)
            <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                <h2 class="text-lg font-semibold">
                    Période invalide
                </h2>

                <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                    Vérifiez les dates sélectionnées.
                </p>
            </div>
        @else
            {{-- Statement header --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold">{{ $customer->name }}</h2>
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">
                            Code : {{ $customer->code }}
                            @if ($customer->tax_identifier)
                                — Identifiant fiscal : {{ $customer->tax_identifier }}
                            @endif
                        </p>
                    </div>

                    <p class="text-sm text-neutral-500 dark:text-neutral-400">
                        Période :
                        {{ $statement['from_date'] !== null ? \Illuminate\Support\Carbon::parse($statement['from_date'])->format('d/m/Y') : '—' }}
                        au
                        {{ $statement['to_date'] !== null ? \Illuminate\Support\Carbon::parse($statement['to_date'])->format('d/m/Y') : '—' }}
                    </p>
                </div>
            </div>

            {{-- Summary --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Solde d'ouverture</p>
                    <p class="mt-1 text-lg font-semibold">{{ number_format((float) $statement['opening_balance'], 3, ',', ' ') }}</p>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Total débits</p>
                    <p class="mt-1 text-lg font-semibold">{{ number_format((float) $statement['total_debit'], 3, ',', ' ') }}</p>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Total crédits</p>
                    <p class="mt-1 text-lg font-semibold">{{ number_format((float) $statement['total_credit'], 3, ',', ' ') }}</p>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Solde de clôture</p>
                    <p class="mt-1 text-lg font-semibold">{{ number_format((float) $statement['closing_balance'], 3, ',', ' ') }}</p>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                        @php
                            $closing = (float) $statement['closing_balance'];
                        @endphp
                        @if ($closing > 0)
                            Le client doit cette somme à la société.
                        @elseif ($closing < 0)
                            Le client dispose d'un solde créditeur (avoir).
                        @else
                            Compte soldé.
                        @endif
                    </p>
                </div>
            </div>

            {{-- Entries table --}}
            <div class="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-neutral-900 dark:border-neutral-700">
                @if (count($statement['entries']) === 0)
                    <div class="p-10 text-center">
                        <h3 class="text-lg font-semibold">
                            Aucun mouvement sur la période
                        </h3>

                        <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                            Aucun document comptabilisé ne correspond aux filtres sélectionnés.
                        </p>
                    </div>
                @else
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                        <thead class="bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Date</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Type</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Document</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Référence</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Description</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Débit</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Crédit</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Solde</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            {{-- Opening balance --}}
                            @if ((float) $statement['opening_balance'] !== 0.0)
                                <tr class="bg-neutral-50 dark:bg-neutral-800/50">
                                    <td colspan="5" class="px-4 py-2 text-sm font-medium text-neutral-500 dark:text-neutral-400">
                                        Solde d'ouverture
                                    </td>
                                    <td class="px-4 py-2"></td>
                                    <td class="px-4 py-2"></td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-sm font-medium">
                                        {{ number_format((float) $statement['opening_balance'], 3, ',', ' ') }}
                                    </td>
                                </tr>
                            @endif

                            @foreach ($statement['entries'] as $row)
                                <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800">
                                    <td class="whitespace-nowrap px-4 py-2 text-sm">
                                        {{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-sm">
                                        {{ $this->typeLabel($row['type']) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 font-mono text-sm">
                                        {{ $row['document_number'] }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-neutral-500 dark:text-neutral-400">
                                        {{ $row['reference'] ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-neutral-500 dark:text-neutral-400">
                                        {{ $row['description'] ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-sm">
                                        {{ (float) $row['debit'] > 0 ? number_format((float) $row['debit'], 3, ',', ' ') : '' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-sm">
                                        {{ (float) $row['credit'] > 0 ? number_format((float) $row['credit'], 3, ',', ' ') : '' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-sm font-medium">
                                        {{ number_format((float) $row['balance'], 3, ',', ' ') }}
                                    </td>
                                </tr>
                            @endforeach

                            {{-- Closing balance --}}
                            <tr class="border-t-2 border-neutral-300 bg-neutral-50 font-semibold dark:border-neutral-600 dark:bg-neutral-800">
                                <td colspan="5" class="px-4 py-2 text-sm">
                                    Solde de clôture
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-sm">
                                    {{ (float) $statement['total_debit'] > 0 ? number_format((float) $statement['total_debit'], 3, ',', ' ') : '' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-sm">
                                    {{ (float) $statement['total_credit'] > 0 ? number_format((float) $statement['total_credit'], 3, ',', ' ') : '' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-sm">
                                    {{ number_format((float) $statement['closing_balance'], 3, ',', ' ') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                @endif
            </div>
        @endif
    @endif

</div>
