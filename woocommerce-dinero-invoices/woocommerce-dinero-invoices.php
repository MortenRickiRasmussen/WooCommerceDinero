<?php
/*
Plugin Name: WooCommerce Dinero Faktura
Plugin URI:
Description: Oprettelse og afsendelse af faktura fra Dinero i forbindelse med gennemført køb
Author: Hexio
Author URI: https://hexio.dk
Version: 1.0.0
*/

//Checks for X Pro and cancels install if not found.
add_action('admin_init', 'woocommerce_dinero_invoices_requires_pro');
function woocommerce_dinero_invoices_requires_pro() {

    if (is_admin() && current_user_can('activate_plugins') && !is_plugin_active('woocommerce/woocommerce.php')) {
        add_action('admin_notices', 'woocommerce_dinero_invoices_requires_pro_notice');

        deactivate_plugins(plugin_basename(__FILE__));

        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }
}

function woocommerce_dinero_invoices_requires_pro_notice() {
    echo '<div class="error"><p>WooCommerce Dinero Faktura kræver at WooCommerce er installeret og aktiveret</p></div>';
}

function woocommerce_dinero_invoices_add_settings_page() {
    add_options_page( 'WooCommerce Dinero Plugin siden', 'WooCommerce Dinero Plugin Menu', 'manage_options', 'woocommerce-dinero-plugin', 'woocommerce_dinero_invoices_render_plugin_settings_page' );
}
add_action( 'admin_menu', 'woocommerce_dinero_invoices_add_settings_page' );

function woocommerce_dinero_invoices_render_plugin_settings_page() {
    ?>
    <h2>WooCommerce Dinero Indstillinger</h2>
    <form action="options.php" method="post">
        <?php 
        settings_fields( 'woocommerce_dinero_options' );
        do_settings_sections( 'woocommerce_dinero_plugin' ); ?>
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Gem' ); ?>" />
    </form>
    <?php
}

function woocommerce_dinero_invoices_register_settings() {
    register_setting( 'woocommerce_dinero_options', 'woocommerce_dinero_options' );
    add_settings_section( 'api_settings', 'Dinero API Indstillinger', 'woocommerce_dinero_plugin_section_text', 'woocommerce_dinero_plugin' );

    add_settings_field( 'woocommerce_dinero_plugin_setting_client_id', 'Dinero Client Id', 'woocommerce_dinero_plugin_setting_client_id', 'woocommerce_dinero_plugin', 'api_settings' );
    add_settings_field( 'woocommerce_dinero_plugin_setting_client_secret', 'Dinero Client Secret', 'woocommerce_dinero_plugin_setting_client_secret', 'woocommerce_dinero_plugin', 'api_settings' );
    add_settings_field( 'woocommerce_dinero_plugin_setting_api_key', 'API Nøgle', 'woocommerce_dinero_plugin_setting_api_key', 'woocommerce_dinero_plugin', 'api_settings' );
    add_settings_field( 'woocommerce_dinero_plugin_setting_organization_id', 'Firma ID', 'woocommerce_dinero_plugin_setting_organization_id', 'woocommerce_dinero_plugin', 'api_settings' );
    add_settings_field( 'woocommerce_dinero_plugin_setting_mails_enabled', 'Send faktura på mail', 'woocommerce_dinero_plugin_setting_mails_enabled', 'woocommerce_dinero_plugin', 'api_settings' );
    add_settings_field( 'woocommerce_dinero_plugin_setting_integration_enabled', 'Integrationen er aktiv', 'woocommerce_dinero_plugin_setting_integration_enabled', 'woocommerce_dinero_plugin', 'api_settings' );

}
add_action( 'admin_init', 'woocommerce_dinero_invoices_register_settings' );

function woocommerce_dinero_plugin_setting_client_id() {
    $options = get_option( 'woocommerce_dinero_options' );
    echo "<input id='woocommerce_dinero_plugin_setting_client_id' name='woocommerce_dinero_options[client_id]' type='password' value='" . esc_attr( $options['client_id'] ) . "' />";
}

function woocommerce_dinero_plugin_setting_client_secret() {
    $options = get_option( 'woocommerce_dinero_options' );
    echo "<input id='woocommerce_dinero_plugin_setting_client_secret' name='woocommerce_dinero_options[client_secret]' type='password' value='" . esc_attr( $options['client_secret'] ) . "' />";
}

function woocommerce_dinero_plugin_setting_api_key() {
    $options = get_option( 'woocommerce_dinero_options' );
    echo "<input id='woocommerce_dinero_plugin_setting_api_key' name='woocommerce_dinero_options[api_key]' type='text' value='" . esc_attr( $options['api_key'] ) . "' />";
}

