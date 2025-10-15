
# Payra Cash PHP SDK

Official PHP SDK for integrating **Payra's on-chain payment system** into your backend applications.

This SDK provides:
- Secure generation of **ECDSA signatures** compatible with the Payra smart contract — used for order payment verification.
- Simple methods for **checking the on-chain status of orders** to confirm completed payments.

## How It Works

The typical flow for signing and verifying a Payra transaction:

1. The **frontend** prepares all required payment parameters:
   - **Network** – blockchain name (e.g. Polygon, Linea)
   - **Token address** – ERC-20 token contract address
   - **Order ID** – unique order identifier
   - **AmountWei** – already converted to the smallest unit (e.g. wei, 10⁶)
   - **Timestamp** – Unix timestamp of the order
   - **Payer wallet address**

2. The frontend sends these parameters to your **backend**.
3. The **backend** uses this SDK to generate a cryptographic **ECDSA signature** with its private key (performed **offline**).
4. The backend returns the generated signature to the frontend.
5. The **frontend** calls the Payra smart contract (`payOrder`) with all parameters **plus** the signature.

This process ensures full compatibility between your backend and Payra’s on-chain verification logic.


## Features

- Generates **Ethereum ECDSA signatures** using the `secp256k1` curve.
- Fully compatible with **Payra's Solidity smart contracts** (`ERC-1155` payment verification).  
- Includes built-in **ABI encoding and decoding** via `web3.php`.
- Supports (`.env`) configuration for multiple blockchain networks.  
- Verifies **order payment status directly on-chain** via RPC or blockchain explorer API.  
- Provides **secure backend integration** using merchant private keys.   
- Includes optional utility helpers for:
  - **Currency conversion** (via [ExchangeRate API](https://www.exchangerate-api.com/))
  - **USD ⇄ WEI** conversion for token precision handling.  

## Setup

Before installing this package, make sure you have an active **Payra** account:

- [https://payra.cash](https://payra.cash)

You will need:
- Your **Merchant ID** (unique for each blockchain network)
- Your **private key** (used to sign Payra transactions securely)

Additionally:
- Create a free account at [QuickNode](https://www.quicknode.com/) to obtain your **RPC URLs** — these are required for reading on-chain order status directly from the blockchain.

Optional (recommended):
- Create a free API key at [ExchangeRate API](https://www.exchangerate-api.com/)  
  if you want to enable **automatic fiat → USD** conversions using the built-in utilities.

## Installation

### Requirements
- PHP 8.1 or higher  
- Composer  
- cURL extension enabled  
- (`.env`) file for environment configuration  

#### Via Composer (recommended)

```bash
composer require payracash/payra-sdk-php
```

#### Or manual installation (for local testing)

```
git clone https://github.com/payracash/payra-sdk-php.git
cd payra-sdk-php
composer install
composer dump-autoload
```

Once installed, make sure to include Composer’s autoloader in your project:

```php
require __DIR__ . '/vendor/autoload.php';
```

## Environment Setup

Create a (`.env`) file in your project root and define the following variables:

```env
# Optional — only needed if you want to use the built-in currency conversion helper
EXCHANGE_RATE_API_KEY=

# Polygon Network Configuration
PAYRA_POLYGON_CORE_FORWARD_CONTRACT_ADDRESS=0xf30070da76B55E5cB5750517E4DECBD6Cc5ce5a8
PAYRA_POLYGON_PRIVATE_KEY=
PAYRA_POLYGON_MERCHANT_ID=
PAYRA_POLYGON_RPC_URL_1=
PAYRA_POLYGON_RPC_URL_2=

# Ethereum Network Configuration
PAYRA_ETHEREUM_CORE_FORWARD_CONTRACT_ADDRESS=
PAYRA_ETHEREUM_PRIVATE_KEY=
PAYRA_ETHEREUM_MERCHANT_ID=
PAYRA_ETHEREUM_RPC_URL_1=
PAYRA_ETHEREUM_RPC_URL_2=

# Linea Network Configuration
PAYRA_LINEA_CORE_FORWARD_CONTRACT_ADDRESS=
PAYRA_LINEA_PRIVATE_KEY=
PAYRA_LINEA_MERCHANT_ID=
PAYRA_LINEA_RPC_URL_1=
PAYRA_LINEA_RPC_URL_2=
```

### Notes

-   Each  **`PRIVATE_KEY`**  and  **`MERCHANT_ID`**  pair must belong to the same blockchain network.

- You can define multiple RPC URLs per network (`RPC_URL_1`, `RPC_URL_2`, `RPC_URL_3`, …).  
  **Note:** At least one RPC URL must be provided per network — otherwise, on-chain status checks will fail.  
  The SDK randomly selects one URL for better reliability and load distribution.

-   The  `EXCHANGE_RATE_API_KEY`  is optional — required only if you use the built-in currency conversion helper `convertToUSD()`

## Usage Example

### Generate Signature

```php
use App\Payra\PayraSignatureGenerator;
use App\Payra\PayraUtils;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Convert USD to Wei
$amountWei = PayraUtils::toWei(3.45, 'polygon', 'usdt'); // in smallest token unit (Wei for USDT/USDC)

$generator = new PayraSignatureGenerator();

$signature = $generator->generateSignature(
    $network,         // e.g. "polygon"
    $tokenAddress,    // ERC-20 USDT or USDC
    $orderId,         // string (unique per merchantId)
    $amountWei,          // in Wei $1 = 1_000_000
    (int) $timestamp,
    $payerAddress     // Public payer wallet address
);
```

Use `PayraUtils::toWei($usdAmount, $network, $tokenSymbol)` to easily convert USD to Wei before generating a signature.

---

### Check Order Status
```php
use App\Payra\PayraOrderVerification;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$orderVerification = new PayraOrderVerification();

// Call order verification (returns array)
$verify = $orderVerification->isOrderPaid(
    $network,   // e.g. "polygon"
    $orderId    // string (unique per merchantId)
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
    'success' => true,   // boolean: whether the RPC request succeeded
    'paid'    => true,   // boolean: whether the order is marked as paid on-chain
    'error'   => null,   // string|null: error message if the request failed, otherwise null
]
```
**Note:** Network identifiers should always be lowercase (e.g., `"polygon"`, `"ethereum"`, `"linea"`, `"flare"`).


## Utilities / Conversion Helpers

The SDK includes  **helper functions**  for working with token amounts and currency conversion.

### 1. Get Token Decimals

```php
use  App\Payra\PayraUtils;

$decimals = PayraUtils::getTokenDecimals('polygon', 'usdt');
echo  $decimals; // e.g., 6
```
Returns the number of decimal places for a given token on a specific network.

---

### 2. Convert USD/Token Amounts to Wei

```php
use App\Payra\PayraUtils;

$amountWei = PayraUtils::toWei(3.34, 'polygon', 'usdt');
echo $amountWei; // "3340000" amount in USD converted to Wei
```

---

### 3. Convert Wei to USD/Token

```php
use App\Payra\PayraUtils;

// Convert from smallest token unit (Wei) to readable amount in USD
$amount = PayraUtils::fromWei('3340000', 'polygon', 'usdt', 2);
echo $amount; // "3.34" amount in USD (formatted to 2 decimals)
```

The optional fourth parameter (`precision`) defines how many decimal places should be returned.  
Default is `2`, but you can adjust it — for example:

```php
PayraUtils::fromWei('3340000', 'polygon', 'usdt', 4); // "3.3400"
```

---

### 4. Currency Conversion (Optional)

Payra processes all payments in  **USD**.  If your store uses another currency (like EUR, AUD, or GBP), you can:

-   Convert the amount to USD on your backend manually,  **or**
-   Use the built-in helper provided in the SDK.

```php
use App\Payra\PayraUtils;

// Convert 120.43 EUR to USD
$amountUSD = PayraUtils::convertToUSD(120.43, 'EUR'); // converted amount in USD
```

#### Setup for Currency Conversion

To use the conversion helper, you need a free API key from  **[exchangerate-api.com](https://exchangerate-api.com/)**.

1.  Register a free account and get your API key.
2.  Add the key to your  (`.env`)  file:

```php
EXCHANGE_RATE_API_KEY=your_api_key_here
```

3.  That’s it — Payra will automatically fetch the exchange rate and calculate the USD amount.

**Note:** The free plan allows 1,500 requests per month, which is sufficient for most stores. Exchange rates on this plan are updated every 24 hours, so with caching, it’s more than enough. Paid plans offer faster update intervals.


## Security Notice

Never expose your private key in frontend or client-side code.  
This SDK is  **server-side only**  and must be used securely on your backend. Never use it in frontend or browser environments. Also, never commit your  (`.env`)  file to version control.

## Project

-   [https://payra.cash](https://payra.cash)
-   [https://payra.tech](https://payra.tech)
-   [https://payra.xyz](https://payra.xyz)
-   [https://payra.eth](https://payra.eth)

## Social Media

- [Telegram Payra Group](https://t.me/+GhTyJJrd4SMyMDA0)
- [Telegram Announcements](https://t.me/payracash)
- [Twix (X)](https://x.com/PayraCash)
- [Hashnode](https://payra.hashnode.dev)

##  License

MIT © [Payra](https://github.com/payracash)
