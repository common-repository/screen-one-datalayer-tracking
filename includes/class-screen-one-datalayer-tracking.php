<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Screen_One_Datalayer_Tracking {
	
	public function __construct() {
		// Contains snippets/JS tracking code
		include_once( 'class-screen-one-datalayer-tracking-js.php' );
	}

	public function call_hook() {
		// Event tracking woocommerce
		add_action( 'wp_footer', array( $this, 'add_to_cart_variable' ) );

		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'add_to_cart' ) );
		add_action( 'woocommerce_after_shop_loop', array( $this, 'after_shop_loop' ) );
		add_action( 'woocommerce_after_cart', array( $this, 'after_cart' ) );
		// add_action( 'woocommerce_after_mini_cart', array( $this, 'after_cart' ) );
		add_action( 'woocommerce_after_single_product', array( $this, 'product_detail' ) );
		add_action( 'woocommerce_after_checkout_form', array( $this, 'after_checkout_shipping_form' ));
		add_action( 'woocommerce_after_checkout_form', array( $this, 'view_checkout' ) );
		add_action( 'woocommerce_thankyou' , array( $this, 'purchase_completed' ) );
		add_action( 'woocommerce_add_to_cart', array( $this, 'add_to_cart_advanced' ), 6, 6 );
	}

	public function add_to_cart_variable() {
		if( is_single() || is_shop()) {?>
			<script>
				let itemAddToCartGA4 = [];
				let itemAddToCartUA = [];
				let valueAddToCartGA4 = 0;
				let indexAddToCart = 1;
			</script>
		<?php }
	}

	public function add_to_cart_advanced($cart_item_key = 0, $product_id = 0, $quantity = 1, $variation_id = 0) {
		Screen_One_Datalayer_Tracking_JS::get_instance()->add_to_cart_advanced( $cart_item_key, $product_id, $quantity, $variation_id );
	}

	public function add_to_cart() {
		if ( ! is_single() ) {
			return;
		}
		Screen_One_Datalayer_Tracking_JS::get_instance()->event_tracking_code( '.add_to_cart_button.product_type_simple' );
	}

	public function after_shop_loop() {
		Screen_One_Datalayer_Tracking_JS::get_instance()->view_list_item();
		Screen_One_Datalayer_Tracking_JS::get_instance()->event_tracking_code( '.add_to_cart_button.product_type_simple' );
	}

	public function product_detail() {
		global $product;

		Screen_One_Datalayer_Tracking_JS::get_instance()->select_item( $product );
		Screen_One_Datalayer_Tracking_JS::get_instance()->product_detail( $product );
	}

	public function after_cart() {
		global $woocommerce;
		if ( is_single() ) {
			return;
		}
		Screen_One_Datalayer_Tracking_JS::get_instance()->remove_from_cart($woocommerce);
    	Screen_One_Datalayer_Tracking_JS::get_instance()->view_cart($woocommerce);
    	Screen_One_Datalayer_Tracking_JS::get_instance()->cart_updated_quantity();
	}

	public function view_checkout() {
		global $woocommerce;
		if(empty($woocommerce)) return;
        $items = $woocommerce->cart->get_cart();
		$checkout = new WC_Checkout;
		$fields = $checkout->get_checkout_fields( 'billing' );
    	Screen_One_Datalayer_Tracking_JS::get_instance()->view_checkout($fields, $items);
	}

	public function after_checkout_shipping_form() {
		Screen_One_Datalayer_Tracking_JS::get_instance()->after_checkout_shipping_form();
	}

	public function purchase_completed($order_id) {
		$order = wc_get_order( $order_id );
		Screen_One_Datalayer_Tracking_JS::get_instance()->purchase_completed($order);
	}
}