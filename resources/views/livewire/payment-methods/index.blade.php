<x-layouts::app :title="__('Moyens de paiement')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Moyens de paiement
                </h1>

                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    @if ($currentCompany)
                        Moyens de paiement de « {{ $currentCompany->name }} ».
                    @else
                        Sélectionnez une société pour voir les moyens de paiement.
                    @endif
                </p>
            </div>

            @if ($currentCompany)
                <div class="flex items-center gap-2">
                    <button
                        wire:click="initializeDefaults"
                        wire:confirm="Initialiser les moyens de paiement par défaut ? Les moyens existants ne seront pas écrasés."
                        class="inline-flex items-center rounded-lg border border-neutral-200 px-3 py-2 text-sm font-medium transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                    >
                        Initialiser les moyens de paiement
                    </button>

                    <a
                        href="{{ route('payment-methods.create') }}"
                        wire:navigate
                        class="inline-flex items-center justify-center rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200"
                    >
                        + Nouveau moyen
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
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Rechercher par code ou nom..."
                    class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800 sm:w-72"
                >

                <select
                    wire:model.live="filterType"
                    class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                >
                    <option value="">Tous les types</option>
                    @foreach ($paymentMethodTypes as $type)
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

            {{-- Payment methods list --}}
            @if ($paymentMethods->isEmpty())
                <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                    <h2 class="text-lg font-semibold">
                        Aucun moyen de paiement
                    </h2>

                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                        Initialisez les moyens de paiement par défaut ou créez un moyen manuellement.
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
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Ordre</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">État</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Défaut</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($paymentMethods as $method)
                                <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium">
                                        {{ $method->code }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        {{ $method->name }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        {{ $method->type->label() }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-neutral-500">
                                        {{ $method->sort_order }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        @if ($method->is_active)
                                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200">
                                                Actif
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">
                                                Inactif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        @if ($method->is_default)
                                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                Par défaut
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                        <div class="flex items-center justify-end gap-2">
                                            @if (Auth::user()->can('update', $method))
                                                <a
                                                    href="{{ route('payment-methods.edit', $method->id) }}"
                                                    wire:navigate
                                                    class="rounded-md px-2 py-1 text-sm text-neutral-600 transition hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800"
                                                >
                                                    Modifier
                                                </a>
                                            @endif

                                            @if (! $method->is_default && Auth::user()->can('setDefault', $method))
                                                <button
                                                    wire:click="setDefault({{ $method->id }})"
                                                    wire:confirm="Définir « {{ $method->name }} » comme moyen de paiement par défaut ?"
                                                    class="rounded-md px-2 py-1 text-sm text-blue-600 transition hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950"
                                                >
                                                    Définir par défaut
                                                </button>
                                            @endif

                                            @if (Auth::user()->can('activate', $method) || Auth::user()->can('deactivate', $method))
                                                <button
                                                    wire:click="toggleActive({{ $method->id }})"
                                                    wire:confirm="{{ $method->is_active ? 'Désactiver ce moyen de paiement ?' : 'Activer ce moyen de paiement ?' }}"
                                                    class="rounded-md px-2 py-1 text-sm transition hover:bg-neutral-100 dark:hover:bg-neutral-800 {{ $method->is_active ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}"
                                                >
                                                    {{ $method->is_active ? 'Désactiver' : 'Activer' }}
                                                </button>
                                            @endif

                                            @if (Auth::user()->can('delete', $method))
                                                <button
                                                    wire:click="delete({{ $method->id }})"
                                                    wire:confirm="Supprimer le moyen de paiement « {{ $method->name }} » ?"
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
            @endif
        @endif

    </div>
</x-layouts::app>
