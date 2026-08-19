<div class="space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">
                Mes entreprises
            </flux:heading>

            <flux:text class="mt-1">
                Gérez les sociétés auxquelles vous avez accès.
            </flux:text>
        </div>

        <flux:button
            variant="primary"
            wire:click="$set('showCreateModal', true)"
        >
            Nouvelle société
        </flux:button>
    </div>

    @if (session()->has('success'))
        <flux:callout variant="success">
            {{ session('success') }}
        </flux:callout>
    @endif

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">

        @forelse ($companies as $company)
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">

                <div class="flex items-start justify-between gap-4">

                    <div>
                        <flux:heading size="lg">
                            {{ $company->name }}
                        </flux:heading>

                        <flux:text class="mt-1">
                            {{ $company->legal_form ?: 'Entreprise' }}
                        </flux:text>
                    </div>

                    <flux:badge color="green">
                        {{ $company->pivot->role }}
                    </flux:badge>

                </div>

                <div class="mt-5 space-y-2 text-sm text-neutral-600 dark:text-neutral-300">

                    @if ($company->tax_identifier)
                        <div>
                            <strong>Identifiant fiscal :</strong>
                            {{ $company->tax_identifier }}
                        </div>
                    @endif

                    @if ($company->city)
                        <div>
                            <strong>Ville :</strong>
                            {{ $company->city }}
                        </div>
                    @endif

                    <div>
                        <strong>Devise :</strong>
                        {{ $company->currency }}
                    </div>

                </div>

                <div class="mt-5">
                    <flux:button
                        variant="ghost"
                        href="{{ route('dashboard') }}"
                    >
                        Ouvrir
                    </flux:button>
                </div>
            </div>
        @empty

            <div class="col-span-full rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                <flux:heading size="lg">
                    Aucune société
                </flux:heading>

                <flux:text class="mt-2">
                    Commencez par créer votre première société.
                </flux:text>
            </div>

        @endforelse

    </div>

    <flux:modal wire:model="showCreateModal" class="md:w-[600px]">

        <div class="space-y-6">

            <div>
                <flux:heading size="lg">
                    Nouvelle société
                </flux:heading>

                <flux:text class="mt-1">
                    Ajoutez les informations principales de la société.
                </flux:text>
            </div>

            <flux:input
                wire:model="name"
                label="Nom de la société"
                placeholder="Ex: Société ABC"
            />

            <flux:input
                wire:model="legal_name"
                label="Raison sociale"
                placeholder="Ex: Société ABC SARL"
            />

            <div class="grid gap-4 md:grid-cols-2">

                <flux:input
                    wire:model="tax_identifier"
                    label="Identifiant fiscal"
                />

                <flux:input
                    wire:model="registration_number"
                    label="Numéro d'immatriculation"
                />

            </div>

            <div class="grid gap-4 md:grid-cols-2">

                <flux:input
                    wire:model="legal_form"
                    label="Forme juridique"
                    placeholder="SARL / SUARL / SA"
                />

                <flux:input
                    wire:model="city"
                    label="Ville"
                    placeholder="Tunis"
                />

            </div>

            <flux:input
                wire:model="address"
                label="Adresse"
            />

            <div class="grid gap-4 md:grid-cols-2">

                <flux:input
                    wire:model="phone"
                    label="Téléphone"
                />

                <flux:input
                    wire:model="email"
                    label="Email"
                    type="email"
                />

            </div>

            <flux:select wire:model="currency" label="Devise">
                <flux:select.option value="TND">
                    TND — Dinar tunisien
                </flux:select.option>
            </flux:select>

            <div class="flex justify-end gap-3">
                <flux:button
                    variant="ghost"
                    wire:click="$set('showCreateModal', false)"
                >
                    Annuler
                </flux:button>

                <flux:button
                    variant="primary"
                    wire:click="createCompany"
                >
                    Créer
                </flux:button>
            </div>

        </div>

    </flux:modal>

</div>
