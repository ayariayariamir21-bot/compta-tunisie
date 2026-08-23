<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Bilan
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
                    <label for="asOfDate" class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Situation au</label>
                    <input
                        id="asOfDate"
                        type="date"
                        wire:model.live="asOfDate"
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

        {{-- Invalid date --}}
        @if ($dateError)
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                {{ $dateError }}
            </div>
        @elseif ($report && $summary)
            {{-- Summary cards --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Total Actif</p>
                    <p class="mt-2 text-xl font-bold tabular-nums">{{ number_format((float) $summary['total_assets'], 3, ',', ' ') }}</p>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Total Passif</p>
                    <p class="mt-2 text-xl font-bold tabular-nums">{{ number_format((float) $summary['total_liabilities'], 3, ',', ' ') }}</p>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Capitaux propres</p>
                    <p class="mt-2 text-xl font-bold tabular-nums">{{ number_format((float) $summary['total_equity'], 3, ',', ' ') }}</p>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Résultat</p>
                    <p class="mt-2 text-xl font-bold tabular-nums {{ $summary['has_profit'] ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                        {{ number_format((float) $summary['result'], 3, ',', ' ') }}
                        {{ $summary['has_profit'] ? '(Bénéfice)' : '(Perte)' }}
                    </p>
                </div>
            </div>

            {{-- Balance check --}}
            @if ($summary['is_balanced'])
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                    Le bilan est équilibré — Total Actif = Total Passif + Capitaux propres ({{ number_format((float) $summary['total_passif_capitaux'], 3, ',', ' ') }})
                </div>
            @else
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                    Le bilan présente un écart de {{ number_format((float) $summary['difference'], 3, ',', ' ') }} entre le Total Actif et le Total Passif + Capitaux propres.
                </div>
            @endif

            {{-- ACTIF --}}
            <div class="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-neutral-900 dark:border-neutral-700">
                <div class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
                    <h2 class="text-sm font-semibold uppercase tracking-wider">Actif</h2>
                </div>

                @if ($report['assets']->isEmpty())
                    <p class="px-4 py-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
                        Aucun compte d'actif avec solde à cette date.
                    </p>
                @else
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                        <thead class="bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Compte</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Solde</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($report['assets'] as $row)
                                <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800" @class(['font-semibold' => $row['is_group']])>
                                    <td class="px-4 py-2 text-sm" style="padding-inline-start: {{ 16 + $row['depth'] * 20 }}px">
                                        <span class="mr-2 font-mono text-xs text-neutral-500">{{ $row['code'] }}</span>
                                        {{ $row['name'] }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-sm tabular-nums">
                                        {{ number_format((float) ($row['is_group'] ? $row['subtotal'] : $row['balance']), 3, ',', ' ') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-neutral-300 bg-neutral-50 font-semibold dark:border-neutral-600 dark:bg-neutral-800">
                            <tr>
                                <td class="px-4 py-3 text-sm uppercase">Total Actif</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm tabular-nums">
                                    {{ number_format((float) $summary['total_assets'], 3, ',', ' ') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                @endif
            </div>

            {{-- PASSIF & CAPITAUX PROPRES --}}
            <div class="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-neutral-900 dark:border-neutral-700">
                <div class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
                    <h2 class="text-sm font-semibold uppercase tracking-wider">Passif & Capitaux propres</h2>
                </div>

                @if ($report['liabilities']->isEmpty() && $report['equity']->isEmpty() && bccomp($report['result']['value'], '0.000', 3) === 0)
                    <p class="px-4 py-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
                        Aucun compte de passif ni de capitaux propres avec solde à cette date.
                    </p>
                @else
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                        <thead class="bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Compte</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Solde</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @if ($report['equity']->isNotEmpty())
                                <tr class="bg-neutral-50/60 dark:bg-neutral-800/40">
                                    <td colspan="2" class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                                        Capitaux propres
                                    </td>
                                </tr>
                                @foreach ($report['equity'] as $row)
                                    <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800" @class(['font-semibold' => $row['is_group']])>
                                        <td class="px-4 py-2 text-sm" style="padding-inline-start: {{ 16 + $row['depth'] * 20 }}px">
                                            <span class="mr-2 font-mono text-xs text-neutral-500">{{ $row['code'] }}</span>
                                            {{ $row['name'] }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2 text-right text-sm tabular-nums">
                                            {{ number_format((float) ($row['is_group'] ? $row['subtotal'] : $row['balance']), 3, ',', ' ') }}
                                        </td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td class="px-4 py-2 text-sm italic" style="padding-inline-start: 36px">
                                        Résultat de l'exercice
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-sm tabular-nums {{ $summary['has_profit'] ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                                        {{ number_format((float) $report['result']['value'], 3, ',', ' ') }}
                                    </td>
                                </tr>
                            @endif

                            @if ($report['liabilities']->isNotEmpty())
                                <tr class="bg-neutral-50/60 dark:bg-neutral-800/40">
                                    <td colspan="2" class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                                        Dettes
                                    </td>
                                </tr>
                                @foreach ($report['liabilities'] as $row)
                                    <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800" @class(['font-semibold' => $row['is_group']])>
                                        <td class="px-4 py-2 text-sm" style="padding-inline-start: {{ 16 + $row['depth'] * 20 }}px">
                                            <span class="mr-2 font-mono text-xs text-neutral-500">{{ $row['code'] }}</span>
                                            {{ $row['name'] }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2 text-right text-sm tabular-nums">
                                            {{ number_format((float) ($row['is_group'] ? $row['subtotal'] : $row['balance']), 3, ',', ' ') }}
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                        <tfoot class="border-t-2 border-neutral-300 bg-neutral-50 font-semibold dark:border-neutral-600 dark:bg-neutral-800">
                            <tr>
                                <td class="px-4 py-2 text-sm uppercase">Total Passif</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-sm tabular-nums">
                                    {{ number_format((float) $summary['total_liabilities'], 3, ',', ' ') }}
                                </td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 text-sm uppercase">Total Passif + Capitaux propres</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-sm tabular-nums">
                                    {{ number_format((float) $summary['total_passif_capitaux'], 3, ',', ' ') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                @endif
            </div>
        @endif
    @endif

</div>
