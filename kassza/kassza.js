/**
 * PunktePass Kassza - POS Application
 *
 * @version 1.0.0
 */

// ==================== CONFIGURATION ====================

const CONFIG = {
    bridgeUrl: 'http://localhost:8080',
    currency: 'RON',
    operator: {
        code: '0001',
        password: '1',
        till: 'I'
    },
    // Demo mode - set to true to test without Datecs device
    demoMode: true
};

// ==================== STATE ====================

let datecs = null;
let cart = [];
let currentMember = null;
let appliedDiscount = 0;
let pendingPaymentType = 0;

// Quick products for demo
const quickProducts = [
    { name: 'Kave', price: 8.00, vat: 2 },
    { name: 'Cappuccino', price: 12.00, vat: 2 },
    { name: 'Croissant', price: 6.50, vat: 2 },
    { name: 'Szendvics', price: 15.00, vat: 2 },
    { name: 'Viz 0.5L', price: 4.00, vat: 1 },
    { name: 'Cola 0.33L', price: 7.00, vat: 1 },
    { name: 'Salata', price: 22.00, vat: 2 },
    { name: 'Pizza szelet', price: 12.00, vat: 2 }
];

// Demo members database
const demoMembers = {
    'PP001': { id: 'PP001', name: 'Kovacs Janos', points: 1250, level: 'Gold', discount: 15 },
    'PP002': { id: 'PP002', name: 'Nagy Maria', points: 580, level: 'Silver', discount: 10 },
    'PP003': { id: 'PP003', name: 'Toth Peter', points: 2100, level: 'Platinum', discount: 20 },
    '0740123456': { id: 'PP004', name: 'Kiss Anna', points: 320, level: 'Bronze', discount: 5 }
};

// ==================== INITIALIZATION ====================

document.addEventListener('DOMContentLoaded', () => {
    initializeDatecs();
    initializeUI();
    updateDateTime();
    setInterval(updateDateTime, 1000);
});

function initializeDatecs() {
    datecs = new DatecsClient(CONFIG.bridgeUrl);
    datecs.setOperator(
        CONFIG.operator.code,
        CONFIG.operator.password,
        CONFIG.operator.till
    );

    // Check connection
    checkConnection();
}

function initializeUI() {
    // Render quick products
    renderQuickProducts();

    // Barcode input handler
    const barcodeInput = document.getElementById('barcodeInput');
    barcodeInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            searchProduct();
        }
    });

    // Focus on barcode input
    barcodeInput.focus();
}

async function checkConnection() {
    const statusEl = document.getElementById('connectionStatus');
    const dotEl = statusEl.querySelector('.status-dot');

    if (CONFIG.demoMode) {
        dotEl.classList.remove('offline');
        dotEl.classList.add('online');
        statusEl.innerHTML = '<span class="status-dot online"></span> Demo mod';
        return;
    }

    try {
        const result = await datecs.ping();
        if (result.success && result.connected) {
            dotEl.classList.remove('offline');
            dotEl.classList.add('online');
            statusEl.innerHTML = '<span class="status-dot online"></span> Datecs OK';
        } else {
            dotEl.classList.remove('online');
            dotEl.classList.add('offline');
            statusEl.innerHTML = '<span class="status-dot offline"></span> Nincs kapcsolat';
        }
    } catch (error) {
        dotEl.classList.remove('online');
        dotEl.classList.add('offline');
        statusEl.innerHTML = '<span class="status-dot offline"></span> Hiba';
    }
}

