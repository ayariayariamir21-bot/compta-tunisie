<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dépense {{ $expense->expense_number }}</h1>
        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
            {{ $expense->status->color() }}">
            {{ $expense->status->label() }}
        </span>
    </div>

    @if (session('success'))
        <div class="rounded-md bg-green-50 p-4 dark:bg-green-900/20">
            <p class="text-sm text-green-700 dark:text-green-400">{{ session('success') }}</p>
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-md bg-red-50 p-4 dark:bg-red-900/20">
            <p class="text-sm text-red-700 dark:text-red-400">{{ session('error') }}</p>
        </div>
    @endif

    <div class="flux-card grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Date de la dépense</p>
            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $expense->expense_date->format('d/m/Y') }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Fournisseur</p>
            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                @if ($expense->supplier)
                    <a href="{{ route('suppliers.show', $expense->supplier_id) }}" class="text-flux-600 hover:text-flux-500 dark:text-flux-400" wire:navigate>{{ $expense->supplier->name }}</a>
                @else
                    —
                @endif
            </p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Mode de règlement</p>
            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $expense->paymentMethod?->name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Référence</p>
            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $expense->reference ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Échéance</p>
            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $expense->due_date?->format('d/m/Y') ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Journal</p>
            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $expense->journal?->code ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Période</p>
            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $expense->accountingPeriod?->name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Créée par</p>
            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $expense->creator?->name ?? '—' }}</p>
        </div>
    </div>

    @if ($expense->description)
        <div class="flux-card">
            <h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Description</h2>
            <p class="text-sm whitespace-pre-line text-gray-500 dark:text-gray-400">{{ $expense->description }}</p>
        </div>
    @endif

    <div class="flux-card">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Lignes de dépense</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Compte</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Libellé</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Qté</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">P.U. HT</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Remise</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total HT</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">TVA</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total TTC</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                    @foreach($expense->lines as $line)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $line->expenseAccount?->code ?? '—' }} — {{ $line->expenseAccount?->name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $line->label }}</td>
                            <td class="px-4 py-3 text-right text-sm text-gray-500 dark:text-gray-400">{{ $line->quantity !== null ? number_format((float) $line->quantity, 3, '.', '') : '—' }}</td>
                            <td class="px-4 py-3 text-right text-sm text-gray-500 dark:text-gray-400">{{ number_format((float) $line->unit_price, 3, '.', '') }}</td>
                            <td class="px-4 py-3 text-right text-sm text-gray-500 dark:text-gray-400">{{ ((float) $line->discount_amount) > 0 ? number_format((float) $line->discount_amount, 3, '.', '') : '—' }}</td>
                            <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">{{ number_format((float) $line->line_subtotal, 3, '.', '') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                @if ($line->tax_code)
                                    {{ $line->tax_code }} ({{ number_format((float) $line->tax_rate, 3, '.', '') }}%) — {{ number_format((float) $line->tax_amount, 3, '.', '') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-white">{{ number_format((float) $line->line_total, 3, '.', '') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex flex-col items-end space-y-1 border-t border-gray-200 pt-4 text-sm dark:border-gray-700">
            <div class="flex w-full max-w-xs justify-between">
                <span class="text-gray-500 dark:text-gray-400">Total HT</span>
                <span class="font-medium text-gray-900 dark:text-white">{{ number_format((float) $expense->subtotal, 3, '.', '') }} {{ $expense->currency }}</span>
            </div>
            <div class="flex w-full max-w-xs justify-between">
                <span class="text-gray-500 dark:text-gray-400">TVA</span>
                <span class="font-medium text-gray-900 dark:text-white">{{ number_format((float) $expense->tax_total, 3, '.', '') }} {{ $expense->currency }}</span>
            </div>
            <div class="flex w-full max-w-xs justify-between border-t border-gray-200 pt-2 text-base dark:border-gray-700">
                <span class="font-semibold text-gray-900 dark:text-white">Total TTC</span>
                <span class="font-bold text-gray-900 dark:text-white">{{ number_format((float) $expense->total, 3, '.', '') }} {{ $expense->currency }}</span>
            </div>
        </div>
    </div>

    <div class="flux-card">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Comptabilisation</h2>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Statut</p>
                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $expense->status->label() }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Comptabilisée le</p>
                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $expense->posted_at?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Exercice</p>
                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $expense->fiscalYear?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Écriture comptable</p>
                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                    @if ($expense->journalEntry)
                        <a href="{{ route('journal-entries.show', $expense->journalEntry->id) }}" class="text-flux-600 hover:text-flux-500 dark:text-flux-400" wire:navigate>{{ $expense->journalEntry->entry_number }}</a>
                    @else
                        —
                    @endif
                </p>
            </div>
        </div>

        @if ($expense->journalEntry)
            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Compte</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Libellé</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Débit</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Crédit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        @foreach($expense->journalEntry->lines as $line)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $line->account?->code ?? '—' }} — {{ $line->account?->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $line->description }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-white">
                                    {{ ((float) $line->debit) > 0 ? number_format((float) $line->debit, 3, '.', '') : '' }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-white">
                                    {{ ((float) $line->credit) > 0 ? number_format((float) $line->credit, 3, '.', '') : '' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($expense->notes)
        <div class="flux-card">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Notes</h2>
            <p class="text-sm whitespace-pre-line text-gray-500 dark:text-gray-400">{{ $expense->notes }}</p>
        </div>
    @endif

    <div class="flex justify-end gap-3">
        @if ($expense->status->value === 'draft')
            <a href="{{ route('expenses.edit', $expense->id) }}" class="flux-btn-secondary" wire:navigate>Modifier</a>
            <button wire:click="cancel" wire:confirm="Voulez-vous vraiment annuler cette dépense ?" class="rounded-md border border-orange-300 px-4 py-2 text-sm font-medium text-orange-600 hover:bg-orange-50 dark:border-orange-700 dark:hover:bg-orange-900/20">Annuler la dépense</button>
            <button wire:click="delete" wire:confirm="Voulez-vous vraiment supprimer cette dépense ? Cette action est irréversible." class="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:hover:bg-red-900/20">Supprimer</button>
            <button wire:click="post" wire:confirm="Voulez-vous vraiment comptabiliser cette dépense ? Cette action est irréversible." class="flux-btn-primary">Comptabiliser</button>
        @elseif ($expense->status->value === 'posted')
            <p class="text-sm italic text-gray-500 dark:text-gray-400">Dépense comptabilisée — document immuable.</p>
        @else
            <p class="text-sm italic text-gray-500 dark:text-gray-400">Dépense annulée.</p>
        @endif
    </div>
</div>
