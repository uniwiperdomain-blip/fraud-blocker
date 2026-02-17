<?php

namespace App\Services;

use App\Models\BlockedIp;
use App\Models\FraudLog;
use App\Models\FraudSetting;
use App\Models\Pageview;
use App\Models\Tenant;
use App\Models\Visitor;
use Illuminate\Support\Facades\Log;

class FraudDetectionService
{
    public function __construct(
        protected IpIntelligenceService $ipIntelligence,
    ) {}

    /**
     * Fast real-time check called during pageview tracking.
     * Returns fraud assessment for immediate flagging.
     */
    public function performRealtimeCheck(
        Tenant $tenant,
        Visitor $visitor,
        Pageview $pageview,
        array $botSignals
    ): array {
        if (!config('fraud.enabled', true)) {
            return ['is_suspicious' => false, 'fraud_score' => 0, 'signals' => []];
        }

        $settings = FraudSetting::getForTenant($tenant);
        $signals = [];
        $totalScore = 0;

        // Signal: Bot detection (real-time)
        if ($settings->bot_detection_enabled) {
            $botResult = $this->checkBotSignals($tenant, $visitor, $pageview, $botSignals, $settings);
            if ($botResult) {
                $signals[] = $botResult;
                $totalScore += $botResult['score_points'];
            }
        }

        // Signal: Rapid clicks (lightweight real-time check)
        if ($settings->rapid_clicks_enabled && $pageview->gclid) {
            $rapidResult = $this->checkRapidClicks($tenant, $pageview, $settings);
            if ($rapidResult) {
                $signals[] = $rapidResult;
                $totalScore += $rapidResult['score_points'];
            }
        }

        // Log any detected signals
        foreach ($signals as $signal) {
            FraudLog::create([
                'tenant_id' => $tenant->id,
                'visitor_id' => $visitor->id,
                'pageview_id' => $pageview->id,
                'ip_address' => $pageview->ip_address,
                'signal_type' => $signal['signal_type'],
                'score_points' => $signal['score_points'],
                'reason' => $signal['reason'],
                'evidence' => $signal['evidence'],
                'gclid' => $pageview->gclid,
                'created_at' => now(),
            ]);
        }

        return [
            'is_suspicious' => $totalScore > 0,
            'fraud_score' => $totalScore,
            'signals' => $signals,
        ];
    }

    /**
     * Full deferred analysis for a pageview. Called by AnalyzeFraudJob.
     * Checks engagement + datacenter IP signals that require delayed data.
     */
    public function analyzePageview(Tenant $tenant, Visitor $visitor, Pageview $pageview): int
    {
        if (!config('fraud.enabled', true)) {
            return 0;
        }

        $settings = FraudSetting::getForTenant($tenant);
        $totalScore = 0;

        // Signal: Low engagement (deferred - needs engagement data)
        if ($settings->low_engagement_enabled && $pageview->gclid) {
            $engagementResult = $this->checkLowEngagement($tenant, $visitor, $pageview, $settings);
            if ($engagementResult) {
                FraudLog::create([
                    'tenant_id' => $tenant->id,
                    'visitor_id' => $visitor->id,
                    'pageview_id' => $pageview->id,
                    'ip_address' => $pageview->ip_address,
                    'signal_type' => $engagementResult['signal_type'],
                    'score_points' => $engagementResult['score_points'],
                    'reason' => $engagementResult['reason'],
                    'evidence' => $engagementResult['evidence'],
                    'gclid' => $pageview->gclid,
                    'created_at' => now(),
                ]);
                $totalScore += $engagementResult['score_points'];
            }
        }

        // Signal: Datacenter/VPN IP (deferred - external API call)
        if ($settings->datacenter_ip_enabled && $pageview->ip_address) {
            $dcResult = $this->checkDatacenterIp($tenant, $pageview, $settings);
            if ($dcResult) {
                // Only log if not already logged for this IP + tenant recently
                $existing = FraudLog::where('tenant_id', $tenant->id)
                    ->where('ip_address', $pageview->ip_address)
                    ->where('signal_type', FraudLog::SIGNAL_DATACENTER_IP)
                    ->where('created_at', '>=', now()->subHours($settings->score_window_hours))
                    ->exists();

                if (!$existing) {
                    FraudLog::create([
                        'tenant_id' => $tenant->id,
                        'visitor_id' => $visitor->id,
                        'pageview_id' => $pageview->id,
                        'ip_address' => $pageview->ip_address,
                        'signal_type' => $dcResult['signal_type'],
                        'score_points' => $dcResult['score_points'],
                        'reason' => $dcResult['reason'],
                        'evidence' => $dcResult['evidence'],
                        'gclid' => $pageview->gclid,
                        'created_at' => now(),
                    ]);
                    $totalScore += $dcResult['score_points'];
                }
            }
        }

        // Update pageview fraud score
        $currentIpScore = $this->getIpFraudScore($tenant, $pageview->ip_address);
        $pageview->update([
            'fraud_score' => $currentIpScore,
            'is_suspicious' => $currentIpScore > 0,
        ]);

        return $totalScore;
    }

