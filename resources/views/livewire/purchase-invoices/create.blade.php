    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Nouvelle facture fournisseur</h1>
            <a href="{{ route('purchase-invoices.index') }}" class="text-sm text-flux-600 hover:text-flux-500 dark:text-flux-400" wire:navigate>Retour à la liste</a>
        </div>

        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-4 dark:bg-red-900/20">
                <p class="text-sm font-medium text-red-700 dark:text-red-400">Veuillez corriger les erreurs suivantes :</p>
                <ul class="mt-2 list-inside list-disc text-sm text-red-600 dark:text-red-400">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form wire:submit="store" class="space-y-6">
            <div class="flux-card">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Informations générales</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <flux:select wire:model="supplier_id" label="Fournisseur *" placeholder="Sélectionner un fournisseur" wire:change="onSupplierSelect($event.target.value)">
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div>
                        <flux:input wire:model="invoice_date" type="date" label="Date de facture *" />
                    </div>
                    <div>
                        <flux:input wire:model="due_date" type="date" label="Date d'échéance" />
                    </div>
                    <div>
                        <flux:input wire:model="supplier_invoice_number" label="N° de facture du fournisseur" maxlength="100" />
                    </div>
                    <div>
                        <flux:select wire:model="journal_id" label="Journal *" placeholder="Sélectionner un journal">
                            @foreach($journals as $journal)
                                <option value="{{ $journal->id }}">{{ $journal->code }} - {{ $journal->name }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div>
                        <flux:input wire:model="payment_terms_days" type="number" label="Délai de paiement (jours) *" min="0" />
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <flux:textarea wire:model="notes" label="Notes" rows="3" />
                    </div>
                </div>
            </div>

            <div class="flux-card">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Lignes de facture</h2>
                    <button type="button" wire:click="addLine" class="text-sm text-flux-600 hover:text-flux-500 dark:text-flux-400">
                        + Ajouter une ligne
                    </button>
                </div>

                <div class="space-y-4">
                    @foreach($lines as $index => $line)
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
                                <div class="sm:col-span-4">
                                    <flux:select wire:model="lines.{{ $index }}.product_id" label="Produit *" placeholder="Sélectionner un produit" wire:change="onProductSelect({{ $index }}, $event.target.value)">
                                        @foreach($products as $product)
                                            <option value="{{ $product->id }}">{{ $product->code }} - {{ $product->name }}</option>
                                        @endforeach
                                    </flux:select>
                                </div>
                                <div class="sm:col-span-3">
                                    <flux:input wire:model="lines.{{ $index }}.description" label="Description" />
                                </div>
                                <div class="sm:col-span-1">
                                    <flux:input wire:model="lines.{{ $index }}.quantity" label="Qté *" />
                                </div>
                                <div class="sm:col-span-1">
                                    <flux:input wire:model="lines.{{ $index }}.unit" label="Unité *" />
                                </div>
                                <div class="sm:col-span-1">
                                    <flux:input wire:model="lines.{{ $index }}.unit_price" label="Prix unit. *" />
                                </div>
                                <div class="sm:col-span-1">
                                    <flux:input wire:model="lines.{{ $index }}.discount_percent" label="Remise %" />
                                </div>
                                <div class="sm:col-span-1">
                                    <flux:select wire:model="lines.{{ $index }}.tax_rate_id" label="TVA" placeholder="Aucune">
                                        @foreach($taxRates as $taxRate)
                                            <option value="{{ $taxRate->id }}">{{ $taxRate->code }} ({{ $taxRate->rate }}%)</option>
                                        @endforeach
                                    </flux:select>
                                </div>
                            </div>
                            @if (count($lines) > 1)
                                <div class="mt-2 text-right">
                                    <button type="button" wire:click="removeLine({{ $index }})" class="text-sm text-red-600 hover:text-red-500 dark:text-red-400">Supprimer cette ligne</button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <button type="submit" wire:loading.attr="disabled" class="flux-btn-primary">
                    Enregistrer le brouillon
                </button>
                <button type="button" wire:click="$set('saveAndPost', true)" wire:loading.attr="disabled" class="flux-btn-green">
                    Enregistrer et comptabiliser
                </button>
            </div>
        </form>
    </div>
