<?php
/**
 * DatecsBridge - HTTP to TCP Bridge for Datecs Fiscal Printer
 *
 * This bridge runs as a local server on the POS machine and translates
 * HTTP/JSON requests from the browser (PunktePass) to TCP commands for Datecs.
 *
 * Usage:
 *   php DatecsBridge.php [port] [datecs_ip] [datecs_port]
 *
 * Example:
 *   php DatecsBridge.php 8080 127.0.0.1 3999
 *
 * @author PunktePass Team
 * @version 1.0.0
 */

require_once __DIR__ . '/DatecsConnector.php';

class DatecsBridge {

    private $httpPort;
    private $datecsIp;
    private $datecsPort;
    private $connector;
    private $running = false;

    // CORS settings
    private $allowedOrigins = ['*']; // Configure for production!

    /**
     * Constructor
     */
    public function __construct($httpPort = 8080, $datecsIp = '127.0.0.1', $datecsPort = 3999) {
        $this->httpPort = $httpPort;
        $this->datecsIp = $datecsIp;
        $this->datecsPort = $datecsPort;
        $this->connector = new DatecsConnector($datecsIp, $datecsPort);
    }

    /**
     * Start the HTTP bridge server
     */
    public function start() {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($socket === false) {
            die("Could not create socket: " . socket_strerror(socket_last_error()) . "\n");
        }

        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

        if (socket_bind($socket, '0.0.0.0', $this->httpPort) === false) {
            die("Could not bind to port {$this->httpPort}: " . socket_strerror(socket_last_error($socket)) . "\n");
        }

        if (socket_listen($socket, 5) === false) {
            die("Could not listen on socket: " . socket_strerror(socket_last_error($socket)) . "\n");
        }

        $this->running = true;

        echo "==============================================\n";
        echo "  PunktePass Datecs Bridge v1.0\n";
        echo "==============================================\n";
        echo "HTTP Server: http://localhost:{$this->httpPort}\n";
        echo "Datecs Device: {$this->datecsIp}:{$this->datecsPort}\n";
        echo "Press Ctrl+C to stop\n";
        echo "==============================================\n\n";

        while ($this->running) {
            $client = @socket_accept($socket);

            if ($client === false) {
                continue;
            }

            $request = socket_read($client, 8192);

            if ($request) {
                $response = $this->handleRequest($request);
                socket_write($client, $response);
            }

            socket_close($client);
        }

        socket_close($socket);
    }

    /**
     * Handle incoming HTTP request
     */
    private function handleRequest($rawRequest) {
        // Parse HTTP request
        $lines = explode("\r\n", $rawRequest);
        $firstLine = explode(' ', $lines[0]);

        $method = $firstLine[0] ?? 'GET';
        $uri = $firstLine[1] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);

        // Get request body (for POST)
        $body = '';
        $bodyStart = strpos($rawRequest, "\r\n\r\n");
        if ($bodyStart !== false) {
            $body = substr($rawRequest, $bodyStart + 4);
        }

        // Handle CORS preflight
        if ($method === 'OPTIONS') {
            return $this->corsResponse();
        }

        // Route request
        $responseData = $this->routeRequest($method, $path, $body);

