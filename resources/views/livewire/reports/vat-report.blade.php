<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Rapport de TVA
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
            </div>
        </div>

        {{-- PDF export --}}
        <div class="flex justify-end">
            <flux:button
                variant="outline"
                href="{{ route('reports.vat.pdf', [
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
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
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">TVA collectée</p>
                    <p class="mt-2 text-xl font-bold tabular-nums">{{ number_format((float) $summary['collected_vat'], 3, ',', ' ') }}</p>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">TVA déductible</p>
                    <p class="mt-2 text-xl font-bold tabular-nums">{{ number_format((float) $summary['deductible_vat'], 3, ',', ' ') }}</p>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">TVA nette</p>
                    <p class="mt-2 text-xl font-bold tabular-nums {{ $summary['is_neutral'] ? '' : ($summary['has_credit'] ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400') }}">
                        {{ number_format((float) $summary['net_vat'], 3, ',', ' ') }}
                        ({{ $summary['status_label'] }})
                    </p>
                </div>
            </div>

            {{-- Bases --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Base taxable — ventes</p>
                    <p class="mt-2 text-lg font-semibold tabular-nums">{{ number_format((float) $summary['total_output_base'], 3, ',', ' ') }}</p>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Base taxable — achats</p>
                    <p class="mt-2 text-lg font-semibold tabular-nums">{{ number_format((float) $summary['total_input_base'], 3, ',', ' ') }}</p>
                </div>
            </div>

            {{-- TVA COLLECTEE --}}
            <div class="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-neutral-900 dark:border-neutral-700">
                <div class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
                    <h2 class="text-sm font-semibold uppercase tracking-wider">TVA collectée</h2>
                </div>

                @if (count(array_filter($report['tax_rates'], fn ($row) => $row['direction'] === 'collectee')) === 0)
                    <p class="px-4 py-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
                        Aucune TVA collectée sur cette période.
                    </p>
                @else
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                        <thead class="bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Code</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Type</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Taux (%)</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Base HT</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">TVA</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($report['tax_rates'] as $row)
                                @continue($row['direction'] !== 'collectee')
                                <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800">
                                    <td class="px-4 py-2 text-sm">
                                        <span class="font-mono text-xs text-neutral-500">{{ $row['code'] }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-sm">{{ $row['type_label'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-sm tabular-nums">{{ number_format((float) $row['rate'], 3, ',', ' ') }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-sm tabular-nums">{{ number_format((float) $row['base'], 3, ',', ' ') }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-sm font-medium tabular-nums">{{ number_format((float) $row['vat'], 3, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- TVA DEDUCTIBLE --}}
            <div class="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-neutral-900 dark:border-neutral-700">
                <div class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
                    <h2 class="text-sm font-semibold uppercase tracking-wider">TVA déductible</h2>
                </div>

                @if (count(array_filter($report['tax_rates'], fn ($row) => $row['direction'] === 'deductible')) === 0)
                    <p class="px-4 py-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
                        Aucune TVA déductible sur cette période.
                    </p>
                @else
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                        <thead class="bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Code</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Type</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Taux (%)</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Base HT</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">TVA</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($report['tax_rates'] as $row)
                                @continue($row['direction'] !== 'deductible')
                                <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800">
                                    <td class="px-4 py-2 text-sm">
                                        <span class="font-mono text-xs text-neutral-500">{{ $row['code'] }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-sm">{{ $row['type_label'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-sm tabular-nums">{{ number_format((float) $row['rate'], 3, ',', ' ') }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-sm tabular-nums">{{ number_format((float) $row['base'], 3, ',', ' ') }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-sm font-medium tabular-nums">{{ number_format((float) $row['vat'], 3, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- DOCUMENTS --}}
            <div class="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-neutral-900 dark:border-neutral-700">
                <div class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
                    <h2 class="text-sm font-semibold uppercase tracking-wider">Détail des documents</h2>
                </div>

                @if (count($report['documents']) === 0)
                    <p class="px-4 py-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
                        Aucun document avec TVA sur cette période.
                    </p>
                @else
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                        <thead class="bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Document</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Tiers</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Code TVA</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Base HT</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">TVA</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($report['documents'] as $document)
                                <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800">
                                    <td class="whitespace-nowrap px-4 py-2 text-sm tabular-nums">{{ \Illuminate\Support\Carbon::parse($document['date'])->format('d/m/Y') }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-sm">{{ $document['document_type_label'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-sm font-mono text-xs">{{ $document['document_number'] }}</td>
                                    <td class="px-4 py-2 text-sm">{{ $document['party'] }}</td>
                                    <td class="px-4 py-2 text-sm">
                                        <span class="font-mono text-xs text-neutral-500">{{ $document['code'] }} @ {{ number_format((float) $document['rate'], 3, ',', ' ') }} %</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-sm tabular-nums">{{ number_format((float) $document['base'], 3, ',', ' ') }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-sm font-medium tabular-nums">{{ number_format((float) $document['vat'], 3, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Unattributed movements --}}
            @if (bccomp($report['unattributed_collected'], '0.000', 3) !== 0 || bccomp($report['unattributed_deductible'], '0.000', 3) !== 0)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                    Autres mouvements TVA (écritures manuelles sans pièce associée) :
                    collectée {{ number_format((float) $report['unattributed_collected'], 3, ',', ' ') }},
                    déductible {{ number_format((float) $report['unattributed_deductible'], 3, ',', ' ') }}.
                </div>
            @endif

            {{-- Disclaimer --}}
            <p class="text-xs text-neutral-400 dark:text-neutral-600">
                Rapport comptable interne établi à partir des écritures postées — ne constitue pas une déclaration officielle de TVA.
            </p>
        @endif
    @endif

</div>
