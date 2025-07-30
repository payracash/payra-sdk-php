# Payra PHP SDK

Official PHP SDK for integrating **Payra's on-chain payment system** into your backend.  
Provides a simple way to generate secure ECDSA signatures compatible with the Payra smart contract (e.g. for payment verification).

---

## 🚀 Features

- Ethereum ECDSA signature generation using the `secp256k1` curve  
- Compatible with Payra's Solidity contracts (ERC-1155 payment verification)
- Built-in ABI encoding via `web3.php`
- Supports `.env` configuration for multiple blockchain networks

---

## 📦 Installation

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

## ⚙️ Environment Setup

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

## 🧪 Usage Example

```php
use App\Payra\PayraSignatureGenerator;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$network = 'polygon';
$merchantPrivateKey = $_ENV['PAYRA_' . strtoupper($network) . '_PRIVATE_KEY'];
$merchantId = $_ENV['PAYRA_' . strtoupper($network) . '_MERCHANT_ID'];

$generator = new PayraSignatureGenerator($merchantPrivateKey);

$signature = $generator->generateSignature(
    $tokenAddress,
    $merchantId,
    $orderId,
    $amount,
    (int) $timestamp,
    $payerAddress
);
```

---

## 🛡 Security Notice

Never expose your private key in frontend code or client-side environments.  
This SDK is **server-side only** and must be used securely on your backend.

---

## 📄 License

MIT © [Payra](https://github.com/payracash)
