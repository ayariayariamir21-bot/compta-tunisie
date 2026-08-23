<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Compte de résultat
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
                    <label for="fromDate" class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Du</label>
                    <input
                        id="fromDate"
                        type="date"
                        wire:model.live="fromDate"
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    >
                </div>

                <div>
                    <label for="toDate" class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Au</label>
                    <input
                        id="toDate"
                        type="date"
                        wire:model.live="toDate"
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    >
                </div>

                <div class="flex items-center gap-2 pb-2">
                    <input
                        id="includeZeroBalance"
                        type="checkbox"
                        wire:model.live="includeZeroBalance"
                        value="1"
                        class="h-4 w-4 rounded border-neutral-300"
                    >
                    <label for="includeZeroBalance" class="text-sm">Afficher les comptes à solde nul</label>
                </div>
            </div>
        </div>

        {{-- PDF export --}}
        <div class="flex justify-end">
            <flux:button
                variant="outline"
                href="{{ route('reports.income-statement.pdf', [
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'include_zero_balance' => $includeZeroBalance,
                ]) }}"
                target="_blank"
            >
                <flux:icon name="arrow-down-tray" class="size-4" />
                Exporter PDF
            </flux:button>
        </div>

        {{-- Invalid period --}}
        @if ($dateError)
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                {{ $dateError }}
            </div>
        @elseif ($report && $summary)
            {{-- Period line --}}
            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                Période : {{ \Illuminate\Support\Carbon::parse($report['from_date'])->format('d/m/Y') }} → {{ \Illuminate\Support\Carbon::parse($report['to_date'])->format('d/m/Y') }}
            </p>

            {{-- Summary cards --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Total Produits</p>
                    <p class="mt-2 text-xl font-bold tabular-nums">{{ number_format((float) $summary['total_revenue'], 3, ',', ' ') }}</p>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Total Charges</p>
                    <p class="mt-2 text-xl font-bold tabular-nums">{{ number_format((float) $summary['total_expenses'], 3, ',', ' ') }}</p>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Résultat net</p>
                    <p class="mt-2 text-xl font-bold tabular-nums {{ $summary['is_break_even'] ? '' : ($summary['has_profit'] ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400') }}">
                        {{ number_format((float) $summary['net_result'], 3, ',', ' ') }}
                        {{ $summary['has_profit'] ? '(Bénéfice)' : ($summary['has_loss'] ? '(Perte)' : '(Résultat nul)') }}
                    </p>
                </div>
            </div>

            {{-- PRODUITS --}}
            <div class="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-neutral-900 dark:border-neutral-700">
                <div class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
                    <h2 class="text-sm font-semibold uppercase tracking-wider">Produits</h2>
                </div>

                @if ($report['revenues']->isEmpty())
                    <p class="px-4 py-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
                        Aucun compte de produits avec mouvement sur cette période.
                    </p>
                @else
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                        <thead class="bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Compte</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Montant</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($report['revenues'] as $row)
                                <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800" @class(['font-semibold' => $row['is_group']])>
                                    <td class="px-4 py-2 text-sm" style="padding-inline-start: {{ 16 + $row['depth'] * 20 }}px">
                                        <span class="mr-2 font-mono text-xs text-neutral-500">{{ $row['code'] }}</span>
                                        {{ $row['name'] }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-sm tabular-nums">
                                        {{ number_format((float) ($row['is_group'] ? $row['subtotal'] : $row['amount']), 3, ',', ' ') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-neutral-300 bg-neutral-50 font-semibold dark:border-neutral-600 dark:bg-neutral-800">
                            <tr>
                                <td class="px-4 py-3 text-sm uppercase">Total Produits</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm tabular-nums">
                                    {{ number_format((float) $summary['total_revenue'], 3, ',', ' ') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                @endif
            </div>

            {{-- CHARGES --}}
            <div class="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-neutral-900 dark:border-neutral-700">
                <div class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
                    <h2 class="text-sm font-semibold uppercase tracking-wider">Charges</h2>
                </div>

                @if ($report['expenses']->isEmpty())
                    <p class="px-4 py-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
                        Aucun compte de charges avec mouvement sur cette période.
                    </p>
                @else
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                        <thead class="bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Compte</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Montant</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($report['expenses'] as $row)
                                <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800" @class(['font-semibold' => $row['is_group']])>
                                    <td class="px-4 py-2 text-sm" style="padding-inline-start: {{ 16 + $row['depth'] * 20 }}px">
                                        <span class="mr-2 font-mono text-xs text-neutral-500">{{ $row['code'] }}</span>
                                        {{ $row['name'] }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-sm tabular-nums">
                                        {{ number_format((float) ($row['is_group'] ? $row['subtotal'] : $row['amount']), 3, ',', ' ') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-neutral-300 bg-neutral-50 font-semibold dark:border-neutral-600 dark:bg-neutral-800">
                            <tr>
                                <td class="px-4 py-3 text-sm uppercase">Total Charges</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm tabular-nums">
                                    {{ number_format((float) $summary['total_expenses'], 3, ',', ' ') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                @endif
            </div>

            {{-- RESULTAT --}}
            <div class="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-neutral-900 dark:border-neutral-700">
                <div class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
                    <h2 class="text-sm font-semibold uppercase tracking-wider">Résultat</h2>
                </div>

                <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800">
                            <td class="px-4 py-2 text-sm">
                                Total produits
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right text-sm tabular-nums">
                                {{ number_format((float) $summary['total_revenue'], 3, ',', ' ') }}
                            </td>
                        </tr>
                        <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800">
                            <td class="px-4 py-2 text-sm">
                                − Total charges
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right text-sm tabular-nums">
                                {{ number_format((float) $summary['total_expenses'], 3, ',', ' ') }}
                            </td>
                        </tr>
                        <tr class="bg-neutral-50 font-semibold dark:bg-neutral-800">
                            <td class="px-4 py-3 text-sm uppercase">
                                = Résultat net
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm tabular-nums {{ $summary['is_break_even'] ? '' : ($summary['has_profit'] ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400') }}">
                                {{ number_format((float) $summary['net_result'], 3, ',', ' ') }}
                                {{ $summary['has_profit'] ? '(Bénéfice)' : ($summary['has_loss'] ? '(Perte)' : '(Résultat nul)') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    @endif

</div>
