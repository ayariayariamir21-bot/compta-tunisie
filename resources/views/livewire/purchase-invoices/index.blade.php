    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Factures fournisseurs</h1>
    @can('create', [App\Models\PurchaseInvoice::class, $currentCompany])
            <a href="{{ route('purchase-invoices.create') }}" class="flux-btn-primary" wire:navigate>
                Nouvelle facture fournisseur
            </a>
    @endcan
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

        <div class="flux-card">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-5">
                <div>
                    <flux:input wire:model.live="search" placeholder="Rechercher..." label="Recherche" />
                </div>
                <div>
                    <flux:select wire:model.live="filterStatus" label="Statut" placeholder="Tous">
                        <option value="">Tous les statuts</option>
                        @foreach($purchaseInvoiceStatuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <div>
                    <flux:select wire:model.live="filterSupplierId" label="Fournisseur" placeholder="Tous">
                        <option value="">Tous les fournisseurs</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <div>
                    <flux:input wire:model.live="filterDateFrom" type="date" label="Date début" />
                </div>
                <div>
                    <flux:input wire:model.live="filterDateTo" type="date" label="Date fin" />
                </div>
            </div>
        </div>

        @if ($currentCompany)
            <div class="flux-card">
                @if ($purchaseInvoices->count() === 0)
                    <div class="py-12 text-center">
                        <flux:icon name="document-text" class="mx-auto h-12 w-12 text-gray-400" />
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Aucune facture fournisseur trouvée.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Numéro interne</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">N° fournisseur</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Fournisseur</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Échéance</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total TTC</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Statut</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                @foreach($purchaseInvoices as $purchaseInvoice)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $purchaseInvoice->invoice_number }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $purchaseInvoice->supplier_invoice_number ?? '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $purchaseInvoice->supplier->name }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $purchaseInvoice->invoice_date->format('d/m/Y') }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $purchaseInvoice->due_date ? $purchaseInvoice->due_date->format('d/m/Y') : '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-white">
                                            {{ number_format((float) $purchaseInvoice->total, 3, '.', '') }} {{ $purchaseInvoice->currency }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-center">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                                {{ match($purchaseInvoice->status) {
                                                    \App\Enums\PurchaseInvoiceStatus::DRAFT => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                                    \App\Enums\PurchaseInvoiceStatus::POSTED => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                    \App\Enums\PurchaseInvoiceStatus::CANCELLED => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                } }}">
                                                {{ $purchaseInvoice->status->label() }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                            <a href="{{ route('purchase-invoices.show', $purchaseInvoice->id) }}" class="text-flux-600 hover:text-flux-500 dark:text-flux-400" wire:navigate>Voir</a>
                                            @if ($purchaseInvoice->status->value === 'draft')
                                                <span class="text-gray-300 dark:text-gray-600">|</span>
                                                <a href="{{ route('purchase-invoices.edit', $purchaseInvoice->id) }}" class="text-flux-600 hover:text-flux-500 dark:text-flux-400" wire:navigate>Modifier</a>
                                                <span class="text-gray-300 dark:text-gray-600">|</span>
                                                <button wire:click="post({{ $purchaseInvoice->id }})" wire:confirm="Voulez-vous vraiment comptabiliser cette facture fournisseur ?" class="text-green-600 hover:text-green-500 dark:text-green-400">Comptabiliser</button>
                                                <span class="text-gray-300 dark:text-gray-600">|</span>
                                                <button wire:click="cancel({{ $purchaseInvoice->id }})" wire:confirm="Voulez-vous vraiment annuler cette facture fournisseur ?" class="text-orange-600 hover:text-orange-500 dark:text-orange-400">Annuler</button>
                                                <span class="text-gray-300 dark:text-gray-600">|</span>
                                                <button wire:click="delete({{ $purchaseInvoice->id }})" wire:confirm="Voulez-vous vraiment supprimer cette facture fournisseur ? Cette action est irréversible." class="text-red-600 hover:text-red-500 dark:text-red-400">Supprimer</button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $purchaseInvoices->links() }}
                    </div>
                @endif
            </div>
        @else
            <div class="flux-card">
                <div class="py-12 text-center">
                    <flux:icon name="building-office" class="mx-auto h-12 w-12 text-gray-400" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Veuillez sélectionner une société pour afficher les factures fournisseurs.</p>
                </div>
            </div>
        @endif
    </div>