    /**
     * Calculate total active fraud score for an IP within the tenant's scoring window.
     */
    public function getIpFraudScore(Tenant $tenant, string $ipAddress): int
    {
        $settings = FraudSetting::getForTenant($tenant);

        return (int) FraudLog::where('tenant_id', $tenant->id)
            ->where('ip_address', $ipAddress)
            ->where('created_at', '>=', now()->subHours($settings->score_window_hours))
            ->sum('score_points');
    }

    /**
     * Check if auto-block threshold is reached and block if so.
     */
    public function checkAndBlock(Tenant $tenant, string $ipAddress): ?BlockedIp
    {
        $settings = FraudSetting::getForTenant($tenant);

        if (!$settings->auto_block_enabled) {
            return null;
        }

        $score = $this->getIpFraudScore($tenant, $ipAddress);

        if ($score < $settings->block_threshold) {
            return null;
        }

        // Already blocked?
        $existing = BlockedIp::where('tenant_id', $tenant->id)
            ->where('ip_address', $ipAddress)
            ->first();

        if ($existing) {
            // Reactivate if was deactivated, update score
            $existing->update([
                'fraud_score' => $score,
                'is_active' => true,
            ]);
            return $existing;
        }

        $blocked = BlockedIp::create([
            'tenant_id' => $tenant->id,
            'ip_address' => $ipAddress,
            'fraud_score' => $score,
            'block_reason' => 'auto',
            'is_active' => true,
        ]);

        Log::info("FraudDetection: Auto-blocked IP {$ipAddress} for tenant {$tenant->id} with score {$score}");

        return $blocked;
    }

    /**
     * Check if an IP is currently blocked for a tenant.
     */
    public function isBlocked(Tenant $tenant, string $ipAddress): bool
    {
        return BlockedIp::where('tenant_id', $tenant->id)
            ->where('ip_address', $ipAddress)
            ->active()
            ->exists();
    }

    // -------------------------------------------------------
    // Individual Signal Analyzers
    // -------------------------------------------------------

    protected function checkBotSignals(
        Tenant $tenant,
        Visitor $visitor,
        Pageview $pageview,
        array $botSignals,
        FraudSetting $settings
    ): ?array {
        $indicators = [];
        $userAgent = $pageview->user_agent ?? '';

        // Server-side: Check for known bot user agents
        $botPatterns = config('fraud.bot_patterns', []);
        $legitimateBots = config('fraud.legitimate_bots', []);

        // Skip if it's a legitimate bot
        foreach ($legitimateBots as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return null;
            }
        }

