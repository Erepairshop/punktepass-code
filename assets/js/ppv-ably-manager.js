/**
 * PunktePass - Central Ably Manager
 * Manages a single shared Ably connection for all scripts
 * Reduces connection count from N to 1 per user
 */

(function() {
    'use strict';

    // Singleton instance
    if (window.PPV_ABLY_MANAGER) {
        return;
    }

    class PPVAblyManager {
        constructor() {
            this.instance = null;
            this.channels = {};
            this.subscribers = {};
            this.config = null;
            this.connectionState = 'disconnected';
            this.reconnectAttempts = 0;
            this.maxReconnectAttempts = 5;

        }

        /**
         * Initialize Ably with config
         * @param {object} config - { key: 'ably_key', channel: 'store-123' or 'user-456' }
         */
        init(config) {
            if (!config || !config.key) {
                console.warn('📡 [ABLY_MANAGER] No Ably config provided');
                return false;
            }

            // Already connected with same key
            if (this.instance && this.config && this.config.key === config.key) {
                return true;
            }

            // Close existing connection if different key
            if (this.instance) {
                this.close();
            }

            this.config = config;

            try {
                this.instance = new Ably.Realtime({
                    key: config.key,
                    // Optimize connection
                    disconnectedRetryTimeout: 5000,
                    suspendedRetryTimeout: 10000
                });

                this.setupConnectionHandlers();
                return true;
            } catch (error) {
                console.error('📡 [ABLY_MANAGER] Failed to initialize:', error);
                return false;
            }
        }

        /**
         * Setup connection event handlers
         */
        setupConnectionHandlers() {
            this.instance.connection.on('connected', () => {
                this.connectionState = 'connected';
                this.reconnectAttempts = 0;
                this.notifyStateChange('connected');
            });

            this.instance.connection.on('disconnected', () => {
                this.connectionState = 'disconnected';
                console.warn('📡 [ABLY_MANAGER] Disconnected');
                this.notifyStateChange('disconnected');
            });

            this.instance.connection.on('suspended', () => {
                this.connectionState = 'suspended';
                console.warn('📡 [ABLY_MANAGER] Connection suspended');
                this.notifyStateChange('suspended');
            });

            this.instance.connection.on('failed', () => {
                this.connectionState = 'failed';
                console.error('📡 [ABLY_MANAGER] Connection failed');
                this.notifyStateChange('failed');
            });

            this.instance.connection.on('closed', () => {
                this.connectionState = 'closed';
                this.notifyStateChange('closed');
            });
        }

        /**
         * Subscribe to a channel event
         * @param {string} channelName - Channel name (e.g., 'store-123')
         * @param {string} eventName - Event name (e.g., 'new-scan')
         * @param {function} callback - Callback function
         * @param {string} subscriberId - Unique subscriber ID for cleanup
         */
        subscribe(channelName, eventName, callback, subscriberId = null) {
            if (!this.instance) {
                console.warn('📡 [ABLY_MANAGER] Not initialized, cannot subscribe');
                return false;
            }

            // Get or create channel
            if (!this.channels[channelName]) {
                this.channels[channelName] = this.instance.channels.get(channelName);
            }

            const channel = this.channels[channelName];

            // Store subscriber for cleanup
            const subKey = subscriberId || `${channelName}:${eventName}:${Date.now()}`;
            if (!this.subscribers[subKey]) {
                this.subscribers[subKey] = [];
            }

            // Subscribe to event
            channel.subscribe(eventName, callback);
            this.subscribers[subKey].push({ channel: channelName, event: eventName, callback });

            return subKey;
        }

        /**
         * Unsubscribe from events by subscriber ID
         * @param {string} subscriberId - Subscriber ID returned from subscribe()
         */
        unsubscribe(subscriberId) {
            if (!this.subscribers[subscriberId]) {
                return;
            }

            this.subscribers[subscriberId].forEach(sub => {
                const channel = this.channels[sub.channel];
                if (channel) {
                    channel.unsubscribe(sub.event, sub.callback);
                }
            });

            delete this.subscribers[subscriberId];
        }

        /**
         * Unsubscribe all events for a channel
         * @param {string} channelName - Channel name
         */
        unsubscribeChannel(channelName) {
            // Find and remove all subscribers for this channel
            Object.keys(this.subscribers).forEach(subKey => {
                this.subscribers[subKey] = this.subscribers[subKey].filter(sub => {
                    if (sub.channel === channelName) {
                        const channel = this.channels[channelName];
                        if (channel) {
                            channel.unsubscribe(sub.event, sub.callback);
                        }
                        return false;
                    }
                    return true;
                });

                // Clean up empty subscriber arrays
                if (this.subscribers[subKey].length === 0) {
                    delete this.subscribers[subKey];
                }
            });

            // Detach channel if no more subscribers
            if (this.channels[channelName]) {
                this.channels[channelName].detach();
                delete this.channels[channelName];
            }
        }

        /**
         * Register for connection state changes
         * @param {function} callback - Callback(state)
         */
        onStateChange(callback) {
            if (!this._stateCallbacks) {
                this._stateCallbacks = [];
            }
            this._stateCallbacks.push(callback);
        }

        /**
         * Notify state change callbacks
         */
        notifyStateChange(state) {
            if (this._stateCallbacks) {
                this._stateCallbacks.forEach(cb => cb(state));
            }
        }

        /**
         * Get connection state
         */
        getState() {
            return this.connectionState;
        }

        /**
         * Check if connected
         */
        isConnected() {
            return this.connectionState === 'connected';
        }

        /**
         * Get channel (for direct access if needed)
         */
        getChannel(channelName) {
            if (!this.instance) return null;

            if (!this.channels[channelName]) {
                this.channels[channelName] = this.instance.channels.get(channelName);
            }
            return this.channels[channelName];
        }

        /**
         * Close connection and cleanup
         */
        close() {

            // Unsubscribe all
            Object.keys(this.subscribers).forEach(subKey => {
                this.unsubscribe(subKey);
            });

            // Detach all channels
            Object.keys(this.channels).forEach(channelName => {
                this.channels[channelName].detach();
            });
            this.channels = {};

            // Close connection
            if (this.instance) {
                this.instance.close();
                this.instance = null;
            }

            this.connectionState = 'closed';
            this._stateCallbacks = [];

        }
    }

    // Create singleton instance
    window.PPV_ABLY_MANAGER = new PPVAblyManager();

    // Cleanup on Turbo navigation
    document.addEventListener('turbo:before-visit', function() {
        // Don't close - Turbo keeps the page alive
        // Just log that navigation is happening
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (window.PPV_ABLY_MANAGER) {
            window.PPV_ABLY_MANAGER.close();
        }
    });


})();
