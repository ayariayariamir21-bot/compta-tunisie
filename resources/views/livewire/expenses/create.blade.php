<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Nouvelle dépense</h1>
        <a href="{{ route('expenses.index') }}" class="text-sm text-flux-600 hover:text-flux-500 dark:text-flux-400" wire:navigate>Retour à la liste</a>
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

    @if ($errors->any())
        <div class="rounded-md bg-red-50 p-4 dark:bg-red-900/20">
            <ul class="list-disc space-y-1 pl-5 text-sm text-red-700 dark:text-red-400">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flux-card">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Informations générales</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <flux:select wire:model.live="supplier_id" label="Fournisseur" placeholder="Aucun (dépense directe)">
                    <option value="">—</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="payment_method_id" label="Mode de règlement" placeholder="Aucun (à payer plus tard)">
                    <option value="">—</option>
                    @foreach($methods as $method)
                        <option value="{{ $method->id }}">{{ $method->name }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:select wire:model="journal_id" label="Journal (opérations diverses)" required>
                    <option value="">—</option>
                    @foreach($journals as $journal)
                        <option value="{{ $journal->id }}">{{ $journal->code }} — {{ $journal->name }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:input wire:model="expense_date" type="date" label="Date de la dépense" required />
            </div>
            <div>
                <flux:input wire:model="due_date" type="date" label="Date d'échéance" />
            </div>
            <div>
                <flux:input wire:model="reference" label="Référence" placeholder="N° de justificatif..." />
            </div>
            <div class="sm:col-span-2">
                <flux:input wire:model="description" label="Description" placeholder="Objet de la dépense" />
            </div>
            <div>
                <flux:textarea wire:model="notes" label="Notes" rows="2" />
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            Si un mode de règlement est sélectionné, la dépense est comptabilisée immédiatement (contrepartie banque/caisse). Sinon, si un fournisseur est sélectionné, elle est enregistrée comme à payer (contrepartie compte fournisseur).
        </p>
    </div>

    <div class="flux-card">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Lignes de dépense</h2>
            <button wire:click="addLine" class="flux-btn-secondary">Ajouter une ligne</button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Compte de charge</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Libellé</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Qté</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">P.U. HT</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Remise %</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">TVA</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                    @foreach ($this->lines as $index => $line)
                        <tr>
                            <td class="px-4 py-3">
                                <flux:select wire:model="lines.{{ $index }}.expense_account_id" placeholder="Sélectionner">
                                    <option value="">—</option>
                                    @foreach($expenseAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                    @endforeach
                                </flux:select>
                            </td>
                            <td class="px-4 py-3">
                                <flux:input wire:model="lines.{{ $index }}.label" placeholder="Libellé de la ligne" />
                            </td>
                            <td class="px-4 py-3">
                                <flux:input wire:model="lines.{{ $index }}.quantity" type="number" step="0.001" min="0" class="w-24 text-right" />
                            </td>
                            <td class="px-4 py-3">
                                <flux:input wire:model.live="lines.{{ $index }}.unit_price" type="number" step="0.001" min="0" class="w-28 text-right" />
                            </td>
                            <td class="px-4 py-3">
                                <flux:input wire:model.live="lines.{{ $index }}.discount_percent" type="number" step="0.001" min="0" max="100" class="w-24 text-right" />
                            </td>
                            <td class="px-4 py-3">
                                <flux:select wire:model.live="lines.{{ $index }}.tax_rate_id" placeholder="Aucune">
                                    <option value="">—</option>
                                    @foreach($taxRates as $taxRate)
                                        <option value="{{ $taxRate->id }}">{{ $taxRate->name }} ({{ number_format((float) $taxRate->rate, 3, '.', '') }}%)</option>
                                    @endforeach
                                </flux:select>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="removeLine({{ $index }})" wire:confirm="Voulez-vous supprimer cette ligne ?" class="text-red-600 hover:text-red-500 dark:text-red-400">Supprimer</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-col items-end gap-1 border-t border-gray-200 pt-4 dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Remise totale : <span class="font-semibold text-gray-900 dark:text-white">{{ number_format((float) $this->totals['discount_total'], 3, '.', '') }}</span>
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Total HT : <span class="font-semibold text-gray-900 dark:text-white">{{ number_format((float) $this->totals['subtotal'], 3, '.', '') }}</span>
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                TVA : <span class="font-semibold text-gray-900 dark:text-white">{{ number_format((float) $this->totals['tax_total'], 3, '.', '') }}</span>
            </p>
            <p class="text-base text-gray-500 dark:text-gray-400">
                Total TTC : <span class="font-bold text-gray-900 dark:text-white">{{ number_format((float) $this->totals['total'], 3, '.', '') }} TND</span>
            </p>
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <button wire:click="save(false)" class="flux-btn-primary">Enregistrer le brouillon</button>
        <button wire:click="save(true)" wire:confirm="Voulez-vous créer et comptabiliser cette dépense ? Une écriture comptable sera générée." class="flux-btn-green">Enregistrer et comptabiliser</button>
    </div>
</div>
