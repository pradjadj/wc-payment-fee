<?php
/*
Plugin Name: WC Payment Fee
Description: Adds an additional payment fee calculated from product price + shipping + other fees per payment method.
Version: 1.1
Author: Pradja DJ
Author URI: https://sgnet.co.id
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Payment_Fee_Plugin {

    private static $instance = null;
    private $option_name = 'wc_payment_fee_settings';

    public static function get_instance() {
        if ( self::$instance == null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Add fee on cart calculation with high priority to run after other fees
        add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_payment_fee' ), 99 );

        // Reorder fees to move Payment Fee to the bottom in checkout totals
        add_filter( 'woocommerce_cart_get_fees', array( $this, 'reorder_payment_fee' ), 20, 1 );

        // Add settings link on plugins page
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_settings_link' ) );

        // Register settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Add settings page under WooCommerce menu
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
    }

    public function reorder_payment_fee( $fees ) {
        $payment_fee_key = null;
        foreach ( $fees as $key => $fee ) {
            if ( isset( $fee->name ) && $fee->name === 'Payment Fee' ) {
                $payment_fee_key = $key;
                break;
            }
        }
        if ( $payment_fee_key !== null ) {
            $payment_fee = $fees[ $payment_fee_key ];
            unset( $fees[ $payment_fee_key ] );
            $fees[] = $payment_fee;
        }
        return $fees;
    }

    public function add_payment_fee( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        if ( is_cart() ) {
            return;
        }

        if ( ! WC()->session ) {
            return;
        }

        // Get chosen payment method
        $chosen_payment_method = WC()->session->get( 'chosen_payment_method' );
        if ( ! $chosen_payment_method ) {
            return;
        }

        // Get settings
        $settings = get_option( $this->option_name, array() );

        if ( empty( $settings[ $chosen_payment_method ] ) ) {
            return;
        }

        $method_settings = $settings[ $chosen_payment_method ];

        if ( empty( $method_settings['enabled'] ) ) {
            return;
        }

        // Calculate base amount: (subtotal - coupons) + shipping + other fees except this plugin fee
        $base_amount = 0;

        // Get subtotal after coupons/discounts
        $subtotal = $cart->get_subtotal() + $cart->get_subtotal_tax();
        
        // Get total discount amount (coupons)
        $discount_total = $cart->get_discount_total() + $cart->get_discount_tax();
        
        // Calculate subtotal after coupons
        $subtotal_after_coupons = max( 0, $subtotal - $discount_total );

        // Add subtotal after coupons to base amount
        $base_amount += $subtotal_after_coupons;

        // Shipping total (including taxes)
        $base_amount += $cart->get_shipping_total() + $cart->get_shipping_tax();

        // Other fees except this plugin's fee
        foreach ( $cart->get_fees() as $fee ) {
            if ( isset( $fee->id ) && $fee->id === 'wc_payment_fee' ) {
                continue; // skip this plugin's fee to avoid recursion
            }
            $base_amount += $fee->amount + ( isset( $fee->tax ) ? $fee->tax : 0 );
        }

        // Calculate fee amount
        $fee_amount = 0;
        if ( isset( $method_settings['fee_type'] ) && isset( $method_settings['fee_amount'] ) ) {
            $rounding = ! empty( $method_settings['rounding'] );
            if ( $method_settings['fee_type'] === 'percent' ) {
                // Two-tiered percentage fee calculation
                $first_fee = (float) $method_settings['fee_amount'] / 100 * $base_amount;
                if ( $rounding ) {
                    $first_fee = ceil( $first_fee );
                }
                $new_subtotal = $base_amount + $first_fee;
                $fee_amount = (float) $method_settings['fee_amount'] / 100 * $new_subtotal;
                if ( $rounding ) {
                    $fee_amount = ceil( $fee_amount );
                }
            } else {
                // fixed amount
                $fee_amount = (float) $method_settings['fee_amount'];
                if ( $rounding ) {
                    $fee_amount = ceil( $fee_amount );
                }
            }
        }

        if ( $fee_amount > 0 ) {
            $label = ! empty( $method_settings['fee_label'] ) ? sanitize_text_field( $method_settings['fee_label'] ) : 'Payment Fee';

            $cart->add_fee( $label, $fee_amount, true, '' );
        }
    }

    public function add_settings_link( $links ) {
        $settings_link = '<a href="admin.php?page=wc-payment-fee-settings">Settings</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    public function register_settings() {
        register_setting( 'wc_payment_fee_group', $this->option_name, array( $this, 'sanitize_settings' ) );
    }

    public function sanitize_settings( $input ) {
        if ( ! is_array( $input ) ) {
            return array();
        }
        $sanitized = array();
        foreach ( $input as $method => $settings ) {
            $sanitized[ sanitize_text_field( $method ) ] = array(
                'enabled'    => ! empty( $settings['enabled'] ) ? 1 : 0,
                'fee_type'   => in_array( $settings['fee_type'], array( 'percent', 'fixed' ), true ) ? $settings['fee_type'] : 'fixed',
                'fee_amount' => isset( $settings['fee_amount'] ) ? floatval( $settings['fee_amount'] ) : 0,
                'fee_label'  => isset( $settings['fee_label'] ) ? sanitize_text_field( $settings['fee_label'] ) : '',
                'rounding'   => ! empty( $settings['rounding'] ) ? 1 : 0,
            );
        }
        return $sanitized;
    }

    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            'WC Payment Fee Settings',
            'WC Payment Fee',
            'manage_woocommerce',
            'wc-payment-fee-settings',
            array( $this, 'settings_page_html' )
        );
    }

    public function settings_page_html() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Save settings handled by register_setting

        // Get all payment gateways
        $payment_gateways = WC()->payment_gateways()->payment_gateways();

        // Get saved settings
        $settings = get_option( $this->option_name, array() );
        ?>
        <div class="wrap">
            <h1>WC Payment Fee Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wc_payment_fee_group' );
                do_settings_sections( 'wc_payment_fee_group' );
                ?>
        <table class="form-table" role="presentation">
            <thead>
                <tr>
                    <th>Payment Method</th>
                    <th>Enable Fee</th>
                    <th>Fee Type</th>
                    <th>Fee Amount</th>
                    <th>Fee Label</th>
                    <th>Rounding Up</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $payment_gateways as $gateway_id => $gateway ) :
                $method_settings = isset( $settings[ $gateway_id ] ) ? $settings[ $gateway_id ] : array();
                $enabled = ! empty( $method_settings['enabled'] );
                $fee_type = isset( $method_settings['fee_type'] ) ? $method_settings['fee_type'] : 'fixed';
                $fee_amount = isset( $method_settings['fee_amount'] ) ? $method_settings['fee_amount'] : '';
                $fee_label = isset( $method_settings['fee_label'] ) ? $method_settings['fee_label'] : '';
                $rounding = ! empty( $method_settings['rounding'] );
                ?>
                <tr>
                    <td><?php echo esc_html( $gateway->get_title() ); ?></td>
                    <td><input type="checkbox" name="<?php echo esc_attr( $this->option_name . '[' . esc_attr( $gateway_id ) . '][enabled]' ); ?>" value="1" <?php checked( $enabled, true ); ?>></td>
                    <td>
                        <select name="<?php echo esc_attr( $this->option_name . '[' . esc_attr( $gateway_id ) . '][fee_type]' ); ?>">
                            <option value="fixed" <?php selected( $fee_type, 'fixed' ); ?>>Fixed</option>
                            <option value="percent" <?php selected( $fee_type, 'percent' ); ?>>Percent</option>
                        </select>
                    </td>
                    <td><input type="number" step="0.0001" min="0" name="<?php echo esc_attr( $this->option_name . '[' . esc_attr( $gateway_id ) . '][fee_amount]' ); ?>" value="<?php echo esc_attr( $fee_amount ); ?>"></td>
                    <td><input type="text" name="<?php echo esc_attr( $this->option_name . '[' . esc_attr( $gateway_id ) . '][fee_label]' ); ?>" value="<?php echo esc_attr( $fee_label ); ?>" placeholder="Payment Fee"></td>
                    <td><input type="checkbox" name="<?php echo esc_attr( $this->option_name . '[' . esc_attr( $gateway_id ) . '][rounding]' ); ?>" value="1" <?php checked( $rounding, true ); ?>></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

WC_Payment_Fee_Plugin::get_instance();

add_action( 'wp_enqueue_scripts', function() {
    if ( is_checkout() && ! is_order_received_page() ) {
        wp_enqueue_script( 'wc-payment-fee-js', plugin_dir_url( __FILE__ ) . 'js/wc-payment-fee.js', array( 'jquery', 'wc-checkout' ), '1.0.0', true );
    }
} );


?>
