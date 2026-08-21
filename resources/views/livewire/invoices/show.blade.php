    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Facture {{ $invoice->invoice_number }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                        {{ match($invoice->status) {
                            \App\Enums\InvoiceStatus::DRAFT => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                            \App\Enums\InvoiceStatus::POSTED => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                            \App\Enums\InvoiceStatus::CANCELLED => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                        } }}">
                        {{ $invoice->status->label() }}
                    </span>
                </p>
            </div>
            <div class="flex items-center gap-3">
                @if ($this->isDraft)
                    <a href="{{ route('invoices.edit', $invoice->id) }}" class="flux-btn-primary" wire:navigate>Modifier</a>
                    <button wire:click="post" wire:confirm="Voulez-vous vraiment comptabiliser cette facture ?" class="flux-btn-green">Comptabiliser</button>
                    <button wire:click="cancel" wire:confirm="Voulez-vous vraiment annuler cette facture ?" class="flux-btn-orange">Annuler</button>
                    <button wire:click="delete" wire:confirm="Voulez-vous vraiment supprimer cette facture ? Cette action est irréversible." class="flux-btn-red">Supprimer</button>
                @endif
                <a href="{{ route('invoices.index') }}" class="text-sm text-flux-600 hover:text-flux-500 dark:text-flux-400" wire:navigate>Retour à la liste</a>
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
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Numéro :</dt>
                        <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ $invoice->invoice_number }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Client :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $invoice->customer->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Date :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $invoice->invoice_date->format('d/m/Y') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Échéance :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Devise :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $invoice->currency }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Journal :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $invoice->journal->code }} — {{ $invoice->journal->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Exercice :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $invoice->fiscalYear->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Période :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $invoice->accountingPeriod->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Créé par :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $invoice->creator->name }}</dd>
                    </div>
                    @if ($invoice->quote)
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Devis d'origine :</dt>
                            <dd class="text-sm text-gray-900 dark:text-white">{{ $invoice->quote->quote_number }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="flux-card">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Totaux</h2>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Sous-total HT :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ number_format((float) $invoice->subtotal, 3, '.', '') }} {{ $invoice->currency }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Remise totale :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ number_format((float) $invoice->discount_total, 3, '.', '') }} {{ $invoice->currency }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">TVA totale :</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ number_format((float) $invoice->tax_total, 3, '.', '') }} {{ $invoice->currency }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-gray-200 pt-3 dark:border-gray-700">
                        <dt class="text-base font-semibold text-gray-900 dark:text-white">Total TTC :</dt>
                        <dd class="text-base font-semibold text-gray-900 dark:text-white">{{ number_format((float) $invoice->total, 3, '.', '') }} {{ $invoice->currency }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        @if ($invoice->notes || $invoice->terms)
            <div class="flux-card">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Notes et conditions</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @if ($invoice->notes)
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Notes</h3>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white whitespace-pre-line">{{ $invoice->notes }}</p>
                        </div>
                    @endif
                    @if ($invoice->terms)
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Conditions générales</h3>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white whitespace-pre-line">{{ $invoice->terms }}</p>
                        </div>
                    @endif
                </div>
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
                        @foreach($invoice->lines as $line)
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

        @if ($this->isPosted && $invoice->journalEntry)
            <div class="flux-card">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Écriture comptable associée</h2>
                <div class="mb-4">
                    <span class="text-sm text-gray-500 dark:text-gray-400">N° {{ $invoice->journalEntry->entry_number }} — {{ $invoice->journalEntry->entry_date->format('d/m/Y') }}</span>
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
                            @foreach($invoice->journalEntry->lines as $jeLine)
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
                                    {{ $invoice->journalEntry->totalDebit() }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $invoice->journalEntry->totalCredit() }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif
    </div>
