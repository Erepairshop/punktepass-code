<?php
/**
 * DatecsConnector - Datecs Fiscal Printer TCP/IP Communication Class
 *
 * For PunktePass Kassza integration with Romanian Datecs fiscal devices
 * Protocol: Datecs FP Communication Interface Protocol v2.10
 *
 * @author PunktePass Team
 * @version 1.0.0
 */

class DatecsConnector {

    // Connection settings
    private $ip;
    private $port;
    private $socket;
    private $timeout;

    // Operator settings
    private $operatorCode;
    private $operatorPassword;
    private $tillNumber;

    // Protocol constants
    const SOH = "\x01";  // Start of Header
    const ENQ = "\x05";  // Enquiry
    const ETX = "\x03";  // End of Text
    const TAB = "\t";    // Tab separator

    // Receipt types
    const RECEIPT_FISCAL = 1;
    const RECEIPT_INVOICE = 2;
    const RECEIPT_STORNO = 3;
    const RECEIPT_CREDIT_NOTE = 4;

    // Payment types
    const PAYMENT_CASH = 0;
    const PAYMENT_CARD = 1;
    const PAYMENT_CREDIT = 2;
    const PAYMENT_CHECK = 3;
    const PAYMENT_VOUCHER = 4;
    const PAYMENT_VOUCHER2 = 5;
    const PAYMENT_VOUCHER3 = 6;
    const PAYMENT_VOUCHER4 = 7;
    const PAYMENT_FOREIGN_CURRENCY = 8;
    const PAYMENT_CARD2 = 9;

    // Discount types
    const DISCOUNT_NONE = 0;
    const SURCHARGE_PERCENT = 1;
    const DISCOUNT_PERCENT = 2;
    const SURCHARGE_VALUE = 3;
    const DISCOUNT_VALUE = 4;

    // VAT groups (Romania)
    const VAT_A = 1;  // 19%
    const VAT_B = 2;  // 9%
    const VAT_C = 3;  // 5%
    const VAT_D = 4;  // 0%
    const VAT_E = 5;
    const VAT_F = 6;
    const VAT_G = 7;

    // Status tracking
    private $lastError = '';
    private $lastResponse = '';
    private $sequenceNumber = 0;
    private $receiptOpen = false;

    /**
     * Constructor
     *
     * @param string $ip IP address of Datecs device
     * @param int $port Port number (default 3999)
     * @param int $timeout Connection timeout in seconds
     */
    public function __construct($ip = '127.0.0.1', $port = 3999, $timeout = 10) {
        $this->ip = $ip;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->socket = null;

        // Default operator
        $this->operatorCode = '0001';
        $this->operatorPassword = '1';
        $this->tillNumber = 'I';
    }

    /**
     * Set operator credentials
     *
     * @param string $code Operator code (4 digits)
     * @param string $password Operator password
     * @param string $tillNumber Till/POS number
     */
    public function setOperator($code, $password, $tillNumber = 'I') {
        $this->operatorCode = str_pad($code, 4, '0', STR_PAD_LEFT);
        $this->operatorPassword = $password;
        $this->tillNumber = $tillNumber;
    }

    /**
     * Connect to Datecs device
     *
     * @return bool Success status
     */
    public function connect() {
        $this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($this->socket === false) {
            $this->lastError = "Socket creation failed: " . socket_strerror(socket_last_error());
            return false;
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->timeout, 'usec' => 0]);

        $result = @socket_connect($this->socket, $this->ip, $this->port);

        if ($result === false) {
            $this->lastError = "Connection failed to {$this->ip}:{$this->port} - " . socket_strerror(socket_last_error($this->socket));
            return false;
        }

