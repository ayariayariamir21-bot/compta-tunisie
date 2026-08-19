<x-layouts::app :title="__('Tableau de bord')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        {{-- Header --}}
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Tableau de bord
            </h1>

            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                Vue générale de votre activité comptable et financière.
            </p>
        </div>

        {{-- KPI Cards --}}
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">

            {{-- Chiffre d'affaires --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-neutral-500 dark:text-neutral-400">
                        Chiffre d'affaires
                    </span>

                    <span class="text-xs font-medium text-emerald-600">
                        +0%
                    </span>
                </div>

                <div class="mt-3 text-2xl font-bold tracking-tight">
                    0,00 DT
                </div>

                <p class="mt-1 text-xs text-neutral-500">
                    Exercice en cours
                </p>
            </div>

            {{-- Dépenses --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-neutral-500 dark:text-neutral-400">
                        Dépenses
                    </span>

                    <span class="text-xs font-medium text-neutral-500">
                        0 facture
                    </span>
                </div>

                <div class="mt-3 text-2xl font-bold tracking-tight">
                    0,00 DT
                </div>

                <p class="mt-1 text-xs text-neutral-500">
                    Achats et charges
                </p>
            </div>

            {{-- Trésorerie --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-neutral-500 dark:text-neutral-400">
                        Trésorerie
                    </span>

                    <span class="text-xs font-medium text-blue-600">
                        Banque + caisse
                    </span>
                </div>

                <div class="mt-3 text-2xl font-bold tracking-tight">
                    0,00 DT
                </div>

                <p class="mt-1 text-xs text-neutral-500">
                    Solde disponible
                </p>
            </div>

            {{-- Résultat --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-neutral-500 dark:text-neutral-400">
                        Résultat
                    </span>

                    <span class="text-xs font-medium text-neutral-500">
                        Exercice
                    </span>
                </div>

                <div class="mt-3 text-2xl font-bold tracking-tight">
                    0,00 DT
                </div>

                <p class="mt-1 text-xs text-neutral-500">
                    Produits - charges
                </p>
            </div>

        </div>

        {{-- Main content --}}
        <div class="grid gap-6 xl:grid-cols-3">

            {{-- Activité --}}
            <div class="xl:col-span-2 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-semibold">
                            Activité récente
                        </h2>

                        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                            Les dernières opérations comptables.
                        </p>
                    </div>

                    <span class="rounded-lg bg-neutral-100 px-3 py-1 text-xs font-medium dark:bg-neutral-800">
                        0 opération
                    </span>
                </div>

                <div class="mt-8 flex min-h-48 items-center justify-center rounded-lg border border-dashed border-neutral-300 dark:border-neutral-700">
                    <div class="text-center">
                        <p class="text-sm font-medium">
                            Aucune opération
                        </p>

                        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                            Les écritures apparaîtront ici.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Actions rapides --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="font-semibold">
                    Actions rapides
                </h2>

                <div class="mt-4 grid gap-3">

                    <a
                        href="#"
                        class="rounded-lg border border-neutral-200 px-4 py-3 text-sm font-medium transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                    >
                        + Nouvelle écriture
                    </a>

                    <a
                        href="#"
                        class="rounded-lg border border-neutral-200 px-4 py-3 text-sm font-medium transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                    >
                        + Nouvelle facture
                    </a>

                    <a
                        href="#"
                        class="rounded-lg border border-neutral-200 px-4 py-3 text-sm font-medium transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                    >
                        + Nouveau client
                    </a>

                    <a
                        href="#"
                        class="rounded-lg border border-neutral-200 px-4 py-3 text-sm font-medium transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                    >
                        Voir la balance
                    </a>

                </div>
            </div>

        </div>

    </div>
</x-layouts::app>
