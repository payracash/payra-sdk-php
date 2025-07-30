<?php

// Requires Composer's autoloader to initialize all classes
require dirname(__DIR__) . '/vendor/autoload.php';

// Load environment variables from the .env file
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

header('Content-Type: application/json');
/* The headers below are only required when the backend and frontend are hosted on separate domains. */
//header('Access-Control-Allow-Origin: https://your-front.com');
//header('Access-Control-Allow-Methods: POST, OPTIONS');
//header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request (CORS preflight request)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Only POST requests are allowed.']);
    exit;
}

// Get JSON input data from the request
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input.']);
    exit;
}

// Validate params
$requiredParams = ['tokenAddress', 'orderId', 'amount', 'timestamp'];
foreach ($requiredParams as $param) {
    if (!isset($data[$param])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Missing required parameter: '{$param}'."]);
        exit;
    }
}

$merchantPrivateKey = $_ENV['PAYRA_' . strtoupper($data['network']) . '_PRIVATE_KEY'] ?? null;
$merchantId = $_ENV['PAYRA_' . strtoupper($data['network']) . '_MERCHANT_ID'] ?? null;

if (!$merchantPrivateKey || !$merchantId) {
    throw new \Exception("Missing PAYRA config for network: $network");
}

try {
    // Instance "SDK"
    $signatureGenerator = new App\Payra\PayraSignatureGenerator($merchantPrivateKey);

    // Call generate Signature
    $signature = $signatureGenerator->generateSignature(
        $data['tokenAddress'],
        $merchantId,
        $data['orderId'],
        $data['amount'],
        (int) $data['timestamp'], // cast timestamp to int,
        $data['payerAddress']
    );

    // Return sign to frontend
    echo json_encode([
        'status' => 'success',
        'signature' => $signature,
        'message' => 'Signature generated successfully.'
    ]);

} catch (\Exception $e) {
    // Error ABI encodingu
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

?>
