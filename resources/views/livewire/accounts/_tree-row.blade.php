@php
    $children = $byParent[$account->id] ?? collect();
    $indent = $depth * 24;
@endphp

<div
    class="flex items-center justify-between rounded-lg px-3 py-2 transition hover:bg-neutral-50 dark:hover:bg-neutral-800"
    style="padding-left: {{ 12 + $indent }}px"
>
    <div class="flex items-center gap-3 min-w-0">
        @if ($children->isNotEmpty())
            <svg class="h-4 w-4 shrink-0 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        @else
            <span class="h-4 w-4 shrink-0"></span>
        @endif

        <span class="font-mono text-sm font-semibold text-neutral-700 dark:text-neutral-300 shrink-0">
            {{ $account->code }}
        </span>

        <span class="text-sm text-neutral-800 dark:text-neutral-200 truncate">
            {{ $account->name }}
        </span>

        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium
            {{ match($account->account_type) {
                \App\Enums\AccountType::Asset => 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
                \App\Enums\AccountType::Liability => 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
                \App\Enums\AccountType::Equity => 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300',
                \App\Enums\AccountType::Revenue => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
                \App\Enums\AccountType::Expense => 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
            } }}">
            {{ $account->account_type->label() }}
        </span>

        @if (! $account->is_active)
            <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">
                Inactif
            </span>
        @endif
    </div>

    <div class="flex items-center gap-1 shrink-0">
        @if (! $account->fiscalYear->is_closed)
            <a
                href="{{ route('accounts.edit', $account->id) }}"
                wire:navigate
                class="rounded px-2 py-1 text-xs font-medium text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-800 dark:hover:bg-neutral-700 dark:hover:text-neutral-200"
            >
                Modifier
            </a>

            <button
                wire:click="toggleActive({{ $account->id }})"
                class="rounded px-2 py-1 text-xs font-medium transition
                    {{ $account->is_active ? 'text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950' : 'text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950' }}"
            >
                {{ $account->is_active ? 'Désactiver' : 'Activer' }}
            </button>

            @if (! ($account->children_count > 0))
                <button
                    wire:click="delete({{ $account->id }})"
                    wire:confirm="Supprimer le compte « {{ $account->name }} » ?"
                    class="rounded px-2 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50 dark:hover:bg-red-950"
                >
                    Supprimer
                </button>
            @endif
        @endif
    </div>
</div>

@if ($children->isNotEmpty())
    @foreach ($children->sortBy('code') as $child)
        @include('livewire.accounts._tree-row', [
            'account' => $child,
            'byParent' => $byParent,
            'depth' => $depth + 1,
        ])
    @endforeach
@endif