        // Check for suspicious bot patterns
        foreach ($botPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                $indicators[] = 'bot_user_agent';
                break;
            }
        }

        // Missing user agent
        if (empty($userAgent)) {
            $indicators[] = 'missing_user_agent';
        }

        // Client-side bot signals
        if (!empty($botSignals)) {
            if (!empty($botSignals['webdriver'])) {
                $indicators[] = 'webdriver_true';
            }
            if (!empty($botSignals['honeypot_filled'])) {
                $indicators[] = 'honeypot_filled';
            }
            if (!empty($botSignals['chrome_missing']) && stripos($userAgent, 'Chrome') !== false) {
                $indicators[] = 'chrome_object_missing';
            }
            if (isset($botSignals['languages_count']) && $botSignals['languages_count'] === 0) {
                $indicators[] = 'no_languages';
            }
            if (isset($botSignals['plugins_count']) && $botSignals['plugins_count'] === 0 && stripos($userAgent, 'Firefox') === false) {
                // Firefox legitimately reports 0 plugins
                $indicators[] = 'no_plugins';
            }
            if (isset($botSignals['js_challenge_passed']) && $botSignals['js_challenge_passed'] === false) {
                $indicators[] = 'js_challenge_failed';
            }
        }

        // No fingerprint data at all (headless browser likely)
        $fingerprintSignals = $pageview->bot_signals ?? [];
        if (empty($botSignals) && empty($fingerprintSignals)) {
            $indicators[] = 'no_client_signals';
        }

        if (empty($indicators)) {
            return null;
        }

        return [
            'signal_type' => FraudLog::SIGNAL_BOT_DETECTED,
            'score_points' => $settings->bot_detection_points,
            'reason' => 'Bot indicators detected: ' . implode(', ', $indicators),
            'evidence' => [
                'bot_indicators' => $indicators,
                'user_agent' => mb_substr($userAgent, 0, 200),
                'bot_signals_received' => !empty($botSignals),
            ],
        ];
    }

    protected function checkRapidClicks(
        Tenant $tenant,
        Pageview $pageview,
        FraudSetting $settings
    ): ?array {
        $windowStart = now()->subSeconds($settings->rapid_clicks_window_seconds);

        // Count gclid pageviews from same IP in the window
        $gclidCount = Pageview::where('tenant_id', $tenant->id)
            ->where('ip_address', $pageview->ip_address)
            ->whereNotNull('gclid')
            ->where('created_at', '>=', $windowStart)
            ->count();

        if ($gclidCount < $settings->rapid_clicks_count) {
            return null;
        }

        // Check if there was already a rapid_clicks log for this IP in this window
        $alreadyLogged = FraudLog::where('tenant_id', $tenant->id)
            ->where('ip_address', $pageview->ip_address)
            ->where('signal_type', FraudLog::SIGNAL_RAPID_CLICKS)
            ->where('created_at', '>=', $windowStart)
            ->exists();

        if ($alreadyLogged) {
            return null;
        }

        // Count unique gclids
        $uniqueGclids = Pageview::where('tenant_id', $tenant->id)
            ->where('ip_address', $pageview->ip_address)
            ->whereNotNull('gclid')
            ->where('created_at', '>=', $windowStart)
            ->distinct('gclid')
            ->count('gclid');

        return [
            'signal_type' => FraudLog::SIGNAL_RAPID_CLICKS,
            'score_points' => $settings->rapid_clicks_points,
            'reason' => "{$gclidCount} Google Ads clicks from same IP within {$settings->rapid_clicks_window_seconds}s",
            'evidence' => [
                'gclid_count' => $gclidCount,
                'unique_gclids' => $uniqueGclids,
                'window_seconds' => $settings->rapid_clicks_window_seconds,
                'threshold' => $settings->rapid_clicks_count,
            ],
        ];
    }

    protected function checkLowEngagement(
        Tenant $tenant,
        Visitor $visitor,
        Pageview $pageview,
        FraudSetting $settings
    ): ?array {
        // Get engagement data for this pageview
        $engagement = $pageview->engagements()->first();

        $timeOnPage = $engagement?->time_on_page ?? 0;
        $scrollDepth = $engagement?->scroll_depth ?? 0;
        $clickCount = $pageview->clicks()->count();

        // Check if engagement meets the minimum thresholds
        if ($timeOnPage >= $settings->low_engagement_min_time_seconds) {
            return null;
        }
        if ($scrollDepth >= $settings->low_engagement_min_scroll_depth) {
            return null;
        }
        if ($clickCount > 0) {
            return null;
        }

        // Check if already logged for this pageview
        $alreadyLogged = FraudLog::where('pageview_id', $pageview->id)
            ->where('signal_type', FraudLog::SIGNAL_LOW_ENGAGEMENT)
            ->exists();

        if ($alreadyLogged) {
            return null;
        }

        return [
            'signal_type' => FraudLog::SIGNAL_LOW_ENGAGEMENT,
            'score_points' => $settings->low_engagement_points,
            'reason' => "Ad click with no engagement: {$timeOnPage}s on page, {$scrollDepth}% scroll, {$clickCount} clicks",
            'evidence' => [
                'time_on_page' => $timeOnPage,
                'scroll_depth' => $scrollDepth,
                'clicks_count' => $clickCount,
                'gclid' => $pageview->gclid,
                'min_time_threshold' => $settings->low_engagement_min_time_seconds,
                'min_scroll_threshold' => $settings->low_engagement_min_scroll_depth,
            ],
        ];
    }

    protected function checkDatacenterIp(
        Tenant $tenant,
        Pageview $pageview,
        FraudSetting $settings
    ): ?array {
        $result = $this->ipIntelligence->lookup($pageview->ip_address);

        if (!$result['is_datacenter'] && !$result['is_vpn'] && !$result['is_proxy'] && !$result['is_tor']) {
            return null;
        }

        return [
            'signal_type' => FraudLog::SIGNAL_DATACENTER_IP,
            'score_points' => $settings->datacenter_ip_points,
            'reason' => "Non-residential IP detected: {$result['ip_type']}" . ($result['provider'] ? " ({$result['provider']})" : ''),
            'evidence' => [
                'ip_type' => $result['ip_type'],
                'provider' => $result['provider'],
                'is_vpn' => $result['is_vpn'],
                'is_proxy' => $result['is_proxy'],
                'is_tor' => $result['is_tor'],
                'is_datacenter' => $result['is_datacenter'],
                'source' => $result['source'],
            ],
        ];
    }
}
