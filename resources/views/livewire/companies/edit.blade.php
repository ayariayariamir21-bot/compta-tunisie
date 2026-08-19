<x-layouts::app :title="__('Modifier la société')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Modifier « {{ $company->name }} »
                </h1>

                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    Mettez à jour les informations de la société.
                </p>
            </div>
        </div>

        <form wire:submit="update" class="max-w-3xl space-y-6">

            {{-- Identity --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Identité
                </h2>

                <div class="mt-5 space-y-5">

                    <div>
                        <label for="name" class="mb-1 block text-sm font-medium">
                            Nom de la société *
                        </label>

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

                    <div>
                        <label for="legal_name" class="mb-1 block text-sm font-medium">
                            Raison sociale
                        </label>

                        <input
                            id="legal_name"
                            type="text"
                            wire:model="legal_name"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >

                        @error('legal_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>

            {{-- Registration --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Immatriculation
                </h2>

                <div class="mt-5 grid gap-5 md:grid-cols-2">

                    <div>
                        <label for="tax_identifier" class="mb-1 block text-sm font-medium">
                            Identifiant fiscal
                        </label>

                        <input
                            id="tax_identifier"
                            type="text"
                            wire:model="tax_identifier"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >

                        @error('tax_identifier')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="registration_number" class="mb-1 block text-sm font-medium">
                            Numéro d'immatriculation
                        </label>

                        <input
                            id="registration_number"
                            type="text"
                            wire:model="registration_number"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >

                        @error('registration_number')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>

            {{-- Details --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Détails
                </h2>

                <div class="mt-5 space-y-5">

                    <div class="grid gap-5 md:grid-cols-2">

                        <div>
                            <label for="legal_form" class="mb-1 block text-sm font-medium">
                                Forme juridique
                            </label>

                            <input
                                id="legal_form"
                                type="text"
                                wire:model="legal_form"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                placeholder="SARL / SUARL / SA"
                            >

                            @error('legal_form')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="city" class="mb-1 block text-sm font-medium">
                                Ville
                            </label>

                            <input
                                id="city"
                                type="text"
                                wire:model="city"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                placeholder="Tunis"
                            >

                            @error('city')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>

                    <div>
                        <label for="address" class="mb-1 block text-sm font-medium">
                            Adresse
                        </label>

                        <input
                            id="address"
                            type="text"
                            wire:model="address"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >

                        @error('address')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">

                        <div>
                            <label for="phone" class="mb-1 block text-sm font-medium">
                                Téléphone
                            </label>

                            <input
                                id="phone"
                                type="text"
                                wire:model="phone"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >

                            @error('phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="mb-1 block text-sm font-medium">
                                Email
                            </label>

                            <input
                                id="email"
                                type="email"
                                wire:model="email"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-800"
                            >

                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>

                </div>
            </div>

            {{-- Financial --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Informations financières
                </h2>

                <div class="mt-5 grid gap-5 md:grid-cols-2">

                    <div>
                        <label for="currency" class="mb-1 block text-sm font-medium">
                            Devise *
                        </label>

                        <select
                            id="currency"
                            wire:model="currency"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            <option value="TND">TND — Dinar tunisien</option>
                        </select>

                        @error('currency')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="country" class="mb-1 block text-sm font-medium">
                            Pays *
                        </label>

                        <select
                            id="country"
                            wire:model="country"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            <option value="TN">Tunisie</option>
                        </select>

                        @error('country')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-end gap-3">
                <a
                    href="{{ route('companies.index') }}"
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
