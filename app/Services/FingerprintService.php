<?php

namespace App\Services;

use Illuminate\Http\Request;

class FingerprintService
{
    /**
     * Generate a fingerprint hash from request data and client-provided fingerprint.
     */
    public function generateHash(Request $request, ?array $fingerprintData = null): string
    {
        $components = [];

        // Server-side components
        $components[] = $request->userAgent() ?? '';
        $components[] = $request->header('Accept-Language', '');
        $components[] = $request->header('Accept-Encoding', '');

        // Client-side fingerprint components (sent from JS)
        if ($fingerprintData) {
            // Canvas fingerprint
            if (!empty($fingerprintData['canvas'])) {
                $components[] = $fingerprintData['canvas'];
            }

            // WebGL fingerprint
            if (!empty($fingerprintData['webgl'])) {
                $components[] = $fingerprintData['webgl'];
            }

            // Audio fingerprint
            if (!empty($fingerprintData['audio'])) {
                $components[] = $fingerprintData['audio'];
            }

            // Fonts
            if (!empty($fingerprintData['fonts'])) {
                $components[] = is_array($fingerprintData['fonts'])
                    ? implode(',', $fingerprintData['fonts'])
                    : $fingerprintData['fonts'];
            }

            // Screen info
            if (!empty($fingerprintData['screen'])) {
                $components[] = $fingerprintData['screen'];
            }

            // Timezone
            if (!empty($fingerprintData['timezone'])) {
                $components[] = $fingerprintData['timezone'];
            }

            // Platform
            if (!empty($fingerprintData['platform'])) {
                $components[] = $fingerprintData['platform'];
            }

            // Hardware concurrency
            if (!empty($fingerprintData['hardwareConcurrency'])) {
                $components[] = (string) $fingerprintData['hardwareConcurrency'];
            }

            // Device memory
            if (!empty($fingerprintData['deviceMemory'])) {
                $components[] = (string) $fingerprintData['deviceMemory'];
            }
        }

        $fingerprint = implode('|', array_filter($components));

        return hash('sha256', $fingerprint);
    }

    /**
     * Parse user agent for device info.
     */
    public function parseUserAgent(?string $userAgent): array
    {
        if (empty($userAgent)) {
            return [
                'device_type' => 'unknown',
                'browser' => null,
                'browser_version' => null,
                'os' => null,
                'os_version' => null,
            ];
        }

        $result = [
            'device_type' => 'desktop',
            'browser' => null,
            'browser_version' => null,
            'os' => null,
            'os_version' => null,
        ];

        // Detect device type
        if (preg_match('/Mobile|Android|iPhone|iPad|iPod|webOS|BlackBerry|Opera Mini|IEMobile/i', $userAgent)) {
            $result['device_type'] = 'mobile';
            if (preg_match('/iPad|Tablet/i', $userAgent)) {
                $result['device_type'] = 'tablet';
            }
        }

        // Detect browser
        if (preg_match('/Firefox\/([0-9.]+)/i', $userAgent, $matches)) {
            $result['browser'] = 'Firefox';
            $result['browser_version'] = $matches[1];
        } elseif (preg_match('/Edg\/([0-9.]+)/i', $userAgent, $matches)) {
            $result['browser'] = 'Edge';
            $result['browser_version'] = $matches[1];
        } elseif (preg_match('/Chrome\/([0-9.]+)/i', $userAgent, $matches)) {
            $result['browser'] = 'Chrome';
            $result['browser_version'] = $matches[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/i', $userAgent, $matches)) {
            if (preg_match('/Version\/([0-9.]+)/i', $userAgent, $versionMatches)) {
                $result['browser'] = 'Safari';
                $result['browser_version'] = $versionMatches[1];
            }
        } elseif (preg_match('/MSIE ([0-9.]+)/i', $userAgent, $matches) || preg_match('/Trident.*rv:([0-9.]+)/i', $userAgent, $matches)) {
            $result['browser'] = 'Internet Explorer';
            $result['browser_version'] = $matches[1];
        }

        // Detect OS
        if (preg_match('/Windows NT ([0-9.]+)/i', $userAgent, $matches)) {
            $result['os'] = 'Windows';
            $windowsVersions = [
                '10.0' => '10/11',
                '6.3' => '8.1',
                '6.2' => '8',
                '6.1' => '7',
                '6.0' => 'Vista',
                '5.1' => 'XP',
            ];
            $result['os_version'] = $windowsVersions[$matches[1]] ?? $matches[1];
        } elseif (preg_match('/Mac OS X ([0-9._]+)/i', $userAgent, $matches)) {
            $result['os'] = 'macOS';
            $result['os_version'] = str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/iPhone OS ([0-9_]+)/i', $userAgent, $matches)) {
            $result['os'] = 'iOS';
            $result['os_version'] = str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/iPad.*OS ([0-9_]+)/i', $userAgent, $matches)) {
            $result['os'] = 'iPadOS';
            $result['os_version'] = str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/Android ([0-9.]+)/i', $userAgent, $matches)) {
            $result['os'] = 'Android';
            $result['os_version'] = $matches[1];
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $result['os'] = 'Linux';
        }

        return $result;
    }
}
