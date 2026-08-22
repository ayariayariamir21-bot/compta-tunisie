    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Avoir {{ $creditNote->credit_note_number }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Facture source :
                    @if ($creditNote->invoice)
                        <a href="{{ route('invoices.show', $creditNote->invoice_id) }}" class="text-flux-600 hover:text-flux-500 dark:text-flux-400" wire:navigate>{{ $creditNote->invoice->invoice_number }}</a>
                    @else
                        —
                    @endif
                </p>
            </div>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                {{ match($creditNote->status) {
                    \App\Enums\CreditNoteStatus::DRAFT => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                    \App\Enums\CreditNoteStatus::POSTED => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                    \App\Enums\CreditNoteStatus::CANCELLED => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                } }}">
                {{ $creditNote->status->label() }}
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
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Date de l'avoir</p>
                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $creditNote->credit_note_date->format('d/m/Y') }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Client</p>
                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $creditNote->customer->name }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Motif</p>
                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $creditNote->reason ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Devise</p>
                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $creditNote->currency }}</p>
            </div>
        </div>

        <div class="flux-card">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Lignes</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Produit / Service</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Description</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Qté originale</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Qté créditée</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">P.U. HT</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Remise</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">TVA</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total ligne</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        @foreach($creditNote->lines as $line)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $line->product?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $line->description }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-500">{{ $line->invoiceLine?->quantity ?? $line->quantity }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-blue-600 dark:text-blue-400">{{ $line->quantity }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-500">{{ $line->unit_price }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-500">{{ $line->discount_percent }}%</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-500">{{ rtrim(rtrim((string) $line->tax_rate, '0'), '.') }}%</td>
                                <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">{{ $line->line_total }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex flex-col items-end space-y-1 border-t border-gray-200 pt-4 text-sm dark:border-gray-700">
                <div class="flex w-full max-w-xs justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Total HT</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $creditNote->subtotal }}</span>
                </div>
                <div class="flex w-full max-w-xs justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Remise</span>
                    <span class="font-medium text-gray-900 dark:text-white">-{{ $creditNote->discount_total }}</span>
                </div>
                <div class="flex w-full max-w-xs justify-between">
                    <span class="text-gray-500 dark:text-gray-400">TVA</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $creditNote->tax_total }}</span>
                </div>
                <div class="flex w-full max-w-xs justify-between border-t border-gray-200 pt-2 text-base dark:border-gray-700">
                    <span class="font-semibold text-gray-900 dark:text-white">Total TTC</span>
                    <span class="font-bold text-gray-900 dark:text-white">{{ $creditNote->total }} {{ $creditNote->currency }}</span>
                </div>
            </div>
        </div>

        <div class="flux-card">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Comptabilisation</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Journal</p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $creditNote->journal?->code ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Période</p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $creditNote->accountingPeriod?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Écriture comptable</p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                        @if ($creditNote->journalEntry)
                            {{ $creditNote->journalEntry->entry_number }}
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Comptabilisé le</p>
                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $creditNote->posted_at?->format('d/m/Y H:i') ?? '—' }}</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-3">
            @if ($creditNote->status->value === 'draft')
                <a href="{{ route('credit-notes.edit', $creditNote->id) }}" class="flux-btn-secondary" wire:navigate>Modifier</a>
                <button wire:click="cancel" wire:confirm="Voulez-vous vraiment annuler cet avoir ?" class="rounded-md border border-orange-300 px-4 py-2 text-sm font-medium text-orange-600 hover:bg-orange-50 dark:border-orange-700 dark:hover:bg-orange-900/20">Annuler l'avoir</button>
                <button wire:click="delete" wire:confirm="Voulez-vous vraiment supprimer cet avoir ? Cette action est irréversible." class="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:hover:bg-red-900/20">Supprimer</button>
                <button wire:click="post" wire:confirm="Voulez-vous vraiment comptabiliser cet avoir ? Cette action est irréversible." class="flux-btn-primary">Comptabiliser</button>
            @elseif ($creditNote->status->value === 'posted')
                <p class="text-sm italic text-gray-500 dark:text-gray-400">Avoir comptabilisé — document immuable.</p>
            @else
                <p class="text-sm italic text-gray-500 dark:text-gray-400">Avoir annulé.</p>
            @endif
        </div>
    </div>
