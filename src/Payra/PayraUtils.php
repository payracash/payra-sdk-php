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
        $apiKey = $_ENV['EXCHANGE_RATE_API_KEY'] ?? null;

        if (!$apiKey) {
            throw new \Exception('EXCHANGE_RATE_API_KEY is not set. Please paste the full API URL');
        }

        $apiUrl = "https://v6.exchangerate-api.com/v6/{$apiKey}/latest/USD";

        $fromCurrency = strtoupper($fromCurrency);

        $response = @file_get_contents($apiUrl);
        if ($response === false) {
            throw new \Exception("Failed to connect to ExchangeRate API.");
        }

        $data = json_decode($response, true);

        if (empty($data['conversion_rates'][$fromCurrency])) {
            throw new \Exception("Conversion rate for {$fromCurrency} not found in API response.");
        }

        $rate = $data['conversion_rates'][$fromCurrency];
        $usdValue = round($amount / $rate, 2);

        return $usdValue;
    }
}
