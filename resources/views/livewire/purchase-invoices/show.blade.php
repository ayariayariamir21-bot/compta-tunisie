    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Facture fournisseur {{ $purchaseInvoice->invoice_number }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                        {{ match($purchaseInvoice->status) {
                            \App\Enums\PurchaseInvoiceStatus::DRAFT => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                            \App\Enums\PurchaseInvoiceStatus::POSTED => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                            \App\Enums\PurchaseInvoiceStatus::CANCELLED => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                        } }}">
                        {{ $purchaseInvoice->status->label() }}
                    </span>
                </p>
            </div>
            <div class="flex items-center gap-3">
                @if ($this->isDraft)
                    <a href="{{ route('purchase-invoices.edit', $purchaseInvoice->id) }}" class="flux-btn-primary" wire:navigate>Modifier</a>
                    <button wire:click="post" wire:confirm="Voulez-vous vraiment comptabiliser cette facture fournisseur ?" class="flux-btn-green">Comptabiliser</button>
                    <button wire:click="cancel" wire:confirm="Voulez-vous vraiment annuler cette facture fournisseur ?" class="flux-btn-orange">Annuler</button>
                    <button wire:click="delete" wire:confirm="Voulez-vous vraiment supprimer cette facture fournisseur ? Cette action est irréversible." class="flux-btn-red">Supprimer</button>
                @endif
                @if ($this->isPosted)
                    <a href="{{ route('supplier-payments.create', ['invoice' => $purchaseInvoice->id]) }}" class="flux-btn-green" wire:navigate>Enregistrer un règlement</a>
                @endif
                <a href="{{ route('purchase-invoices.index') }}" class="text-sm text-flux-600 hover:text-flux-500 dark:text-flux-400" wire:navigate>Retour à la liste</a>
            </div>
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

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="flux-card">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Informations générales</h2>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Numéro interne :</dt>
                        <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ $purchaseInvoice->invoice_number }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">N° de facture du fournisseur :</dt>
                        <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ $purchaseInvoice->supplier_invoice_number ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Fournisseur :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $purchaseInvoice->supplier->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Date :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $purchaseInvoice->invoice_date->format('d/m/Y') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Échéance :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $purchaseInvoice->due_date ? $purchaseInvoice->due_date->format('d/m/Y') : '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Devise :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $purchaseInvoice->currency }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Journal :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $purchaseInvoice->journal->code }} — {{ $purchaseInvoice->journal->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Exercice :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $purchaseInvoice->fiscalYear->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Période :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $purchaseInvoice->accountingPeriod->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Créé par :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $purchaseInvoice->creator->name }}</dd>
                    </div>
                </dl>
            </div>

            <div class="flux-card">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Totaux</h2>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Sous-total HT :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ number_format((float) $purchaseInvoice->subtotal, 3, '.', '') }} {{ $purchaseInvoice->currency }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Remise totale :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ number_format((float) $purchaseInvoice->discount_total, 3, '.', '') }} {{ $purchaseInvoice->currency }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">TVA totale :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ number_format((float) $purchaseInvoice->tax_total, 3, '.', '') }} {{ $purchaseInvoice->currency }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-gray-200 pt-3 dark:border-gray-700">
                        <dt class="text-base font-semibold text-gray-900 dark:text-white">Total TTC :</dt>
                        <dd class="text-base font-semibold text-gray-900 dark:text-white">{{ number_format((float) $purchaseInvoice->total, 3, '.', '') }} {{ $purchaseInvoice->currency }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        @if ($purchaseInvoice->notes)
            <div class="flux-card">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Notes</h2>
                <p class="text-sm text-gray-900 dark:text-white whitespace-pre-line">{{ $purchaseInvoice->notes }}</p>
            </div>
        @endif

        <div class="flux-card">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Lignes de facture</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">#</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Produit</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Description</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Qté</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Prix unit.</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Remise</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Sous-total</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">TVA</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        @foreach($purchaseInvoice->lines as $line)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $line->sort_order }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $line->product->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $line->description }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900 dark:text-white">{{ number_format((float) $line->quantity, 3, '.', '') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900 dark:text-white">{{ number_format((float) $line->unit_price, 3, '.', '') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-500 dark:text-gray-400">{{ number_format((float) $line->discount_percent, 1, '.', '') }}%</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900 dark:text-white">{{ number_format((float) $line->line_subtotal, 3, '.', '') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-500 dark:text-gray-400">{{ number_format((float) $line->tax_amount, 3, '.', '') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">{{ number_format((float) $line->line_total, 3, '.', '') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($this->isPosted && $purchaseInvoice->journalEntry)
            <div class="flux-card">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Écriture comptable associée</h2>
                <div class="mb-4">
                    <span class="text-sm text-gray-500 dark:text-gray-400">N° {{ $purchaseInvoice->journalEntry->entry_number }} — {{ $purchaseInvoice->journalEntry->entry_date->format('d/m/Y') }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Compte</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Description</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Débit</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Crédit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                            @foreach($purchaseInvoice->journalEntry->lines as $jeLine)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $jeLine->account->code }} — {{ $jeLine->account->name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $jeLine->description }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900 dark:text-white">
                                        {{ bccomp((string) $jeLine->debit, '0', 3) > 0 ? number_format((float) $jeLine->debit, 3, '.', '') : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900 dark:text-white">
                                        {{ bccomp((string) $jeLine->credit, '0', 3) > 0 ? number_format((float) $jeLine->credit, 3, '.', '') : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-white text-right">Totaux :</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $purchaseInvoice->journalEntry->totalDebit() }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $purchaseInvoice->journalEntry->totalCredit() }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif
    </div>
