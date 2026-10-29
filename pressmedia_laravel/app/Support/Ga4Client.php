<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Google Analytics Data API client (runReport) with a service account (Settings > Analytics: property id
 * and the service account JSON key, stored encrypted). Off unless the owner enables GA4 (Q7 decision).
 */
class Ga4Client
{
    public static function configured(): bool
    {
        return Settings::get('ga4Enabled') && Settings::get('ga4PropertyId') && Settings::get('ga4Credentials');
    }

    /** @return list<array<string, string>> rows keyed by dimension/metric names */
    public static function report(array $dimensions, array $metrics, string $from, string $to, int $limit = 20): array
    {
        $body = ['dateRanges' => [['startDate' => $from, 'endDate' => $to]], 'limit' => $limit,
            'dimensions' => array_map(fn ($d) => ['name' => $d], $dimensions), 'metrics' => array_map(fn ($m) => ['name' => $m], $metrics)];
        if ($metrics) {
            $body['orderBys'] = [$dimensions === ['date'] ? ['dimension' => ['dimensionName' => 'date']] : ['metric' => ['metricName' => $metrics[0]], 'desc' => true]];
        }
        $res = Http::withToken(self::token())->post('https://analyticsdata.googleapis.com/v1beta/properties/'.Settings::get('ga4PropertyId').':runReport', $body);
        if (! $res->ok()) {
            throw new RuntimeException('GA4 report failed: '.$res->status());
        }
        $names = array_merge($dimensions, $metrics);

        return array_map(fn ($row) => array_combine($names, array_merge(
            array_column($row['dimensionValues'] ?? [], 'value'), array_column($row['metricValues'] ?? [], 'value'))), $res->json('rows') ?? []);
    }

    /** OAuth access token from a service-account JWT (RS256), cached for its lifetime. */
    private static function token(): string
    {
        return Cache::remember('ga4-token', 3300, function () {
            $key = json_decode((string) Settings::get('ga4Credentials'), true);
            if (! isset($key['client_email'], $key['private_key'])) {
                throw new RuntimeException('GA4 credentials are not a service account JSON key.');
            }
            $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $now = time();
            $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode([
                'iss' => $key['client_email'], 'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
                'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
            ]));
            openssl_sign($unsigned, $sig, $key['private_key'], OPENSSL_ALGO_SHA256);
            $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned.'.'.$b64($sig),
            ]);
            if (! $res->ok()) {
                throw new RuntimeException('GA4 authentication failed: '.$res->status());
            }

            return $res->json('access_token');
        });
    }
}
