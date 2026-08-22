    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Modifier l'avoir {{ $creditNote->credit_note_number }}</h1>
            <a href="{{ route('credit-notes.show', $creditNote->id) }}" class="text-flux-600 hover:text-flux-500 dark:text-flux-400" wire:navigate>Retour</a>
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

        <div class="flux-card grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <flux:input wire:model="credit_note_date" type="date" label="Date de l'avoir" required />
            </div>
            <div>
                <flux:input wire:model="reason" label="Motif" maxlength="500" />
            </div>
            <div>
                <flux:input wire:model="notes" label="Notes" maxlength="5000" />
            </div>
        </div>

        <div class="flux-card">
            <h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Lignes</h2>
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                Facture source : {{ $creditNote->invoice?->invoice_number }} — Client : {{ $creditNote->customer->name }}
            </p>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Description</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Qté originale</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Déjà créditée (autres avoirs)</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Disponible max</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Qté à créditer</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">P.U. HT</th>
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
                                <td class="px-4 py-3 text-right text-sm text-gray-500">{{ $line['unit_price'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex flex-col items-end space-y-1 border-t border-gray-200 pt-4 text-sm dark:border-gray-700">
                <div class="flex w-full max-w-xs justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Total HT</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $totals['subtotal'] }}</span>
                </div>
                <div class="flex w-full max-w-xs justify-between">
                    <span class="text-gray-500 dark:text-gray-400">TVA</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $totals['tax_total'] }}</span>
                </div>
                <div class="flex w-full max-w-xs justify-between border-t border-gray-200 pt-2 text-base dark:border-gray-700">
                    <span class="font-semibold text-gray-900 dark:text-white">Total TTC</span>
                    <span class="font-bold text-gray-900 dark:text-white">{{ $totals['total'] }} {{ $creditNote->currency }}</span>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="button" wire:click="update" class="flux-btn-primary">
                Enregistrer les modifications
            </button>
        </div>
    </div>
