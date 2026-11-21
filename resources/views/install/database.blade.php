<x-install-layout :step="2">
    <x-card class="p-6" x-data="{ driver: @js($defaults['driver']) }">
        <h2 class="mb-1 text-lg font-semibold">{{ __('Database') }}</h2>
        <p class="mb-4 text-sm text-gray-500">{{ __('Create an empty database first, then enter its details. The tables are created for you.') }}</p>

        @if ($errors->has('database'))
            <div role="alert" class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ $errors->first('database') }}</div>
        @endif

        <form method="POST" action="{{ route('install.database.save') }}" class="space-y-5">
            @csrf

            <x-field name="driver" :label="__('Database type')">
                <x-select id="driver" name="driver" x-model="driver">
                    <option value="mysql">MySQL / MariaDB</option>
                    <option value="sqlite">SQLite</option>
                </x-select>
            </x-field>

            <div class="grid gap-5 sm:grid-cols-2" x-show="driver === 'mysql'">
                <x-field name="db_host" :label="__('Host')">
                    <x-text-input id="db_host" name="db_host" :value="$defaults['host']" />
                </x-field>
                <x-field name="db_port" :label="__('Port')">
                    <x-text-input id="db_port" name="db_port" type="number" :value="$defaults['port']" />
                </x-field>
                <x-field name="username" :label="__('Username')">
                    <x-text-input id="username" name="username" :value="$defaults['username']" autocomplete="off" />
                </x-field>
                <x-field name="password" :label="__('Password')" :hint="$hasSavedPassword ? __('Leave blank to use the password saved in .env.') : null">
                    <x-text-input id="password" name="password" type="password" autocomplete="off" />
                </x-field>
            </div>

            <x-field name="database" :label="__('Database name')" :hint="__('For SQLite, a file path such as database/database.sqlite.')">
                <x-text-input id="database" name="database" :value="$defaults['database']" required />
            </x-field>

            <x-button>{{ __('Test connection and continue') }}</x-button>
        </form>
    </x-card>
</x-install-layout>
