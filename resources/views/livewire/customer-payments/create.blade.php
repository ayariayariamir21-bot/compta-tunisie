    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Nouvel encaissement</h1>
            <a href="{{ route('customer-payments.index') }}" class="text-sm text-flux-600 hover:text-flux-500 dark:text-flux-400" wire:navigate>Retour à la liste</a>
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
                    <flux:select wire:model.live="customer_id" label="Client" placeholder="Sélectionner un client" required>
                        <option value="">—</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <div>
                    <flux:input wire:model="payment_date" type="date" label="Date de paiement" required />
                </div>
                <div>
                    <flux:select wire:model.live="payment_method_id" label="Moyen de paiement" placeholder="Sélectionner un moyen" required>
                        <option value="">—</option>
                        @foreach($methods as $method)
                            <option value="{{ $method->id }}">{{ $method->name }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <div>
                    <flux:select wire:model="journal_id" label="Journal" required>
                        <option value="">—</option>
                        @foreach($journals as $journal)
                            <option value="{{ $journal->id }}">{{ $journal->code }} — {{ $journal->name }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <div>
                    <flux:select wire:model="destination_account_id" label="Compte de destination" required>
                        <option value="">—</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <div>
                    <flux:input wire:model="amount" type="number" step="0.001" min="0.001" label="Montant du règlement" required />
                </div>
                <div>
                    <flux:input wire:model="reference" label="Référence" placeholder="N° de chèque, virement..." />
                </div>
                <div class="sm:col-span-2">
                    <flux:textarea wire:model="notes" label="Notes" rows="2" />
                </div>
            </div>
        </div>

        <div class="flux-card">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Imputation sur factures</h2>
            @if ($this->customer_id === null)
                <p class="text-sm text-gray-500 dark:text-gray-400">Sélectionnez un client pour afficher ses factures ouvertes.</p>
            @elseif (count($this->allocations) === 0)
                <p class="text-sm text-gray-500 dark:text-gray-400">Aucune facture ouverte pour ce client. Le règlement sera enregistré comme avance (non imputé).</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Facture</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Date</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total TTC</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Avoirs</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Déjà réglé</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Reste à régler</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Montant imputé</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                            @foreach ($this->allocations as $index => $allocation)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $allocation['number'] }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $allocation['date'] }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-500 dark:text-gray-400">
                                        {{ number_format((float) $allocation['total'], 3, '.', '') }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-orange-600 dark:text-orange-400">
                                        {{ number_format((float) $allocation['credited'], 3, '.', '') }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-500 dark:text-gray-400">
                                        {{ number_format((float) $allocation['paid'], 3, '.', '') }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-white">
                                        {{ number_format((float) $allocation['remaining'], 3, '.', '') }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        <flux:input wire:model="allocations.{{ $index }}.amount" type="number" step="0.001" min="0" class="w-32 text-right" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="mt-4 flex flex-col items-end gap-1 border-t border-gray-200 pt-4 dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Total imputé : <span class="font-semibold text-gray-900 dark:text-white">{{ number_format((float) $this->totals['allocated'], 3, '.', '') }}</span>
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Montant non imputé : <span class="font-semibold {{ bccomp($this->totals['unallocated'], '0', 3) > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-gray-900 dark:text-white' }}">{{ number_format((float) $this->totals['unallocated'], 3, '.', '') }}</span>
                </p>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <button wire:click="save(false)" class="flux-btn-primary">Enregistrer le brouillon</button>
            <button wire:click="save(true)" wire:confirm="Voulez-vous créer et comptabiliser cet encaissement ? Une écriture comptable sera générée." class="flux-btn-green">Enregistrer et comptabiliser</button>
        </div>
    </div>
