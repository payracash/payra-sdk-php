<?php
namespace App\Payra;

use Web3\Web3;
use Web3\Utils;
use Web3\Contract;
use Web3\Contracts\Ethabi;
use Web3\Providers\HttpProvider;

class PayraOrderVerification
{
    /**
     * Get detailed status of an order from Payra smart contract.
     *
     * This method queries the Payra Core contract (via Forward contract)
     * and retrieves full payment information for a given order.
     *
     * Returned data includes:
     *  - whether the order is paid
     *  - payment token address
     *  - paid amount
     *  - fee amount
     *  - payment timestamp
     *
     * @param string $network Network name (e.g. "linea", "ethereum", "polygon")
     * @param string $orderId Unique order identifier (e.g. "shop-1-0937266")
     *
     * @return array {
     *   @type bool|null   $paid
     *   @type string|null $token
     *   @type int|null    $amount
     *   @type int|null    $fee
     *   @type int|null    $timestamp
     *   @type bool        $success
     *   @type string|null $error
     * }
     */
    public function getOrderStatus(string $network, string $orderId): array
    {
        [
            'instance' => $instance,
            'ethabi'   => $ethabi,
            'coreFn'   => $coreFn,
            'data'     => $data,
        ] = $this->prepareForwardCall(
            $network,
            'getOrderStatus',
            [
                $_ENV["PAYRA_" . strtoupper($network) . "_MERCHANT_ID"],
                $orderId
            ]
        );

        try {
             $resultValue = null;
             $done = false;

             $instance->call('forward', $data, function ($err, $result) use ($ethabi, &$resultValue, &$done) {
                 if ($err) {
                     throw new \RuntimeException("RPC call failed: " . $err->getMessage());
                 }

                 // ABI output: tuple(bool,address,uint256,uint256,uint256)
                 $outputTypes = [
                     'bool',
                     'address',
                     'uint256',
                     'uint256',
                     'uint256'
                 ];

                 $decoded = $ethabi->decodeParameters($outputTypes, $result[0]);

                 $resultValue = [
                     'paid'      => (bool)$decoded[0],
                     'token'     => $decoded[1],
                     'amount'    => (int)$decoded[2]->toString(),
                     'fee'       => (int)$decoded[3]->toString(),
                     'timestamp' => (int)$decoded[4]->toString(),
                 ];

                 $done = true;
             });

             // Timeout protection
             $start = microtime(true);
             $timeout = 5;

             while (!$done && (microtime(true) - $start) < $timeout) {
                 usleep(1000);
             }

             if (!$done) {
                 return [
                     'success' => false,
                     'paid'    => null,
                     'error'   => 'Timeout waiting for forward() response',
                     'token'   => null,
                     'amount'  => null,
                     'fee'     => null,
                     'timestamp' => null,
                 ];
             }

             return array_merge(
                 ['success' => true, 'error' => null],
                 $resultValue
             );

         } catch (\Throwable $e) {
             error_log("getOrderStatus failed: " . $e->getMessage());

             return [
                 'success'   => false,
                 'paid'      => null,
                 'error'     => $e->getMessage(),
                 'token'     => null,
                 'amount'    => null,
                 'fee'       => null,
                 'timestamp' => null,
             ];
         }
     }

