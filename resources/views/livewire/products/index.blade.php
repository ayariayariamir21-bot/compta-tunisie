<x-layouts::app :title="__('Produits & Services')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Produits & Services
                </h1>

                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    @if ($currentCompany)
                        Catalogue de « {{ $currentCompany->name }} ».
                    @else
                        Sélectionnez une société pour voir les produits et services.
                    @endif
                </p>
            </div>

            @if ($currentCompany)
                <div class="flex items-center gap-2">
                    <a
                        href="{{ route('products.create') }}"
                        wire:navigate
                        class="inline-flex items-center justify-center rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200"
                    >
                        + Nouveau produit/service
                    </a>
                </div>
            @endif
        </div>

        {{-- Success message --}}
        @if (session()->has('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        {{-- Error message --}}
        @if (session()->has('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                {{ session('error') }}
            </div>
        @endif

        @if (! $currentCompany)
            <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                <h2 class="text-lg font-semibold">
                    Aucune société sélectionnée
                </h2>

                <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                    Sélectionnez une société dans le menu latéral.
                </p>
            </div>
        @else
            {{-- Filters --}}
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:flex-wrap">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Rechercher par code, nom, description..."
                    class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800 sm:w-96"
                >

                <select
                    wire:model.live="filterType"
                    class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                >
                    <option value="">Tous les types</option>
                    @foreach ($productTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>

                <select
                    wire:model.live="filterActive"
                    class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                >
                    <option value="">Tous les états</option>
                    <option value="1">Actifs</option>
                    <option value="0">Inactifs</option>
                </select>

                <select
                    wire:model.live="filterSellable"
                    class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                >
                    <option value="">Vendable (tous)</option>
                    <option value="1">Vendable</option>
                    <option value="0">Non vendable</option>
                </select>

                <select
                    wire:model.live="filterPurchasable"
                    class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                >
                    <option value="">Achetable (tous)</option>
                    <option value="1">Achetable</option>
                    <option value="0">Non achetable</option>
                </select>
            </div>

            {{-- Products list --}}
            @if ($products->isEmpty())
                <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                    <h2 class="text-lg font-semibold">
                        Aucun produit/service
                    </h2>

                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                        Créez un nouveau produit ou service pour commencer.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border bg-white shadow-sm dark:bg-neutral-900 dark:border-neutral-700">
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                        <thead class="bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Code</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Nom</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Unité</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Prix achat</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Prix vente</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">TVA</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Vendable</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Achetable</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">État</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($products as $product)
                                <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium">
                                        <a
                                            href="{{ route('products.show', $product->id) }}"
                                            wire:navigate
                                            class="text-neutral-900 hover:underline dark:text-neutral-100"
                                        >
                                            {{ $product->code }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        {{ $product->name }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $product->type->value === 'product' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' }}">
                                            {{ $product->type->label() }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-neutral-500">
                                        {{ $product->unit }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-neutral-500">
                                        {{ $product->purchase_price ? number_format((float) $product->purchase_price, 3, ',', '.') : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-neutral-500">
                                        {{ $product->sale_price ? number_format((float) $product->sale_price, 3, ',', '.') : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-neutral-500">
                                        {{ $product->taxRate ? $product->taxRate->name : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        @if ($product->is_sellable)
                                            <span class="text-green-600 dark:text-green-400">✓</span>
                                        @else
                                            <span class="text-neutral-400">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        @if ($product->is_purchasable)
                                            <span class="text-green-600 dark:text-green-400">✓</span>
                                        @else
                                            <span class="text-neutral-400">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        @if ($product->is_active)
                                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200">
                                                Actif
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">
                                                Inactif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                        <div class="flex items-center justify-end gap-2">
                                            <a
                                                href="{{ route('products.show', $product->id) }}"
                                                wire:navigate
                                                class="rounded-md px-2 py-1 text-sm text-neutral-600 transition hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800"
                                            >
                                                Voir
                                            </a>

                                            @if (Auth::user()->can('update', $product))
                                                <a
                                                    href="{{ route('products.edit', $product->id) }}"
                                                    wire:navigate
                                                    class="rounded-md px-2 py-1 text-sm text-neutral-600 transition hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800"
                                                >
                                                    Modifier
                                                </a>
                                            @endif

                                            @if (Auth::user()->can('activate', $product) || Auth::user()->can('deactivate', $product))
                                                <button
                                                    wire:click="toggleActive({{ $product->id }})"
                                                    wire:confirm="{{ $product->is_active ? 'Désactiver ce produit/service ?' : 'Activer ce produit/service ?' }}"
                                                    class="rounded-md px-2 py-1 text-sm transition hover:bg-neutral-100 dark:hover:bg-neutral-800 {{ $product->is_active ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}"
                                                >
                                                    {{ $product->is_active ? 'Désactiver' : 'Activer' }}
                                                </button>
                                            @endif

                                            @if (Auth::user()->can('delete', $product))
                                                <button
                                                    wire:click="delete({{ $product->id }})"
                                                    wire:confirm="Supprimer le produit/service « {{ $product->name }} » ?"
                                                    class="rounded-md px-2 py-1 text-sm text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950"
                                                >
                                                    Supprimer
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-2">
                    {{ $products->links() }}
                </div>
            @endif
        @endif

    </div>
</x-layouts::app>
