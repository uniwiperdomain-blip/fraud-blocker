<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Click;
use App\Models\Engagement;
use App\Models\Event;
use App\Models\FormSubmission;
use App\Models\Pageview;
use App\Models\Tenant;
use App\Services\VisitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TrackingController extends Controller
{
    protected VisitorService $visitorService;

    public function __construct(VisitorService $visitorService)
    {
        $this->visitorService = $visitorService;
    }

    /**
     * Serve the tracking pixel JavaScript.
     */
    public function servePixel(string $pixelCode): Response
    {
        $tenant = Tenant::where('pixel_code', $pixelCode)
            ->where('is_active', true)
            ->first();

        if (!$tenant) {
            return response('// Invalid pixel code', 404)
                ->header('Content-Type', 'application/javascript');
        }

        $script = view('pixel.script', [
            'pixelCode' => $pixelCode,
            'apiBase' => url('/api/track'),
        ])->render();

        return response($script)
            ->header('Content-Type', 'application/javascript')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Track a page view.
     */
    public function pageview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pixelCode' => 'required|string',
            'cookieId' => 'required|string',
            'url' => 'required|string',
            'urlPath' => 'nullable|string',
            'title' => 'nullable|string',
            'referrer' => 'nullable|string',
            'utm_source' => 'nullable|string',
            'utm_medium' => 'nullable|string',
            'utm_campaign' => 'nullable|string',
            'utm_content' => 'nullable|string',
            'utm_term' => 'nullable|string',
            'fbclid' => 'nullable|string',
            'gclid' => 'nullable|string',
            'ttclid' => 'nullable|string',
            'fbp' => 'nullable|string',
            'fbc' => 'nullable|string',
            'campaign_id' => 'nullable|string',
            'ad_id' => 'nullable|string',
            'h_ad_id' => 'nullable|string',
            'screenWidth' => 'nullable|integer',
            'screenHeight' => 'nullable|integer',
            'viewport' => 'nullable|string',
            'isMobile' => 'nullable|boolean',
            'isIOS' => 'nullable|boolean',
            'isSafari' => 'nullable|boolean',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'fingerprint' => 'nullable|array',
        ]);

        $tenant = Tenant::where('pixel_code', $data['pixelCode'])
            ->where('is_active', true)
            ->first();

        if (!$tenant) {
            return response()->json(['error' => 'Invalid pixel code'], 400);
        }

        // Find or create visitor
        $visitor = $this->visitorService->findOrCreateVisitor(
            $tenant,
            $data['cookieId'],
            $request,
            $data
        );

        // Update visitor last seen
        $visitor->update(['last_seen_at' => now()]);
        $visitor->incrementPageviewCount();

        // Create pageview record
        $pageview = Pageview::create([
            'tenant_id' => $tenant->id,
            'visitor_id' => $visitor->id,
            'url' => $data['url'],
            'path' => $data['urlPath'] ?? parse_url($data['url'], PHP_URL_PATH),
            'title' => $data['title'] ?? null,
            'referrer' => $data['referrer'] ?? null,
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'utm_content' => $data['utm_content'] ?? null,
            'utm_term' => $data['utm_term'] ?? null,
            'fbclid' => $data['fbclid'] ?? null,
            'gclid' => $data['gclid'] ?? null,
            'ttclid' => $data['ttclid'] ?? null,
            'fbp' => $data['fbp'] ?? null,
            'fbc' => $data['fbc'] ?? null,
            'campaign_id' => $data['campaign_id'] ?? null,
            'ad_id' => $data['ad_id'] ?? null,
            'h_ad_id' => $data['h_ad_id'] ?? null,
            'screen_width' => $data['screenWidth'] ?? null,
            'screen_height' => $data['screenHeight'] ?? null,
            'viewport' => $data['viewport'] ?? null,
            'is_mobile' => $data['isMobile'] ?? false,
            'is_ios' => $data['isIOS'] ?? false,
            'is_safari' => $data['isSafari'] ?? false,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url_email' => $data['email'] ?? null,
            'url_phone' => $data['phone'] ?? null,
            'created_at' => now(),
        ]);

        // Auto-identify visitor if email/phone provided in URL
        if (!empty($data['email']) || !empty($data['phone'])) {
            $this->visitorService->identifyVisitor($visitor, [
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'sessionId' => $visitor->id,
            'pageviewId' => $pageview->id,
            'pixelId' => $tenant->pixel_code,
        ]);
    }

    /**
     * Track a form submission.
     */
    public function form(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pixelCode' => 'required|string',
            'cookieId' => 'required|string',
            'url' => 'required|string',
            'formId' => 'nullable|string',
            'formAction' => 'nullable|string',
            'triggerType' => 'nullable|string',
            'fields' => 'nullable|array',
        ]);

        $tenant = Tenant::where('pixel_code', $data['pixelCode'])
            ->where('is_active', true)
            ->first();

        if (!$tenant) {
            return response()->json(['error' => 'Invalid pixel code'], 400);
        }

        // Find visitor
        $visitor = $this->visitorService->findOrCreateVisitor(
            $tenant,
            $data['cookieId'],
            $request,
            $data
        );

        // Get latest pageview
        $pageview = $this->visitorService->getLatestPageview($visitor);

        // Extract contact info from fields
        $fields = $data['fields'] ?? [];
        $email = $this->extractFieldValue($fields, ['email', 'e-mail', 'mail', 'contact_email', 'user_email']);
        $phone = $this->extractFieldValue($fields, ['phone', 'tel', 'telephone', 'mobile', 'contact_phone', 'user_phone']);
        $firstName = $this->extractFieldValue($fields, ['first_name', 'firstname', 'fname', 'given_name']);
        $lastName = $this->extractFieldValue($fields, ['last_name', 'lastname', 'lname', 'surname', 'family_name']);
        $fullName = $this->extractFieldValue($fields, ['full_name', 'fullname', 'name', 'your_name']);
        $company = $this->extractFieldValue($fields, ['company', 'organization', 'company_name', 'business']);

        // Extract step info
        $stepNumber = isset($fields['__ak_step_number']) ? (int) $fields['__ak_step_number'] : null;
        $totalSteps = isset($fields['__ak_total_steps']) ? (int) $fields['__ak_total_steps'] : null;
        $stepLabel = $fields['__ak_step_label'] ?? null;
        $stepId = $fields['__ak_step_id'] ?? null;

        // Remove internal fields from stored data
        $cleanFields = array_filter($fields, fn($key) => !str_starts_with($key, '__ak_'), ARRAY_FILTER_USE_KEY);

        // Create form submission
        $formSubmission = FormSubmission::create([
            'tenant_id' => $tenant->id,
            'visitor_id' => $visitor->id,
            'pageview_id' => $pageview?->id,
            'form_id' => $data['formId'] ?? null,
            'form_action' => $data['formAction'] ?? null,
            'trigger_type' => $data['triggerType'] ?? null,
            'fields' => $cleanFields,
            'email' => $email,
            'phone' => $phone,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $fullName,
            'company' => $company,
            'step_number' => $stepNumber,
            'total_steps' => $totalSteps,
            'step_label' => $stepLabel,
            'step_id' => $stepId,
            'page_url' => $data['url'],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        // Update visitor with contact info
        $visitor->incrementFormSubmissionCount();
        if ($email || $phone || $fullName || $firstName) {
            $this->visitorService->identifyVisitor($visitor, [
                'email' => $email,
                'phone' => $phone,
                'name' => $fullName ?? trim(($firstName ?? '') . ' ' . ($lastName ?? '')) ?: null,
            ]);
        }

        return response()->json([
            'success' => true,
            'formSubmissionId' => $formSubmission->id,
            'contactId' => $visitor->id,
        ]);
    }

    /**
     * Track a click event.
     */
    public function click(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pixelCode' => 'required|string',
            'cookieId' => 'required|string',
            'url' => 'required|string',
            'elementType' => 'nullable|string',
            'elementText' => 'nullable|string',
            'elementId' => 'nullable|string',
            'elementClass' => 'nullable|string',
            'elementHref' => 'nullable|string',
            'isFormButton' => 'nullable|boolean',
        ]);

        $tenant = Tenant::where('pixel_code', $data['pixelCode'])
            ->where('is_active', true)
            ->first();

        if (!$tenant) {
            return response()->json(['error' => 'Invalid pixel code'], 400);
        }

        $visitor = $this->visitorService->findOrCreateVisitor(
            $tenant,
            $data['cookieId'],
            $request,
            $data
        );

        $pageview = $this->visitorService->getLatestPageview($visitor);

        $click = Click::create([
            'tenant_id' => $tenant->id,
            'visitor_id' => $visitor->id,
            'pageview_id' => $pageview?->id,
            'element_type' => $data['elementType'] ?? null,
            'element_text' => isset($data['elementText']) ? substr($data['elementText'], 0, 512) : null,
            'element_id' => $data['elementId'] ?? null,
            'element_class' => isset($data['elementClass']) ? substr($data['elementClass'], 0, 512) : null,
            'element_href' => $data['elementHref'] ?? null,
            'is_form_button' => $data['isFormButton'] ?? false,
            'url' => $data['url'],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'clickId' => $click->id,
        ]);
    }

    /**
     * Track engagement metrics.
     */
    public function engagement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pixelCode' => 'required|string',
            'cookieId' => 'required|string',
            'url' => 'required|string',
            'timeOnPage' => 'nullable|integer',
            'scrollDepth' => 'nullable|integer|min:0|max:100',
        ]);

        $tenant = Tenant::where('pixel_code', $data['pixelCode'])
            ->where('is_active', true)
            ->first();

        if (!$tenant) {
            return response()->json(['error' => 'Invalid pixel code'], 400);
        }

        $visitor = $this->visitorService->findOrCreateVisitor(
            $tenant,
            $data['cookieId'],
            $request,
            $data
        );

        $pageview = $this->visitorService->getLatestPageview($visitor);

        $engagement = Engagement::create([
            'tenant_id' => $tenant->id,
            'visitor_id' => $visitor->id,
            'pageview_id' => $pageview?->id,
            'time_on_page' => $data['timeOnPage'] ?? null,
            'scroll_depth' => $data['scrollDepth'] ?? null,
            'url' => $data['url'],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'engagementId' => $engagement->id,
        ]);
    }

    /**
     * Track a custom event.
     */
    public function event(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pixelCode' => 'required|string',
            'cookieId' => 'required|string',
            'url' => 'required|string',
            'eventName' => 'required|string',
            'eventData' => 'nullable|array',
        ]);

        $tenant = Tenant::where('pixel_code', $data['pixelCode'])
            ->where('is_active', true)
            ->first();

        if (!$tenant) {
            return response()->json(['error' => 'Invalid pixel code'], 400);
        }

        $visitor = $this->visitorService->findOrCreateVisitor(
            $tenant,
            $data['cookieId'],
            $request,
            $data
        );

        $pageview = $this->visitorService->getLatestPageview($visitor);

        $event = Event::create([
            'tenant_id' => $tenant->id,
            'visitor_id' => $visitor->id,
            'pageview_id' => $pageview?->id,
            'event_name' => $data['eventName'],
            'event_data' => $data['eventData'] ?? null,
            'url' => $data['url'],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'eventId' => $event->id,
        ]);
    }

    /**
     * Identify a visitor with contact info.
     */
    public function identify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pixelCode' => 'required|string',
            'cookieId' => 'required|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'userData' => 'nullable|array',
        ]);

        $tenant = Tenant::where('pixel_code', $data['pixelCode'])
            ->where('is_active', true)
            ->first();

        if (!$tenant) {
            return response()->json(['error' => 'Invalid pixel code'], 400);
        }

        $visitor = $this->visitorService->findOrCreateVisitor(
            $tenant,
            $data['cookieId'],
            $request,
            $data
        );

        $this->visitorService->identifyVisitor($visitor, [
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'userData' => $data['userData'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'visitorId' => $visitor->id,
        ]);
    }

    /**
     * Helper to extract field value from various possible field names.
     */
    protected function extractFieldValue(array $fields, array $possibleNames): ?string
    {
        foreach ($possibleNames as $name) {
            // Check exact match
            if (!empty($fields[$name])) {
                return $fields[$name];
            }

            // Check case-insensitive
            foreach ($fields as $key => $value) {
                if (strtolower($key) === strtolower($name) && !empty($value)) {
                    return $value;
                }
                // Check if key contains the name
                if (stripos($key, $name) !== false && !empty($value)) {
                    return $value;
                }
            }
        }

        return null;
    }
}