function woocommerce_dinero_plugin_setting_organization_id() {
    $options = get_option( 'woocommerce_dinero_options' );
    echo "<input id='woocommerce_dinero_plugin_setting_organization_id' name='woocommerce_dinero_options[organization_id]' type='text' value='" . esc_attr( $options['organization_id'] ) . "' />";
}

function woocommerce_dinero_plugin_setting_mails_enabled() {
    $options = get_option( 'woocommerce_dinero_options' );
    echo '<input type="checkbox" id="woocommerce_dinero_plugin_setting_mails_enabled" name="woocommerce_dinero_options[mails_enabled]" value="1"' . checked( 1, $options['mails_enabled'], false ) . '/>';

}

function woocommerce_dinero_plugin_setting_integration_enabled() {
    $options = get_option( 'woocommerce_dinero_options' );
    echo '<input type="checkbox" id="woocommerce_dinero_plugin_setting_integration_enabled" name="woocommerce_dinero_options[integration_enabled]" value="1"' . checked( 1, $options['integration_enabled'], false ) . '/>';
}

add_action('woocommerce_order_status_completed', 'woocommerce_dinero_invoices_payment_complete',10,1);
function woocommerce_dinero_invoices_payment_complete( $order_id ){
    $options = get_option( 'woocommerce_dinero_options' );

    if (!$options['integration_enabled']) {
        return;
    }

    $order = wc_get_order( $order_id );

    $accessToken = get_dinero_token();

    $contact = [
        'name' => $order->get_billing_first_name() . $order->get_billing_last_name(),
        'zipCode' => $order->get_billing_postcode(),
        'city' => $order->get_billing_city(),
        'street' => $order->get_billing_address_1(),
        'Email' => $order->get_billing_email(),
        'phone' => $order->get_billing_phone(),
        'countryKey' => 'DK'
    ];

    $dineroContact = do_dinero_call("contacts", $contact, $accessToken);

    $lines = [];

    foreach ( $order->get_items() as $item_id => $item ) {
        array_push($lines, [
            'description' => $item->get_name(),
            'quantity' => $item->get_quantity(),
            'accountNumber' => 1000,
            'unit' => 'Parts',
            'baseAmountValue' => $item->get_subtotal()
        ]);
    }

    $orderDate = date_create($order->get_date_completed()->date);

    $invoice = [
        'contactGuid' => $dineroContact->ContactGuid,
        'date' => date_format($orderDate,"Y-m-d"),
        'externalReference' => $order->get_id(),
        'productLines' => $lines,
        'showLinesInclVat' => true,
        'paymentConditionType' => 'Paid'
    ];

    $dineroInvoice = do_dinero_call("invoices", $invoice, $accessToken);

    $bookInvoice = [
        'guid' => $dineroInvoice->Guid,
        'timestamp' => $dineroInvoice->TimeStamp
    ];

    do_dinero_call("invoices/$dineroInvoice->Guid/book", $bookInvoice, $accessToken);

    if ($options['mails_enabled']) {
        do_dinero_call("invoices/$dineroInvoice->Guid/email", null, $accessToken);
    }
}

function get_dinero_token() {
    $options = get_option( 'woocommerce_dinero_options' );

    $api_key = $options['api_key'];
    $clientId = $options['client_id'];
    $clientSecret = $options['client_secret'];

    $basicAuth = base64_encode("$clientId:$clientSecret");

    $ch = curl_init("https://authz.dinero.dk/dineroapi/oauth/token");

    curl_setopt( $ch, CURLOPT_POST, 1);
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_POSTFIELDS,"grant_type=password&scope=read write&username=$api_key&password=$api_key");
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded', "Authorization: Basic $basicAuth"));

    // grab URL and pass it to the browser
    $result = curl_exec($ch);

    return json_decode($result)->access_token;
}

function do_dinero_call($url, $data, $accessToken, $version = "v1", $method = 'POST') {
    $options = get_option( 'woocommerce_dinero_options' );
    $organization_id = $options['organization_id'];

    $ch = curl_init("https://api.dinero.dk/$version/$organization_id/$url");

    $headers = array("Authorization: Bearer $accessToken");

    if ($method !== "GET") {
        array_push($headers, 'Content-Type:application/json');
    }

    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($data));

    // grab URL and pass it to the browser
    $result = curl_exec($ch);

    return json_decode($result);
}

function post_webhook($data) {
    $ch = curl_init("https://webhook.site/3706dfd7-b4f5-4e72-8f30-8cb307c05671");

    // set URL and other appropriate options
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($data));

    // grab URL and pass it to the browser
    curl_exec($ch);
}