<x-layouts::app :title="__('Exercices comptables')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Exercices comptables
                </h1>

                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    @if ($currentCompany)
                        Exercices de « {{ $currentCompany->name }} ».
                    @else
                        Sélectionnez une société pour gérer ses exercices.
                    @endif
                </p>
            </div>

            @if ($currentCompany)
                <a
                    href="{{ route('fiscal-years.create') }}"
                    wire:navigate
                    class="inline-flex items-center justify-center rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200"
                >
                    + Nouvel exercice
                </a>
            @endif
        </div>

        {{-- Success message --}}
        @if (session()->has('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        @if (! $currentCompany)
            <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                <h2 class="text-lg font-semibold">
                    Aucune société sélectionnée
                </h2>

                <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                    Sélectionnez une société dans le menu lateral pour voir ses exercices.
                </p>
            </div>
        @else
            {{-- Fiscal Years --}}
            <div class="space-y-4">

                @forelse ($fiscalYears as $fy)
                    <div class="rounded-xl border bg-white p-5 shadow-sm dark:bg-neutral-900 {{ $fy->is_active ? 'border-blue-200 dark:border-blue-800' : 'border-neutral-200 dark:border-neutral-700' }}">

                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h2 class="text-lg font-semibold">
                                            {{ $fy->name }}
                                        </h2>

                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $fy->is_active ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400' }}">
                                            {{ $fy->is_active ? 'Actif' : 'Inactif' }}
                                        </span>

                                        @if ($fy->is_closed)
                                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                                Clôturé
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mt-1 flex items-center gap-4 text-sm text-neutral-500 dark:text-neutral-400">
                                        <span>Code: {{ $fy->code }}</span>
                                        <span>•</span>
                                        <span>{{ $fy->start_date->format('d/m/Y') }} — {{ $fy->end_date->format('d/m/Y') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                @if (! $fy->is_closed)
                                    <a
                                        href="{{ route('fiscal-years.edit', $fy->id) }}"
                                        wire:navigate
                                        class="inline-flex items-center rounded-lg border border-neutral-200 px-3 py-2 text-sm font-medium transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                                    >
                                        Modifier
                                    </a>
                                @endif

                                @if (! $fy->is_active && ! $fy->is_closed)
                                    <button
                                        wire:click="activate({{ $fy->id }})"
                                        wire:confirm="Voulez-vous activer cet exercice ? L'exercice actif sera automatiquement désactivé."
                                        class="inline-flex items-center rounded-lg border border-blue-200 px-3 py-2 text-sm font-medium text-blue-600 transition hover:bg-blue-50 dark:border-blue-900 dark:text-blue-400 dark:hover:bg-blue-950"
                                    >
                                        Activer
                                    </button>
                                @endif

                                @if ($fy->is_active && ! $fy->is_closed)
                                    <button
                                        wire:click="close({{ $fy->id }})"
                                        wire:confirm="Voulez-vous vraiment clôturer cet exercice ? Cette action est irréversible."
                                        class="inline-flex items-center rounded-lg border border-amber-200 px-3 py-2 text-sm font-medium text-amber-600 transition hover:bg-amber-50 dark:border-amber-900 dark:text-amber-400 dark:hover:bg-amber-950"
                                    >
                                        Clôturer
                                    </button>
                                @endif
                            </div>
                        </div>

                    </div>
                @empty

                    <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                        <h2 class="text-lg font-semibold">
                            Aucun exercice
                        </h2>

                        <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                            Créez votre premier exercice comptable pour « {{ $currentCompany->name }} ».
                        </p>
                    </div>

                @endforelse

            </div>
        @endif

    </div>
</x-layouts::app>
