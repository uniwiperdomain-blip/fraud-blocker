<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        {{-- Connected Accounts Section --}}
        <x-filament::section>
            <x-slot name="heading">Connected Google Ads Accounts</x-slot>

            @if(count($accounts) > 0)
                <div class="space-y-3">
                    @foreach($accounts as $account)
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div>
                                <div class="font-medium">{{ $account['account_name'] ?? 'Google Ads Account' }}</div>
                                <div class="text-sm text-gray-500">Customer ID: {{ $account['customer_id'] }}</div>
                                @if($account['last_synced_at'])
                                    <div class="text-xs text-gray-400">Last synced: {{ \Carbon\Carbon::parse($account['last_synced_at'])->diffForHumans() }}</div>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                @if($account['last_sync_status'] === 'success')
                                    <x-filament::badge color="success">Synced</x-filament::badge>
                                @elseif($account['last_sync_status'] === 'error')
                                    <x-filament::badge color="danger">Error</x-filament::badge>
                                @else
                                    <x-filament::badge color="gray">Pending</x-filament::badge>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-6 text-gray-500">
                    <p>No Google Ads accounts connected.</p>
                    <p class="text-sm mt-1">Connect your account to automatically push IP exclusions, or use the manual export below.</p>
                </div>
            @endif

            <div class="mt-4">
                <x-filament::button wire:click="connectGoogleAds" icon="heroicon-o-link">
                    Connect Google Ads Account
                </x-filament::button>
            </div>
        </x-filament::section>

        {{-- Manual Export Section --}}
        <x-filament::section>
            <x-slot name="heading">Manual IP Export</x-slot>
            <x-slot name="description">Copy these IPs to your Google Ads campaign IP exclusion settings</x-slot>

            @if(strlen($blockedIpsList) > 0)
                <div class="space-y-3">
                    <textarea
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 font-mono text-sm"
                        rows="10"
                        readonly
                        id="blocked-ips-textarea"
                    >{{ $blockedIpsList }}</textarea>

                    <div class="flex gap-2">
                        <x-filament::button
                            color="gray"
                            icon="heroicon-o-clipboard-document"
                            x-on:click="navigator.clipboard.writeText(document.getElementById('blocked-ips-textarea').value); $notification({ title: 'Copied!', description: 'IPs copied to clipboard' })"
                        >
                            Copy to Clipboard
                        </x-filament::button>

                        <x-filament::button
                            color="gray"
                            icon="heroicon-o-arrow-down-tray"
                            wire:click="downloadCsv"
                        >
                            Download CSV
                        </x-filament::button>
                    </div>
                </div>
            @else
                <div class="text-center py-6 text-gray-500">
                    <p>No blocked IPs to export for this pixel.</p>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