function updateDateTime() {
    const now = new Date();
    const formatted = now.toLocaleString('hu-HU', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    document.getElementById('currentDateTime').textContent = formatted;
}

// ==================== QUICK PRODUCTS ====================

function renderQuickProducts() {
    const container = document.getElementById('quickProducts');
    container.innerHTML = quickProducts.map((product, index) => `
        <div class="quick-btn" onclick="addQuickProduct(${index})">
            <div class="name">${product.name}</div>
            <div class="price">${product.price.toFixed(2)} ${CONFIG.currency}</div>
        </div>
    `).join('');
}

function addQuickProduct(index) {
    const product = quickProducts[index];
    addToCart({
        name: product.name,
        price: product.price,
        qty: 1,
        vat: product.vat
    });
}

// ==================== PRODUCT SEARCH ====================

function searchProduct() {
    const barcode = document.getElementById('barcodeInput').value.trim();

    if (!barcode) return;

    // In real implementation, search in database
    // For demo, check if it's a number (treat as price for quick entry)
    if (!isNaN(barcode)) {
        addToCart({
            name: 'Termek',
            price: parseFloat(barcode),
            qty: 1,
            vat: 1
        });
    } else {
        // Treat as product name
        addToCart({
            name: barcode,
            price: 0,
            qty: 1,
            vat: 1
        });
        setStatus('Adj meg arat!');
    }

    document.getElementById('barcodeInput').value = '';
    document.getElementById('barcodeInput').focus();
}

function addManualProduct() {
    const name = document.getElementById('productName').value.trim();
    const price = parseFloat(document.getElementById('productPrice').value) || 0;
    const qty = parseFloat(document.getElementById('productQty').value) || 1;
    const vat = parseInt(document.getElementById('productVat').value) || 1;

    if (!name) {
        setStatus('Add meg a termek nevet!');
        return;
    }

    if (price <= 0) {
        setStatus('Add meg az arat!');
        return;
    }

    addToCart({ name, price, qty, vat });

    // Clear inputs
    document.getElementById('productName').value = '';
    document.getElementById('productPrice').value = '';
    document.getElementById('productQty').value = '1';
    document.getElementById('barcodeInput').focus();
}

// ==================== CART MANAGEMENT ====================

function addToCart(item) {
    // Check if item already exists (same name and price)
    const existingIndex = cart.findIndex(i =>
        i.name === item.name && i.price === item.price && i.vat === item.vat
    );

    if (existingIndex >= 0) {
        cart[existingIndex].qty += item.qty;
    } else {
        cart.push({
            id: Date.now(),
            name: item.name,
            price: item.price,
            qty: item.qty,
            vat: item.vat
        });
    }

    renderCart();
    setStatus(`${item.name} hozzaadva`);
}

function removeFromCart(id) {
    cart = cart.filter(item => item.id !== id);
    renderCart();
}

function clearCart() {
    if (cart.length === 0) return;

    if (confirm('Biztosan uriteni akarod a kosarat?')) {
        cart = [];
        appliedDiscount = 0;
        renderCart();
        setStatus('Kosar uriteve');
    }
}

function renderCart() {
    const container = document.getElementById('cartItems');

    if (cart.length === 0) {
        container.innerHTML = '<div class="cart-empty">A kosar ures</div>';
    } else {
        container.innerHTML = cart.map(item => `
            <div class="cart-item">
                <div class="cart-item-info">
                    <div class="cart-item-name">${item.name}</div>
                    <div class="cart-item-details">
                        ${item.qty} x ${item.price.toFixed(2)} ${CONFIG.currency}
                        (${getVatLabel(item.vat)})
                    </div>
                </div>
                <div class="cart-item-price">${(item.qty * item.price).toFixed(2)}</div>
                <button class="cart-item-remove" onclick="removeFromCart(${item.id})">X</button>
            </div>
        `).join('');
    }

    updateTotals();
}

function getVatLabel(vat) {
    const labels = { 1: '19%', 2: '9%', 3: '5%', 4: '0%' };
    return labels[vat] || '19%';
}

function updateTotals() {
    const subtotal = cart.reduce((sum, item) => sum + (item.qty * item.price), 0);
    const discountAmount = subtotal * (appliedDiscount / 100);
    const grandTotal = subtotal - discountAmount;

    document.getElementById('subtotal').textContent = `${subtotal.toFixed(2)} ${CONFIG.currency}`;
    document.getElementById('grandTotal').textContent = `${grandTotal.toFixed(2)} ${CONFIG.currency}`;

    const discountRow = document.getElementById('discountRow');
    if (appliedDiscount > 0) {
        discountRow.style.display = 'flex';
        document.getElementById('discountPercent').textContent = appliedDiscount;
        document.getElementById('discountAmount').textContent = `-${discountAmount.toFixed(2)} ${CONFIG.currency}`;
    } else {
        discountRow.style.display = 'none';
    }
}

function getGrandTotal() {
    const subtotal = cart.reduce((sum, item) => sum + (item.qty * item.price), 0);
    return subtotal * (1 - appliedDiscount / 100);
}

// ==================== PUNKTEPASS MEMBER ====================

function lookupMember() {
    const memberId = document.getElementById('memberIdInput').value.trim();

    if (!memberId) {
        setStatus('Add meg a PunktePass ID-t vagy telefont!');
        return;
    }

    // Demo lookup
    const member = demoMembers[memberId];

    if (member) {
        currentMember = member;
        showMemberInfo(member);
        setStatus(`Tag megtalálva: ${member.name}`);
    } else {
        setStatus('Tag nem talalhato!');
        // In real implementation, call PunktePass API
    }
}

function showMemberInfo(member) {
    document.getElementById('memberInfo').style.display = 'block';
    document.getElementById('memberName').textContent = member.name;
    document.getElementById('memberPoints').textContent = member.points.toLocaleString();
    document.getElementById('memberLevel').textContent = member.level;
    document.getElementById('memberDiscount').textContent = member.discount + '%';
    document.getElementById('applyDiscountPercent').value = member.discount;
}

function clearMember() {
    currentMember = null;
    appliedDiscount = 0;
    document.getElementById('memberInfo').style.display = 'none';
    document.getElementById('memberIdInput').value = '';
    renderCart();
    setStatus('Tag torolve');
}

function applyMemberDiscount() {
    if (!currentMember) {
        setStatus('Elobb keress egy tagot!');
        return;
    }

    const discount = parseFloat(document.getElementById('applyDiscountPercent').value) || 0;

    if (discount < 0 || discount > currentMember.discount) {
        setStatus(`Maximum kedvezmeny: ${currentMember.discount}%`);
        return;
    }

    appliedDiscount = discount;
    renderCart();
    setStatus(`${discount}% kedvezmeny alkalmazva`);
}

// ==================== PAYMENT ====================

function processPayment(paymentType) {
    if (cart.length === 0) {
        setStatus('A kosar ures!');
        return;
    }

    pendingPaymentType = paymentType;
    const total = getGrandTotal();

    document.getElementById('modalTotal').textContent = `${total.toFixed(2)} ${CONFIG.currency}`;
    document.getElementById('receivedAmount').value = '';
    document.getElementById('changeAmount').textContent = `0.00 ${CONFIG.currency}`;
    document.getElementById('paymentModal').style.display = 'flex';

    // For card payment, auto-fill exact amount
    if (paymentType === 1) {
        document.getElementById('receivedAmount').value = total.toFixed(2);
        calculateChange();
    }

    setTimeout(() => {
        document.getElementById('receivedAmount').focus();
    }, 100);
}

function calculateChange() {
    const total = getGrandTotal();
    const received = parseFloat(document.getElementById('receivedAmount').value) || 0;
    const change = received - total;

    document.getElementById('changeAmount').textContent =
        `${Math.max(0, change).toFixed(2)} ${CONFIG.currency}`;
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
    document.getElementById('barcodeInput').focus();
}

async function confirmPayment() {
    const total = getGrandTotal();
    const received = parseFloat(document.getElementById('receivedAmount').value) || 0;

    if (received < total) {
        setStatus('Nem eleg a kapott osszeg!');
        return;
    }

    closePaymentModal();
    setStatus('Fizetes folyamatban...');

    if (CONFIG.demoMode) {
        // Simulate receipt printing
        await simulatePrint();
    } else {
        // Real Datecs printing
        await printReceipt();
    }
}

async function simulatePrint() {
    setStatus('Nyugta nyomtatasa... (Demo)');

    // Simulate delay
    await new Promise(resolve => setTimeout(resolve, 1500));

    console.log('=== NYUGTA ===');
    console.log('PunktePass Kassza');
    console.log('---------------');
    cart.forEach(item => {
        console.log(`${item.name} x${item.qty} = ${(item.qty * item.price).toFixed(2)}`);
    });
    if (appliedDiscount > 0) {
        console.log(`Kedvezmeny: -${appliedDiscount}%`);
    }
    console.log('---------------');
    console.log(`VEGOSSZEG: ${getGrandTotal().toFixed(2)} ${CONFIG.currency}`);
    if (currentMember) {
        console.log(`PunktePass: ${currentMember.name} (${currentMember.id})`);
    }
    console.log('===============');

    // Clear cart after successful payment
    cart = [];
    appliedDiscount = 0;
    currentMember = null;
    document.getElementById('memberInfo').style.display = 'none';
    document.getElementById('memberIdInput').value = '';
    renderCart();

    setStatus('Fizetes sikeres! Nyugta kinyomtatva.');
}

async function printReceipt() {
    try {
        const result = await datecs.processSale({
            items: cart.map(item => ({
                name: item.name,
                price: item.price,
                qty: item.qty,
                vat: item.vat
            })),
            punkteDiscount: appliedDiscount,
            paymentType: pendingPaymentType,
            punktePassId: currentMember?.id || ''
        });

        if (result.success) {
            // Clear cart
            cart = [];
            appliedDiscount = 0;
            currentMember = null;
            document.getElementById('memberInfo').style.display = 'none';
            document.getElementById('memberIdInput').value = '';
            renderCart();

            setStatus('Fizetes sikeres!');
        } else {
            setStatus('Hiba: ' + (result.error || 'Ismeretlen hiba'));
        }
    } catch (error) {
        setStatus('Kapcsolati hiba: ' + error.message);
    }
}

// ==================== OTHER ACTIONS ====================

async function voidTransaction() {
    if (cart.length === 0) {
        setStatus('Nincs mit sztornozni');
        return;
    }

    if (!confirm('Biztosan sztornozni akarod a tranzakciot?')) {
        return;
    }

    if (!CONFIG.demoMode) {
        try {
            await datecs.voidReceipt();
        } catch (error) {
            console.error('Void error:', error);
        }
    }

    cart = [];
    appliedDiscount = 0;
    renderCart();
    setStatus('Tranzakcio sztornozva');
}

async function openDrawer() {
    setStatus('Fiok nyitasa...');

    if (CONFIG.demoMode) {
        setStatus('Fiok kinyitva (Demo)');
        return;
    }

    try {
        const result = await datecs.openDrawer();
        setStatus(result.success ? 'Fiok kinyitva' : 'Hiba: ' + result.error);
    } catch (error) {
        setStatus('Hiba: ' + error.message);
    }
}

async function printXReport() {
    if (!confirm('X riport nyomtatasa?')) return;

    setStatus('X riport nyomtatasa...');

    if (CONFIG.demoMode) {
        setStatus('X riport kinyomtatva (Demo)');
        return;
    }

    try {
        const result = await datecs.printXReport();
        setStatus(result.success ? 'X riport kinyomtatva' : 'Hiba: ' + result.error);
    } catch (error) {
        setStatus('Hiba: ' + error.message);
    }
}

async function printZReport() {
    if (!confirm('FIGYELEM! Z riport (napzaras) nyomtatasa?\nEz lezarja a napot!')) return;

    setStatus('Z riport nyomtatasa...');

    if (CONFIG.demoMode) {
        setStatus('Z riport kinyomtatva (Demo)');
        return;
    }

    try {
        const result = await datecs.printZReport();
        setStatus(result.success ? 'Z riport kinyomtatva' : 'Hiba: ' + result.error);
    } catch (error) {
        setStatus('Hiba: ' + error.message);
    }
}

// ==================== UTILITY ====================

function setStatus(message) {
    document.getElementById('statusMessage').textContent = message;
    console.log('[Kassza]', message);
}

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    // F1 - Help
    if (e.key === 'F1') {
        e.preventDefault();
        alert('PunktePass Kassza\n\nF2 - Keszpenz fizetes\nF3 - Kartyas fizetes\nF4 - Fiok nyitas\nF8 - Sztorno\nESC - Megse');
    }

    // F2 - Cash payment
    if (e.key === 'F2') {
        e.preventDefault();
        processPayment(0);
    }

    // F3 - Card payment
    if (e.key === 'F3') {
        e.preventDefault();
        processPayment(1);
    }

    // F4 - Open drawer
    if (e.key === 'F4') {
        e.preventDefault();
        openDrawer();
    }

    // F8 - Void
    if (e.key === 'F8') {
        e.preventDefault();
        voidTransaction();
    }

    // ESC - Close modal
    if (e.key === 'Escape') {
        closePaymentModal();
    }

    // Enter in payment modal
    if (e.key === 'Enter' && document.getElementById('paymentModal').style.display === 'flex') {
        confirmPayment();
    }
});