        return true;
    }

    /**
     * Disconnect from device
     */
    public function disconnect() {
        if ($this->socket) {
            socket_close($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Check if connected
     *
     * @return bool
     */
    public function isConnected() {
        return $this->socket !== null;
    }

    /**
     * Send raw command to device
     *
     * @param int $command Command number
     * @param string $data Command data
     * @return array|false Response array or false on error
     */
    public function sendCommand($command, $data = '') {
        if (!$this->isConnected()) {
            if (!$this->connect()) {
                return false;
            }
        }

        // Build message
        $this->sequenceNumber = ($this->sequenceNumber % 255) + 32;
        $seq = chr($this->sequenceNumber);
        $cmd = chr($command + 32);

        $message = $seq . $cmd . $data;
        $len = chr(strlen($message) + 32 + 3);

        // Calculate BCC (checksum)
        $bcc = $this->calculateBCC($len . $message . self::ENQ);

        // Build full packet
        $packet = self::SOH . $len . $message . self::ENQ . $bcc . self::ETX;

        // Send
        $sent = @socket_write($this->socket, $packet, strlen($packet));

        if ($sent === false) {
            $this->lastError = "Send failed: " . socket_strerror(socket_last_error($this->socket));
            return false;
        }

        // Receive response
        $response = $this->receiveResponse();

        return $response;
    }

    /**
     * Receive and parse response
     *
     * @return array|false
     */
    private function receiveResponse() {
        $buffer = '';
        $maxAttempts = 50;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $chunk = @socket_read($this->socket, 1024);

            if ($chunk === false || $chunk === '') {
                $attempt++;
                usleep(10000); // 10ms wait
                continue;
            }

            $buffer .= $chunk;

            // Check for complete message (ends with ETX)
            if (strpos($buffer, self::ETX) !== false) {
                break;
            }

            $attempt++;
        }

        if (empty($buffer)) {
            $this->lastError = "No response received";
            return false;
        }

        $this->lastResponse = bin2hex($buffer);

        // Parse response
        return $this->parseResponse($buffer);
    }

    /**
     * Parse device response
     *
     * @param string $response Raw response
     * @return array Parsed response
     */
    private function parseResponse($response) {
        $result = [
            'success' => false,
            'data' => '',
            'status' => [],
            'error' => ''
        ];

        // Remove framing characters
        $response = trim($response, self::SOH . self::ETX);

        if (strlen($response) < 6) {
            $result['error'] = 'Response too short';
            return $result;
        }

        // Extract data (between command byte and ENQ)
        $enqPos = strpos($response, self::ENQ);
        if ($enqPos !== false && $enqPos > 2) {
            $result['data'] = substr($response, 2, $enqPos - 2);
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * Calculate BCC checksum
     *
     * @param string $data Data to checksum
     * @return string 4-byte BCC
     */
    private function calculateBCC($data) {
        $sum = 0;
        for ($i = 0; $i < strlen($data); $i++) {
            $sum += ord($data[$i]);
        }

        return sprintf('%04X', $sum);
    }

    // =========================================================================
    // FISCAL RECEIPT COMMANDS
    // =========================================================================

    /**
     * Open fiscal receipt (Command 48)
     *
     * @param int $type Receipt type (1=fiscal, 2=invoice, etc.)
     * @param string $customerCIF Customer tax ID (optional)
     * @return bool Success
     */
    public function openReceipt($type = self::RECEIPT_FISCAL, $customerCIF = '') {
        $data = $type . self::TAB
              . $this->operatorCode . self::TAB
              . $this->operatorPassword . self::TAB
              . $this->tillNumber . self::TAB
              . $customerCIF . self::TAB;

        $response = $this->sendCommand(48, $data);

        if ($response && $response['success']) {
            $this->receiptOpen = true;
            return true;
        }

        return false;
    }

    /**
     * Register sale item (Command 49)
     *
     * @param string $name Product name
     * @param float $price Unit price
     * @param float $quantity Quantity
     * @param int $vatGroup VAT group (1-7)
     * @param int $discountType Discount type (0-4)
     * @param float $discountValue Discount value
     * @param int $department Department number
     * @param string $unit Unit of measure
     * @return bool Success
     */
    public function addSaleItem($name, $price, $quantity = 1, $vatGroup = self::VAT_A,
                                $discountType = self::DISCOUNT_NONE, $discountValue = 0,
                                $department = 1, $unit = 'BUC.') {

        // Sanitize product name (max 36 chars, no special chars)
        $name = $this->sanitizeText($name, 36);

        $data = $name . self::TAB
              . $vatGroup . self::TAB
              . number_format($price, 2, '.', '') . self::TAB
              . number_format($quantity, 3, '.', '') . self::TAB
              . ($discountType > 0 ? $discountType : '') . self::TAB
              . ($discountValue > 0 ? number_format($discountValue, 2, '.', '') : '') . self::TAB
              . $department . self::TAB
              . $unit . self::TAB;

        $response = $this->sendCommand(49, $data);

        return $response && $response['success'];
    }

    /**
     * Add sale item with PunktePass discount
     *
     * @param string $name Product name
     * @param float $price Unit price
     * @param float $quantity Quantity
     * @param float $punkteDiscount Discount percentage from PunktePass points
     * @param int $vatGroup VAT group
     * @return bool Success
     */
    public function addSaleItemWithPunkteDiscount($name, $price, $quantity, $punkteDiscount, $vatGroup = self::VAT_A) {
        return $this->addSaleItem(
            $name,
            $price,
            $quantity,
            $vatGroup,
            self::DISCOUNT_PERCENT,
            $punkteDiscount
        );
    }

    /**
     * Subtotal with optional discount (Command 51)
     *
     * @param bool $print Print subtotal
     * @param bool $display Show on display
     * @param int $discountType Discount type for entire receipt
     * @param float $discountValue Discount value
     * @return array|false Subtotal info
     */
    public function subtotal($print = true, $display = true, $discountType = 0, $discountValue = 0) {
        $data = ($print ? '1' : '0') . self::TAB
              . ($display ? '1' : '0') . self::TAB
              . ($discountType > 0 ? $discountType : '') . self::TAB
              . ($discountValue > 0 ? number_format($discountValue, 2, '.', '') : '') . self::TAB;

        $response = $this->sendCommand(51, $data);

        return $response;
    }

    /**
     * Apply PunktePass discount to entire receipt
     *
     * @param float $discountPercent Discount percentage
     * @return array|false
     */
    public function applyPunktePassDiscount($discountPercent) {
        return $this->subtotal(true, true, self::DISCOUNT_PERCENT, $discountPercent);
    }

    /**
     * Apply PunktePass fixed discount to entire receipt
     *
     * @param float $discountAmount Fixed discount amount
     * @return array|false
     */
    public function applyPunktePassFixedDiscount($discountAmount) {
        return $this->subtotal(true, true, self::DISCOUNT_VALUE, $discountAmount);
    }

    /**
     * Register payment (Command 53)
     *
     * @param int $paymentType Payment type (0=cash, 1=card, etc.)
     * @param float $amount Amount (0 for exact amount)
     * @return array|false Payment result with change
     */
    public function payment($paymentType = self::PAYMENT_CASH, $amount = 0) {
        $data = $paymentType . self::TAB
              . ($amount > 0 ? number_format($amount, 2, '.', '') : '') . self::TAB;

        $response = $this->sendCommand(53, $data);

        return $response;
    }

    /**
     * Close fiscal receipt (Command 56)
     *
     * @return bool Success
     */
    public function closeReceipt() {
        $response = $this->sendCommand(56);

        if ($response && $response['success']) {
            $this->receiptOpen = false;
            return true;
        }

        return false;
    }

    /**
     * Print free text in receipt (Command 54)
     *
     * @param string $text Text to print
     * @param bool $bold Bold text
     * @param bool $italic Italic text
     * @param bool $underline Underlined text
     * @param bool $doubleHeight Double height text
     * @param bool $doubleWidth Double width text
     * @return bool Success
     */
    public function printText($text, $bold = false, $italic = false, $underline = false,
                              $doubleHeight = false, $doubleWidth = false) {
        $text = $this->sanitizeText($text, 48);

        $data = $text . self::TAB
              . ($bold ? '1' : '0') . self::TAB
              . ($italic ? '1' : '0') . self::TAB
              . ($underline ? '1' : '0') . self::TAB
              . ($doubleHeight ? '1' : '0') . self::TAB
              . ($doubleWidth ? '1' : '0') . self::TAB
              . self::TAB;

        $response = $this->sendCommand(54, $data);

        return $response && $response['success'];
    }

    /**
     * Print QR code (Command 84)
     *
     * @param string $data QR code data (URL, text, etc.)
     * @return bool Success
     */
    public function printQRCode($data) {
        $cmdData = '4' . self::TAB . $data . self::TAB;

        $response = $this->sendCommand(84, $cmdData);

        return $response && $response['success'];
    }

    /**
     * Void/cancel open receipt (Command 60)
     *
     * @return bool Success
     */
    public function voidReceipt() {
        $response = $this->sendCommand(60);

        if ($response && $response['success']) {
            $this->receiptOpen = false;
            return true;
        }

        return false;
    }

    // =========================================================================
    // NON-FISCAL RECEIPT COMMANDS
    // =========================================================================

    /**
     * Open non-fiscal receipt (Command 38)
     *
     * @return bool Success
     */
    public function openNonFiscalReceipt() {
        $response = $this->sendCommand(38);
        return $response && $response['success'];
    }

    /**
     * Print text line in non-fiscal receipt (Command 42)
     *
     * @param string $text Text to print
     * @return bool Success
     */
    public function printNonFiscalText($text) {
        $text = $this->sanitizeText($text, 48);
        $data = $text . self::TAB;

        $response = $this->sendCommand(42, $data);
        return $response && $response['success'];
    }

    /**
     * Close non-fiscal receipt (Command 39)
     *
     * @return bool Success
     */
    public function closeNonFiscalReceipt() {
        $response = $this->sendCommand(39);
        return $response && $response['success'];
    }

    // =========================================================================
    // REPORTS
    // =========================================================================

    /**
     * Print X report (Command 69)
     *
     * @return bool Success
     */
    public function printXReport() {
        $data = 'X' . self::TAB;
        $response = $this->sendCommand(69, $data);
        return $response && $response['success'];
    }

    /**
     * Print Z report - daily closure (Command 69)
     *
     * @return bool Success
     */
    public function printZReport() {
        $data = 'Z' . self::TAB;
        $response = $this->sendCommand(69, $data);
        return $response && $response['success'];
    }

    // =========================================================================
    // UTILITY COMMANDS
    // =========================================================================

    /**
     * Open cash drawer (Command 106)
     *
     * @param int $duration Duration in ms (0-65535)
     * @return bool Success
     */
    public function openCashDrawer($duration = 300) {
        $data = $duration . self::TAB;
        $response = $this->sendCommand(106, $data);
        return $response && $response['success'];
    }

    /**
     * Get device status (Command 90)
     *
     * @return array|false Status info
     */
    public function getStatus() {
        $response = $this->sendCommand(90);
        return $response;
    }

    /**
     * Print diagnostic (Command 71)
     *
     * @return bool Success
     */
    public function printDiagnostic() {
        $response = $this->sendCommand(71);
        return $response && $response['success'];
    }

    /**
     * Set date and time (Command 61)
     *
     * @param DateTime $dateTime Date and time to set
     * @return bool Success
     */
    public function setDateTime($dateTime = null) {
        if ($dateTime === null) {
            $dateTime = new DateTime();
        }

        $data = $dateTime->format('d-m-Y H:i:s') . self::TAB;
        $response = $this->sendCommand(61, $data);
        return $response && $response['success'];
    }

    /**
     * Display text on customer display (Command 35 - line 2, Command 47 - line 1)
     *
     * @param string $line1 First line text (max 20 chars)
     * @param string $line2 Second line text (max 20 chars)
     * @return bool Success
     */
    public function displayText($line1, $line2 = '') {
        $success = true;

        if ($line1) {
            $data = $this->sanitizeText($line1, 20) . self::TAB;
            $response = $this->sendCommand(47, $data);
            $success = $success && ($response && $response['success']);
        }

        if ($line2) {
            $data = $this->sanitizeText($line2, 20) . self::TAB;
            $response = $this->sendCommand(35, $data);
            $success = $success && ($response && $response['success']);
        }

        return $success;
    }

    /**
     * Clear customer display (Command 33)
     *
     * @return bool Success
     */
    public function clearDisplay() {
        $response = $this->sendCommand(33);
        return $response && $response['success'];
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Sanitize text for Datecs (remove diacritics, limit length)
     *
     * @param string $text Input text
     * @param int $maxLength Maximum length
     * @return string Sanitized text
     */
    private function sanitizeText($text, $maxLength = 36) {
        // Romanian diacritics to ASCII
        $search = ['ă', 'â', 'î', 'ș', 'ț', 'Ă', 'Â', 'Î', 'Ș', 'Ț', 'ş', 'ţ', 'Ş', 'Ţ'];
        $replace = ['a', 'a', 'i', 's', 't', 'A', 'A', 'I', 'S', 'T', 's', 't', 'S', 'T'];
        $text = str_replace($search, $replace, $text);

        // Hungarian diacritics
        $search = ['á', 'é', 'í', 'ó', 'ö', 'ő', 'ú', 'ü', 'ű', 'Á', 'É', 'Í', 'Ó', 'Ö', 'Ő', 'Ú', 'Ü', 'Ű'];
        $replace = ['a', 'e', 'i', 'o', 'o', 'o', 'u', 'u', 'u', 'A', 'E', 'I', 'O', 'O', 'O', 'U', 'U', 'U'];
        $text = str_replace($search, $replace, $text);

        // Remove any remaining non-ASCII
        $text = preg_replace('/[^\x20-\x7E]/', '', $text);

        // Limit length
        return substr($text, 0, $maxLength);
    }

    /**
     * Get last error message
     *
     * @return string Error message
     */
    public function getLastError() {
        return $this->lastError;
    }

    /**
     * Get last raw response (hex)
     *
     * @return string Response in hex
     */
    public function getLastResponse() {
        return $this->lastResponse;
    }

    /**
     * Check if receipt is currently open
     *
     * @return bool
     */
    public function isReceiptOpen() {
        return $this->receiptOpen;
    }

    // =========================================================================
    // HIGH-LEVEL PUNKTEPASS METHODS
    // =========================================================================

    /**
     * Complete sale with PunktePass integration
     *
     * @param array $items Array of items: [['name' => '', 'price' => 0, 'qty' => 1, 'vat' => 1], ...]
     * @param float $punkteDiscount PunktePass discount percentage (0-100)
     * @param int $paymentType Payment type
     * @param float $paymentAmount Payment amount (0 for exact)
     * @param string $customerCIF Customer tax ID
     * @param string $punktePassId PunktePass member ID (for QR code)
     * @return array Result with success status and receipt number
     */
    public function processPunktePassSale($items, $punkteDiscount = 0, $paymentType = self::PAYMENT_CASH,
                                          $paymentAmount = 0, $customerCIF = '', $punktePassId = '') {
        $result = [
            'success' => false,
            'error' => '',
            'total' => 0,
            'discount' => 0
        ];

        // Connect if not connected
        if (!$this->isConnected()) {
            if (!$this->connect()) {
                $result['error'] = $this->lastError;
                return $result;
            }
        }

        // Open receipt
        if (!$this->openReceipt(self::RECEIPT_FISCAL, $customerCIF)) {
            $result['error'] = 'Failed to open receipt: ' . $this->lastError;
            return $result;
        }

        // Add items
        $total = 0;
        foreach ($items as $item) {
            $name = $item['name'] ?? 'Termek';
            $price = floatval($item['price'] ?? 0);
            $qty = floatval($item['qty'] ?? 1);
            $vat = intval($item['vat'] ?? self::VAT_A);

            if (!$this->addSaleItem($name, $price, $qty, $vat)) {
                $this->voidReceipt();
                $result['error'] = 'Failed to add item: ' . $name;
                return $result;
            }

            $total += $price * $qty;
        }

        $result['total'] = $total;

        // Apply PunktePass discount if any
        if ($punkteDiscount > 0) {
            if (!$this->applyPunktePassDiscount($punkteDiscount)) {
                $this->voidReceipt();
                $result['error'] = 'Failed to apply PunktePass discount';
                return $result;
            }
            $result['discount'] = $total * ($punkteDiscount / 100);
        }

        // Print PunktePass info
        if ($punktePassId) {
            $this->printText('--- PunktePass ---', true);
            $this->printText('ID: ' . $punktePassId);
            if ($punkteDiscount > 0) {
                $this->printText('Kedvezmeny: ' . $punkteDiscount . '%');
            }
        }

        // Payment
        $paymentResult = $this->payment($paymentType, $paymentAmount);
        if (!$paymentResult || !$paymentResult['success']) {
            $this->voidReceipt();
            $result['error'] = 'Payment failed';
            return $result;
        }

        // Print QR code if PunktePass ID provided
        if ($punktePassId) {
            $qrData = 'https://punktepass.com/member/' . $punktePassId;
            $this->printQRCode($qrData);
        }

        // Close receipt
        if (!$this->closeReceipt()) {
            $result['error'] = 'Failed to close receipt';
            return $result;
        }

        $result['success'] = true;

        return $result;
    }

    /**
     * Print PunktePass member info (non-fiscal)
     *
     * @param string $memberId Member ID
     * @param string $memberName Member name
     * @param int $points Current points
     * @param int $availableDiscount Available discount %
     * @return bool Success
     */
    public function printPunktePassInfo($memberId, $memberName, $points, $availableDiscount) {
        if (!$this->openNonFiscalReceipt()) {
            return false;
        }

        $this->printNonFiscalText('================================');
        $this->printNonFiscalText('       PUNKTEPASS INFO          ');
        $this->printNonFiscalText('================================');
        $this->printNonFiscalText('');
        $this->printNonFiscalText('Nev: ' . $memberName);
        $this->printNonFiscalText('ID: ' . $memberId);
        $this->printNonFiscalText('Pontok: ' . $points);
        $this->printNonFiscalText('Elerheto kedvezmeny: ' . $availableDiscount . '%');
        $this->printNonFiscalText('');
        $this->printNonFiscalText('================================');

        // Print QR code for member profile
        $this->printQRCode('https://punktepass.com/member/' . $memberId);

        return $this->closeNonFiscalReceipt();
    }
}
