<x-layouts::app :title="__('Modifier le devis')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Modifier le devis</h1>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Brouillon en cours de modification.</p>
            </div>
        </div>

        <form wire:submit="update" class="max-w-5xl space-y-6">

            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">Informations générales</h2>
                <div class="mt-5 space-y-5">

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="customer_id" class="mb-1 block text-sm font-medium">Client *</label>
                            <select id="customer_id" wire:model="customer_id"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                                <option value="">— Sélectionner un client —</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                @endforeach
                            </select>
                            @error('customer_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="currency" class="mb-1 block text-sm font-medium">Devise *</label>
                            <input id="currency" type="text" wire:model="currency" maxlength="3"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                            @error('currency') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-5 md:grid-cols-3">
                        <div>
                            <label for="quote_date" class="mb-1 block text-sm font-medium">Date du devis *</label>
                            <input id="quote_date" type="date" wire:model="quote_date"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                            @error('quote_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="valid_until" class="mb-1 block text-sm font-medium">Date de validité</label>
                            <input id="valid_until" type="date" wire:model="valid_until"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                            @error('valid_until') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="payment_terms_days" class="mb-1 block text-sm font-medium">Conditions de paiement (jours)</label>
                            <input id="payment_terms_days" type="number" wire:model="payment_terms_days" min="0"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                            @error('payment_terms_days') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Lignes du devis</h2>
                    <button type="button" wire:click="addLine"
                        class="rounded-lg border border-neutral-300 px-3 py-1.5 text-sm font-medium transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800">
                        + Ajouter une ligne
                    </button>
                </div>

                @error('lines') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                <div class="mt-5 space-y-4">
                    @foreach ($lines as $index => $line)
                        <div class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700" wire:key="{{ $line['_key'] }}">
                            <div class="grid gap-3 md:grid-cols-12">
                                <div class="md:col-span-3">
                                    <label class="mb-1 block text-xs font-medium text-neutral-500">Produit *</label>
                                    <select wire:model="lines.{{ $index }}.product_id" wire:change="onProductSelect({{ $index }}, $event.target.value)"
                                        class="w-full rounded-lg border border-neutral-300 px-2 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                                        <option value="">—</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}">{{ $product->code }} — {{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                    @error("lines.{$index}.product_id") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label class="mb-1 block text-xs font-medium text-neutral-500">Description</label>
                                    <input type="text" wire:model="lines.{{ $index }}.description"
                                        class="w-full rounded-lg border border-neutral-300 px-2 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                                </div>

                                <div class="md:col-span-1">
                                    <label class="mb-1 block text-xs font-medium text-neutral-500">Qté *</label>
                                    <input type="text" wire:model="lines.{{ $index }}.quantity"
                                        class="w-full rounded-lg border border-neutral-300 px-2 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                                    @error("lines.{$index}.quantity") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div class="md:col-span-1">
                                    <label class="mb-1 block text-xs font-medium text-neutral-500">Unité</label>
                                    <input type="text" wire:model="lines.{{ $index }}.unit"
                                        class="w-full rounded-lg border border-neutral-300 px-2 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                                </div>

                                <div class="md:col-span-1">
                                    <label class="mb-1 block text-xs font-medium text-neutral-500">Prix unit.</label>
                                    <input type="text" wire:model="lines.{{ $index }}.unit_price"
                                        class="w-full rounded-lg border border-neutral-300 px-2 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                                    @error("lines.{$index}.unit_price") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div class="md:col-span-1">
                                    <label class="mb-1 block text-xs font-medium text-neutral-500">Remise %</label>
                                    <input type="text" wire:model="lines.{{ $index }}.discount_percent"
                                        class="w-full rounded-lg border border-neutral-300 px-2 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                                </div>

                                <div class="md:col-span-2">
                                    <label class="mb-1 block text-xs font-medium text-neutral-500">TVA</label>
                                    <select wire:model="lines.{{ $index }}.tax_rate_id"
                                        class="w-full rounded-lg border border-neutral-300 px-2 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                                        <option value="">—</option>
                                        @foreach ($taxRates as $taxRate)
                                            <option value="{{ $taxRate->id }}">{{ $taxRate->name }} ({{ $taxRate->rate }}%)</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="flex items-end md:col-span-1">
                                    @if (count($lines) > 1)
                                        <button type="button" wire:click="removeLine({{ $index }})"
                                            class="rounded-md px-2 py-2 text-sm text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950" title="Supprimer la ligne">
                                            ✕
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">Notes & Conditions</h2>
                <div class="mt-5 space-y-5">
                    <div>
                        <label for="notes" class="mb-1 block text-sm font-medium">Notes internes</label>
                        <textarea id="notes" wire:model="notes" rows="3"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"></textarea>
                        @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="terms" class="mb-1 block text-sm font-medium">Conditions générales</label>
                        <textarea id="terms" wire:model="terms" rows="3"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"></textarea>
                        @error('terms') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('quotes.index') }}" wire:navigate
                    class="rounded-lg border border-neutral-300 px-4 py-2.5 text-sm font-medium hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800">
                    Annuler
                </a>

                <button type="submit"
                    class="rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-neutral-800 dark:bg-white dark:text-neutral-900">
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</x-layouts::app>
