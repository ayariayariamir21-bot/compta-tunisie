<x-layouts::app :title="__('Client : ' . $customer->name)">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ $customer->name }}
                </h1>

                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    {{ $customer->code }} — {{ $customer->customer_type->label() }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                @if (Auth::user()->can('update', $customer))
                    <a
                        href="{{ route('customers.edit', $customer->id) }}"
                        wire:navigate
                        class="inline-flex items-center justify-center rounded-lg border border-neutral-200 px-4 py-2.5 text-sm font-medium transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                    >
                        Modifier
                    </a>
                @endif

                <a
                    href="{{ route('customers.index') }}"
                    wire:navigate
                    class="rounded-lg border border-neutral-300 px-4 py-2.5 text-sm font-medium hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                >
                    Retour à la liste
                </a>
            </div>
        </div>

        {{-- Status --}}
        @if (! $customer->is_active)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                Ce client est inactif.
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
                        <dd class="font-medium">{{ $customer->code }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Nom</dt>
                        <dd class="font-medium">{{ $customer->name }}</dd>
                    </div>
                    @if ($customer->legal_name)
                        <div class="flex justify-between text-sm">
                            <dt class="text-neutral-500 dark:text-neutral-400">Raison sociale</dt>
                            <dd class="font-medium">{{ $customer->legal_name }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Type</dt>
                        <dd class="font-medium">{{ $customer->customer_type->label() }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Tax --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Informations fiscales
                </h2>

                <dl class="mt-4 space-y-3">
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Identifiant fiscal</dt>
                        <dd class="font-medium">{{ $customer->tax_identifier ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">RNE</dt>
                        <dd class="font-medium">{{ $customer->rne ?: '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Address --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Adresse
                </h2>

                <dl class="mt-4 space-y-3">
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Adresse</dt>
                        <dd class="font-medium text-right">{{ $customer->address ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Code postal</dt>
                        <dd class="font-medium">{{ $customer->postal_code ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Ville</dt>
                        <dd class="font-medium">{{ $customer->city ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Gouvernorat</dt>
                        <dd class="font-medium">{{ $customer->governorate ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Pays</dt>
                        <dd class="font-medium">{{ $customer->country }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Contact --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Contact
                </h2>

                <dl class="mt-4 space-y-3">
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Téléphone</dt>
                        <dd class="font-medium">{{ $customer->phone ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Mobile</dt>
                        <dd class="font-medium">{{ $customer->mobile ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Email</dt>
                        <dd class="font-medium">{{ $customer->email ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Site web</dt>
                        <dd class="font-medium">
                            @if ($customer->website)
                                <a href="{{ $customer->website }}" target="_blank" class="text-blue-600 hover:underline dark:text-blue-400">
                                    {{ $customer->website }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Payment & Accounting --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Paiement et comptabilité
                </h2>

                <dl class="mt-4 space-y-3">
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Délai de paiement</dt>
                        <dd class="font-medium">{{ $customer->payment_terms_days }} jours</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Limite de crédit</dt>
                        <dd class="font-medium">
                            {{ $customer->credit_limit ? number_format((float) $customer->credit_limit, 3, ',', '.') . ' TND' : '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Compte comptable</dt>
                        <dd class="font-medium">
                            @if ($customer->account)
                                {{ $customer->account->code }} — {{ $customer->account->name }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Status --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    État
                </h2>

                <dl class="mt-4 space-y-3">
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Statut</dt>
                        <dd>
                            @if ($customer->is_active)
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

        </div>

        {{-- Notes --}}
        @if ($customer->notes)
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Notes
                </h2>
                <p class="mt-3 text-sm text-neutral-700 dark:text-neutral-300 whitespace-pre-line">
                    {{ $customer->notes }}
                </p>
            </div>
        @endif

        {{-- Future sections placeholders --}}
        <div class="rounded-xl border border-dashed border-neutral-300 p-6 text-center dark:border-neutral-700">
            <h2 class="text-lg font-semibold text-neutral-400 dark:text-neutral-500">
                Factures, paiements et relevé de compte
            </h2>
            <p class="mt-2 text-sm text-neutral-400 dark:text-neutral-500">
                Ces sections seront disponibles lorsque les modules de facturation et de paiement seront implémentés.
            </p>
        </div>

    </div>
</x-layouts::app>
