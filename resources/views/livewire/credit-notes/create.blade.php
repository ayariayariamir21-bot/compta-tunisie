    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Nouvel avoir</h1>
            <a href="{{ route('credit-notes.index') }}" class="text-flux-600 hover:text-flux-500 dark:text-flux-400" wire:navigate>Retour aux avoirs</a>
        </div>

        @if (session('success'))
            <div class="rounded-md bg-green-50 p-4 dark:bg-green-900/20">
                <p class="text-sm text-green-700 dark:text-green-400">{{ session('success') }}</p>
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 dark:bg-red-900/20">
                <p class="text-sm text-red-700 dark:text-red-400">{{ session('error') }}</p>
            </div>
        @endif

        @if ($currentCompany === null)
            <div class="flux-card">
                <div class="py-12 text-center">
                    <flux:icon name="building-office" class="mx-auto h-12 w-12 text-gray-400" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Veuillez sélectionner une société.</p>
                </div>
            </div>
        @elseif ($invoice === null)
            <div class="flux-card">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Sélectionner la facture source</h2>

                @error('invoice')
                    <div class="mb-4 rounded-md bg-red-50 p-4 dark:bg-red-900/20">
                        <p class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
                    </div>
                @enderror

                @if ($invoices->count() === 0)
                    <div class="py-12 text-center">
                        <flux:icon name="document-text" class="mx-auto h-12 w-12 text-gray-400" />
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Aucune facture comptabilisée disponible à créditer.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Numéro</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Date</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total TTC</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                @foreach($invoices as $availableInvoice)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ $availableInvoice->invoice_number }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $availableInvoice->customer->name }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $availableInvoice->invoice_date->format('d/m/Y') }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-white">
                                            {{ number_format((float) $availableInvoice->total, 3, '.', '') }} {{ $availableInvoice->currency }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                            <button type="button" wire:click="loadInvoice({{ $availableInvoice->id }})" class="font-medium text-flux-600 hover:text-flux-500 dark:text-flux-400">Créditer</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @else
            @php($sourceInvoice = \App\Models\Invoice::find($invoice))

            <div class="flux-card">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Facture source : {{ $sourceInvoice?->invoice_number }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $sourceInvoice?->customer?->name }} — Total TTC
                            {{ number_format((float) ($sourceInvoice?->total ?? 0), 3, '.', '') }} {{ $sourceInvoice?->currency }}
                        </p>
                    </div>
                    <button type="button" wire:click="$set('invoice', null)" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Changer de facture</button>
                </div>
            </div>

            <div class="flux-card grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <flux:input wire:model="credit_note_date" type="date" label="Date de l'avoir" required />
                </div>
                <div>
                    <flux:input wire:model="reason" label="Motif" placeholder="Retour marchandise, remise accordée..." maxlength="500" />
                </div>
                <div>
                    <flux:input wire:model="notes" label="Notes" maxlength="5000" />
                </div>
            </div>

            <div class="flux-card">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Lignes à créditer</h2>

                @error('lines')
                    <div class="mb-4 rounded-md bg-red-50 p-4 dark:bg-red-900/20">
                        <p class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
                    </div>
                @enderror

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Description</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Qté originale</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Déjà créditée</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Disponible</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Qté à créditer</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Unité</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">P.U. HT</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Remise</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">TVA</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                            @foreach($lines as $index => $line)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $line['description'] }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-500">{{ $line['original_quantity'] }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-500">{{ $line['credited_quantity'] }}</td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold text-blue-600 dark:text-blue-400">{{ $line['available_quantity'] }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <flux:input
                                            :wire:model="'lines.' . $index . '.quantity'"
                                            type="number"
                                            min="0"
                                            step="0.001"
                                            :max="$line['available_quantity']"
                                            class="w-28 text-right"
                                        />
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $line['unit'] }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-500">{{ $line['unit_price'] }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-500">{{ $line['discount_percent'] }}%</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $line['tax_label'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex flex-col items-end space-y-1 border-t border-gray-200 pt-4 text-sm dark:border-gray-700">
                    <div class="flex w-full max-w-xs justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Total HT</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $this->totals['subtotal'] }}</span>
                    </div>
                    <div class="flex w-full max-w-xs justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Remise</span>
                        <span class="font-medium text-gray-900 dark:text-white">-{{ $this->totals['discount_total'] }}</span>
                    </div>
                    <div class="flex w-full max-w-xs justify-between">
                        <span class="text-gray-500 dark:text-gray-400">TVA</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $this->totals['tax_total'] }}</span>
                    </div>
                    <div class="flex w-full max-w-xs justify-between border-t border-gray-200 pt-2 text-base dark:border-gray-700">
                        <span class="font-semibold text-gray-900 dark:text-white">Total TTC</span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $this->totals['total'] }} TND</span>
                    </div>
                </div>
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" wire:click="save(false)" wire:loading.attr="disabled" class="flux-btn-secondary">
                    Enregistrer le brouillon
                </button>
                <button type="button" wire:click="save(true)" class="flux-btn-primary">
                    Enregistrer et comptabiliser
                </button>
            </div>
        @endif
    </div>
