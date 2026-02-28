<?php
// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options
delete_option( 'wc_payment_fee_settings' );
delete_option( 'wc_payment_fee_auto_updates_enabled' );
delete_transient( 'wc_payment_fee_github_release' );
?>
