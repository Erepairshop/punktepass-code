/**
 * PunktePass Profile Lite - Media Module
 * Contains: File upload, gallery management, image preview
 * Depends on: pp-profile-core.js
 */
(function() {
    'use strict';

    // Guard against multiple loads
    if (window.PPV_PROFILE_MEDIA_LOADED) return;
    window.PPV_PROFILE_MEDIA_LOADED = true;

    const { STATE, t, showAlert } = window.PPV_PROFILE || {};

    // ============================================================
    // MEDIA MANAGER CLASS
    // ============================================================
    class MediaManager {
        constructor(form) {
            this.$form = form;
            this.maxFileSize = 4 * 1024 * 1024; // 4MB
            this.allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        }

        /**
         * Bind file input change events
         */
        bindFileInputs() {
            if (!this.$form) return;

            this.$form.querySelectorAll('.ppv-file-input').forEach(input => {
                input.addEventListener('change', (e) => this.handleFileUpload(e));
            });
        }

        /**
         * Bind gallery delete buttons
         */
        bindGalleryDelete() {
            document.querySelectorAll('.ppv-gallery-delete-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const imageUrl = e.target.dataset.imageUrl;
                    if (imageUrl) {
                        this.deleteGalleryImage(imageUrl);
                    }
                });
            });
        }

        /**
         * Handle file upload with validation and preview
         */
        handleFileUpload(e) {
            const input = e.target;
            const files = input.files;

            if (!files.length) return;

            // Validate all files
            for (let file of files) {
                if (file.size > this.maxFileSize) {
                    showAlert(t('file_too_large') || 'File too large (max 4MB)', 'error');
                    input.value = '';
                    return;
                }

                if (!this.allowedTypes.includes(file.type)) {
                    showAlert(t('invalid_file_type') || 'Invalid file type (JPG, PNG, WebP only)', 'error');
                    input.value = '';
                    return;
                }
            }

            // Generate previews
            for (let file of files) {
                this.generatePreview(file, input);
            }
        }

        /**
         * Generate image preview
         */
        generatePreview(file, input) {
            const reader = new FileReader();

            reader.onload = (ev) => {
                const preview = document.createElement('img');
                preview.src = ev.target.result;
                preview.style.maxWidth = '100%';
                preview.style.borderRadius = '8px';

                const container = input.closest('.ppv-media-group')?.querySelector('[id*="preview"]');
                if (container) {
                    const isGallery = container.id === 'ppv-gallery-preview';

                    if (isGallery) {
                        // Gallery: append multiple images
                        if (!container.style.gridTemplateColumns) {
                            container.style.display = 'grid';
                            container.style.gridTemplateColumns = 'repeat(auto-fill, minmax(100px, 1fr))';
                            container.style.gap = '10px';
                            container.style.marginTop = '10px';
                        }
                        container.appendChild(preview);
                    } else {
                        // Single image: replace
                        container.innerHTML = '';
                        container.appendChild(preview);
                    }
                }
            };

            reader.readAsDataURL(file);
        }

        /**
         * Delete gallery image via AJAX
         */
        async deleteGalleryImage(imageUrl) {
            if (!confirm(t('delete_image_confirm') || 'Delete this image?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'ppv_delete_gallery_image');
            formData.append('ppv_nonce', STATE.nonce);
            formData.append('store_id', this.$form.querySelector('[name="store_id"]')?.value);
            formData.append('image_url', imageUrl);

            try {
                const response = await fetch(STATE.ajaxUrl + '?action=ppv_delete_gallery_image', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showAlert(t('image_deleted') || 'Image deleted!', 'success');
                    location.reload();
                } else {
                    showAlert(data.data?.msg || t('delete_error') || 'Error deleting image', 'error');
                }
            } catch (err) {
                console.error('[Profile] Delete gallery image error:', err);
                showAlert(t('delete_error') || 'Error deleting image', 'error');
            }
        }

        /**
         * Delete media (logo/cover) via AJAX
         */
        async deleteMedia(mediaType) {
            if (!confirm(t('delete_media_confirm') || 'Delete this media?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'ppv_delete_media');
            formData.append('ppv_nonce', STATE.nonce);
            formData.append('store_id', this.$form.querySelector('[name="store_id"]')?.value);
            formData.append('media_type', mediaType);

            try {
                const response = await fetch(STATE.ajaxUrl + '?action=ppv_delete_media', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showAlert(t('media_deleted') || 'Media deleted!', 'success');
                    location.reload();
                } else {
                    showAlert(data.data?.msg || t('delete_error') || 'Error deleting media', 'error');
                }
            } catch (err) {
                console.error('[Profile] Delete media error:', err);
                showAlert(t('delete_error') || 'Error deleting media', 'error');
            }
        }
    }

    // ============================================================
    // EXPORT TO GLOBAL
    // ============================================================
    window.PPV_PROFILE = window.PPV_PROFILE || {};
    window.PPV_PROFILE.MediaManager = MediaManager;

    console.log('[Profile-Media] Module loaded v3.0');

})();
