<?php

namespace App\Services;

use App\Models\GoogleAdsAccount;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleAdsService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $developerToken;
    protected string $redirectUri;

    public function __construct()
    {
        $this->clientId = config('services.google_ads.client_id', '');
        $this->clientSecret = config('services.google_ads.client_secret', '');
        $this->developerToken = config('services.google_ads.developer_token', '');
        $this->redirectUri = config('services.google_ads.redirect_uri', '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret) && !empty($this->developerToken);
    }

    /**
     * Generate the OAuth authorization URL.
     */
    public function getAuthorizationUrl(Tenant $tenant): string
    {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/adwords',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $tenant->id,
        ]);

        return "https://accounts.google.com/o/oauth2/v2/auth?{$params}";
    }

    /**
     * Exchange authorization code for tokens.
     */
    public function handleCallback(Tenant $tenant, string $code): GoogleAdsAccount
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to exchange authorization code: ' . $response->body());
        }

        $tokens = $response->json();

        // Get accessible customer IDs
        $customerIds = $this->getAccessibleCustomerIds($tokens['access_token']);
        $customerId = $customerIds[0] ?? '';

        return GoogleAdsAccount::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customerId,
            'account_name' => 'Google Ads Account',
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? '',
            'token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
            'auto_sync_enabled' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Refresh an expired access token.
     */
    public function refreshToken(GoogleAdsAccount $account): void
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'refresh_token' => $account->refresh_token,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to refresh token: ' . $response->body());
        }

        $tokens = $response->json();

        $account->update([
            'access_token' => $tokens['access_token'],
            'token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
        ]);
    }

    /**
     * Ensure the access token is valid, refresh if needed.
     */
    protected function ensureValidToken(GoogleAdsAccount $account): string
    {
        if ($account->isTokenExpired()) {
            $this->refreshToken($account);
            $account->refresh();
        }

        return $account->access_token;
    }

    /**
     * Push IP exclusions to all campaigns in the account.
     */
    public function pushIpExclusions(GoogleAdsAccount $account, array $ipAddresses): void
    {
        $accessToken = $this->ensureValidToken($account);
        $customerId = preg_replace('/\D/', '', $account->customer_id);

        // Build mutate operations for campaign criterion
        $operations = [];
        foreach ($ipAddresses as $ip) {
            $operations[] = [
                'create' => [
                    'campaign' => "customers/{$customerId}/campaigns/-",
                    'negative' => true,
                    'ipBlock' => [
                        'ipAddress' => $ip,
                    ],
                ],
            ];
        }

        // Use Google Ads REST API
        $managerId = $account->manager_customer_id
            ? preg_replace('/\D/', '', $account->manager_customer_id)
            : null;

        $headers = [
            'Authorization' => "Bearer {$accessToken}",
            'developer-token' => $this->developerToken,
            'Content-Type' => 'application/json',
        ];

        if ($managerId) {
            $headers['login-customer-id'] = $managerId;
        }

        // First, get all campaign IDs
        $campaigns = $this->getCampaignIds($account, $accessToken, $customerId, $headers);

        foreach ($campaigns as $campaignId) {
            foreach ($ipAddresses as $ip) {
                try {
                    $response = Http::withHeaders($headers)
                        ->post(
                            "https://googleads.googleapis.com/v18/customers/{$customerId}/campaignCriteria:mutate",
                            [
                                'operations' => [
                                    [
                                        'create' => [
                                            'campaign' => "customers/{$customerId}/campaigns/{$campaignId}",
                                            'negative' => true,
                                            'ipBlock' => [
                                                'ipAddress' => $ip,
                                            ],
                                        ],
                                    ],
                                ],
                            ]
                        );

                    if (!$response->successful()) {
                        Log::warning("GoogleAds: Failed to add IP exclusion {$ip} to campaign {$campaignId}: " . $response->body());
                    }
                } catch (\Exception $e) {
                    Log::warning("GoogleAds: Exception adding IP exclusion {$ip}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Get campaign IDs for the account.
     */
    protected function getCampaignIds(GoogleAdsAccount $account, string $accessToken, string $customerId, array $headers): array
    {
        try {
            $response = Http::withHeaders($headers)
                ->post(
                    "https://googleads.googleapis.com/v18/customers/{$customerId}/googleAds:searchStream",
                    [
                        'query' => "SELECT campaign.id FROM campaign WHERE campaign.status = 'ENABLED'",
                    ]
                );

            if (!$response->successful()) {
                Log::warning('GoogleAds: Failed to fetch campaigns: ' . $response->body());
                return [];
            }

            $results = $response->json();
            $campaignIds = [];

            foreach ($results as $batch) {
                foreach ($batch['results'] ?? [] as $result) {
                    $campaignIds[] = $result['campaign']['id'] ?? null;
                }
            }

            return array_filter($campaignIds);
        } catch (\Exception $e) {
            Log::warning('GoogleAds: Exception fetching campaigns: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get list of accessible customer IDs.
     */
    public function getAccessibleCustomerIds(string $accessToken): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'developer-token' => $this->developerToken,
            ])->get('https://googleads.googleapis.com/v18/customers:listAccessibleCustomers');

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();
            $ids = [];

            foreach ($data['resourceNames'] ?? [] as $name) {
                // Format: customers/1234567890
                $ids[] = str_replace('customers/', '', $name);
            }

            return $ids;
        } catch (\Exception $e) {
            Log::warning('GoogleAds: Failed to list accessible customers: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Remove an IP exclusion from all campaigns.
     */
    public function removeIpExclusion(GoogleAdsAccount $account, string $ipAddress): void
    {
        // Implementation similar to pushIpExclusions but with 'remove' operation
        Log::info("GoogleAds: Remove IP exclusion {$ipAddress} - to be implemented with criterion resource names");
    }
}
