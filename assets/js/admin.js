/**
 * Hostaway WP Admin JavaScript
 */
(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        HostawayAdmin.init();
    });
    
    // Main admin object
    window.HostawayAdmin = {
        
        // Initialize all components
        init: function() {
            this.initGalleryUpload();
            this.initSyncButtons();
            this.initTestButtons();
        },
        
        // Initialize gallery upload
        initGalleryUpload: function() {
            var mediaUploader;
            
            $('#add-gallery-images').on('click', function(e) {
                e.preventDefault();
                
                // If the uploader object has already been created, reopen the dialog
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                
                // Create the media frame
                mediaUploader = wp.media({
                    title: hostawayAdmin.strings.selectImages || 'Select Images',
                    button: {
                        text: hostawayAdmin.strings.selectImages || 'Select Images'
                    },
                    multiple: true,
                    library: {
                        type: 'image'
                    }
                });
                
                // When files are selected
                mediaUploader.on('select', function() {
                    var attachments = mediaUploader.state().get('selection').toJSON();
                    var galleryIds = $('#gallery_ids').val();
                    var currentIds = galleryIds ? galleryIds.split(',') : [];
                    
                    attachments.forEach(function(attachment) {
                        if (currentIds.indexOf(attachment.id.toString()) === -1) {
                            currentIds.push(attachment.id);
                            
                            // Add to preview
                            var galleryItem = $('<div class="gallery-item" data-id="' + attachment.id + '">' +
                                '<img src="' + attachment.sizes.thumbnail.url + '" alt="' + attachment.alt + '">' +
                                '<button type="button" class="remove-gallery-item">×</button>' +
                                '</div>');
                            
                            $('.gallery-preview').append(galleryItem);
                        }
                    });
                    
                    // Update hidden field
                    $('#gallery_ids').val(currentIds.join(','));
                });
                
                // Open the uploader dialog
                mediaUploader.open();
            });
            
            // Remove gallery item
            $(document).on('click', '.remove-gallery-item', function() {
                var $item = $(this).closest('.gallery-item');
                var id = $item.data('id');
                var galleryIds = $('#gallery_ids').val();
                var currentIds = galleryIds ? galleryIds.split(',') : [];
                
                // Remove from array
                currentIds = currentIds.filter(function(currentId) {
                    return currentId !== id.toString();
                });
                
                // Update hidden field
                $('#gallery_ids').val(currentIds.join(','));
                
                // Remove from preview
                $item.remove();
            });
        },
        
        // Initialize sync buttons
        initSyncButtons: function() {
            $('.sync-single-property').on('click', function() {
                var $button = $(this);
                var propertyId = $button.data('property-id');
                var originalText = $button.text();
                
                $button.prop('disabled', true).text(hostawayAdmin.strings.syncing || 'Syncing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hostaway_sync_single_property',
                        nonce: hostawayAdmin.nonce,
                        property_id: propertyId
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            $button.after('<div class="hostaway-notice success">' + 
                                (response.data.message || hostawayAdmin.strings.syncSuccess || 'Sync completed successfully') + 
                                '</div>');
                            
                            // Remove notice after 3 seconds
                            setTimeout(function() {
                                $button.next('.hostaway-notice').fadeOut();
                            }, 3000);
                        } else {
                            // Show error message
                            $button.after('<div class="hostaway-notice error">' + 
                                (response.data.message || hostawayAdmin.strings.syncError || 'Sync failed') + 
                                '</div>');
                        }
                    },
                    error: function() {
                        $button.after('<div class="hostaway-notice error">' + 
                            (hostawayAdmin.strings.syncError || 'Sync failed') + 
                            '</div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text(originalText);
                    }
                });
            });
        },
        
        // Initialize test buttons
        initTestButtons: function() {
            // Test API connection
            $('#test-api-connection').on('click', function() {
                var $button = $(this);
                var $result = $('#api-test-result');
                var originalText = $button.text();
                
                $button.prop('disabled', true).text(hostawayAdmin.strings.testing || 'Testing...');
                $result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hostaway_test_api',
                        nonce: hostawayAdmin.nonce,
                        api_key: $('#hostaway_api_key').val(),
                        api_secret: $('#hostaway_api_secret').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<div class="hostaway-test-result success">✓ ' + 
                                (response.data.message || hostawayAdmin.strings.testSuccess || 'Connection successful') + 
                                '</div>');
                        } else {
                            $result.html('<div class="hostaway-test-result error">✗ ' + 
                                (response.data.message || hostawayAdmin.strings.testError || 'Connection failed') + 
                                '</div>');
                        }
                    },
                    error: function() {
                        $result.html('<div class="hostaway-test-result error">✗ ' + 
                            (hostawayAdmin.strings.testError || 'Connection failed') + 
                            '</div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text(originalText);
                    }
                });
            });
            
            // Manual sync
            $('#manual-sync').on('click', function() {
                var $button = $(this);
                var $result = $('#sync-result');
                var originalText = $button.text();
                
                $button.prop('disabled', true).text(hostawayAdmin.strings.syncing || 'Syncing...');
                $result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hostaway_sync_now',
                        nonce: hostawayAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<div class="hostaway-test-result success">✓ ' + 
                                (hostawayAdmin.strings.syncSuccess || 'Sync completed successfully') + 
                                '</div>');
                        } else {
                            $result.html('<div class="hostaway-test-result error">✗ ' + 
                                (hostawayAdmin.strings.syncError || 'Sync failed') + 
                                '</div>');
                        }
                    },
                    error: function() {
                        $result.html('<div class="hostaway-test-result error">✗ ' + 
                            (hostawayAdmin.strings.syncError || 'Sync failed') + 
                            '</div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text(originalText);
                    }
                });
            });
        },
        
        // Utility functions
        showNotice: function(message, type) {
            type = type || 'info';
            
            var notice = $('<div class="hostaway-notice ' + type + '">' + message + '</div>');
            
            // Insert after the first h1 or h2
            $('h1, h2').first().after(notice);
            
            // Remove notice after 5 seconds
            setTimeout(function() {
                notice.fadeOut(function() {
                    notice.remove();
                });
            }, 5000);
        },
        
        showLoading: function(element) {
            $(element).addClass('hostaway-loading');
        },
        
        hideLoading: function(element) {
            $(element).removeClass('hostaway-loading');
        }
    };
    
})(jQuery);
