
# Payra Cash PHP SDK (Backend Signature and Check Order Status)

Official PHP SDK for integrating **Payra's on-chain payment system** into your backend.  

This SDK provides:  
- Secure generation of **ECDSA signatures** compatible with the Payra smart contract (used for payment verification).  
- Easy integration for **checking the on-chain status of orders** to confirm whether payments have been completed.  

---

## Features

- Ethereum ECDSA signature generation using the `secp256k1` curve  
- Compatible with Payra's Solidity contracts (ERC-1155 payment verification)  
- Built-in ABI encoding via `web3.php`  
- Supports `.env` configuration for multiple blockchain networks  
- Order status verification directly against the blockchain  
- Secure backend integration with merchant private keys  

---

## Setup

Before installing this package, make sure you have an active Payra account:

- [https://payra.cash](https://payra.cash)

You will need your `merchantID` and a dedicated account (private key) to generate valid payment signatures.

Additionally, you must create a free account at [QuickNode](https://www.quicknode.com/) to obtain an API key.  
This key is required for sending RPC requests to the blockchain in order to verify the on-chain status of orders.

---

## Installation

### Via Composer (recommended)

```
composer require payracash/payra-sdk-php
```

### Or manual installation (for local testing)

```
git clone https://github.com/payracash/payra-sdk-php.git
cd payra-sdk-php
composer install
composer dump-autoload
```

---

## Environment Setup

Create a `.env` file with the following variables:

```
QUICK_NODE_RPC_API_KEY=your_quick_node_api_key

PAYRA_POLYGON_CORE_FORWARD_CONTRACT_ADDRESS=0xf30070da76B55E5cB5750517E4DECBD6Cc5ce5a8
PAYRA_POLYGON_PRIVATE_KEY=your_private_key_here
PAYRA_POLYGON_MERCHANT_ID=your_merchant_id_here

PAYRA_ETHEREUM_CORE_FORWARD_CONTRACT_ADDRESS=
PAYRA_ETHEREUM_PRIVATE_KEY=
PAYRA_ETHEREUM_MERCHANT_ID=

PAYRA_LINEA_CORE_FORWARD_CONTRACT_ADDRESS=
PAYRA_LINEA_PRIVATE_KEY=
PAYRA_LINEA_MERCHANT_ID=
```

---

## Usage Example

### Generate Signature

```php
use App\Payra\PayraSignatureGenerator;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$generator = new PayraSignatureGenerator();

$signature = $generator->generateSignature(
    $network,         // e.g. "polygon"
    $tokenAddress,    // ERC-20 USDT or USDC
    $orderId,         // string (unique per merchantId)
    $amount,          // in Wei $1 = 1_000_000
    (int) $timestamp,
    $payerAddress     // Public payer wallet address
);
```

---

### Check Order Status
```php
use App\Payra\PayraOrderVerification;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$orderVerification = new App\Payra\PayraOrderVerification();

// Call order verification (returns array)
$verify = $orderVerification->isOrderPaid(
    $network,   // e.g. "polygon"
    $orderId.   // string (unique per merchantId)
);

if ($verify['paid']) {
    echo "Order is paid";
} else {
    echo "Order not yet paid.";
}
```

### Example response structure

```php
[
    'success' => true,   // boolean: indicates whether the RPC request was successful  
    'paid'    => true,   // boolean: indicates whether the order is paid  
    'error'   => null,   // string|null: error message if something went wrong, otherwise null  
]
```
Note: network identifiers should be lowercase (e.g., `"polygon"`, `"ethereum"`, `"linea"`, `"flare"`).

---

## Security Notice

Never expose your private key in frontend code or client-side environments.  
This SDK is **server-side only** and must be used securely on your backend.

Never commit your `.env` file to version control.

---

## Project

-   [https://payra.cash](https://payra.cash)
-   [https://payra.tech](https://payra.tech)
-   [https://payra.xyz](https://payra.xyz)
-   [https://payra.eth](https://payra.eth)

---

## Social Media

- [Telegram Payra Group](https://t.me/+GhTyJJrd4SMyMDA0)
- [Telegram Announcements](https://t.me/payracash)
- [Twix (X)](https://x.com/PayraCash)
- [Hashnode](https://payra.hashnode.dev)

---

##  License

MIT © [Payra](https://github.com/payracash)
