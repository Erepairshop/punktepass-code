/**
 * PunktePass Debug Logger
 * Production-safe logging utility
 *
 * Usage:
 *   ppvLog('User logged in', { userId: 123 });
 *   ppvLog('Error occurred', error, 'error');
 *
 * In production (PPV_DEBUG=false): No console output
 * In development (PPV_DEBUG=true): Full console logging
 */

(function(window) {
    'use strict';

    // Check debug mode from PHP (window.PPV_DEBUG) or localStorage
    const isDebug = window.PPV_DEBUG === true || localStorage.getItem('ppv_debug') === 'true';

    // Save original console methods for emergency access
    if (!window._ppvConsole) {
        window._ppvConsole = {
            log: console.log.bind(console),
            warn: console.warn.bind(console),
            error: console.error.bind(console),
            debug: console.debug.bind(console),
            info: console.info.bind(console)
        };
    }

    /**
     * Main logging function
     * @param {string} message - Log message
     * @param {*} data - Optional data to log
     * @param {string} level - Log level: 'log', 'warn', 'error', 'info', 'debug'
     */
    window.ppvLog = function(message, data, level) {
        if (!isDebug) return;

        level = level || 'log';
        const timestamp = new Date().toISOString().split('T')[1].split('.')[0];
        const prefix = `[PPV ${timestamp}]`;

        if (data !== undefined) {
            window._ppvConsole[level](`${prefix} ${message}`, data);
        } else {
            window._ppvConsole[level](`${prefix} ${message}`);
        }
    };

    // Shorthand methods
    window.ppvLog.warn = function(message, data) {
        window.ppvLog(message, data, 'warn');
    };

    window.ppvLog.error = function(message, data) {
        window.ppvLog(message, data, 'error');
    };

    window.ppvLog.info = function(message, data) {
        window.ppvLog(message, data, 'info');
    };

    window.ppvLog.debug = function(message, data) {
        window.ppvLog(message, data, 'debug');
    };

    // Enable debug mode from console
    window.ppvDebugEnable = function() {
        localStorage.setItem('ppv_debug', 'true');
        console.log('✅ PPV Debug mode enabled. Reload page to see debug logs.');
    };

    window.ppvDebugDisable = function() {
        localStorage.removeItem('ppv_debug');
        console.log('🔇 PPV Debug mode disabled. Reload page.');
    };

    // Disable native console methods in production (keep warn/error for critical issues)
    if (!isDebug) {
        console.log = function() {};
        console.debug = function() {};
        console.info = function() {};
        // Keep console.warn and console.error for critical production issues
    }

    // Log initialization
    if (isDebug) {
        window._ppvConsole.info('🐛 PPV Debug Mode: ENABLED');
        window._ppvConsole.info('💡 To disable: ppvDebugDisable()');
    }

})(window);
