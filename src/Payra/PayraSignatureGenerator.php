<?php
namespace App\Payra;

use Web3\Contracts\Ethabi;
use Elliptic\EC;
use Web3\Utils;

class PayraSignatureGenerator
{
    private Ec $ec;
    private Ethabi $ethabi;
    private Utils $utils;

    /**
     * Constructor for PayraSignatureGenerator.
     *
     * @param string $merchantPrivateKey Your Ethereum private key (64 hex characters, without '0x').
     * @throws \Exception If the private key is invalid.
     */
    public function __construct()
    {
        $this->ec = new EC('secp256k1');
        $this->ethabi = new Ethabi;
        $this->utils = new Utils;
    }

    /**
     * Generates a signature for a Payra PayOrder transaction.
     * IMPORTANT: The order and types of parameters MUST match EXACTLY as expected by the Payra smart contract.
     * @param string $network name (e.g., 'polygon').
     * @param string $tokenAddress Address of the ERC-20 token (e.g., '0xc2132D05D31c914a87C6611C10748AEb04B58eF').
     * @param string $orderId Unique identifier of the order (e.g., 'order_19_984723').
     * @param string $amountWei Amount in the smallest unit of the token (e.g., '13360000' for 1.336 USDT).
     * @param int $timestamp Transaction time in Unix timestamp format (e.g., 1728392929).
     * @param string $payerAddress Payer wallet Address (e.g., '0xc87a3D05D31c914a87C6611C10748AEb0a5e$2').
     * @return string Signature in the format '0x<r><s><v>' (65-byte hex with '0x' prefix).
     * @throws \Exception If an error occurs during signature generation.
     */
    public function generateSignature(
        string $network,
        string $tokenAddress,
        string $orderId,
        string $amountWei,
        int $timestamp,
        string $payerAddress,
    ): string {
        $network = strtoupper($network);

        $merchantPrivateKey = $_ENV["PAYRA_{$network}_PRIVATE_KEY"] ?? null;
        $merchantId = $_ENV["PAYRA_{$network}_MERCHANT_ID"] ?? null;

        if (!$merchantPrivateKey || !$merchantId) {
            throw new \Exception("Missing merchant credentials for network: $network");
        }

        try {

            $types = ['address', 'uint256', 'string', 'uint256', 'uint256', 'address'];
            $values = [$tokenAddress, $merchantId, $orderId, $amountWei, $timestamp, $payerAddress];

            $encoded = $this->ethabi->encodeParameters($types, $values);
            $messageHash = ltrim($this->utils::sha3($encoded), '0x');
            $prefixedMessage = "\x19Ethereum Signed Message:\n32" . hex2bin($messageHash);
            $finalHash = $this->utils::sha3($prefixedMessage);

            $key = $this->ec->keyFromPrivate($merchantPrivateKey, 'hex');
            $signature = $key->sign($finalHash, ['canonical' => true]);

            $r = str_pad($signature->r->toString(16), 64, '0', STR_PAD_LEFT);
            $s = str_pad($signature->s->toString(16), 64, '0', STR_PAD_LEFT);
            $v = dechex($signature->recoveryParam + 27);

            return '0x' . $r . $s . $v;

        } catch (\Exception $e) {
            error_log('Error generating Payra signature: ' . $e->getMessage());
            throw new \Exception('Failed to generate signature: ' . $e->getMessage(), 0, $e);
        }
    }
}
