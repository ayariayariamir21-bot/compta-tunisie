<x-layouts::app :title="__('Nouvel exercice')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Nouvel exercice comptable
                </h1>

                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    Définissez la période de votre nouvel exercice.
                </p>
            </div>
        </div>

        <form wire:submit="store" class="max-w-2xl space-y-6">

            {{-- Dates --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">
                    Informations de l'exercice
                </h2>

                <div class="mt-5 space-y-5">

                    <div>
                        <label for="name" class="mb-1 block text-sm font-medium">
                            Nom de l'exercice *
                        </label>

                        <input
                            id="name"
                            type="text"
                            wire:model="name"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            placeholder="Ex: Exercice 2026"
                        >

                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="code" class="mb-1 block text-sm font-medium">
                            Code *
                        </label>

                        <input
                            id="code"
                            type="text"
                            wire:model="code"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            placeholder="Ex: 2026"
                        >

                        @error('code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">

                        <div>
                            <label for="start_date" class="mb-1 block text-sm font-medium">
                                Date de début *
                            </label>

                            <input
                                id="start_date"
                                type="date"
                                wire:model="start_date"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >

                            @error('start_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="end_date" class="mb-1 block text-sm font-medium">
                                Date de fin *
                            </label>

                            <input
                                id="end_date"
                                type="date"
                                wire:model="end_date"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >

                            @error('end_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>

                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-end gap-3">
                <a
                    href="{{ route('fiscal-years.index') }}"
                    wire:navigate
                    class="rounded-lg border border-neutral-300 px-4 py-2.5 text-sm font-medium hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                >
                    Annuler
                </a>

                <button
                    type="submit"
                    class="rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-neutral-800 dark:bg-white dark:text-neutral-900"
                >
                    Créer l'exercice
                </button>
            </div>

        </form>

    </div>
</x-layouts::app>
