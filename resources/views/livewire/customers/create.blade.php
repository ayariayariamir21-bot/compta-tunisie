<x-layouts::app :title="__('Nouveau client')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Nouveau client
                </h1>

                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    Ajoutez un nouveau client à la société.
                </p>
            </div>
        </div>

        <form wire:submit="store" class="max-w-4xl space-y-6">

            {{-- Identity --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Identité du client
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
                                    placeholder="Ex: CL001"
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
                                placeholder="Nom du client"
                            >
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="customer_type" class="mb-1 block text-sm font-medium">Type de client *</label>
                            <select
                                id="customer_type"
                                wire:model="customer_type"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
                                <option value="">— Sélectionner —</option>
                                @foreach ($customerTypes as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                            @error('customer_type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="legal_name" class="mb-1 block text-sm font-medium">Raison sociale</label>
                            <input
                                id="legal_name"
                                type="text"
                                wire:model="legal_name"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                placeholder="Raison sociale"
                            >
                            @error('legal_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label for="tax_identifier" class="mb-1 block text-sm font-medium">Identifiant fiscal</label>
                                <input
                                    id="tax_identifier"
                                    type="text"
                                    wire:model="tax_identifier"
                                    class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                    placeholder="MF / Matricule fiscal"
                                >
                                @error('tax_identifier')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="rne" class="mb-1 block text-sm font-medium">RNE</label>
                                <input
                                    id="rne"
                                    type="text"
                                    wire:model="rne"
                                    class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                    placeholder="Registre National"
                                >
                                @error('rne')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Address --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Adresse
                </h2>

                <div class="mt-5 space-y-5">

                    <div>
                        <label for="address" class="mb-1 block text-sm font-medium">Adresse</label>
                        <input
                            id="address"
                            type="text"
                            wire:model="address"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            placeholder="Adresse complète"
                        >
                        @error('address')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-5 md:grid-cols-4">
                        <div>
                            <label for="postal_code" class="mb-1 block text-sm font-medium">Code postal</label>
                            <input
                                id="postal_code"
                                type="text"
                                wire:model="postal_code"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                placeholder="1000"
                            >
                            @error('postal_code')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="city" class="mb-1 block text-sm font-medium">Ville</label>
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

                        <div>
                            <label for="governorate" class="mb-1 block text-sm font-medium">Gouvernorat</label>
                            <input
                                id="governorate"
                                type="text"
                                wire:model="governorate"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                placeholder="Tunis"
                            >
                            @error('governorate')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="country" class="mb-1 block text-sm font-medium">Pays *</label>
                            <input
                                id="country"
                                type="text"
                                wire:model="country"
                                maxlength="2"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                placeholder="TN"
                            >
                            @error('country')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                </div>
            </div>

            {{-- Contact --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Contact
                </h2>

                <div class="mt-5 space-y-5">

                    <div class="grid gap-5 md:grid-cols-3">
                        <div>
                            <label for="phone" class="mb-1 block text-sm font-medium">Téléphone</label>
                            <input
                                id="phone"
                                type="text"
                                wire:model="phone"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                placeholder="+216 XX XXX XXX"
                            >
                            @error('phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="mobile" class="mb-1 block text-sm font-medium">Mobile</label>
                            <input
                                id="mobile"
                                type="text"
                                wire:model="mobile"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                placeholder="+216 XX XXX XXX"
                            >
                            @error('mobile')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="mb-1 block text-sm font-medium">Email</label>
                            <input
                                id="email"
                                type="email"
                                wire:model="email"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                placeholder="contact@client.tn"
                            >
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="website" class="mb-1 block text-sm font-medium">Site web</label>
                        <input
                            id="website"
                            type="url"
                            wire:model="website"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            placeholder="https://www.client.tn"
                        >
                        @error('website')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>

            {{-- Payment & Accounting --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Paiement et comptabilité
                </h2>

                <div class="mt-5 space-y-5">

                    <div class="grid gap-5 md:grid-cols-3">
                        <div>
                            <label for="payment_terms_days" class="mb-1 block text-sm font-medium">Délai de paiement (jours) *</label>
                            <input
                                id="payment_terms_days"
                                type="number"
                                wire:model="payment_terms_days"
                                min="0"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
                            @error('payment_terms_days')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="credit_limit" class="mb-1 block text-sm font-medium">Limite de crédit (TND)</label>
                            <input
                                id="credit_limit"
                                type="number"
                                wire:model="credit_limit"
                                min="0"
                                step="0.001"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                placeholder="0.000"
                            >
                            @error('credit_limit')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="account_id" class="mb-1 block text-sm font-medium">Compte comptable</label>
                            <select
                                id="account_id"
                                wire:model="account_id"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
                                <option value="">— Aucun compte —</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                @endforeach
                            </select>
                            @error('account_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="notes" class="mb-1 block text-sm font-medium">Notes</label>
                        <textarea
                            id="notes"
                            wire:model="notes"
                            rows="3"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            placeholder="Notes internes..."
                        ></textarea>
                        @error('notes')
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
                        <label for="is_active" class="text-sm font-medium">Client actif</label>
                    </div>

                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a
                    href="{{ route('customers.index') }}"
                    wire:navigate
                    class="rounded-lg border border-neutral-300 px-4 py-2.5 text-sm font-medium hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                >
                    Annuler
                </a>

                <button
                    type="submit"
                    class="rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-neutral-800 dark:bg-white dark:text-neutral-900"
                >
                    Créer le client
                </button>
            </div>

        </form>

    </div>
</x-layouts::app>
