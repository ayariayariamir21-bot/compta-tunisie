<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <div class="px-3 pb-2">
                <livewire:company-switcher />
            </div>

            <div class="px-3 pb-2">
                <livewire:accounting-period-switcher />
            </div>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="building-office" :href="route('companies.index')" :current="request()->routeIs('companies.*')" wire:navigate>
                        {{ __('Entreprises') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="calendar" :href="route('fiscal-years.index')" :current="request()->routeIs('fiscal-years.*')" wire:navigate>
                        {{ __('Exercices comptables') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="calendar-days" :href="route('accounting-periods.index')" :current="request()->routeIs('accounting-periods.*')" wire:navigate>
                        {{ __('Périodes comptables') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="list-bullet" :href="route('accounts.index')" :current="request()->routeIs('accounts.*')" wire:navigate>
                        {{ __('Plan comptable') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="book-open" :href="route('journals.index')" :current="request()->routeIs('journals.*')" wire:navigate>
                        {{ __('Journaux') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="document-text" :href="route('journal-entries.index')" :current="request()->routeIs('journal-entries.*')" wire:navigate>
                        {{ __('Écritures comptables') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="currency-dollar" :href="route('payment-methods.index')" :current="request()->routeIs('payment-methods.*')" wire:navigate>
                        {{ __('Moyens de paiement') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="calculator" :href="route('tax-rates.index')" :current="request()->routeIs('tax-rates.*')" wire:navigate>
                        {{ __('Taxes') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Ventes')" class="grid">
                    <flux:sidebar.item icon="users" :href="route('customers.index')" :current="request()->routeIs('customers.*')" wire:navigate>
                        {{ __('Clients') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Achats')" class="grid">
                    <flux:sidebar.item icon="truck" :href="route('suppliers.index')" :current="request()->routeIs('suppliers.*')" wire:navigate>
                        {{ __('Fournisseurs') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Rapports comptables')" class="grid">
                    <flux:sidebar.item icon="book-open" :href="route('reports.general-ledger')" :current="request()->routeIs('reports.general-ledger')" wire:navigate>
                        {{ __('Grand Livre') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="calculator" :href="route('reports.trial-balance')" :current="request()->routeIs('reports.trial-balance')" wire:navigate>
                        {{ __('Balance') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                    {{ __('Repository') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
                    {{ __('Documentation') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
