/**
 * DatecsClient - JavaScript client for PunktePass Datecs Bridge
 *
 * Usage:
 *   const datecs = new DatecsClient('http://localhost:8080');
 *   await datecs.processSale({ items: [...], punkteDiscount: 10 });
 *
 * @version 1.0.0
 */

class DatecsClient {

    /**
     * Constructor
     * @param {string} bridgeUrl - URL of the Datecs Bridge server (default: http://localhost:8080)
     */
    constructor(bridgeUrl = 'http://localhost:8080') {
        this.bridgeUrl = bridgeUrl.replace(/\/$/, ''); // Remove trailing slash
        this.operator = {
            code: '0001',
            password: '1',
            till: 'I'
        };
    }

    /**
     * Set operator credentials
     * @param {string} code - Operator code (4 digits)
     * @param {string} password - Operator password
     * @param {string} till - Till/POS number
     */
    setOperator(code, password, till = 'I') {
        this.operator = { code, password, till };
    }

    /**
     * Make HTTP request to bridge
     * @private
     */
    async request(endpoint, data = null) {
        const options = {
            method: data ? 'POST' : 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        };

        if (data) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(`${this.bridgeUrl}${endpoint}`, options);
            const result = await response.json();
            return result;
        } catch (error) {
            return {
                success: false,
                error: `Bridge connection failed: ${error.message}`
            };
        }
    }

    // ==================== STATUS ====================

    /**
     * Check bridge status
     * @returns {Promise<Object>}
     */
    async getStatus() {
        return this.request('/status');
    }

    /**
     * Ping Datecs device
     * @returns {Promise<Object>}
     */
    async ping() {
        return this.request('/ping');
    }

    // ==================== RECEIPT OPERATIONS ====================

    /**
     * Open fiscal receipt
     * @param {number} type - Receipt type (1=fiscal, 2=invoice)
     * @param {string} cif - Customer tax ID
     * @returns {Promise<Object>}
     */
    async openReceipt(type = 1, cif = '') {
        return this.request('/receipt/open', {
            type,
            cif,
            operator: this.operator
        });
    }

    /**
     * Add item to receipt
     * @param {Object} item - Item details
     * @param {string} item.name - Product name
     * @param {number} item.price - Unit price
     * @param {number} item.qty - Quantity (default 1)
     * @param {number} item.vat - VAT group 1-7 (default 1)
     * @param {number} item.discountType - Discount type (0=none, 2=%, 4=value)
     * @param {number} item.discountValue - Discount amount
     * @param {string} item.unit - Unit of measure (default 'BUC.')
     * @returns {Promise<Object>}
     */
    async addItem(item) {
        return this.request('/receipt/item', {
            name: item.name || 'Termek',
            price: item.price || 0,
            qty: item.qty || 1,
            vat: item.vat || 1,
            discountType: item.discountType || 0,
            discountValue: item.discountValue || 0,
            department: item.department || 1,
            unit: item.unit || 'BUC.'
        });
    }

    /**
     * Add item with PunktePass discount
     * @param {string} name - Product name
     * @param {number} price - Unit price
     * @param {number} qty - Quantity
     * @param {number} discountPercent - Discount percentage
     * @param {number} vat - VAT group
     * @returns {Promise<Object>}
     */
    async addItemWithDiscount(name, price, qty, discountPercent, vat = 1) {
        return this.addItem({
            name,
            price,
            qty,
            vat,
            discountType: 2, // Percentage discount
            discountValue: discountPercent
        });
    }

    /**
     * Print subtotal
     * @param {Object} options - Options
     * @param {boolean} options.print - Print subtotal
     * @param {boolean} options.display - Show on display
     * @param {number} options.discountType - Discount type for whole receipt
     * @param {number} options.discountValue - Discount value
     * @returns {Promise<Object>}
     */
    async subtotal(options = {}) {
        return this.request('/receipt/subtotal', {
            print: options.print !== false,
            display: options.display !== false,
            discountType: options.discountType || 0,
            discountValue: options.discountValue || 0
        });
    }

    /**
     * Apply PunktePass discount to entire receipt
     * @param {number} discountPercent - Discount percentage
     * @returns {Promise<Object>}
     */
    async applyPunktePassDiscount(discountPercent) {
        return this.subtotal({
            discountType: 2,
            discountValue: discountPercent
        });
    }

    /**
     * Register payment
     * @param {number} type - Payment type (0=cash, 1=card)
     * @param {number} amount - Amount (0 for exact)
     * @returns {Promise<Object>}
     */
    async payment(type = 0, amount = 0) {
        return this.request('/receipt/payment', { type, amount });
    }

    /**
     * Close fiscal receipt
     * @returns {Promise<Object>}
     */
    async closeReceipt() {
        return this.request('/receipt/close');
    }

    /**
     * Void/cancel open receipt
     * @returns {Promise<Object>}
     */
    async voidReceipt() {
        return this.request('/receipt/void');
    }

    /**
     * Print text in receipt
     * @param {string} text - Text to print
     * @param {Object} style - Text style options
     * @returns {Promise<Object>}
     */
    async printText(text, style = {}) {
        return this.request('/receipt/text', {
            text,
            bold: style.bold || false,
            italic: style.italic || false,
            underline: style.underline || false,
            doubleHeight: style.doubleHeight || false,
            doubleWidth: style.doubleWidth || false
        });
    }

    /**
     * Print QR code
     * @param {string} data - QR code data
     * @returns {Promise<Object>}
     */
    async printQRCode(data) {
        return this.request('/receipt/qrcode', { data });
    }

    // ==================== HIGH-LEVEL PUNKTEPASS SALE ====================

    /**
     * Process complete PunktePass sale
     * @param {Object} options - Sale options
     * @param {Array} options.items - Array of items [{name, price, qty, vat}]
     * @param {number} options.punkteDiscount - PunktePass discount percentage
     * @param {number} options.paymentType - Payment type (0=cash, 1=card)
     * @param {number} options.paymentAmount - Payment amount (0 for exact)
     * @param {string} options.customerCIF - Customer tax ID
     * @param {string} options.punktePassId - PunktePass member ID
     * @returns {Promise<Object>}
     */
    async processSale(options) {
        return this.request('/sale', {
            items: options.items || [],
            punkteDiscount: options.punkteDiscount || 0,
            paymentType: options.paymentType || 0,
            paymentAmount: options.paymentAmount || 0,
            customerCIF: options.customerCIF || '',
            punktePassId: options.punktePassId || '',
            operator: this.operator
        });
    }

    // ==================== NON-FISCAL ====================

    /**
     * Open non-fiscal receipt
     * @returns {Promise<Object>}
     */
    async openNonFiscalReceipt() {
        return this.request('/nonfiscal/open');
    }

    /**
     * Print non-fiscal text
     * @param {string} text - Text to print
     * @returns {Promise<Object>}
     */
    async printNonFiscalText(text) {
        return this.request('/nonfiscal/text', { text });
    }

    /**
     * Close non-fiscal receipt
     * @returns {Promise<Object>}
     */
    async closeNonFiscalReceipt() {
        return this.request('/nonfiscal/close');
    }

    /**
     * Print PunktePass member info (non-fiscal)
     * @param {Object} member - Member info
     * @param {string} member.id - Member ID
     * @param {string} member.name - Member name
     * @param {number} member.points - Current points
     * @param {number} member.discount - Available discount %
     * @returns {Promise<Object>}
     */
    async printPunktePassInfo(member) {
        return this.request('/punktepass/info', {
            memberId: member.id,
            memberName: member.name,
            points: member.points,
            discount: member.discount
        });
    }

    // ==================== REPORTS ====================

    /**
     * Print X report
     * @returns {Promise<Object>}
     */
    async printXReport() {
        return this.request('/report/x');
    }

    /**
     * Print Z report (daily closure)
     * @returns {Promise<Object>}
     */
    async printZReport() {
        return this.request('/report/z');
    }

    // ==================== UTILITY ====================

    /**
     * Open cash drawer
     * @param {number} duration - Duration in ms
     * @returns {Promise<Object>}
     */
    async openDrawer(duration = 300) {
        return this.request('/drawer/open', { duration });
    }

    /**
     * Display text on customer display
     * @param {string} line1 - First line
     * @param {string} line2 - Second line
     * @returns {Promise<Object>}
     */
    async displayText(line1, line2 = '') {
        return this.request('/display', { line1, line2 });
    }

    /**
     * Clear customer display
     * @returns {Promise<Object>}
     */
    async clearDisplay() {
        return this.request('/display/clear');
    }

    /**
     * Print diagnostic
     * @returns {Promise<Object>}
     */
    async printDiagnostic() {
        return this.request('/diagnostic');
    }

    // ==================== CONVENIENCE METHODS ====================

    /**
     * Quick sale - single item cash payment
     * @param {string} name - Product name
     * @param {number} price - Price
     * @param {number} qty - Quantity
     * @returns {Promise<Object>}
     */
    async quickSale(name, price, qty = 1) {
        return this.processSale({
            items: [{ name, price, qty }],
            paymentType: 0
        });
    }

    /**
     * Quick PunktePass sale with discount
     * @param {Array} items - Items array
     * @param {string} memberId - PunktePass member ID
     * @param {number} discountPercent - Discount to apply
     * @returns {Promise<Object>}
     */
    async punktePassSale(items, memberId, discountPercent) {
        return this.processSale({
            items,
            punktePassId: memberId,
            punkteDiscount: discountPercent
        });
    }
}

// Payment type constants
DatecsClient.PAYMENT_CASH = 0;
DatecsClient.PAYMENT_CARD = 1;
DatecsClient.PAYMENT_CREDIT = 2;
DatecsClient.PAYMENT_CHECK = 3;
DatecsClient.PAYMENT_VOUCHER = 4;

// VAT group constants (Romania)
DatecsClient.VAT_19 = 1;
DatecsClient.VAT_9 = 2;
DatecsClient.VAT_5 = 3;
DatecsClient.VAT_0 = 4;

// Discount type constants
DatecsClient.DISCOUNT_NONE = 0;
DatecsClient.DISCOUNT_PERCENT = 2;
DatecsClient.DISCOUNT_VALUE = 4;

// Export for different environments
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DatecsClient;
}

if (typeof window !== 'undefined') {
    window.DatecsClient = DatecsClient;
}
