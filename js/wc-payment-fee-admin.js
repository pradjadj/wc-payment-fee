/**
 * WC Payment Fee - Admin Script
 * Handles auto-update toggle functionality
 */

(function( $ ) {
    'use strict';

    $(document).ready(function() {
        // Handle auto-update toggle button click
        $(document).on('click', '.wc-payment-fee-toggle-autoupdate', function(e) {
            e.preventDefault();

            var $button = $(this);
            var nonce = $button.data('nonce');

            // Disable button while processing
            $button.prop('disabled', true).css('opacity', '0.6');

            $.ajax({
                url: wcPaymentFeeAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_payment_fee_toggle_autoupdate',
                    nonce: nonce,
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var $parentRow = $button.closest('tr');

                        // Update button text and class
                        $button.text(data.button_text);
                        
                        // Toggle button class
                        if (data.enabled) {
                            $button
                                .removeClass('wc-payment-fee-enable-autoupdate')
                                .addClass('wc-payment-fee-disable-autoupdate');
                        } else {
                            $button
                                .removeClass('wc-payment-fee-disable-autoupdate')
                                .addClass('wc-payment-fee-enable-autoupdate');
                        }

                        // Show success message
                        var message = data.enabled 
                            ? 'Auto-updates enabled' 
                            : 'Auto-updates disabled';
                        
                        showNotice(message, 'success');
                    } else {
                        showNotice('Error toggling auto-updates', 'error');
                    }
                },
                error: function() {
                    showNotice('Error toggling auto-updates', 'error');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false).css('opacity', '1');
                }
            });
        });

        /**
         * Show admin notice
         */
        function showNotice(message, type) {
            var noticeClass = 'notice-' + (type === 'success' ? 'success' : 'error');
            var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Insert notice at top of page
            $('h1').first().after(notice);

            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                notice.fadeOut(function() {
                    notice.remove();
                });
            }, 3000);
        }
    });

})(jQuery);
