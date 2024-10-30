<?php

/**
 * Plugin Name: IranDargah Payment Gateway for Give
 * Plugin URI: https://irandargah.com/plugins/Wordpress/
 * Description: این افزونه، پرداخت آنلاین <a href="https://irandargah.com">ایران درگاه</a> را برای افزونه‌ی Give فعال می‌کند.
 * Author: Iran Dargah
 * Version: 1.0.0
 * Author URI: https://irandargah.com
 * Text Domain: irandargah-for-give
 * Domain Path: /languages/

 * @package irandargah-payment-gateway-for-give
 **/

// Exit, if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

function give_irandargah_plugin_links($links)
{
    $settings_url = add_query_arg(
        [
            'post_type' => 'give_forms',
            'page' => 'give-settings',
            'tab' => 'gateways',
            'section' => 'irandargah-settings',
        ],
        admin_url('edit.php')
    );

    $plugin_links = [
        '<a href="' . esc_url($settings_url) . '">' . __('Settings', 'irandargah-for-give') . '</a>',
        '<a href="https://docs.irandargah.com">' . __('Docs', 'irandargah-for-give') . '</a>',
    ];

    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'give_irandargah_plugin_links');

/**
 * Registers our text domain with WP
 */
function give_irandargah_load_textdomain()
{
    load_plugin_textdomain('irandargah-for-give', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'give_irandargah_load_textdomain');

/**
 * Register payment method.
 *
 * @since 1.0.0
 *
 * @param array $gateways List of registered gateways.
 *
 * @return array
 */
function irandargah_for_give_register_payment_method($gateways)
{
    $gateways['irandargah'] = [
        'admin_label'    => __('Iran Dargah', 'irandargah-for-give'), // This label will be displayed under Give settings in admin.
        'checkout_label' => __('Iran Dargah', 'irandargah-for-give'), // This label will be displayed on donation form in frontend.
    ];

    return $gateways;
}
add_filter('give_payment_gateways', 'irandargah_for_give_register_payment_method');

/**
 * IranDargah Gateway form output
 *
 * IranDargah Gateway does not use a CC form, but it does display a note to the user.
 *
 * @return bool
 **/
function irandargah_for_give_form_output()
{
    printf(
        '
        <fieldset class="no-fields">
            <div style="display: flex; justify-content: center; margin-top: 20px;">
               %4$s
            </div>
            <p style="text-align: center;"><b>%1$s</b></p>
            <p style="text-align: center;"><b>%2$s</b> %3$s</p>
        </fieldset>
        ',
        esc_html__('Make your donation quickly and securely with IranDargah', 'irandargah-for-give'),
        esc_html__('How it works:', 'irandargah-for-give'),
        esc_html__(
            'You will be redirected to IranDargah to complete your donation with your debit card account. Once complete, you will be redirected back to this site to view your receipt.',
            'irandargah-for-give'
        ),
        file_get_contents(plugins_url('/assets/images/irandargah-logo.svg', __FILE__))
    );
    return true;
}
add_action('give_irandargah_cc_form', 'irandargah_for_give_form_output');

/**
 * Register Section for Payment Gateway Settings.
 *
 * @param array $sections List of payment gateway sections.
 *
 * @since 1.0.0
 *
 * @return array
 */
function irandargah_for_give_register_payment_gateway_sections($sections)
{
    $sections['irandargah-settings'] = __('Iran Dargah', 'irandargah-for-give');

    return $sections;
}
add_filter('give_get_sections_gateways', 'irandargah_for_give_register_payment_gateway_sections');

/**
 * Register Admin Settings.
 *
 * @param array $settings List of admin settings.
 *
 * @since 1.0.0
 *
 * @return array
 */
function irandargah_for_give_register_payment_gateway_setting_fields($settings)
{
    switch (give_get_current_setting_section()) {

        case 'irandargah-settings':
            $settings = [
                [
                    'id'   => 'give_title_irandargah',
                    'type' => 'title',
                ],
            ];

            $settings[] = [
                'name' => __('Merchant ID', 'irandargah-for-give'),
                'desc' => __('Enter your Merchant ID.', 'irandargah-for-give'),
                'id'   => 'irandargah_for_give_merchant_id',
                'type' => 'api_key',
            ];

            $settings[] = [
                'id'   => 'give_title_irandargah',
                'type' => 'sectionend',
            ];

            break;
    } // End switch().

    return $settings;
}
add_filter('give_get_settings_gateways', 'irandargah_for_give_register_payment_gateway_setting_fields');

/**
 * Process IranDargah checkout submission.
 *
 * @param array $purchase_data List of purchase data.
 *
 * @since  1.0.0
 * @access public
 *
 * @return void
 */
function irandargah_for_give_process_payment($purchase_data)
{

    // Make sure we don't have any left over errors present.
    give_clear_errors();

    // Any errors?
    $errors = give_get_errors();

    // No errors, proceed.
    if (!$errors) {

        $donation_amount = !empty($purchase_data['price']) ? $purchase_data['price'] : 0;
        $currency        = give_get_currency($purchase_data['post_data']['give-form-id'], $purchase_data);

        // Setup the payment details.
        $donation_data = [
            'price'           => $donation_amount,
            'give_form_title' => $purchase_data['post_data']['give-form-title'],
            'give_form_id'    => intval($purchase_data['post_data']['give-form-id']),
            'give_price_id'   => isset($purchase_data['post_data']['give-price-id']) ? $purchase_data['post_data']['give-price-id'] : 0,
            'date'            => $purchase_data['date'],
            'user_email'      => $purchase_data['user_email'],
            'purchase_key'    => $purchase_data['purchase_key'],
            'currency'        => $currency,
            'user_info'       => $purchase_data['user_info'],
            'status'          => 'pending',
            'gateway'         => 'irandargah',
        ];

        // Record the pending donation.
        $donation_id = give_insert_payment($donation_data);

        if (!$donation_id) {

            // Record Gateway Error as Pending Donation in Give is not created.
            give_record_gateway_error(
                __('IranDargah Error', 'irandargah-for-give'),
                sprintf(
                    __('Unable to create a pending donation with Give.', 'irandargah-for-give')
                )
            );

            // Send user back to checkout.
            give_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['give-gateway']);
            return;
        }

        $callbackURL = add_query_arg(
            [
                'payment-confirmation' => 'irandargah',
                'payment-id'           => $donation_id,
            ],
            home_url('index.php')
        );

        $data = [
            'merchantID'  => give_is_test_mode() ? 'TEST' : give_get_option('irandargah_for_give_merchant_id'),
            'amount'      => $donation_amount * ($currency == 'IRT' ? 10 : 1),
            'callbackURL' => $callbackURL,
            'orderId'     => $donation_id,
            'description' => 'پرداخت از GiveWP برای فرم ' . $purchase_data['post_data']['give-form-title']
        ];

        $action   = give_is_test_mode() ? 'sandbox/payment' : 'payment';
        $response = idpg_give_send_request($action, $data);
        $result   = json_decode(wp_remote_retrieve_body($response));

        if (is_wp_error($response)) {
            give_insert_payment_note($donation_id, sprintf(
                __('Unable to Connect to the Gateway [error: %s]', 'irandargah-for-give'),
                $response->get_error_message()
            ));
            give_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['give-gateway']);
            return;
        } else if (empty($result) or empty($result->authority)) {
            give_insert_payment_note($donation_id, sprintf(
                __('Unable to Connect to the IranDargah [message: %s]', 'irandargah-for-give'),
                $result->message
            ));
            give_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['give-gateway']);
            return;
        } else {
            $payment_URL = give_is_test_mode() ? 'https://dargaah.com/sandbox/ird/startpay/' : 'https://dargaah.com/ird/startpay/';
            wp_redirect($payment_URL . $result->authority);
            exit();
        }
    } else {
        // Send user back to checkout.
        give_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['give-gateway']);
    } // End if().
}
add_action('give_gateway_irandargah', 'irandargah_for_give_process_payment');

