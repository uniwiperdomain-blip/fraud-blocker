<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Visitor;
use App\Models\Pageview;
use Illuminate\Http\Request;

class VisitorService
{
    protected FingerprintService $fingerprintService;

    public function __construct(FingerprintService $fingerprintService)
    {
        $this->fingerprintService = $fingerprintService;
    }

    /**
     * Find or create a visitor for the given tenant and cookie ID.
     */
    public function findOrCreateVisitor(
        Tenant $tenant,
        string $cookieId,
        Request $request,
        array $data = []
    ): Visitor {
        // Generate fingerprint hash
        $fingerprintData = $data['fingerprint'] ?? null;
        $fingerprintHash = $this->fingerprintService->generateHash($request, $fingerprintData);

        // Try to find existing visitor by cookie_id first
        $visitor = Visitor::where('tenant_id', $tenant->id)
            ->where('cookie_id', $cookieId)
            ->first();

        if ($visitor) {
            // Update fingerprint if not set
            if (empty($visitor->fingerprint_hash) && $fingerprintHash) {
                $visitor->update(['fingerprint_hash' => $fingerprintHash]);
            }
            return $visitor;
        }

        // Try to find by fingerprint hash (for returning visitors with cleared cookies)
        if ($fingerprintHash) {
            $visitor = Visitor::where('tenant_id', $tenant->id)
                ->where('fingerprint_hash', $fingerprintHash)
                ->first();

            if ($visitor) {
                // Update cookie_id for this returning visitor
                $visitor->update(['cookie_id' => $cookieId]);
                return $visitor;
            }
        }

        // Parse user agent for device info
        $deviceInfo = $this->fingerprintService->parseUserAgent($request->userAgent());

        // Create new visitor
        $visitor = Visitor::create([
            'tenant_id' => $tenant->id,
            'cookie_id' => $cookieId,
            'fingerprint_hash' => $fingerprintHash,
            'device_type' => $data['isMobile'] ?? false ? 'mobile' : $deviceInfo['device_type'],
            'browser' => $deviceInfo['browser'],
            'browser_version' => $deviceInfo['browser_version'],
            'os' => $deviceInfo['os'],
            'os_version' => $deviceInfo['os_version'],
            'first_utm_source' => $data['utm_source'] ?? null,
            'first_utm_medium' => $data['utm_medium'] ?? null,
            'first_utm_campaign' => $data['utm_campaign'] ?? null,
            'first_utm_content' => $data['utm_content'] ?? null,
            'first_utm_term' => $data['utm_term'] ?? null,
            'first_referrer' => $data['referrer'] ?? null,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'visit_count' => 1,
            'pageview_count' => 0,
            'form_submission_count' => 0,
        ]);

        return $visitor;
    }

    /**
     * Identify a visitor with contact information.
     */
    public function identifyVisitor(Visitor $visitor, array $data): Visitor
    {
        $updateData = [];

        if (!empty($data['email']) && empty($visitor->identified_email)) {
            $updateData['identified_email'] = $data['email'];
        }

        if (!empty($data['phone']) && empty($visitor->identified_phone)) {
            $updateData['identified_phone'] = $data['phone'];
        }

        if (!empty($data['name']) && empty($visitor->identified_name)) {
            $updateData['identified_name'] = $data['name'];
        }

        if (!empty($data['userData'])) {
            $existingData = $visitor->identified_data ?? [];
            $updateData['identified_data'] = array_merge($existingData, $data['userData']);
        }

        if (!empty($updateData)) {
            $visitor->update($updateData);
        }

        return $visitor->fresh();
    }

    /**
     * Get the most recent pageview for a visitor.
     */
    public function getLatestPageview(Visitor $visitor): ?Pageview
    {
        return $visitor->pageviews()->latest('created_at')->first();
    }

    /**
     * Update visitor location from IP (you would integrate with a GeoIP service).
     */
    public function updateLocation(Visitor $visitor, string $ipAddress): void
    {
        // TODO: Integrate with a GeoIP service like MaxMind, IP-API, etc.
        // For now, just store the IP for later processing
        // $geoData = $this->geoIpService->lookup($ipAddress);
        // $visitor->update([
        //     'country' => $geoData['country'],
        //     'country_code' => $geoData['country_code'],
        //     'city' => $geoData['city'],
        //     'region' => $geoData['region'],
        //     'timezone' => $geoData['timezone'],
        // ]);
    }

    /**
     * Merge two visitors (when we identify they're the same person).
     */
    public function mergeVisitors(Visitor $primary, Visitor $secondary): Visitor
    {
        // Update all related records to point to primary visitor
        $secondary->pageviews()->update(['visitor_id' => $primary->id]);
        $secondary->formSubmissions()->update(['visitor_id' => $primary->id]);
        $secondary->clicks()->update(['visitor_id' => $primary->id]);
        $secondary->engagements()->update(['visitor_id' => $primary->id]);
        $secondary->events()->update(['visitor_id' => $primary->id]);

        // Merge identification data
        if (empty($primary->identified_email) && !empty($secondary->identified_email)) {
            $primary->identified_email = $secondary->identified_email;
        }
        if (empty($primary->identified_phone) && !empty($secondary->identified_phone)) {
            $primary->identified_phone = $secondary->identified_phone;
        }
        if (empty($primary->identified_name) && !empty($secondary->identified_name)) {
            $primary->identified_name = $secondary->identified_name;
        }

        // Update counts
        $primary->visit_count += $secondary->visit_count;
        $primary->pageview_count += $secondary->pageview_count;
        $primary->form_submission_count += $secondary->form_submission_count;

        // Keep earliest first_seen_at
        if ($secondary->first_seen_at < $primary->first_seen_at) {
            $primary->first_seen_at = $secondary->first_seen_at;
            $primary->first_utm_source = $secondary->first_utm_source;
            $primary->first_utm_medium = $secondary->first_utm_medium;
            $primary->first_utm_campaign = $secondary->first_utm_campaign;
            $primary->first_referrer = $secondary->first_referrer;
        }

        $primary->save();

        // Delete the secondary visitor
        $secondary->delete();

        return $primary;
    }
}
