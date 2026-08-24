<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                Membres de la société
            </h1>

            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                Gérez les rôles et les accès des membres de votre société.
            </p>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
            {{ session('error') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
            <thead class="bg-neutral-50 dark:bg-neutral-800">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Membre</th>
                    <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Rôle</th>
                    <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Statut</th>
                    <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse ($members as $member)
                    <tr wire:key="member-{{ $member['id'] }}">
                        <td class="px-5 py-4">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $member['name'] }}
                            </div>
                            <div class="text-xs text-neutral-500 dark:text-neutral-400">
                                {{ $member['email'] }}
                            </div>
                        </td>

                        <td class="px-5 py-4">
                            @if ($member['is_last_admin'])
                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                    Administrateur
                                </span>
                            @else
                                <select
                                    wire:change="changeRole({{ $member['id'] }}, $event.target.value)"
                                    class="rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-800 dark:text-white"
                                >
                                    @foreach (\App\Enums\CompanyRole::cases() as $role)
                                        <option value="{{ $role->value }}" wire:key="role-{{ $member['id'] }}-{{ $role->value }}" @selected($member['role'] === $role->value)>
                                            {{ $role->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                        </td>

                        <td class="px-5 py-4">
                            @if ($member['is_active'])
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/40 dark:text-green-300">
                                    Actif
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">
                                    Révoqué
                                </span>
                            @endif
                        </td>

                        <td class="px-5 py-4 text-right whitespace-nowrap">
                            @if ($member['is_active'])
                                <button
                                    type="button"
                                    wire:click="deactivateMember({{ $member['id'] }})"
                                    wire:confirm="Révoquer l'accès de ce membre pour cette société ?"
                                    class="text-sm text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300"
                                >
                                    Révoquer l'accès
                                </button>
                            @else
                                <button
                                    type="button"
                                    wire:click="activateMember({{ $member['id'] }})"
                                    class="mr-3 text-sm text-green-600 hover:text-green-700 dark:text-green-400 dark:hover:text-green-300"
                                >
                                    Restaurer
                                </button>
                                <button
                                    type="button"
                                    wire:click="removeMember({{ $member['id'] }})"
                                    wire:confirm="Retirer définitivement ce membre de la société ?"
                                    class="text-sm text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                >
                                    Retirer
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400">
                            Aucun membre dans cette société.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
