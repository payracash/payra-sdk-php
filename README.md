# Payra  PHP SDK

Official **PHP SDK** for integrating **Payra's on-chain payment system** into your backend applications.

This SDK provides:
- Secure generation of **ECDSA signatures** compatible with the Payra smart contract — used for order payment verification.
- Simple methods for **checking the on-chain status of orders** to confirm completed payments.

## How It Works

The typical flow for signing and verifying a Payra transaction:
1. The **frontend** prepares all required payment parameters:
	-  **Network** – blockchain name (e.g. Polygon, Linea)
	-  **Token address** – ERC-20 token contract address
	-  **Order ID** – unique order identifier
	-  **Amount WEI** – already converted to the smallest unit (e.g. wei, 10⁶)
	-  **Timestamp** – Unix timestamp of the order
	-  **Payer wallet address** – the wallet address from which the user will make the on-chain payment
3. The frontend sends these parameters to your **backend**.
4. The **backend** uses this SDK to generate a cryptographic **ECDSA signature** with its signature key (performed **offline**).
5. The backend returns the generated signature to the frontend.
6. The **frontend** calls the Payra smart contract (`payOrder`) with all parameters **plus** the signature.

This process ensures full compatibility between your backend and Payra’s on-chain verification logic.

## Features

- Generates **Ethereum ECDSA signatures** using the `secp256k1` curve.
- Fully compatible with **Payra's Solidity smart contracts** (`ERC-1155` payment verification).  
- Includes built-in **ABI encoding and decoding** via `web3.php`.
- Supports `.env` and  `config/payra.php` configuration for multiple blockchain networks.  
- Laravel IoC container integration (easy dependency injection)
- Verifies **order payment status directly on-chain** via RPC or blockchain explorer API.  
- Provides **secure backend integration** for signing and verifying transactions.
- Includes optional utility helpers for:
  - **Currency conversion** (via [ExchangeRate API](https://www.exchangerate-api.com/))
  - **USD ⇄ WEI** conversion for token precision handling.

## Setup

Before installing this package, make sure you have an active **Payra** account:

[https://payra.cash/products/on-chain-payments/registration](https://payra.cash/products/on-chain-payments/registration#registration-form)

Before installing this package, make sure you have a **MerchantID**

- Your **Merchant ID** (unique for each blockchain network)
- Your **Signature Key** (used to sign Payra transactions securely)

Additionally:
To obtain your **RPC URLs** which are required for reading on-chain order statuses directly from the blockchain, you can use the public free endpoints provided with this package or create an account on one of the following services for better performance and reliability:

-   **QuickNode** – Extremely fast and excellent for Polygon/Mainnet. ([quicknode.com](https://quicknode.com/))
    
-   **Alchemy** – Offers a great developer dashboard and high reliability. ([alchemy.com](https://alchemy.com/))
    
-   **DRPC** – Decentralized RPC with a generous free tier and a strict no-log policy. ([drpc.org](https://drpc.org))
    
-   **Infura** – The industry standard; very stable, especially for Ethereum. ([infura.io](https://infura.io))

Optional (recommended):
- Create a free API key at [ExchangeRate API](https://www.exchangerate-api.com/) to enable **automatic fiat → USD conversions** using the built-in utility helpers.

## Installation

### Requirements

- PHP 8.1 or higher  
- Composer  
- cURL extension enabled  
- `.env` file for environment configuration  

### Via Composer (recommended)

```bash
composer require payracash/payra-sdk-php
```

#### Or manual installation (for local testing)

```bash
git clone https://github.com/payracash/payra-sdk-php.git
cd payra-sdk-php
composer install
composer dump-autoload
```

Once installed, make sure to include Composer’s autoloader in your project:

```php
require __DIR__ . '/vendor/autoload.php';
```

## Environment Configuration

Create a `.env` file in your project root (you can copy from example):

```bash
cp  .env.example  .env
```

This file stores your **private configuration** and connection settings for all supported networks. Never commit `.env` to version control.

### Required Variables

#### Exchange Rate (optional)

Used for automatic fiat → USD conversions via the built-in Payra utilities.

```bash
# Optional — only needed if you want to use the built-in currency conversion helper
PAYRA_EXCHANGE_RATE_API_KEY=         # Your ExchangeRate API key (from exchangerate-api.com)
PAYRA_EXCHANGE_RATE_CACHE_TIME=720   # Cache duration in minutes (default: 720 = 12h)

# Polygon Network Configuration
PAYRA_POLYGON_OCP_GATEWAY_CONTRACT_ADDRESS=0xc56c55D9cF0FF05c85A2DF5BFB9a65b34804063b
PAYRA_POLYGON_SIGNATURE_KEY=
PAYRA_POLYGON_MERCHANT_ID=
PAYRA_POLYGON_RPC_URL_1=https://polygon-rpc.com
PAYRA_POLYGON_RPC_URL_2=

# Ethereum Network Configuration
PAYRA_ETHEREUM_OCP_GATEWAY_CONTRACT_ADDRESS=
PAYRA_ETHEREUM_SIGNATURE_KEY=
PAYRA_ETHEREUM_MERCHANT_ID=
PAYRA_ETHEREUM_RPC_URL_1=
PAYRA_ETHEREUM_RPC_URL_2=

# Linea Network Configuration
PAYRA_LINEA_OCP_GATEWAY_CONTRACT_ADDRESS=
PAYRA_LINEA_SIGNATURE_KEY=
PAYRA_LINEA_MERCHANT_ID=
PAYRA_LINEA_RPC_URL_1=
PAYRA_LINEA_RPC_URL_2=
```

#### Important Notes

- The cache automatically refreshes when it expires.
- You can adjust the cache duration by setting `PAYRA_EXCHANGE_RATE_CACHE_TIME`:
	-  `5` → cache for 5 minutes
	-  `60` → cache for 1 hour
	-  `720` → cache for 12 hours (default)
- Each network (Polygon, Ethereum, Linea) has its own **merchant ID**, **signature key**, and **RPC URLs**.
- The SDK automatically detects which chain configuration to use based on the selected network.
- You can use multiple RPC URLs for redundancy (the SDK will automatically fall back if one fails).
- Contract addresses correspond to the deployed Payra Core Forward contracts per network.

## Usage Example

### Generate Signature

```php
use App\Payra\PayraSignature;
use App\Payra\PayraUtils;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Convert USD to Wei
$amountWei = PayraUtils::toWei(3.45, 'polygon', 'usdt'); // in smallest token unit (Wei for USDT/USDC)

$payraSignature = new PayraSignature();

$signature = $payraSignature->generate(
    $network,         	// e.g. "polygon"
    $tokenAddress,    	// ERC-20 USDT or USDC
    $orderId,         	// string (unique per merchantId)
    $amountWei,       	// in Wei $1 = 1_000_000
    (int) $timestamp,
    $payerAddress     	// Public payer wallet address
);
```

Use `PayraUtils::toWei($usdAmount, $network, $tokenSymbol)` to easily convert USD to Wei before generating a signature.

#### Input Parameters

| Field         | Type     | Description                                  |
|--------------|----------|----------------------------------------------|
| **`network`**    | `string` | Selected network name                        |
| **`tokenAddress`** | `string` | ERC20 token contract address                 |
| **`orderId`**     | `string` | Unique order reference (e.g. ORDER-123)      |
| **`amountWei`**      | `string` or `integer` | Token amount in smallest unit (e.g. wei)     |
| **`timestamp`**   | `number` | Unix timestamp of signature creation         |
| **`payerAddress`**   | `string` | Payer Wallet Address     

---

### Get Order Details

Retrieve **full payment details** for a specific order from the Payra smart contract. This method returns the complete on-chain payment data associated with the order, including:
-   whether the order has been paid,
-   the payment token address,
-   the paid amount,
-   the fee amount,
-   and the payment timestamp.

Use this method when you need  **detailed information**  about the payment or want to display full transaction data.

```php
use App\Payra\PayraOrderService;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$orderService = new PayraOrderService();

// Call order verification (returns array)
$orderDetails = $orderService->getDetails(
    $network,   // e.g. "polygon"
    $orderId    // string (unique per merchantId)
);

if ($orderDetails['paid']) {
    echo "Order is paid";
} else {
    echo "Order not yet paid.";
}
```

### Example response structure

```php
[
    "success"   => true,   // boolean: whether the RPC request succeeded
    "error"     => null,   // string|null: error message if the request failed
    "paid"      => true,   // boolean: whether the order is marked as paid on-chain
    "token"     => '0xc2132d05d31c914a87c6611c10748aeb04b58e8f', // payment token (USDT, USDC, etc.)
    "amount"    => 400000, // amount in wei
    "fee"       => 3600,   // fee in wei
    "timestamp" => 1765138941 // UNIX timestamp
]
```

---

### Check Order Paid Status

Perform a  **simple payment check**  for a specific order. This method only verifies whether the order has been paid (`true`  or  `false`) and does  **not**  return any additional payment details.

Use this method when you only need a  **quick boolean confirmation**  of the payment status.

```php
use App\Payra\PayraOrderService;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$orderService = new PayraOrderService();

// Call order verification (returns array)
$isPaid = $orderService->isPaid(
    $network,   // e.g. "polygon"
    $orderId    // string (unique per merchantId)
);

if ($isPaid['paid']) {
    echo "Order is paid";
} else {
    echo "Order not yet paid.";
}
```

### Example response structure

```php
[
    "success" => true,   // boolean: whether the RPC request succeeded
    "paid"    => true,   // boolean: whether the order is marked as paid on-chain
    "error"   => null,   // string|null: error message if the request failed, otherwise null
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
2.  Add the key to your  `.env`  file:

```php
PAYRA_EXCHANGE_RATE_API_KEY=your_api_key_here
```

4.  That’s it — Payra will automatically fetch the exchange rate and calculate the USD amount.

**Note:** The free plan allows 1,500 requests per month, which is sufficient for most stores. Exchange rates on this plan are updated every 24 hours, so with caching, it’s more than enough. Paid plans offer faster update intervals.

## Security Notice

Never expose your signature key in frontend or client-side code.  
This SDK is  **server-side only**  and must be used securely on your backend. Never use it in frontend or browser environments. Also, never commit your `.env`  file to version control.

## Project

- [https://payra.cash](https://payra.cash)
- [https://payra.tech](https://payra.tech)
- [https://payra.xyz](https://payra.xyz)
- [https://payra.eth](https://payra.eth.limo) - suporrted by Brave and Opera Browser or .limo

## Social Media

- [Telegram Payra Group](https://t.me/+GhTyJJrd4SMyMDA0)
- [Telegram Announcements](https://t.me/payracash)
- [Twix (X)](https://x.com/PayraCash)
- [Dev.to](https://dev.to/payracash)

## License

MIT © [Payra](https://payra.cash)