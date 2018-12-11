<?php
/**
 * Shop System Plugins - Terms of Use
 *
 * The plugins offered are provided free of charge by Wirecard AG and are explicitly not part
 * of the Wirecard AG range of products and services.
 *
 * They have been tested and approved for full functionality in the standard configuration
 * (status on delivery) of the corresponding shop system. They are under General Public
 * License version 3 (GPLv3) and can be used, developed and passed on to third parties under
 * the same terms.
 *
 * However, Wirecard AG does not provide any guarantee or accept any liability for any errors
 * occurring when used in an enhanced, customized shop system configuration.
 *
 * Operation in an enhanced, customized configuration is at your own risk and requires a
 * comprehensive test phase by the user of the plugin.
 *
 * Customers use the plugins at their own risk. Wirecard AG does not guarantee their full
 * functionality neither does Wirecard AG assume liability for any disadvantages related to
 * the use of the plugins. Additionally, Wirecard AG does not guarantee the full functionality
 * for customized shop systems or installed plugins of other vendors of plugins within the same
 * shop system.
 *
 * Customers are responsible for testing the plugin's functionality before starting productive
 * operation.
 *
 * By installing the plugin into the shop system the customer agrees to these terms of use.
 * Please do not use the plugin if you do not agree to these terms of use!
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( WIRECARD_EXTENSION_BASEDIR . 'classes/includes/class-wc-wirecard-payment-gateway.php' );

use Wirecard\PaymentSdk\Config\Config;
use Wirecard\PaymentSdk\Config\PaymentMethodConfig;
use Wirecard\PaymentSdk\Entity\Amount;
use Wirecard\PaymentSdk\Transaction\PayolutionInvoiceTransaction;

/**
 * Class WC_Gateway_Wirecard_Payolution_Invoice
 *
 * @extends WC_Wirecard_Payment_Gateway
 *
 * @since   1.4.1
 */
class WC_Gateway_Wirecard_Payolution_Invoice extends WC_Wirecard_Payment_Gateway {

