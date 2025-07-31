# Payra PHP SDK (Backend Signature Generation)

Official PHP SDK for integrating **Payra's on-chain payment system** into your backend.  Provides a simple way to generate secure ECDSA signatures compatible with the Payra smart contract (e.g. for payment verification).

---

## Features

- Ethereum ECDSA signature generation using the `secp256k1` curve  
- Compatible with Payra's Solidity contracts (ERC-1155 payment verification)
- Built-in ABI encoding via `web3.php`
- Supports `.env` configuration for multiple blockchain networks

---

## SETUP

Before installing this package, make sure you have an active Payra account:

- [https://payra.cash](https://payra.cash)

You will need your merchantID and a dedicated account (private key) to generate valid payment signatures.

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
```

---

## Environment Setup

Create a `.env` file with the following variables:

```
PAYRA_POLYGON_PRIVATE_KEY=your_private_key_here
PAYRA_POLYGON_MERCHANT_ID=your_merchant_id_here

PAYRA_ETHEREUM_PRIVATE_KEY=
PAYRA_ETHEREUM_MERCHANT_ID=

PAYRA_LINEA_PRIVATE_KEY=
PAYRA_LINEA_MERCHANT_ID=
```

---

## Usage Example

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

## Security Notice

Never expose your private key in frontend code or client-side environments.  
This SDK is **server-side only** and must be used securely on your backend.

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

---

##  License

MIT © [Payra](https://github.com/payracash)
