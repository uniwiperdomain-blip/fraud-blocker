<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IpIntelligenceService
{
    /**
     * Look up IP intelligence data. Results are cached.
     *
     * @return array{is_datacenter: bool, is_vpn: bool, is_proxy: bool, is_tor: bool, provider: ?string, ip_type: string, source: string}
     */
    public function lookup(string $ipAddress): array
    {
        $cacheHours = config('fraud.ip_intelligence.cache_hours', 24);
        $cacheKey = "ip_intelligence:{$ipAddress}";

        return Cache::remember($cacheKey, now()->addHours($cacheHours), function () use ($ipAddress) {
            return $this->performLookup($ipAddress);
        });
    }

    protected function performLookup(string $ipAddress): array
    {
        $default = [
            'is_datacenter' => false,
            'is_vpn' => false,
            'is_proxy' => false,
            'is_tor' => false,
            'provider' => null,
            'ip_type' => 'residential',
            'source' => 'none',
        ];

        // Skip private/reserved IPs
        if ($this->isPrivateIp($ipAddress)) {
            return $default;
        }

        $provider = config('fraud.ip_intelligence.provider', 'ipinfo');

        if ($provider === 'none') {
            return $default;
        }

        if ($provider === 'ipinfo') {
            return $this->lookupIpinfo($ipAddress) ?? $default;
        }

        return $default;
    }

    protected function lookupIpinfo(string $ipAddress): ?array
    {
        $token = config('services.ipinfo.token');

        if (empty($token)) {
            Log::warning('IpIntelligence: IPINFO_TOKEN not configured');
            return null;
        }

        try {
            $response = Http::timeout(5)
                ->get("https://ipinfo.io/{$ipAddress}", [
                    'token' => $token,
                ]);

            if (!$response->successful()) {
                Log::warning("IpIntelligence: ipinfo.io returned {$response->status()} for {$ipAddress}");
                return null;
            }

            $data = $response->json();
            $privacy = $data['privacy'] ?? [];
            $company = $data['company'] ?? [];
            $asn = $data['asn'] ?? [];

            $isVpn = $privacy['vpn'] ?? false;
            $isProxy = $privacy['proxy'] ?? false;
            $isTor = $privacy['tor'] ?? false;
            $isHosting = $privacy['hosting'] ?? false;

            // Determine IP type from company type or ASN type
            $companyType = $company['type'] ?? $asn['type'] ?? 'isp';
            $isDatacenter = $isHosting || in_array($companyType, ['hosting', 'business']);

            $providerName = $company['name'] ?? $asn['name'] ?? null;

            return [
                'is_datacenter' => $isDatacenter,
                'is_vpn' => $isVpn,
                'is_proxy' => $isProxy,
                'is_tor' => $isTor,
                'provider' => $providerName,
                'ip_type' => $isDatacenter ? 'hosting' : ($isVpn ? 'vpn' : ($isProxy ? 'proxy' : ($isTor ? 'tor' : 'residential'))),
                'source' => 'ipinfo.io',
            ];
        } catch (\Exception $e) {
            Log::warning("IpIntelligence: Failed to query ipinfo.io for {$ipAddress}: {$e->getMessage()}");
            return null;
        }
    }

    protected function isPrivateIp(string $ipAddress): bool
    {
        return !filter_var(
            $ipAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Check if an IP is suspicious (datacenter, VPN, proxy, or Tor).
     */
    public function isSuspicious(string $ipAddress): bool
    {
        $result = $this->lookup($ipAddress);

        return $result['is_datacenter'] || $result['is_vpn'] || $result['is_proxy'] || $result['is_tor'];
    }
}