    /**
     * Verify the authenticity of an order signature.
     *
     * This method validates that the provided order data (identified by $orderId)
     * has been correctly signed by the merchant's private key, and that the
     * resulting signature matches the expected Payra smart contract verification logic.
     *
     * @param string $orderId Unique order identifier (e.g., "shop-1-0937266").
     * @return bool Returns TRUE if the signature is valid and matches the provided order data,
     *              FALSE if the signature is invalid or does not match.
     * @throws \Exception If verification cannot be performed due to invalid inputs, missing signature fields, or cryptographic errors.
     */
    public function isOrderPaid(string $network, string $orderId): array
    {
        [
            'instance' => $instance,
            'ethabi'   => $ethabi,
            'coreFn'   => $coreFn,
            'data'     => $data,
        ] = $this->prepareForwardCall(
            $network,
            'isOrderPaid',
            [
                $_ENV["PAYRA_" . strtoupper($network) . "_MERCHANT_ID"],
                $orderId
            ]
        );

        // Call forward()
        try {
            $resultValue = null;
            $done = false;

            $instance->call('forward', $data, function ($err, $result) use ($ethabi, $coreFn, &$resultValue, &$done) {
                if ($err) {
                    throw new \RuntimeException("RPC call failed: " . $err->getMessage());
                }

                try {
                    $decoded = $ethabi->decodeParameters(array_column($coreFn['outputs'], 'type'), $result[0]);
                    $resultValue = (bool)$decoded[0];
                } catch (\Throwable $e) {
                    throw new \RuntimeException("Decoding failed: " . $e->getMessage(), 0, $e);
                }

                $done = true;
            });

            $start = microtime(true);
            $timeout = 5;

            while (!$done && (microtime(true) - $start) < $timeout) {
                usleep(1000);
            }

            if (!$done) {
                return [
                    'success' => false,
                    'paid'    => null,
                    'error'   => 'Timeout waiting for forward() response',
                ];
            }

            return [
                'success' => true,
                'paid'    => $resultValue,
                'error'   => null,
            ];

        } catch (\Throwable $e) {
            error_log("Forward() failed: " . $e->getMessage());
            return [
                'success' => false,
                'paid'    => null,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate the RPC URL for a given blockchain network.
     *
     * This method retrieves the base RPC URL template for the specified network
     * and replaces the placeholder `{API_KEY}` with the key from
     * the environment configuration (`QUICK_NODE_RPC_API_KEY`).
     *
     * @param string $network Target blockchain network identifier (e.g., "POLYGON", "ETHEREUM").
     * @return string Fully qualified RPC URL with API key included.
     * @throws \Exception If the API key is missing from the environment or
     *                    if the provided network is not supported.
     */
    private function getRpcUrl(string $network): string
    {
        $network = strtoupper($network);

        $urls = [];
        $i = 1;

        while (true) {
            $envKey = "PAYRA_{$network}_RPC_URL_{$i}";
            $value = $_ENV[$envKey] ?? getenv($envKey);
            if (!$value) break;
            $urls[] = trim($value);
            $i++;
        }

        if (empty($urls)) {
            throw new \Exception("No RPC URLs found for network: {$network}");
        }

        return $urls[array_rand($urls)];
    }

    /**
     * Find function in ABI by name
     */
    private function findFunction(array $abi, string $name): array
    {
        foreach ($abi as $entry) {
            if (($entry['type'] ?? null) === 'function' && ($entry['name'] ?? null) === $name) {
                return $entry;
            }
        }
        throw new \Exception("Function {$name} not found in ABI!");
    }

    /**
     * Prepare Forward Call
     */
    private function prepareForwardCall(
        string $network,
        string $coreFunctionName,
        array $params
    ): array {
        $network = strtoupper($network);
        $rpcUrl = $this->getRpcUrl($network);

        $merchantId = $_ENV["PAYRA_{$network}_MERCHANT_ID"] ?? null;
        $forwardAddress = $_ENV["PAYRA_{$network}_CORE_FORWARD_CONTRACT_ADDRESS"] ?? null;

        if (!$merchantId || !$forwardAddress) {
            throw new \RuntimeException("Missing merchant ID or forward contract address");
        }

        $provider = new HttpProvider($rpcUrl, 5);
        $web3 = new Web3($provider);
        $ethabi = new Ethabi;

        // Load ABI
        $abiArray = json_decode(
            file_get_contents(dirname(__DIR__) . '/contracts/payraABI.json'),
            true
        );

        // Find functions
        $coreFn = $this->findFunction($abiArray, $coreFunctionName);
        $forwardFn = $this->findFunction($abiArray, 'forward');

        // Build selector
        $signature = $coreFn['name'] . '(' . implode(',', array_column($coreFn['inputs'], 'type')) . ')';
        $selector = substr(Utils::sha3($signature), 0, 10);

        // Encode params
        $encodedParams = $ethabi->encodeParameters(
            array_column($coreFn['inputs'], 'type'),
            $params
        );

        // Calldata
        $data = $selector . substr($encodedParams, 2);

        // Forward contract
        $forwarder = new Contract($web3->provider, [$forwardFn]);
        $instance = $forwarder->at($forwardAddress);

        return [
            'instance' => $instance,
            'ethabi'   => $ethabi,
            'coreFn'   => $coreFn,
            'data'     => $data,
        ];
    }

}
