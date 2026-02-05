<?php
namespace App\Payra;

use Web3\Web3;
use Web3\Utils;
use Web3\Contract;
use Web3\Contracts\Ethabi;
use Web3\Providers\HttpProvider;

class PayraOrderService
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
     *   @type bool        $success
     *   @type string|null $error
     *   @type bool|null   $paid
     *   @type string|null $token
     *   @type int|null    $amount
     *   @type int|null    $fee
     *   @type int|null    $timestamp
     * }
     */
    public function getDetails(string $network, string $orderId): array
    {
        try {
            $setup = $this->getPayraContracts($network);
            $contract = $setup['userDataContract'];
            
            $orderData = null;
            $done = false;

            $contract->at($setup['userDataAddr'])->call('getOrderDetails', $setup['merchantId'], $orderId, function ($err, $result) use (&$orderData, &$done) {
                if ($err) throw $err;
                
                $orderData = [
                    'paid'      => $result['paid'] ?? $result[0],
                    'token'     => $result['token'] ?? $result[1],
                    'amount'    => $result['amount'] ?? $result[2],
                    'fee'       => $result['fee'] ?? $result[3],
                    'timestamp' => $result['timestamp'] ?? $result[4],
                ];

                $done = true;
            });

            $this->waitForCallback($done);

            return [
                'success'   => true,
                'error'     => null,
                'paid'      => (bool)$orderData['paid'],
                'token'     => $orderData['token'],
                'amount'    => $orderData['amount']->toString(),
                'fee'       => $orderData['fee']->toString(),
                'timestamp' => (int)$orderData['timestamp']->toString(),
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
                'paid' => null, 'token' => null, 'amount' => null, 'fee' => null, 'timestamp' => null,
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
    public function isPaid(string $network, string $orderId): array
    {
        try {
            $setup = $this->getPayraContracts($network);
            $contract = $setup['userDataContract'];
            
            $isPaid = false;
            $done = false;

            $contract->at($setup['userDataAddr'])->call('isOrderPaid', $setup['merchantId'], $orderId, function ($err, $result) use (&$isPaid, &$done) {
                if ($err) throw $err;
                $isPaid = (bool)$result[0];
                $done = true;
            });

            $this->waitForCallback($done);

            return [
                'success' => true,
                'error'   => null,
                'paid'    => $isPaid,
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
                'paid'    => null,
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
     * Internal helper to initialize Payra contracts and provider.
     */
    private function getPayraContracts(string $network): array 
    {  
        $network = strtoupper($network);
        $rpcUrl = $this->getRpcUrl($network);

        $merchantId = $_ENV["PAYRA_{$network}_MERCHANT_ID"] ?? null;
        $gatewayAddr = $_ENV["PAYRA_{$network}_OCP_GATEWAY_CONTRACT_ADDRESS"] ?? null;
    
        if (!$merchantId || !$gatewayAddr) {
            throw new \RuntimeException("Missing merchant ID or forward contract address");
        }

        $web3 = new Web3(new HttpProvider($rpcUrl, 5));
        $ethabi = new Ethabi();
        $payraABI = json_decode(file_get_contents(dirname(__DIR__) . '/contracts/payraABI.json'), true);
 
        $gatewayContract = new Contract($web3->provider, $payraABI);
     
        $userDataAddr = null;
        $done = false;
        
        $gatewayContract->at($gatewayAddr)->call('getRegistryDetails', function ($err, $result) use (&$userDataAddr, &$done) {
            if (!$err) {
                $userDataAddr = $result['userData'] ?? $result[2] ?? null;
            }
            $done = true;
        });

        $this->waitForCallback($done);

        if (!$userDataAddr) {
            throw new \RuntimeException("Could not retrieve userDataAddress from Gateway");
        }

        return [
            'userDataContract' => new Contract($web3->provider, $payraABI),
            'userDataAddr'     => $userDataAddr,
            'merchantId'       => $merchantId,
            'ethabi'           => $ethabi
        ];
    }

    /**
     * Helper for await in PHP
     */
    private function waitForCallback(&$done, $timeout = 5) {
        $start = microtime(true);
        while (!$done && (microtime(true) - $start) < $timeout) {
            usleep(10000);
        }
        if (!$done) throw new \RuntimeException("RPC Timeout");
    }

}
