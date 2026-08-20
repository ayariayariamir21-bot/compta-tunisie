<x-layouts::app :title="__('Nouveau produit/service')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Nouveau produit/service
                </h1>

                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    Ajoutez un nouveau produit ou service au catalogue.
                </p>
            </div>
        </div>

        <form wire:submit="store" class="max-w-4xl space-y-6">

            {{-- Identity --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Identité
                </h2>

                <div class="mt-5 space-y-5">

                    <div class="grid gap-5 md:grid-cols-3">
                        <div>
                            <label for="code" class="mb-1 block text-sm font-medium">Code *</label>
                            <div class="flex gap-2">
                                <input
                                    id="code"
                                    type="text"
                                    wire:model="code"
                                    class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                    placeholder="Ex: PRD001"
                                >
                                <button
                                    type="button"
                                    wire:click="generateCode"
                                    wire:loading.attr="disabled"
                                    class="shrink-0 rounded-lg border border-neutral-300 px-3 py-2.5 text-sm font-medium transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                                    title="Générer un code unique"
                                >
                                    Générer
                                </button>
                            </div>
                            @error('code')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="name" class="mb-1 block text-sm font-medium">Nom *</label>
                            <input
                                id="name"
                                type="text"
                                wire:model="name"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                placeholder="Nom du produit/service"
                            >
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="type" class="mb-1 block text-sm font-medium">Type *</label>
                            <select
                                id="type"
                                wire:model="type"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
                                <option value="">— Sélectionner —</option>
                                @foreach ($productTypes as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                            @error('type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="description" class="mb-1 block text-sm font-medium">Description</label>
                            <textarea
                                id="description"
                                wire:model="description"
                                rows="3"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                placeholder="Description du produit/service..."
                            ></textarea>
                            @error('description')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="unit" class="mb-1 block text-sm font-medium">Unité *</label>
                            <input
                                id="unit"
                                type="text"
                                wire:model="unit"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                placeholder="unit"
                            >
                            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Ex: unit, pièce, heure, kg, litre, jour, service</p>
                            @error('unit')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                </div>
            </div>

            {{-- Pricing --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Prix
                </h2>

                <div class="mt-5 space-y-5">

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="purchase_price" class="mb-1 block text-sm font-medium">Prix d'achat (TND)</label>
                            <input
                                id="purchase_price"
                                type="number"
                                wire:model="purchase_price"
                                min="0"
                                step="0.001"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                placeholder="0.000"
                            >
                            @error('purchase_price')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="sale_price" class="mb-1 block text-sm font-medium">Prix de vente (TND)</label>
                            <input
                                id="sale_price"
                                type="number"
                                wire:model="sale_price"
                                min="0"
                                step="0.001"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                placeholder="0.000"
                            >
                            @error('sale_price')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                </div>
            </div>

            {{-- Accounting --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Comptabilité
                </h2>

                <div class="mt-5 space-y-5">

                    <div class="grid gap-5 md:grid-cols-3">
                        <div>
                            <label for="tax_rate_id" class="mb-1 block text-sm font-medium">Taux de TVA</label>
                            <select
                                id="tax_rate_id"
                                wire:model="tax_rate_id"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
                                <option value="">— Aucun taux —</option>
                                @foreach ($taxRates as $taxRate)
                                    <option value="{{ $taxRate->id }}">{{ $taxRate->name }} ({{ $taxRate->rate }}%)</option>
                                @endforeach
                            </select>
                            @error('tax_rate_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="sales_account_id" class="mb-1 block text-sm font-medium">Compte de vente</label>
                            <select
                                id="sales_account_id"
                                wire:model="sales_account_id"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
                                <option value="">— Aucun compte —</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                @endforeach
                            </select>
                            @error('sales_account_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="purchase_account_id" class="mb-1 block text-sm font-medium">Compte d'achat</label>
                            <select
                                id="purchase_account_id"
                                wire:model="purchase_account_id"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
                                <option value="">— Aucun compte —</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                @endforeach
                            </select>
                            @error('purchase_account_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                </div>
            </div>

            {{-- Availability --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Disponibilité
                </h2>

                <div class="mt-5 space-y-4">

                    <div class="flex items-center gap-2">
                        <input
                            id="is_active"
                            type="checkbox"
                            wire:model="is_active"
                            class="h-4 w-4 rounded border-neutral-300"
                        >
                        <label for="is_active" class="text-sm font-medium">Produit/service actif</label>
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="is_sellable"
                            type="checkbox"
                            wire:model="is_sellable"
                            class="h-4 w-4 rounded border-neutral-300"
                        >
                        <label for="is_sellable" class="text-sm font-medium">Vendable (peut apparaître sur les factures de vente)</label>
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="is_purchasable"
                            type="checkbox"
                            wire:model="is_purchasable"
                            class="h-4 w-4 rounded border-neutral-300"
                        >
                        <label for="is_purchasable" class="text-sm font-medium">Achetable (peut apparaître sur les factures d'achat)</label>
                    </div>

                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a
                    href="{{ route('products.index') }}"
                    wire:navigate
                    class="rounded-lg border border-neutral-300 px-4 py-2.5 text-sm font-medium hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                >
                    Annuler
                </a>

                <button
                    type="submit"
                    class="rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-neutral-800 dark:bg-white dark:text-neutral-900"
                >
                    Créer le produit/service
                </button>
            </div>

        </form>

    </div>
</x-layouts::app>
