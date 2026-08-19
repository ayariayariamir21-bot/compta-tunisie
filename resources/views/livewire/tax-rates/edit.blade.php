<x-layouts::app :title="__('Modifier le taux de taxe')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Modifier le taux de taxe
                </h1>

                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    {{ $taxRate->code }} — {{ $taxRate->name }}
                </p>
            </div>
        </div>

        <form wire:submit="update" class="max-w-2xl space-y-6">

            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Informations du taux de taxe
                </h2>

                <div class="mt-5 space-y-5">

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="code" class="mb-1 block text-sm font-medium">Code *</label>
                            <input
                                id="code"
                                type="text"
                                wire:model="code"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
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
                            >
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="rate" class="mb-1 block text-sm font-medium">Taux (%) *</label>
                            <input
                                id="rate"
                                type="text"
                                wire:model="rate"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
                            @error('rate')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="type" class="mb-1 block text-sm font-medium">Type de taxe *</label>
                            <select
                                id="type"
                                wire:model="type"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
                                @foreach ($taxTypes as $type)
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
                            <label for="sort_order" class="mb-1 block text-sm font-medium">Ordre d'affichage *</label>
                            <input
                                id="sort_order"
                                type="number"
                                wire:model="sort_order"
                                min="0"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
                            @error('sort_order')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="description" class="mb-1 block text-sm font-medium">Description</label>
                            <input
                                id="description"
                                type="text"
                                wire:model="description"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
                            @error('description')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex items-center gap-6">
                        <div class="flex items-center gap-2">
                            <input
                                id="is_active"
                                type="checkbox"
                                wire:model="is_active"
                                class="h-4 w-4 rounded border-neutral-300"
                            >
                            <label for="is_active" class="text-sm font-medium">Taux actif</label>
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                id="is_default"
                                type="checkbox"
                                wire:model="is_default"
                                class="h-4 w-4 rounded border-neutral-300"
                            >
                            <label for="is_default" class="text-sm font-medium">Taux par défaut</label>
                        </div>
                    </div>

                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a
                    href="{{ route('tax-rates.index') }}"
                    wire:navigate
                    class="rounded-lg border border-neutral-300 px-4 py-2.5 text-sm font-medium hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                >
                    Annuler
                </a>

                <button
                    type="submit"
                    class="rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-neutral-800 dark:bg-white dark:text-neutral-900"
                >
                    Enregistrer les modifications
                </button>
            </div>

        </form>

    </div>
</x-layouts::app>
