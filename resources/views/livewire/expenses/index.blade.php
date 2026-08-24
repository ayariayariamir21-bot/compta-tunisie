<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dépenses</h1>
    @can('create', [App\Models\Expense::class, $currentCompany])
        <a href="{{ route('expenses.create') }}" class="flux-btn-primary" wire:navigate>
            Nouvelle dépense
        </a>
    @endcan
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

    <div class="flux-card">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <div>
                <flux:input wire:model.live="search" placeholder="Rechercher..." label="Recherche" />
            </div>
            <div>
                <flux:select wire:model.live="filterStatus" label="Statut" placeholder="Tous">
                    <option value="">Tous les statuts</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="filterSupplierId" label="Fournisseur" placeholder="Tous">
                    <option value="">Tous les fournisseurs</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="filterPaymentMethodId" label="Mode de règlement" placeholder="Tous">
                    <option value="">Tous les modes</option>
                    @foreach($paymentMethods as $method)
                        <option value="{{ $method->id }}">{{ $method->name }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:input wire:model.live="filterDateFrom" type="date" label="Date début" />
            </div>
            <div>
                <flux:input wire:model.live="filterDateTo" type="date" label="Date fin" />
            </div>
        </div>
    </div>

    @if ($currentCompany)
        <div class="flux-card">
            @if ($expenses->count() === 0)
                <div class="py-12 text-center">
                    <flux:icon name="receipt-percent" class="mx-auto h-12 w-12 text-gray-400" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Aucune dépense trouvée.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Numéro</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Fournisseur</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Description</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total TTC</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Statut</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                            @foreach($expenses as $expense)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $expense->expense_number }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $expense->expense_date->format('d/m/Y') }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $expense->supplier?->name ?? '—' }}
                                    </td>
                                    <td class="max-w-xs truncate px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $expense->description ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-white">
                                        {{ number_format((float) $expense->total, 3, '.', '') }} {{ $expense->currency }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-center">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                            {{ $expense->status->color() }}">
                                            {{ $expense->status->label() }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <a href="{{ route('expenses.show', $expense) }}" class="text-flux-600 hover:text-flux-500 dark:text-flux-400" wire:navigate>Voir</a>
                                        @if ($expense->isDraft())
                                            <span class="text-gray-300 dark:text-gray-600">|</span>
                                            <a href="{{ route('expenses.edit', $expense) }}" class="text-flux-600 hover:text-flux-500 dark:text-flux-400" wire:navigate>Modifier</a>
                                            <span class="text-gray-300 dark:text-gray-600">|</span>
                                            <button wire:click="post({{ $expense->id }})" wire:confirm="Voulez-vous vraiment comptabiliser cette dépense ?" class="text-green-600 hover:text-green-500 dark:text-green-400">Comptabiliser</button>
                                            <span class="text-gray-300 dark:text-gray-600">|</span>
                                            <button wire:click="cancel({{ $expense->id }})" wire:confirm="Voulez-vous vraiment annuler cette dépense ?" class="text-orange-600 hover:text-orange-500 dark:text-orange-400">Annuler</button>
                                            <span class="text-gray-300 dark:text-gray-600">|</span>
                                            <button wire:click="delete({{ $expense->id }})" wire:confirm="Voulez-vous vraiment supprimer cette dépense ? Cette action est irréversible." class="text-red-600 hover:text-red-500 dark:text-red-400">Supprimer</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $expenses->links() }}
                </div>
            @endif
        </div>
    @else
        <div class="flux-card">
            <div class="py-12 text-center">
                <flux:icon name="building-office" class="mx-auto h-12 w-12 text-gray-400" />
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Veuillez sélectionner une société pour afficher les dépenses.</p>
            </div>
        </div>
    @endif
</div>
