<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('rentdesk.locales.'.app()->getLocale().'.dir', 'ltr') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <title>{{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <x-js-config />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-100 font-sans text-gray-900 antialiased">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen">
            <div x-cloak x-show="sidebarOpen" x-on:click="sidebarOpen = false" class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden"></div>

            <aside
                class="fixed inset-y-0 start-0 z-40 print:hidden w-64 -translate-x-full rtl:translate-x-full overflow-y-auto bg-gray-900 p-4 transition-transform lg:translate-x-0"
                x-bind:class="{ 'translate-x-0 rtl:translate-x-0': sidebarOpen }"
            >
                <a href="{{ route('dashboard') }}" class="mb-6 block px-3 text-lg font-bold text-white">
                    {{ config('app.name') }}
                </a>

                @php
                    $canRentals = auth()->user()->can('viewAny', App\Models\Rental::class);
                    $canBookings = config('rentdesk.public_booking') && auth()->user()->can('viewAny', App\Models\BookingRequest::class);
                    $canCustomers = auth()->user()->can('viewAny', App\Models\Customer::class);
                    $canInvoices = auth()->user()->can('viewAny', App\Models\Invoice::class);
                    $canReports = auth()->user()->can('reports.view');
                    $canSettings = auth()->user()->can('settings.manage');
                    $heading = 'px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wider text-gray-500';
                @endphp

                <nav class="space-y-1" aria-label="{{ __('Main navigation') }}">
                    <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-sidebar-link>

                    @if ($canRentals || $canBookings)
                        <p class="{{ $heading }}">{{ __('Rentals') }}</p>

                        @if ($canRentals)
                            <x-sidebar-link :href="route('rentals.index')" :active="request()->routeIs('rentals.*', 'items.*')">
                                {{ __('Rentals') }}
                            </x-sidebar-link>
                        @endif
                        @if ($canBookings)
                            <x-sidebar-link :href="route('booking-requests.index')" :active="request()->routeIs('booking-requests.*')">
                                {{ __('Booking requests') }}
                            </x-sidebar-link>
                        @endif
                        @if ($canRentals)
                            <x-sidebar-link :href="route('calendar')" :active="request()->routeIs('calendar')">
                                {{ __('Calendar') }}
                            </x-sidebar-link>
                            <x-sidebar-link :href="route('availability')" :active="request()->routeIs('availability')">
                                {{ __('Availability') }}
                            </x-sidebar-link>
                            <x-sidebar-link :href="route('scan')" :active="request()->routeIs('scan')">
                                {{ __('Scan unit') }}
                            </x-sidebar-link>
                        @endif
                    @endif

                    @if ($canCustomers || $canInvoices || $canReports)
                        <p class="{{ $heading }}">{{ __('Billing') }}</p>

                        @if ($canCustomers)
                            <x-sidebar-link :href="route('customers.index')" :active="request()->routeIs('customers.*', 'documents.*')">
                                {{ __('Customers') }}
                            </x-sidebar-link>
                        @endif
                        @if ($canInvoices)
                            <x-sidebar-link :href="route('invoices.index')" :active="request()->routeIs('invoices.*')">
                                {{ __('Invoices') }}
                            </x-sidebar-link>
                        @endif
                        @if ($canReports)
                            <x-sidebar-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                                {{ __('Reports') }}
                            </x-sidebar-link>
                        @endif
                    @endif

                    <p class="{{ $heading }}">{{ __('Inventory') }}</p>

                    @can('viewAny', App\Models\Product::class)
                        <x-sidebar-link :href="route('products.index')" :active="request()->routeIs('products.*', 'units.*')">
                            {{ __('Products') }}
                        </x-sidebar-link>
                    @endcan
                    @can('viewAny', App\Models\Category::class)
                        <x-sidebar-link :href="route('categories.index')" :active="request()->routeIs('categories.*')">
                            {{ __('Categories') }}
                        </x-sidebar-link>
                    @endcan
                    @can('viewAny', App\Models\Brand::class)
                        <x-sidebar-link :href="route('brands.index')" :active="request()->routeIs('brands.*')">
                            {{ __('Brands') }}
                        </x-sidebar-link>
                    @endcan
                    @can('viewAny', App\Models\Kit::class)
                        <x-sidebar-link :href="route('kits.index')" :active="request()->routeIs('kits.*')">
                            {{ __('Kits') }}
                        </x-sidebar-link>
                    @endcan
                    @can('viewAny', App\Models\Location::class)
                        <x-sidebar-link :href="route('locations.index')" :active="request()->routeIs('locations.*')">
                            {{ __('Locations') }}
                        </x-sidebar-link>
                    @endcan
                    @can('viewAny', App\Models\StockTransfer::class)
                        <x-sidebar-link :href="route('transfers.index')" :active="request()->routeIs('transfers.*')">
                            {{ __('Transfers') }}
                        </x-sidebar-link>
                    @endcan
                    @can('viewAny', App\Models\MaintenanceTask::class)
                        <x-sidebar-link :href="route('maintenance.index')" :active="request()->routeIs('maintenance.*')">
                            {{ __('Maintenance') }}
                        </x-sidebar-link>
                    @endcan

                    @if ($canSettings)
                        <p class="{{ $heading }}">{{ __('Administration') }}</p>

                        <x-sidebar-link :href="route('settings.edit')" :active="request()->routeIs('settings.*')">
                            {{ __('Settings') }}
                        </x-sidebar-link>
                        <x-sidebar-link :href="route('languages.index')" :active="request()->routeIs('languages.*')">
                            {{ __('Languages') }}
                        </x-sidebar-link>
                        <x-sidebar-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                            {{ __('Users') }}
                        </x-sidebar-link>
                    @endif
                </nav>
            </aside>

            <div class="lg:ps-64 print:ps-0">
                <header class="sticky top-0 z-20 print:hidden flex h-14 items-center justify-between border-b border-gray-200 bg-white px-4 sm:px-6">
                    <button type="button" class="rounded-md p-2 text-gray-500 hover:bg-gray-100 lg:hidden" x-on:click="sidebarOpen = true" aria-label="{{ __('Open menu') }}">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>

                    <div class="hidden text-sm font-medium text-gray-700 lg:block">
                        {{ $header ?? '' }}
                    </div>

                    <div class="ms-auto me-3"><x-locale-switcher /></div>

                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button type="button" class="inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900">
                                {{ Auth::user()->name }}
                                <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">{{ __('Profile') }}</x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </header>

                <main class="p-4 sm:p-6">
                    <x-flash />

                    {{ $slot }}
                </main>
            </div>
        </div>
        @if (request()->boolean('print'))
            <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
        @endif
        @stack('scripts')
    </body>
</html>