	public function __construct() {
		$this->type               = 'payolution-inv';
		$this->id                 = 'wirecard_ee_payolution-inv';
		$this->icon               = WIRECARD_EXTENSION_URL . 'assets/images/payolution.png';
		$this->method_title       = __( 'heading_title_payolutioninvoice', 'wirecard-woocommerce-extension' );
		$this->method_name        = __( 'payolutioninvoice', 'wirecard-woocommerce-extension' );
		$this->method_description = __( 'payolutioninvoice_desc', 'wirecard-woocommerce-extension' );
		$this->has_fields         = true;

		$this->supports = array(
			'products',
			'refunds',
		);

		$this->cancel         = array( 'authorization' );
		$this->capture        = array( 'authorization' );
		$this->refund         = array( 'capture-authorization' );
		$this->payment_action = 'reserve';

		$this->init_form_fields();
		$this->init_settings();

		$this->title   = $this->get_option( 'title' );
		$this->enabled = $this->get_option( 'enabled' );

		$this->additional_helper = new Additional_Information();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_after_checkout_validation', 'validate', 10, 2 );

		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ), 999 );

		parent::add_payment_gateway_actions();
	}

	/**
	 * Load form fields for configuration
	 *
	 * @since 1.4.1
	 */
	public function init_form_fields() {

		$countries_obj = new WC_Countries();
		$countries     = $countries_obj->__get( 'countries' );

		$this->form_fields = array(
			'enabled'               => array(
				'title'       => __( 'text_enable_disable', 'wirecard-woocommerce-extension' ),
				'type'        => 'checkbox',
				'description' => __( 'config_status_desc_payolutioninvoice', 'wirecard-woocommerce-extension' ),
				'label'       => __( 'enable_heading_title_payolutioninvoice', 'wirecard-woocommerce-extension' ),
				'default'     => 'no',
			),
			'title'                 => array(
				'title'       => __( 'config_title', 'wirecard-woocommerce-extension' ),
				'type'        => 'text',
				'description' => __( 'config_title_desc', 'wirecard-woocommerce-extension' ),
				'default'     => __( 'heading_title_payolutioninvoice', 'wirecard-woocommerce-extension' ),
			),
			'merchant_account_id'   => array(
				'title'       => __( 'config_merchant_account_id', 'wirecard-woocommerce-extension' ),
				'type'        => 'text',
				'description' => __( 'config_merchant_account_id_desc', 'wirecard-woocommerce-extension' ),
				'default'     => '2048677d-57f4-44b0-8d67-9014c6631d5f',
			),
			'secret'                => array(
				'title'       => __( 'config_merchant_secret', 'wirecard-woocommerce-extension' ),
				'type'        => 'text',
				'description' => __( 'config_merchant_secret_desc', 'wirecard-woocommerce-extension' ),
				'default'     => '74bd2f0c-6d1b-4e9a-b278-abc34b83ab9f',
			),
			'credentials'           => array(
				'title'       => __( 'text_credentials', 'wirecard-woocommerce-extension' ),
				'type'        => 'title',
				'description' => __( 'text_credentials_desc', 'wirecard-woocommerce-extension' ),
			),
			'base_url'              => array(
				'title'       => __( 'config_base_url', 'wirecard-woocommerce-extension' ),
				'type'        => 'text',
				'description' => __( 'config_base_url_desc', 'wirecard-woocommerce-extension' ),
				'default'     => 'https://api-test.wirecard.com',
			),
			'http_user'             => array(
				'title'       => __( 'config_http_user', 'wirecard-woocommerce-extension' ),
				'type'        => 'text',
				'description' => __( 'config_http_user_desc', 'wirecard-woocommerce-extension' ),
				'default'     => '16390-testing',
			),
			'http_pass'             => array(
				'title'       => __( 'config_http_password', 'wirecard-woocommerce-extension' ),
				'type'        => 'text',
				'description' => __( 'config_http_password_desc', 'wirecard-woocommerce-extension' ),
				'default'     => '3!3013=D3fD8X7',
			),
			'test_button'           => array(
				'title'   => __( 'test_config', 'wirecard-woocommerce-extension' ),
				'type'    => 'button',
				'class'   => 'wc_wirecard_test_credentials_button button-primary',
				'default' => __( 'test_credentials', 'wirecard-woocommerce-extension' ),
			),
			'advanced'              => array(
				'title'       => __( 'text_advanced', 'wirecard-woocommerce-extension' ),
				'type'        => 'title',
				'description' => '',
			),
			'billing_shipping_same' => array(
				'title'       => __( 'text_enable_disable', 'wirecard-woocommerce-extension' ),
				'type'        => 'checkbox',
				'description' => __( 'config_billing_shipping_desc_payolution', 'wirecard-woocommerce-extension' ),
				'label'       => __( 'config_billing_shipping', 'wirecard-woocommerce-extension' ),
				'default'     => 'yes',
			),
			'billing_countries'     => array(
				'title'          => __( 'config_billing_countries', 'wirecard-woocommerce-extension' ),
				'type'           => 'multiselect',
				'description'    => __( 'config_billing_countries_desc_payolution', 'wirecard-woocommerce-extension' ),
				'options'        => $countries,
				'default'        => array( 'AT', 'DE', 'CH', 'NL' ),
				'multiple'       => true,
				'select_buttons' => true,
				'css'            => 'height: 100px',
			),
			'shipping_countries'    => array(
				'title'          => __( 'config_shipping_countries', 'wirecard-woocommerce-extension' ),
				'type'           => 'multiselect',
				'description'    => __( 'config_shipping_countries_desc_payolution', 'wirecard-woocommerce-extension' ),
				'options'        => $countries,
				'default'        => array( 'AT', 'DE', 'CH', 'NL' ),
				'multiple'       => true,
				'select_buttons' => true,
				'css'            => 'height: 100px',
			),
			'allowed_currencies'    => array(
				'title'          => __( 'config_allowed_currencies', 'wirecard-woocommerce-extension' ),
				'type'           => 'multiselect',
				'description'    => __( 'config_allowed_currencies_desc_payolution', 'wirecard-woocommerce-extension' ),
				'options'        => get_woocommerce_currencies(),
				'default'        => array( 'EUR' ),
				'multiple'       => true,
				'select_buttons' => true,
				'css'            => 'height: 100px',
			),
			'min_amount'            => array(
				'title'       => __( 'config_basket_min', 'wirecard-woocommerce-extension' ),
				'description' => __( 'config_basket_min_desc_payolution', 'wirecard-woocommerce-extension' ),
				'default'     => 25,
			),
			'max_amount'            => array(
				'title'       => __( 'config_basket_max', 'wirecard-woocommerce-extension' ),
				'description' => __( 'config_basket_max_desc_payolution', 'wirecard-woocommerce-extension' ),
				'default'     => 3500,
			),
			'descriptor'            => array(
				'title'       => __( 'text_enable_disable', 'wirecard-woocommerce-extension' ),
				'type'        => 'checkbox',
				'description' => __( 'config_descriptor_desc', 'wirecard-woocommerce-extension' ),
				'label'       => __( 'config_descriptor', 'wirecard-woocommerce-extension' ),
				'default'     => 'no',
			),
			'send_additional'       => array(
				'title'       => __( 'text_enable_disable', 'wirecard-woocommerce-extension' ),
				'type'        => 'checkbox',
				'description' => __( 'config_additional_info_desc', 'wirecard-woocommerce-extension' ),
				'label'       => __( 'config_additional_info', 'wirecard-woocommerce-extension' ),
				'default'     => 'yes',
			),
			'payolution_mid'        => array(
				'title'       => __( 'config_payolution_merchant_id', 'wirecard-woocommerce-extension' ),
				'type'        => 'text',
				'description' => __( 'config_payolution_merchant_id_desc', 'wirecard-woocommerce-extension' ),
			),
		);
	}

	/**
	 * Create payment method configuration
	 *
	 * @param null $base_url
	 * @param null $http_user
	 * @param null $http_pass
	 *
	 * @return Config
	 *
	 * @since 1.4.1
	 */
	public function create_payment_config( $base_url = null, $http_user = null, $http_pass = null ) {

		if ( is_null( $base_url ) ) {
			$base_url  = $this->get_option( 'base_url' );
			$http_user = $this->get_option( 'http_user' );
			$http_pass = $this->get_option( 'http_pass' );
		}

		$config         = parent::create_payment_config( $base_url, $http_user, $http_pass );
		$payment_config = new PaymentMethodConfig( PayolutionInvoiceTransaction::NAME, $this->get_option( 'merchant_account_id' ), $this->get_option( 'secret' ) );
		$config->add( $payment_config );

		return $config;
	}

	/**
	 * Return true if the payment method is available
	 *
	 * @return bool
	 *
	 * @since 1.4.1
	 */
	public function is_available() {

		if ( parent::is_available() ) {
			$customer    = WC()->customer;
			$cart        = WC()->cart;
			$price_total = floatval( $cart->get_total( 'total' ) );

			if ( ! in_array( get_woocommerce_currency(), $this->get_option( 'allowed_currencies' ) ) ||
				! $this->validate_cart_amounts( $price_total ) ||
				! $this->validate_cart_products( $cart ) ||
				! $this->validate_billing_shipping_address( $customer ) ||
				! $this->validate_countries( $customer ) ) {
				return false;
			}

			return true;
		}

		return false;
	}

	/**
	 * Execute payment transaction
	 *
	 * @param int $order_id
	 *
	 * @return array|bool|void
	 *
	 * @since 1.4.1
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		$birth_date_str = sanitize_text_field( $_POST['payolution_date_of_birth'] );
		if ( ! $this->validate_date_of_birth( $birth_date_str ) ) {
			return false;
		}

		$gdpr_allowance = sanitize_text_field( $_POST['payolution_gpdr_agreement'] );
		if ( ! $this->validate_gdpr_allowance_given( $gdpr_allowance ) ) {
			return false;
		}

		$this->transaction = new PayolutionInvoiceTransaction();
		parent::process_payment( $order_id );

		$this->transaction->setAccountHolder(
			$this->additional_helper->create_account_holder(
				$order,
				'billing',
				new DateTime( $birth_date_str )
			)
		);

		return $this->execute_transaction( $this->transaction, $this->config, $this->payment_action, $order );
	}

	/**
	 * Create transaction for cancel
	 *
	 * @param int        $order_id
	 * @param float|null $amount
	 *
	 * @return PayolutionInvoiceTransaction
	 *
	 * @since 1.4.1
	 */
	public function process_cancel( $order_id, $amount = null ) {

		$order  = wc_get_order( $order_id );
		$config = $this->create_payment_config();

		$transaction = new PayolutionInvoiceTransaction();
		$transaction->setParentTransactionId( $order->get_transaction_id() );

		if ( $this->get_option( 'send_additional' ) == 'yes' ) {
			$basket = $this->additional_helper->create_basket_from_parent_transaction(
				$order,
				$config,
				PayolutionInvoiceTransaction::NAME
			);
			$transaction->setBasket( $basket );
		}

		if ( ! is_null( $amount ) ) {
			$transaction->setAmount( new Amount( $amount, $order->get_currency() ) );
		}

		return $transaction;
	}

	/**
	 * Create transaction for capture
	 *
	 * @param int        $order_id
	 * @param float|null $amount
	 *
	 * @return PayolutionInvoiceTransaction
	 *
	 * @since 1.4.1
	 */
	public function process_capture( $order_id, $amount = null ) {

		$order  = wc_get_order( $order_id );
		$config = $this->create_payment_config();

		$transaction = new PayolutionInvoiceTransaction();
		$transaction->setParentTransactionId( $order->get_transaction_id() );

		if ( $this->get_option( 'send_additional' ) == 'yes' ) {
			$basket = $this->additional_helper->create_basket_from_parent_transaction(
				$order,
				$config,
				PayolutionInvoiceTransaction::NAME
			);
			$transaction->setBasket( $basket );
		}

		if ( ! is_null( $amount ) ) {
			$transaction->setAmount( new Amount( $amount, $order->get_currency() ) );
		}

		return $transaction;
	}

	/**
	 * Execute refund transaction
	 *
	 * @param int $order_id
	 * @param float|null $amount
	 * @param string $reason
	 *
	 * @throws Exception
	 *
	 * @return bool|string|WP_Error
	 *
	 * @since 1.4.1
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		$order  = wc_get_order( $order_id );
		$config = $this->create_payment_config();

		$this->transaction = new PayolutionInvoiceTransaction();
		$this->transaction->setParentTransactionId( $order->get_transaction_id() );

		if ( $this->get_option( 'send_additional' ) == 'yes' ) {
			$basket = $this->additional_helper->create_basket_from_parent_transaction(
				$order,
				$config,
				PayolutionInvoiceTransaction::NAME
			);
			$this->transaction->setBasket( $basket );
		}

		if ( ! is_null( $amount ) ) {
			$this->transaction->setAmount( new Amount( $amount, $order->get_currency() ) );
		}

		return $this->execute_refund( $this->transaction, $config, $order );
	}

	/**
	 * Inject the customized scripts in payment page
	 *
	 * @since 1.4.1
	 */
	public function payment_scripts() {
		wp_register_style( 'payment_select_style', WIRECARD_EXTENSION_URL . 'assets/styles/multiple_additional_elements.css', array(), null, false );
	}

	/**
	 * Add additional fields for this payment method while perform checkout
	 *
	 * @since 1.4.1
	 */
	public function payment_fields() {

		wp_enqueue_style( 'payment_select_style' );

		$date_of_birth_label = __( 'Date of birth', 'wirecard-woocommerce-extension' );

		$html = <<<BIRTHDAY_INPUT_FIELD
<p class="form-row form-row-wide">
  <label for="payolution_date_of_birth validate-required" class="">$date_of_birth_label
  <abbr class="required" title="required">*</abbr></label>
  <input class="input-text" name="payolution_date_of_birth" id="payolution_date_of_birth" type="date" />
</p>
BIRTHDAY_INPUT_FIELD;

		$gdpr_agreement_label   = __( 'GPRR allowance', 'wirecard-woocommerce-extension' );
		$gdpr_agreement_link    = 'https://payment.payolution.com/payolution-payment/infoport/dataprivacyconsent?mId=' . base64_encode( $this->get_option( 'payolution_mid' ) );
		$gdpr_agreement_rawtext = __( 'I agree that the data which are necessary for the liquidation of purchase on account and which are used to complete the identity and credit check are transmitted to payolution. <a href="%LINK%" target="_blank">My consent</a> can be revoked at any time with effect for the future.', 'wirecard-woocommerce-extension' );
		$gdpr_agreement_text    = str_replace( '%LINK%', $gdpr_agreement_link, $gdpr_agreement_rawtext );

		$html .= <<<AGREEMENT_CHECKBOX
<p class="form-row form-row-wide validate-required">
   <label for="payolution_gpdr_agreement" class="">$gdpr_agreement_label
   <abbr class="required" title="required">*</abbr></label>
   <input type="checkbox" class="" name="payolution_gpdr_agreement" id="payolution_gpdr_agreement_checkbox" value="1" /> $gdpr_agreement_text
</p>
AGREEMENT_CHECKBOX;

		echo $html;
	}

	/**
	 * Check input is not empty, a valid date and difference to NOW() is at least 18 years
	 *
	 * @param $date_str
	 *
	 * @return bool
	 *
	 * @since 1.4.1
	 */
	public function validate_date_of_birth( $date_str ) {

		if ( empty( $date_str ) ) {
			wc_add_notice( __( 'You need to enter your birthdate to proceed.', 'wirecard-woocommerce-extension' ), 'error' );
			return false;
		}

		try {
			$birth_day  = new DateTime( $date_str );
			$difference = $birth_day->diff( new DateTime() );
			$age        = $difference->format( '%y' );
			if ( $age < 18 ) {
				wc_add_notice( __( 'You need to be older then 18 to order.', 'wirecard-woocommerce-extension' ), 'error' );
				return false;
			}
			return true;

		} catch ( Exception $e ) {
			wc_add_notice( __( 'You need to enter a valid date as birthdate.', 'wirecard-woocommerce-extension' ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Check if the checkbox with data processing agreement is selected in form request
	 *
	 * @param $date_str
	 *
	 * @return bool
	 *
	 * @since 1.4.1
	 */
	public function validate_gdpr_allowance_given( $gdpr_allowance_str ) {

		if ( empty( $gdpr_allowance_str ) || ( '1' != $gdpr_allowance_str ) ) {
			wc_add_notice( __( 'You need to give your consent for data processing', 'wirecard-woocommerce-extension' ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Check if no item in basket os a downloadable product or a virtual product
	 *
	 * @param WC_Cart $cart
	 *
	 * @return bool
	 *
	 * @since 1.4.1
	 */
	public function validate_cart_products( $cart ) {

		foreach ( $cart->cart_contents as $item ) {
			$product = new WC_Product( $item['product_id'] );
			if ( $product->is_downloadable() || $product->is_virtual() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if the given total amount between configured limit (closed interval, limits itself are allowed)
	 *
	 * @param float $total
	 *
	 * @return bool
	 *
	 * @since 1.4.1
	 */
	public function validate_cart_amounts( $total ) {

		$min_allowed_amount = max( 0, floatval( $this->get_option( 'min_amount' ) ) );
		$max_allowed_amount = max( 0, floatval( $this->get_option( 'max_amount' ) ) );

		if ( ( $total <= $min_allowed_amount ) || ( $total >= $max_allowed_amount ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if the customer shipping address is empty/the same as billing address
	 *
	 * Only if the option 'billing_shipping_same' is set
	 *
	 * @param WC_Customer $customer
	 *
	 * @return bool
	 *
	 * @since 1.4.1
	 */
	public function validate_billing_shipping_address( $customer ) {

		if ( $this->get_option( 'billing_shipping_same' ) == 'yes' ) {
			$fields = array(
				'first_name',
				'last_name',
				'address_1',
				'address_2',
				'city',
				'country',
				'postcode',
				'state',
			);

			foreach ( $fields as $field ) {
				$shipping_address_value = call_user_func( array( $customer, 'get_shipping_' . $field ) );
				if ( ! empty( $shipping_address_value ) ) {
					$billing_address_value = call_user_func( array( $customer, 'get_billing_' . $field ) );
					if ( $billing_address_value != $shipping_address_value ) {
						return false;
					}
				}
			}
		}

		return true;
	}

	/**
	 * Check if shipping country / billing country one of the allowed countries
	 *
	 * @param WC_Customer $customer
	 *
	 * @return bool
	 *
	 * @since 1.4.1
	 */
	public function validate_countries( $customer ) {

		$shipping_country = $customer->get_shipping_country();
		if ( ! empty( $shipping_country ) ) {
			if ( ! in_array( $shipping_country, $this->get_option( 'shipping_countries' ) ) ) {
				return false;
			}
		}

		$billing_country = $customer->get_billing_country();
		if ( ! empty( $billing_country ) ) {
			if ( ! in_array( $billing_country, $this->get_option( 'shipping_countries' ) ) ) {
				return false;
			}
		}

		return true;
	}
}
