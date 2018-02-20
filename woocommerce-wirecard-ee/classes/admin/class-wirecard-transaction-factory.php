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

use Wirecard\PaymentSdk\Response\SuccessResponse;

class Wirecard_Transaction_Factory {

	private $table_name;

	private $fields_list;

	public function __construct() {
		global $wpdb;

		$this->table_name = $wpdb->base_prefix . 'wirecard_payment_gateway_tx';

		$this->fields_list = array(
			'tx_id'                 => array(
				'title' => __( 'Transaction', 'woocommerce-gateway-wirecard' )
			),
			'transaction_id'        => array(
				'title' => __( 'Transaction ID', 'woocommerce-gateway-wirecard' )
			),
			'parent_transaction_id' => array(
				'title' => __( 'Parenttransaction ID', 'woocommerce-gateway-wirecard' )
			),
			'payment_method'        => array(
				'title' => __( 'Payment method', 'woocommerce-gateway-wirecard' )
			),
			'transaction_state'     => array(
				'title' => __( 'Transaction state', 'woocommerce-gateway-wirecard' )
			),
			'transaction_type'      => array(
				'title' => __( 'Action', 'woocommerce-gateway-wirecard' )
			),
			'amount'                => array(
				'title' => __( 'Amount', 'woocommerce-gateway-wirecard' )
			),
			'currency'              => array(
				'title' => __( 'Currency', 'woocommerce-gateway-wirecard' )
			),
			'order_id'              => array(
				'title' => __( 'Order number', 'woocommerce-gateway-wirecard' )
			)
		);
	}

	/**
	 * Create transaction entry in database
	 *
	 * @param WC_Order                        $order
	 * @param SuccessResponse $response
	 */
	public function create_transaction( $order, $response ) {
		global $wpdb;

		$wpdb->insert(
			$this->table_name,
			array(
				'transaction_id'        => $response->getTransactionId(),
				'parent_transaction_id' => $response->getParentTransactionId(),
				'payment_method'        => $response->getPaymentMethod(),
				'transaction_state'     => 'success',
				'transaction_type'      => $response->getTransactionType(),
				'amount'                => $order->get_total(),
				'currency'              => get_woocommerce_currency(),
				'order_id'              => $order->get_id(),
			)
		);

		return $wpdb->insert_id;
	}

	/**
	 * Get transaction html table for overview beginning from $start to $stop
	 *
	 * @since 1.0.0
	 *
	 * @param int $page
	 *
	 * @return int $row_count
	 */
	public function get_rows( $page = 1 ) {
		global $wpdb;

		$start = ( $page * 20 ) - 19;

		$start --;
		$query = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wirecard_payment_gateway_tx ORDER BY tx_id DESC LIMIT %d,20", $start );
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		$sum_query = "SELECT CEILING(COUNT(*)/20) as pages FROM {$wpdb->prefix}wirecard_payment_gateway_tx";

		$pages = $wpdb->get_row( $sum_query );

		if ( $pages == null ) {
			$pages        = new stdClass();
			$pages->pages = 1;
		}

		echo "<tr>";
		foreach ( $this->fields_list as $field_key => $field_value ) {
			echo "<th>";
			echo $field_value['title'];
			echo "</th>";
		}
		echo "</tr>";

		foreach ( $rows as $row ) {
			echo "<tr>";

			foreach ( $this->fields_list as $field_key => $field_value ) {
				echo "<td>";
				if ( key_exists( $field_key, $row ) ) {
					if ( 'transaction_id' == $field_key || 'parent_transaction_id' == $field_key ) {
						echo "<a href='?page=wirecardpayment&id={$row[ $field_key ]}'>" . $row[$field_key] . "</a>";
					} else {
						echo $row[$field_key];
					}
				}
				echo "</td>";
			}

			echo "</tr>";
		}

		return $pages->pages;
	}

	/**
	 * Default transaction dashboard
	 *
	 * @param $transaction_id
	 */
	public function show_transaction( $transaction_id ) {
		echo "Trasactiondetails:";
		echo "<a href='?page=wirecardpayment'>" . __( 'Back', 'woocommerce' ) . "</a>";
	}
}