/**
 * Process IranDargah callback request.
 *
 * @since  1.0.0
 * @access public
 *
 * @return void
 */
function idpg_give_callback()
{

    if (!isset($_REQUEST['payment-confirmation']) && sanitize_text_field($_REQUEST['payment-confirmation']) != 'irandargah') {
        return;
    }

    if (
        !isset($_REQUEST['code']) || empty($_REQUEST['code']) ||
        !isset($_REQUEST['authority']) || empty($_REQUEST['authority']) ||
        !isset($_REQUEST['amount']) || empty($_REQUEST['amount']) ||
        !isset($_REQUEST['pan']) || empty($_REQUEST['pan']) ||
        !isset($_REQUEST['orderId']) || empty($_REQUEST['orderId'])
    ) {
        give_update_payment_status($_REQUEST['payment-id'], 'failed');
        wp_redirect(give_get_failed_transaction_uri());
        return;
    }

    $payment_id = sanitize_text_field($_REQUEST['orderId']);
    $amount     = sanitize_text_field($_REQUEST['amount']);
    $authority  = sanitize_text_field($_REQUEST['authority']);
    $code       = sanitize_text_field($_REQUEST['code']);
    $message    = sanitize_text_field($_REQUEST['message']);

    if (give_get_payment_status($payment_id) == 'failed' || give_get_payment_status($payment_id) == 'cancelled') {
        give_insert_payment_note($payment_id, sprintf(
            __('Payment Failed. The error is %s.', 'irandargah-for-give'),
            $message
        ));
        give_update_payment_status($payment_id, 'failed');
        wp_redirect(give_get_failed_transaction_uri());
        return;
    }

    if (give_check_for_existing_payment($payment_id)) {
        give_insert_payment_note($payment_id, sprintf(
            __('Payment has been completed before.', 'irandargah-for-give')
        ));
        give_update_payment_status($payment_id, 'failed');
        wp_redirect(give_get_failed_transaction_uri());
        return;
    }

    $payment = give_get_payment_by('id', $payment_id);

    if (empty($payment)) {
        give_insert_payment_note($payment_id, sprintf(
            __('Payment not found.', 'irandargah-for-give')
        ));
        give_update_payment_status($payment_id, 'failed');
        wp_redirect(give_get_failed_transaction_uri());
        return;
    }

    if ($payment->gateway !== 'irandargah') {
        give_insert_payment_note($payment_id, sprintf(
            __('Payment gateway is not equal to the gateway in the database.', 'irandargah-for-give')
        ));
        give_update_payment_status($payment_id, 'failed');
        wp_redirect(give_get_failed_transaction_uri());
        return;
    }

    if (intval($payment->total) !== ($amount / ($payment->currency == 'IRT' ? 10 : 1))) {
        give_insert_payment_note($payment_id, sprintf(
            __('Payment amount is not equal to the amount in the database.', 'irandargah-for-give')
        ));
        give_update_payment_status($payment_id, 'failed');
        wp_redirect(give_get_failed_transaction_uri());
        return;
    }

    if ($code == "100") {
        $data = [
            'merchantID' => give_is_test_mode() ? 'TEST' : give_get_option('irandargah_for_give_merchant_id'),
            'authority' => $authority,
            'amount' => $amount,
            'orderId' => $payment_id,
        ];

        $action   = give_is_test_mode() ? 'sandbox/verification' : 'verification';
        $response = idpg_give_send_request($action, $data);
        $result   = json_decode(wp_remote_retrieve_body($response));

        if ($result->status == 100) {
            give_set_payment_transaction_id($result->orderId, $result->refId);
            give_update_payment_status($result->orderId, 'publish');
            give_insert_payment_note($result->orderId, sprintf(__('IranDargah Payment Completed. The Transaction Id is %s.', 'irandargah-for-give'), $result->refId));
            give_send_to_success_page();
        } else {
            give_update_payment_status($result->orderId, 'failed');
            give_insert_payment_note($result->orderId, sprintf(__('Transaction failed. Status: %s', 'irandargah-for-give'), $result->message));
            wp_redirect(give_get_failed_transaction_uri());
        }
    } else {
        give_update_payment_status($payment_id, 'failed');
        give_insert_payment_note($payment_id, $message);
        wp_redirect(give_get_failed_transaction_uri());
    }
}
add_action('wp_head', 'idpg_give_callback');


function idpg_give_send_request($action, $data)
{
    try {
        $i = 10;
        $response = null;
        while ($i > 0) {
            $response = wp_remote_post('https://dargaah.com/' . $action, array(
                'body' => json_encode($data),
                'timeout' => '10',
                'sslverify' => false,
                'headers' => ['Content-Type' => 'application/json'],
            ));
            if (is_wp_error($response)) {
                $i--;
                continue;
            } else {
                break;
            }
        }
        return $response;
    } catch (Exception $ex) {
        return false;
    }
}