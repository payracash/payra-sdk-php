<?php
namespace App\Payra;

class PayraUtils
{
    private static array $defaultDecimals =
    [
        'POLYGON_USDT'  => 6,
        'POLYGON_USDC'  => 6,
    ];

    public static function getTokenDecimals(string $network, string $symbol): int
    {
        $key = strtoupper("{$network}_{$symbol}");
        $envKey = "PAYRA_{$key}_DECIMALS";
        $envValue = $_ENV[$envKey] ?? null;

        if ($envValue !== null) {
            return (int)$envValue;
        }

        return self::$defaultDecimals[$key] ?? 18;
    }

    public static function toWei(string|float $amount, string $network, string $symbol): string
    {
        $decimals = self::getTokenDecimals($network, $symbol);
        $multiplier = bcpow('10', (string)$decimals);
        return bcmul((string)$amount, $multiplier, 0);
    }

    public static function fromWei(string $amountWei, string $network, string $symbol, int $precision = 2): string
    {
        $decimals = self::getTokenDecimals($network, $symbol);
        $divisor = bcpow('10', (string)$decimals);
        $value = bcdiv($amountWei, $divisor, $decimals);
        return number_format((float)$value, $precision, '.', '');
    }

    public static function convertToUSD(float $amount, string $fromCurrency): float
    {
        $apiKey = $_ENV['PAYRA_EXCHANGE_RATE_API_KEY'] ?? null;
        if (!$apiKey) {
            throw new \Exception('PAYRA_EXCHANGE_RATE_API_KEY is not set in environment.');
        }

        // Cache TTL (in minutes, default: 720)
        $cacheMinutes = (int)($_ENV['PAYRA_EXCHANGE_RATE_CACHE_TIME'] ?? 720);
        $cacheTTL = $cacheMinutes * 60; // seconds

        // Path to cache file (e.g. /tmp/payra_exchange_rate.json)
        $cacheFile = sys_get_temp_dir() . '/payra_cash_exchange_rate_cache.json';

        $apiUrl = "https://v6.exchangerate-api.com/v6/{$apiKey}/latest/USD";
        $fromCurrency = strtoupper($fromCurrency);
        $currentTime = time();

        // Try to read cache
        if (file_exists($cacheFile)) {
            $cache = json_decode(@file_get_contents($cacheFile), true);

            if (
                isset($cache['timestamp'], $cache['data']) &&
                ($currentTime - $cache['timestamp']) < $cacheTTL
            ) {
                $data = $cache['data'];
            } else {
                $data = self::refreshExchangeRateCache($apiUrl, $cacheFile);
            }
        } else {
            $data = self::refreshExchangeRateCache($apiUrl, $cacheFile);
        }

        // Validate conversion rate
        if (empty($data['conversion_rates'][$fromCurrency])) {
            throw new \Exception("Conversion rate for {$fromCurrency} not found in API response.");
        }

        // 3. Convert to USD
        $rate = $data['conversion_rates'][$fromCurrency];
        return round($amount / $rate, 2);
    }

    private static function refreshExchangeRateCache(string $apiUrl, string $cacheFile): array
    {
        $response = @file_get_contents($apiUrl);
        if ($response === false) {
            // If the API request fails, try to use the last cached data (if available)
            if (file_exists($cacheFile)) {
                $cache = json_decode(@file_get_contents($cacheFile), true);
                if (!empty($cache['data'])) {
                    return $cache['data'];
                }
            }
            throw new \Exception("Failed to connect to ExchangeRate API.");
        }

        $data = json_decode($response, true);
        if (empty($data['conversion_rates'])) {
            throw new \Exception("Invalid data from ExchangeRate API.");
        }

        // Save cache to file
        @file_put_contents($cacheFile, json_encode([
            'timestamp' => time(),
            'data' => $data,
        ], JSON_PRETTY_PRINT));

        return $data;
    }
}
