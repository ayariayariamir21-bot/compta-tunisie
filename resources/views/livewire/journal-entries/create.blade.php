<x-layouts::app :title="__('Nouvelle écriture')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Nouvelle écriture comptable</h1>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Créez une nouvelle écriture en brouillon.</p>
            </div>
        </div>

        <form wire:submit="store" class="space-y-6">

            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">En-tête</h2>
                <div class="mt-5 space-y-5">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="journal_id" class="mb-1 block text-sm font-medium">Journal *</label>
                            <select id="journal_id" wire:model="journal_id" class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                                <option value="">— Sélectionner —</option>
                                @foreach ($journals as $journal)
                                    <option value="{{ $journal->id }}">{{ $journal->code }} — {{ $journal->name }}</option>
                                @endforeach
                            </select>
                            @error('journal_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="entry_date" class="mb-1 block text-sm font-medium">Date *</label>
                            <input id="entry_date" type="date" wire:model="entry_date" class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                            @error('entry_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="reference" class="mb-1 block text-sm font-medium">Référence</label>
                            <input id="reference" type="text" wire:model="reference" class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800" placeholder="Ex: FAC-2026-0001">
                            @error('reference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="description" class="mb-1 block text-sm font-medium">Description</label>
                            <input id="description" type="text" wire:model="description" class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Lignes</h2>
                    <button type="button" wire:click="addLine" class="rounded-md px-3 py-1.5 text-sm font-medium text-blue-600 transition hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950">+ Ajouter une ligne</button>
                </div>

                <div class="mt-5 space-y-4">
                    @foreach ($lines as $index => $line)
                        <div class="grid gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700" style="grid-template-columns: 1fr 1fr 120px 120px 40px;">
                            <div>
                                @if ($index === 0)<label class="mb-1 block text-xs font-medium text-neutral-500">Compte *</label>@endif
                                <select wire:model="lines.{{ $index }}.account_id" class="w-full rounded-lg border border-neutral-300 px-2 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                                    <option value="">—</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                @if ($index === 0)<label class="mb-1 block text-xs font-medium text-neutral-500">Description</label>@endif
                                <input type="text" wire:model="lines.{{ $index }}.description" class="w-full rounded-lg border border-neutral-300 px-2 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                            </div>
                            <div>
                                @if ($index === 0)<label class="mb-1 block text-xs font-medium text-neutral-500">Débit</label>@endif
                                <input type="text" wire:model="lines.{{ $index }}.debit" class="w-full rounded-lg border border-neutral-300 px-2 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800" placeholder="0.000">
                            </div>
                            <div>
                                @if ($index === 0)<label class="mb-1 block text-xs font-medium text-neutral-500">Crédit</label>@endif
                                <input type="text" wire:model="lines.{{ $index }}.credit" class="w-full rounded-lg border border-neutral-300 px-2 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800" placeholder="0.000">
                            </div>
                            <div class="flex items-end justify-center">
                                @if ($index === 0)<label class="mb-1 block text-xs font-medium text-transparent">&nbsp;</label>@endif
                                @if (count($lines) > 1)
                                    <button type="button" wire:click="removeLine({{ $index }})" class="rounded p-1.5 text-red-500 transition hover:bg-red-50 dark:hover:bg-red-950">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @error('lines') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                <div class="mt-5 flex items-center justify-end gap-6 rounded-lg bg-neutral-50 px-4 py-3 dark:bg-neutral-800">
                    <div class="text-sm">
                        <span class="font-medium">Total Débit:</span>
                        <span class="ml-1">{{ number_format((float) $getTotalDebit(), 3, '.', ' ') }}</span>
                    </div>
                    <div class="text-sm">
                        <span class="font-medium">Total Crédit:</span>
                        <span class="ml-1">{{ number_format((float) $getTotalCredit(), 3, '.', ' ') }}</span>
                    </div>
                    <div class="text-sm">
                        <span class="font-medium">Différence:</span>
                        <span class="ml-1 {{ $isBalanced() ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format((float) bcsub($getTotalDebit(), $getTotalCredit(), 3), 3, '.', ' ') }}
                        </span>
                    </div>
                    <div>
                        @if ($isBalanced())
                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Équilibrée</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">Non équilibrée</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('journal-entries.index') }}" wire:navigate class="rounded-lg border border-neutral-300 px-4 py-2.5 text-sm font-medium hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800">Annuler</a>
                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-neutral-800 dark:bg-white dark:text-neutral-900">Enregistrer le brouillon</button>
            </div>

        </form>

    </div>
</x-layouts::app>
