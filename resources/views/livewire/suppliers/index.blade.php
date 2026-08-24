<x-layouts::app :title="__('Fournisseurs')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Fournisseurs
                </h1>

                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    @if ($currentCompany)
                        Fournisseurs de « {{ $currentCompany->name }} ».
                    @else
                        Sélectionnez une société pour voir les fournisseurs.
                    @endif
                </p>
            </div>

            @if ($currentCompany)
    @can('create', [App\Models\Supplier::class, $currentCompany])
                <div class="flex items-center gap-2">
                    <a
                        href="{{ route('suppliers.create') }}"
                        wire:navigate
                        class="inline-flex items-center justify-center rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200"
                    >
                        + Nouveau fournisseur
                    </a>
                </div>
    @endcan
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
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Rechercher par code, nom, email, téléphone..."
                    class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800 sm:w-96"
                >

                <select
                    wire:model.live="filterType"
                    class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                >
                    <option value="">Tous les types</option>
                    @foreach ($supplierTypes as $type)
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
            </div>

            {{-- Suppliers list --}}
            @if ($suppliers->isEmpty())
                <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                    <h2 class="text-lg font-semibold">
                        Aucun fournisseur
                    </h2>

                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                        Créez un nouveau fournisseur pour commencer.
                    </p>
                </div>
            @else
                <div class="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-neutral-900 dark:border-neutral-700">
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                        <thead class="bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Code</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Nom</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Identifiant fiscal</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Ville</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Téléphone</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Compte</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">État</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($suppliers as $supplier)
                                <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium">
                                        <a
                                            href="{{ route('suppliers.show', $supplier->id) }}"
                                            wire:navigate
                                            class="text-neutral-900 hover:underline dark:text-neutral-100"
                                        >
                                            {{ $supplier->code }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        {{ $supplier->name }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        {{ $supplier->supplier_type->label() }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-neutral-500">
                                        {{ $supplier->tax_identifier ?: '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-neutral-500">
                                        {{ $supplier->city ?: '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-neutral-500">
                                        {{ $supplier->phone ?: ($supplier->mobile ?: '—') }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-neutral-500">
                                        {{ $supplier->email ?: '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-neutral-500">
                                        {{ $supplier->account ? $supplier->account->code : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        @if ($supplier->is_active)
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
                                                href="{{ route('suppliers.show', $supplier->id) }}"
                                                wire:navigate
                                                class="rounded-md px-2 py-1 text-sm text-neutral-600 transition hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800"
                                            >
                                                Voir
                                            </a>

                                            @if (Auth::user()->can('update', $supplier))
                                                <a
                                                    href="{{ route('suppliers.edit', $supplier->id) }}"
                                                    wire:navigate
                                                    class="rounded-md px-2 py-1 text-sm text-neutral-600 transition hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800"
                                                >
                                                    Modifier
                                                </a>
                                            @endif

                                            @if (Auth::user()->can('activate', $supplier) || Auth::user()->can('deactivate', $supplier))
                                                <button
                                                    wire:click="toggleActive({{ $supplier->id }})"
                                                    wire:confirm="{{ $supplier->is_active ? 'Désactiver ce fournisseur ?' : 'Activer ce fournisseur ?' }}"
                                                    class="rounded-md px-2 py-1 text-sm transition hover:bg-neutral-100 dark:hover:bg-neutral-800 {{ $supplier->is_active ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}"
                                                >
                                                    {{ $supplier->is_active ? 'Désactiver' : 'Activer' }}
                                                </button>
                                            @endif

                                            @if (Auth::user()->can('delete', $supplier))
                                                <button
                                                    wire:click="delete({{ $supplier->id }})"
                                                    wire:confirm="Supprimer le fournisseur « {{ $supplier->name }} » ?"
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
                    {{ $suppliers->links() }}
                </div>
            @endif
        @endif

    </div>
</x-layouts::app>
