<x-layouts::app :title="__('Mes entreprises')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Mes entreprises
                </h1>

                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    Gérez les sociétés auxquelles vous avez accès.
                </p>
            </div>

            <a
                href="{{ route('companies.create') }}"
                wire:navigate
                class="inline-flex items-center justify-center rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200"
            >
                + Nouvelle société
            </a>
        </div>

        {{-- Success message --}}
        @if (session()->has('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        {{-- Companies --}}
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">

            @forelse ($companies as $company)
                <div class="rounded-xl border bg-white p-5 shadow-sm dark:bg-neutral-900 {{ $company->is_active ? 'border-neutral-200 dark:border-neutral-700' : 'border-neutral-200/50 opacity-60 dark:border-neutral-700/50' }}">

                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold">
                                {{ $company->name }}
                            </h2>

                            <div class="mt-1 flex items-center gap-2">
                                @if ($company->legal_form)
                                    <span class="text-sm text-neutral-500 dark:text-neutral-400">
                                        {{ $company->legal_form }}
                                    </span>
                                @endif

                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $company->is_active ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300' : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400' }}">
                                    {{ $company->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>

                        <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                            {{ $company->pivot->role }}
                        </span>
                    </div>

                    <div class="mt-5 space-y-2 text-sm">

                        @if ($company->tax_identifier)
                            <div>
                                <span class="text-neutral-500 dark:text-neutral-400">
                                    Identifiant fiscal:
                                </span>
                                {{ $company->tax_identifier }}
                            </div>
                        @endif

                        @if ($company->registration_number)
                            <div>
                                <span class="text-neutral-500 dark:text-neutral-400">
                                    Immatriculation:
                                </span>
                                {{ $company->registration_number }}
                            </div>
                        @endif

                        @if ($company->city)
                            <div>
                                <span class="text-neutral-500 dark:text-neutral-400">
                                    Ville:
                                </span>
                                {{ $company->city }}
                            </div>
                        @endif

                        <div>
                            <span class="text-neutral-500 dark:text-neutral-400">
                                Devise:
                            </span>
                            {{ $company->currency }}
                        </div>

                    </div>

                    <div class="mt-6 flex items-center gap-2">
                        <a
                            href="{{ route('companies.edit', $company->id) }}"
                            wire:navigate
                            class="inline-flex items-center rounded-lg border border-neutral-200 px-3 py-2 text-sm font-medium transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                        >
                            Modifier
                        </a>

                        @if ($company->pivot->role === 'admin')
                            @if ($company->is_active)
                                <button
                                    wire:click="deactivate({{ $company->id }})"
                                    wire:confirm="Voulez-vous vraiment désactiver cette société ?"
                                    class="inline-flex items-center rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950"
                                >
                                    Désactiver
                                </button>
                            @else
                                <button
                                    wire:click="activate({{ $company->id }})"
                                    class="inline-flex items-center rounded-lg border border-green-200 px-3 py-2 text-sm font-medium text-green-600 transition hover:bg-green-50 dark:border-green-900 dark:text-green-400 dark:hover:bg-green-950"
                                >
                                    Activer
                                </button>
                            @endif
                        @endif
                    </div>

                </div>
            @empty

                <div class="col-span-full rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                    <h2 class="text-lg font-semibold">
                        Aucune société
                    </h2>

                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                        Commencez par créer votre première société.
                    </p>
                </div>

            @endforelse

        </div>

    </div>
</x-layouts::app>
