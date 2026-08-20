<x-layouts::app :title="__('Produit : ' . $product->name)">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ $product->name }}
                </h1>

                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    {{ $product->code }} — {{ $product->type->label() }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                @if (Auth::user()->can('update', $product))
                    <a
                        href="{{ route('products.edit', $product->id) }}"
                        wire:navigate
                        class="inline-flex items-center justify-center rounded-lg border border-neutral-200 px-4 py-2.5 text-sm font-medium transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                    >
                        Modifier
                    </a>
                @endif

                <a
                    href="{{ route('products.index') }}"
                    wire:navigate
                    class="rounded-lg border border-neutral-300 px-4 py-2.5 text-sm font-medium hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                >
                    Retour à la liste
                </a>
            </div>
        </div>

        {{-- Status --}}
        @if (! $product->is_active)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                Ce produit/service est inactif.
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">

            {{-- Identity --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Identité
                </h2>

                <dl class="mt-4 space-y-3">
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Code</dt>
                        <dd class="font-medium">{{ $product->code }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Nom</dt>
                        <dd class="font-medium">{{ $product->name }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Type</dt>
                        <dd class="font-medium">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $product->type->value === 'product' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' }}">
                                {{ $product->type->label() }}
                            </span>
                        </dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Unité</dt>
                        <dd class="font-medium">{{ $product->unit }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Statut</dt>
                        <dd>
                            @if ($product->is_active)
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200">
                                    Actif
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">
                                    Inactif
                                </span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Description --}}
            @if ($product->description)
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <h2 class="text-lg font-semibold">
                        Description
                    </h2>
                    <p class="mt-3 text-sm text-neutral-700 dark:text-neutral-300 whitespace-pre-line">
                        {{ $product->description }}
                    </p>
                </div>
            @endif

            {{-- Pricing --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Prix
                </h2>

                <dl class="mt-4 space-y-3">
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Prix d'achat</dt>
                        <dd class="font-medium">
                            {{ $product->purchase_price ? number_format((float) $product->purchase_price, 3, ',', '.') . ' TND' : '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Prix de vente</dt>
                        <dd class="font-medium">
                            {{ $product->sale_price ? number_format((float) $product->sale_price, 3, ',', '.') . ' TND' : '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Accounting --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Comptabilité
                </h2>

                <dl class="mt-4 space-y-3">
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Taux de TVA</dt>
                        <dd class="font-medium">
                            @if ($product->taxRate)
                                {{ $product->taxRate->name }} ({{ $product->taxRate->rate }}%)
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Compte de vente</dt>
                        <dd class="font-medium">
                            @if ($product->salesAccount)
                                {{ $product->salesAccount->code }} — {{ $product->salesAccount->name }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Compte d'achat</dt>
                        <dd class="font-medium">
                            @if ($product->purchaseAccount)
                                {{ $product->purchaseAccount->code }} — {{ $product->purchaseAccount->name }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Availability --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Disponibilité
                </h2>

                <dl class="mt-4 space-y-3">
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Vendable</dt>
                        <dd class="font-medium">
                            @if ($product->is_sellable)
                                <span class="text-green-600 dark:text-green-400">Oui</span>
                            @else
                                <span class="text-neutral-400">Non</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Achetable</dt>
                        <dd class="font-medium">
                            @if ($product->is_purchasable)
                                <span class="text-green-600 dark:text-green-400">Oui</span>
                            @else
                                <span class="text-neutral-400">Non</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

        </div>

        {{-- Future sections placeholders --}}
        <div class="rounded-xl border border-dashed border-neutral-300 p-6 text-center dark:border-neutral-700">
            <h2 class="text-lg font-semibold text-neutral-400 dark:text-neutral-500">
                Historique des ventes, achats et mouvements de stock
            </h2>
            <p class="mt-2 text-sm text-neutral-400 dark:text-neutral-500">
                Ces sections seront disponibles lorsque les modules de facturation et de stock seront implémentés.
            </p>
        </div>

    </div>
</x-layouts::app>