        return $this->jsonResponse($responseData);
    }

    /**
     * Route request to appropriate handler
     */
    private function routeRequest($method, $path, $body) {
        $data = json_decode($body, true) ?? [];

        switch ($path) {

            // ==================== STATUS ====================
            case '/':
            case '/status':
                return [
                    'success' => true,
                    'service' => 'PunktePass Datecs Bridge',
                    'version' => '1.0.0',
                    'datecs' => [
                        'ip' => $this->datecsIp,
                        'port' => $this->datecsPort
                    ]
                ];

            case '/ping':
                return $this->pingDatecs();

            // ==================== RECEIPT ====================
            case '/receipt/open':
                return $this->openReceipt($data);

            case '/receipt/item':
                return $this->addItem($data);

            case '/receipt/subtotal':
                return $this->subtotal($data);

            case '/receipt/payment':
                return $this->payment($data);

            case '/receipt/close':
                return $this->closeReceipt();

            case '/receipt/void':
                return $this->voidReceipt();

            case '/receipt/text':
                return $this->printText($data);

            case '/receipt/qrcode':
                return $this->printQRCode($data);

            // ==================== PUNKTEPASS SALE ====================
            case '/sale':
                return $this->processSale($data);

            // ==================== NON-FISCAL ====================
            case '/nonfiscal/open':
                return $this->openNonFiscal();

            case '/nonfiscal/text':
                return $this->printNonFiscalText($data);

            case '/nonfiscal/close':
                return $this->closeNonFiscal();

            case '/punktepass/info':
                return $this->printPunktePassInfo($data);

            // ==================== REPORTS ====================
            case '/report/x':
                return $this->printXReport();

            case '/report/z':
                return $this->printZReport();

            // ==================== UTILITY ====================
            case '/drawer/open':
                return $this->openDrawer($data);

            case '/display':
                return $this->displayText($data);

            case '/display/clear':
                return $this->clearDisplay();

            case '/diagnostic':
                return $this->printDiagnostic();

            default:
                return [
                    'success' => false,
                    'error' => 'Unknown endpoint: ' . $path
                ];
        }
    }

    // ==================== HANDLER METHODS ====================

    private function pingDatecs() {
        if ($this->connector->connect()) {
            $status = $this->connector->getStatus();
            $this->connector->disconnect();
            return [
                'success' => true,
                'connected' => true,
                'status' => $status
            ];
        }
        return [
            'success' => false,
            'connected' => false,
            'error' => $this->connector->getLastError()
        ];
    }

    private function openReceipt($data) {
        $type = intval($data['type'] ?? 1);
        $cif = $data['cif'] ?? '';
        $operator = $data['operator'] ?? null;

        if ($operator) {
            $this->connector->setOperator(
                $operator['code'] ?? '0001',
                $operator['password'] ?? '1',
                $operator['till'] ?? 'I'
            );
        }

        $this->connector->connect();
        $result = $this->connector->openReceipt($type, $cif);

        return [
            'success' => $result,
            'error' => $result ? '' : $this->connector->getLastError()
        ];
    }

    private function addItem($data) {
        $result = $this->connector->addSaleItem(
            $data['name'] ?? 'Termek',
            floatval($data['price'] ?? 0),
            floatval($data['qty'] ?? 1),
            intval($data['vat'] ?? 1),
            intval($data['discountType'] ?? 0),
            floatval($data['discountValue'] ?? 0),
            intval($data['department'] ?? 1),
            $data['unit'] ?? 'BUC.'
        );

        return [
            'success' => $result,
            'error' => $result ? '' : $this->connector->getLastError()
        ];
    }

    private function subtotal($data) {
        $result = $this->connector->subtotal(
            boolval($data['print'] ?? true),
            boolval($data['display'] ?? true),
            intval($data['discountType'] ?? 0),
            floatval($data['discountValue'] ?? 0)
        );

        return [
            'success' => $result && $result['success'],
            'data' => $result['data'] ?? '',
            'error' => ($result && $result['success']) ? '' : $this->connector->getLastError()
        ];
    }

    private function payment($data) {
        $result = $this->connector->payment(
            intval($data['type'] ?? 0),
            floatval($data['amount'] ?? 0)
        );

        return [
            'success' => $result && $result['success'],
            'data' => $result['data'] ?? '',
            'error' => ($result && $result['success']) ? '' : $this->connector->getLastError()
        ];
    }

    private function closeReceipt() {
        $result = $this->connector->closeReceipt();
        $this->connector->disconnect();

        return [
            'success' => $result,
            'error' => $result ? '' : $this->connector->getLastError()
        ];
    }

    private function voidReceipt() {
        $result = $this->connector->voidReceipt();
        $this->connector->disconnect();

        return [
            'success' => $result,
            'error' => $result ? '' : $this->connector->getLastError()
        ];
    }

    private function printText($data) {
        $result = $this->connector->printText(
            $data['text'] ?? '',
            boolval($data['bold'] ?? false),
            boolval($data['italic'] ?? false),
            boolval($data['underline'] ?? false),
            boolval($data['doubleHeight'] ?? false),
            boolval($data['doubleWidth'] ?? false)
        );

        return [
            'success' => $result,
            'error' => $result ? '' : $this->connector->getLastError()
        ];
    }

    private function printQRCode($data) {
        $result = $this->connector->printQRCode($data['data'] ?? '');

        return [
            'success' => $result,
            'error' => $result ? '' : $this->connector->getLastError()
        ];
    }

    private function processSale($data) {
        $operator = $data['operator'] ?? null;
        if ($operator) {
            $this->connector->setOperator(
                $operator['code'] ?? '0001',
                $operator['password'] ?? '1',
                $operator['till'] ?? 'I'
            );
        }

        $result = $this->connector->processPunktePassSale(
            $data['items'] ?? [],
            floatval($data['punkteDiscount'] ?? 0),
            intval($data['paymentType'] ?? 0),
            floatval($data['paymentAmount'] ?? 0),
            $data['customerCIF'] ?? '',
            $data['punktePassId'] ?? ''
        );

        $this->connector->disconnect();

        return $result;
    }

    private function openNonFiscal() {
        $this->connector->connect();
        $result = $this->connector->openNonFiscalReceipt();

        return [
            'success' => $result,
            'error' => $result ? '' : $this->connector->getLastError()
        ];
    }

    private function printNonFiscalText($data) {
        $result = $this->connector->printNonFiscalText($data['text'] ?? '');

        return [
            'success' => $result,
            'error' => $result ? '' : $this->connector->getLastError()
        ];
    }

    private function closeNonFiscal() {
        $result = $this->connector->closeNonFiscalReceipt();
        $this->connector->disconnect();

        return [
            'success' => $result,
            'error' => $result ? '' : $this->connector->getLastError()
        ];
    }

    private function printPunktePassInfo($data) {
        $this->connector->connect();
        $result = $this->connector->printPunktePassInfo(
            $data['memberId'] ?? '',
            $data['memberName'] ?? '',
            intval($data['points'] ?? 0),
            intval($data['discount'] ?? 0)
        );
        $this->connector->disconnect();

        return [
            'success' => $result,
            'error' => $result ? '' : $this->connector->getLastError()
        ];
    }

    private function printXReport() {
        $this->connector->connect();
        $result = $this->connector->printXReport();
        $this->connector->disconnect();

        return [
            'success' => $result,
            'error' => $result ? '' : $this->connector->getLastError()
        ];
    }

    private function printZReport() {
        $this->connector->connect();
        $result = $this->connector->printZReport();
        $this->connector->disconnect();

        return [
            'success' => $result,
            'error' => $result ? '' : $this->connector->getLastError()
        ];
    }

    private function openDrawer($data) {
        $this->connector->connect();
        $result = $this->connector->openCashDrawer(intval($data['duration'] ?? 300));
        $this->connector->disconnect();

        return [
            'success' => $result,
            'error' => $result ? '' : $this->connector->getLastError()
        ];
    }

    private function displayText($data) {
        $this->connector->connect();
        $result = $this->connector->displayText(
            $data['line1'] ?? '',
            $data['line2'] ?? ''
        );
        $this->connector->disconnect();

        return [
            'success' => $result,
            'error' => $result ? '' : $this->connector->getLastError()
        ];
    }

    private function clearDisplay() {
        $this->connector->connect();
        $result = $this->connector->clearDisplay();
        $this->connector->disconnect();

        return [
            'success' => $result,
            'error' => $result ? '' : $this->connector->getLastError()
        ];
    }

    private function printDiagnostic() {
        $this->connector->connect();
        $result = $this->connector->printDiagnostic();
        $this->connector->disconnect();

        return [
            'success' => $result,
            'error' => $result ? '' : $this->connector->getLastError()
        ];
    }

    // ==================== HTTP RESPONSE HELPERS ====================

    private function jsonResponse($data) {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $response = "HTTP/1.1 200 OK\r\n";
        $response .= "Content-Type: application/json; charset=utf-8\r\n";
        $response .= "Access-Control-Allow-Origin: *\r\n";
        $response .= "Access-Control-Allow-Methods: GET, POST, OPTIONS\r\n";
        $response .= "Access-Control-Allow-Headers: Content-Type\r\n";
        $response .= "Content-Length: " . strlen($json) . "\r\n";
        $response .= "Connection: close\r\n";
        $response .= "\r\n";
        $response .= $json;

        return $response;
    }

    private function corsResponse() {
        $response = "HTTP/1.1 200 OK\r\n";
        $response .= "Access-Control-Allow-Origin: *\r\n";
        $response .= "Access-Control-Allow-Methods: GET, POST, OPTIONS\r\n";
        $response .= "Access-Control-Allow-Headers: Content-Type\r\n";
        $response .= "Content-Length: 0\r\n";
        $response .= "Connection: close\r\n";
        $response .= "\r\n";

        return $response;
    }
}

// ==================== CLI ENTRY POINT ====================

if (php_sapi_name() === 'cli') {
    $httpPort = intval($argv[1] ?? 8080);
    $datecsIp = $argv[2] ?? '127.0.0.1';
    $datecsPort = intval($argv[3] ?? 3999);

    $bridge = new DatecsBridge($httpPort, $datecsIp, $datecsPort);
    $bridge->start();
}
