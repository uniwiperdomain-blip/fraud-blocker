<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\GoogleAdsService;
use Illuminate\Http\Request;

class GoogleAdsController extends Controller
{
    public function __construct(
        protected GoogleAdsService $googleAdsService,
    ) {}

    /**
     * Redirect to Google OAuth consent screen.
     */
    public function redirect(Tenant $tenant)
    {
        if (!$this->googleAdsService->isConfigured()) {
            return redirect()->back()->with('error', 'Google Ads API is not configured. Please set the required environment variables.');
        }

        $url = $this->googleAdsService->getAuthorizationUrl($tenant);

        return redirect($url);
    }

    /**
     * Handle OAuth callback from Google.
     */
    public function callback(Request $request)
    {
        $code = $request->get('code');
        $tenantId = $request->get('state');

        if (!$code || !$tenantId) {
            return redirect('/admin/google-ads-settings')
                ->with('error', 'Authorization failed. Missing code or state.');
        }

        $tenant = Tenant::findOrFail($tenantId);

        try {
            $account = $this->googleAdsService->handleCallback($tenant, $code);

            return redirect('/admin/google-ads-settings')
                ->with('success', "Google Ads account connected successfully (Customer ID: {$account->formatted_customer_id})");
        } catch (\Exception $e) {
            return redirect('/admin/google-ads-settings')
                ->with('error', 'Failed to connect Google Ads: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect a Google Ads account.
     */
    public function disconnect(Request $request, int $accountId)
    {
        $account = \App\Models\GoogleAdsAccount::findOrFail($accountId);
        $account->update(['is_active' => false]);

        return redirect('/admin/google-ads-settings')
            ->with('success', 'Google Ads account disconnected.');
    }
}
