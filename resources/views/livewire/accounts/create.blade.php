<x-layouts::app :title="__('Nouveau compte')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Nouveau compte
                </h1>

                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    Ajoutez un nouveau compte au plan comptable.
                </p>
            </div>
        </div>

        <form wire:submit="store" class="max-w-2xl space-y-6">

            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Informations du compte
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
                                placeholder="Ex: 401"
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
                                placeholder="Ex: Fournisseurs"
                            >
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="account_type" class="mb-1 block text-sm font-medium">Type de compte *</label>
                        <select
                            id="account_type"
                            wire:model="account_type"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            <option value="">— Sélectionner —</option>
                            @foreach ($accountTypes as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        @error('account_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="parent_id" class="mb-1 block text-sm font-medium">Compte parent</label>
                        <select
                            id="parent_id"
                            wire:model="parent_id"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            <option value="">— Aucun parent (compte racine) —</option>
                            @foreach ($parents as $parent)
                                <option value="{{ $parent->id }}">
                                    {{ $parent->code }} — {{ $parent->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('parent_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="description" class="mb-1 block text-sm font-medium">Description</label>
                        <textarea
                            id="description"
                            wire:model="description"
                            rows="3"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            placeholder="Description optionnelle"
                        ></textarea>
                        @error('description')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="is_active"
                            type="checkbox"
                            wire:model="is_active"
                            class="h-4 w-4 rounded border-neutral-300"
                        >
                        <label for="is_active" class="text-sm font-medium">Compte actif</label>
                    </div>

                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a
                    href="{{ route('accounts.index') }}"
                    wire:navigate
                    class="rounded-lg border border-neutral-300 px-4 py-2.5 text-sm font-medium hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                >
                    Annuler
                </a>

                <button
                    type="submit"
                    class="rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-neutral-800 dark:bg-white dark:text-neutral-900"
                >
                    Créer le compte
                </button>
            </div>

        </form>

    </div>
</x-layouts::app>
