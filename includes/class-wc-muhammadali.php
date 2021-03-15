<?php 

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include WC_muhammadali_DIR . "/includes/class_api_muhammadali.php";
include WC_muhammadali_DIR . "/includes/label_on_processing_orders.php";

function woocommerce_muhammadali_init(){
	if (!class_exists('WC_woocommerce_muhammadali')){

		class WC_woocommerce_muhammadali extends WC_Shipping_Method {

			public function __construct($instance_id = 0){
				$this->id = 'woocommerce_muhammadali';
				$this->instance_id = absint($instance_id);
				$this->title = 'muhammadali plugin';
				$this->method_title = 'muhammadali woocommerce';
				$this->method_description = 'Shipping calculator muhammadali';	
				$this->supports           = array(
					'shipping-zones',
					'instance-settings',
					);	
				$this->init();  				
			}

			public function init(){
				$this->init_form_fields();
				$this->init_instance_settings();
				add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
			}

			public function init_form_fields(){
				$this->instance_form_fields = [
				'enabled' => [
				'title' => __('Active','woocommerce_muhammadali'),
				'type' => 'checkbox',
				'label' => 'Active',
				'default' => 'yes',
				'description' => 'Please inform if this shipping method is valid'
				],	
				'token' => [
				'title' => __('Your access token muhammadali','woocommerce_muhammadali'),
				'type' => 'text',						
				'default' => '',
				'description' => 'If you dont have your token yet, contact muhammadali support'
				],									
				'source_zip_code' => [
				'title' => __('CEP source for calculation'),
				'type' => 'text',
				'default' => '00000-000',
				'class' => 'as_mask_zip_code',
				'description' => 'Minimum weight for the customer to be able to choose this modality'
				],						
				'show_delivery_time' => [
				'title' => __('Show delivery time','woocommerce_muhammadali'),
				'type' => 'checkbox',
				'label' => 'Show delivery time',
				'default' => 'yes',
				'description' => 'Tell us if we must show the delivery time'
				],	
				'show_estimate_on_product_page' => [
				'title' => __('Calculate shipping on the product page','woocommerce_muhammadali'),
				'type' => 'checkbox',
				'label' => 'Shipping on the product page',
				'default' => 'yes',
				'description' => 'Inform if you want the customer to have a shipping forecast on the product page'
				],						
				];
			}

			function admin_options() {

				if ( ! $this->instance_id ) {
					echo '<h2>' . esc_html( $this->get_method_title() ) . '</h2>';
				}
				echo wp_kses_post( wpautop( $this->get_method_description() ) );
				echo $this->get_admin_options_html();

			}

			public function calculate_shipping( $package = false) {

				$use_this_method = $this->validate_shipping($package);
				if (!$use_this_method) return false;

				$product_statements = $this->sumarize_package($package);

				$token = $this->instance_settings['token'];

				$muhammadali = new Muhammadali($token);
				$muhammadali->setWeight($product_statements['total_weight']);
				$muhammadali->setSourceZipCode($this->instance_settings['source_zip_code']);
				$muhammadali->setTargetZipCode($package['destination']['postcode']);
				$muhammadali->setPackageValue($product_statements['total_value']);
				$muhammadali->setWidth($product_statements['maximum_width']);
				$muhammadali->setHeight($product_statements['maximum_height']);
				$muhammadali->setLength($product_statements['maximum_length']);

				$muhammadali->calculate_shipping();

				$received_rates = $muhammadali->getRates();

				if (isset($received_rates->error)){
					echo "<pre>";
					print_r($received_rates->message);
					echo "</pre>";
					return;
				}

				if (count($received_rates)==0) return;

				$show_delivery_time = $this->instance_settings['show_delivery_time'];

				foreach($received_rates as $rate){

					$meta_delivery_time = [];

					$prazo_em_dias = preg_replace("/[^0-9]/","",$rate->deadline);

					$meta_delivery = array(					
						'_muhammadali_id' => $rate->_id,
						'_type_send' => $rate->type_send,
						'_muhammadali_token' => $this->instance_settings['token']
						);

					if ( 'yes' === $show_delivery_time ) $meta_delivery['_muhammadali_delivery_estimate'] = intval( $prazo_em_dias );

					$prazo_texto = "";
					if ( 'yes' === $show_delivery_time ) $prazo_texto = " (".$rate->deadline.")";
					

					$rates = [
					'id' => 'woocommerce_muhammadali_'.$rate->name,
					'label' => $rate->name.$prazo_texto,
					'cost' => $rate->price_finish,
					'meta_data' => $meta_delivery
					];	        					
					$this->add_rate($rates, $package);
				}

				return;    			

			}

			public function forecast_shipping( $muhammadali_product = false) {					        	

				global $product;
				global $woocommerce;
				if ($this->instance_settings['enabled']!='yes') return;

				if ($this->instance_settings['show_estimate_on_product_page']!='yes') return;				

				$token = $this->instance_settings['token'];

				$height = (integer)preg_replace("/[^0-9]/","",$product->get_height());
				$width = (integer)preg_replace("/[^0-9]/","",$product->get_width());
				$length = (integer)preg_replace("/[^0-9]/","",$product->get_length());

				$dimensions = [];
				$dimensions[] = wc_get_dimension($length,'cm');
				$dimensions[] = wc_get_dimension($width,'cm');
				$dimensions[] = wc_get_dimension($height,'cm');

				sort($dimensions);

				$length = $dimensions[2];
				$width = $dimensions[1];
				$height = $dimensions[0];

				$weight = wc_get_weight($product->get_weight(),'kg');
				$price = $product->get_price();

				if ($weight==0 || $height==0 || $width==0 || $length==0) return;

				$product_link = $product->get_permalink();

				if (isset($_POST['muhammadali_forecast_zip_code'])){
					$target_zip_code = $_POST['muhammadali_forecast_zip_code'];
				} else {
					$shipping_zip_code = $woocommerce->customer->get_shipping_postcode();			
					if (trim($shipping_zip_code)!="") {
						$target_zip_code = $shipping_zip_code;
					} else {
						$target_zip_code = $woocommerce->customer->get_billing_postcode();
					}
				}

				echo "
				<style>
					.as-row{margin:0px -15px;}
					.as-row::before,.as-row::after{display: table; content: ' ';}
					.as-row::after{clear:both;}
					.as-col-xs-12, .as-col-sm-4, .as-col-sm-8,.as-col-md-9,.as-col-md-3, as-col-sm-12, as-col-md-12{position: relative;min-height: 1px;padding-right: 15px;padding-left: 15px;}
					.as-col-xs-12{float:left;width:100%;}
					@media (min-width:600px) {.as-col-sm-4,.as-col-sm-8,as-col-sm-12{float:left;}.as-col-sm-4{width:33.33%;}.as-col-sm-8{width:66.33%;}.as-col-sm-12{width:100%;}}
					@media (min-width:992px){.as-col-md-3,.as-col-md-9,.as-col-md-12{float:left;}.as-col-md-3{width:25%;}.as-col-md-9{width:75%};as-col-md-12{width:100%;}}
					.muhammadali_shipping_forecast_form{padding-top:20px;}					
					.muhammadali_shipping_forecast_form input{max-width:100% !important;text-align:center;height:42px;}					
					.muhammadali_shipping_forecast_table{padding:20px 0px;}
					.muhammadali_shipping_forecast_table table{width:100%;}					
					.woocommerce div.product form.cart div.quantity, .woocommerce div.product form.cart .button{top:auto;}
				</style>
				<div style='clear:both;'></div>
				<div class='muhammadali_shipping_forecast_form as-row'>
					<div class=''>
						<form method='post' action='{$product_link}' id='muhammadali_shipping_forecast'>	
							<div class=''>				
								<div class='as-col-md-3 as-col-sm-4 as-col-xs-12'>
									<input type='text' value='{$target_zip_code}' class='as_mask_zip_code' name='muhammadali_forecast_zip_code'/>
								</div>
								<div class='as-col-md-9 as-col-sm-8 as-col-xs-12'>
									<button type='submit' id='muhammadali_shipping_forecast_submit' class='single_add_to_cart_button button alt'>Calculate shipping</button>
								</div>
							</div>
						</form>
					</div>
				</div>";


				if (trim($target_zip_code)=="") return;

				$target_zip_code = preg_replace("/[^0-9]/","",$target_zip_code);
				$source_zip_code = preg_replace("/[^0-9]/","",$this->instance_settings['source_zip_code']);

				$rates = [];

				$muhammadali = new muhammadali($token);
				$muhammadali->setWeight($weight);
				$muhammadali->setSourceZipCode($source_zip_code);
				$muhammadali->setTargetZipCode($target_zip_code);
				$muhammadali->setPackageValue($price);
				$muhammadali->setWidth($width);
				$muhammadali->setHeight($height);
				$muhammadali->setLength($length);
				
				$muhammadali->calculate_shipping();
				$received_rates = $muhammadali->getRates();
			
				if (isset($received_rates->error)){
					echo "<pre>";
					echo "<p>".$received_rates->message."</p>";
					echo "</pre>";
					return;
				}				
				
				if (count($received_rates)==0) return [];

				$show_delivery_time = $this->instance_settings['show_delivery_time'];

				$rates = [];

				foreach($received_rates as $rate){

					$rate_item = [];

					$prazo_texto = "";
					if ( 'yes' === $show_delivery_time ) $prazo_texto = " (".$rate->deadline.")";

					$rate_item['label'] = $rate->name . $prazo_texto;
					$rate_item['cost'] = wc_price($rate->price_finish);

					$rates[] = $rate_item;					
				}				



				if (count($rates)==0) return;

				echo "
				<div class='muhammadali_shipping_forecast_table as-row'>
					<div class='as-col-xs-12 as-col-sm-12 as-col-md-12'>
						<table>
							<thead>
								<tr>
									<th>Delivery method by muhammadali</th>
									<th>Estimated cost</th>
								</tr>
							</thead>
							<tbody>";

								foreach($rates as $rate){

									echo "<tr>";
									echo "<td>" . $rate['label'] . "</td>";
									echo "<td>" . $rate['cost'] . "</td>";
									echo "</tr>";
								}

								echo "
							</tbody>
						</table>
					</div>
				</div>
				";

			}

			private function validate_shipping($package = false){

				if ($this->instance_settings['enabled']!='yes') return false;	        	

				return true;

			}

			private function sumarize_package($package){
				$package_values = [
				'total_weight' => 0,
				'total_value' => 0,
				'maximum_length' => 0,
				'maximum_width' => 0,
				'maximum_height' => 0
				];	        	

				foreach($package['contents'] as $item){

					$product = $item['data'];	        		

					$height = (integer)preg_replace("/[^0-9]/","",$product->get_height());
					$width = (integer)preg_replace("/[^0-9]/","",$product->get_width());
					$length = (integer)preg_replace("/[^0-9]/","",$product->get_length());

					$dimensions = [];
					$dimensions[] = wc_get_dimension($length,'cm');
					$dimensions[] = wc_get_dimension($width,'cm');
					$dimensions[] = wc_get_dimension($height,'cm');

					sort($dimensions);

					$length = $dimensions[2];
					$width = $dimensions[1];
					$height = $dimensions[0];

					$weight = wc_get_weight($product->get_weight(),'kg');
					$package_values['total_weight']+= $weight;

					$value = $item['line_total'];
					$package_values['total_value'] += $value;

					if ($width > $package_values['maximum_width']) $package_values['maximum_width'] = $width;
					if ($height > $package_values['maximum_height']) $package_values['maximum_height'] = $height;
					if ($length > $package_values['maximum_length']) $package_values['maximum_length'] = $length;

				}	        	

				return $package_values;

			}

		}
	}
}
add_action('woocommerce_shipping_init','woocommerce_muhammadali_init');

function add_woocommerce_muhammadali( $methods ) {
	$methods['woocommerce_muhammadali'] = 'WC_woocommerce_muhammadali'; 
	return $methods;
}

add_filter( 'woocommerce_shipping_methods', 'add_woocommerce_muhammadali' );

add_action('wp_enqueue_scripts','muhammadali_enqueue_user_scripts');
add_action('admin_enqueue_scripts','muhammadali_enqueue_user_scripts');
function muhammadali_enqueue_user_scripts(){

	wp_enqueue_script('auge_jquery_masks',plugins_url()."/woocommerce-muhammadali/assets/jquery.mask.min.js",array(),false,true);
	wp_enqueue_script('auge_jquery_mask_formats',plugins_url()."/woocommerce-muhammadali/assets/auge_masks.js",array(),false,true);	
	wp_enqueue_script('muhammadali_scripts',plugins_url()."/woocommerce-muhammadali/assets/muhammadali.js",array(),false,true);	
	return;
}

function muhammadali_shipping_forecast_on_product_page(){
	global $woocommerce;
	if (!is_product()) return;

	if (isset($_POST['muhammadali_forecast_zip_code'])){
		$target_zip_code = $_POST['muhammadali_forecast_zip_code'];
	} else {
		$shipping_zip_code = $woocommerce->customer->get_shipping_postcode();			
		if (trim($shipping_zip_code)!="") {
			$target_zip_code = $shipping_zip_code;
		} else {
			$target_zip_code = $woocommerce->customer->get_billing_postcode();
		}
	}

	$metodos_de_entrega = muhammadali_get_metodos_de_entrega($target_zip_code);

	if (count($metodos_de_entrega)==0) return;

	foreach($metodos_de_entrega as $metodo){
		if (is_object($metodo) && get_class($metodo)=="WC_woocommerce_muhammadali"){
			$muhammadali_class = $metodo;
			break;
		}
	}	

	$muhammadali_class->forecast_shipping();
	
}
add_action('woocommerce_after_add_to_cart_form','muhammadali_shipping_forecast_on_product_page',50);

function muhammadali_get_metodos_de_entrega($cep_destinatario) {

	$metodos_de_entrega = [];

	$delivery_zones = WC_Shipping_Zones::get_zones();

	if (count($delivery_zones) < 1) {		
		return $metodos_de_entrega;
	}

	$metodos_de_entrega = [
	'shipping_methods' => []
	];

	foreach ($delivery_zones as $key_delivery_zone => $delivery_zone) {

		if (count($delivery_zone['shipping_methods']) < 1) {
			continue;			
		}
		$cep_destinatario_permitido = false;
		foreach ($delivery_zone['zone_locations'] as $zone_location) {
			switch ($zone_location->type) {
				case 'country':
				if ($zone_location->code == 'BR')
					$cep_destinatario_permitido = true;
				break;
				case 'postcode':
				$ceps = explode(PHP_EOL, $zone_location->code);
				foreach ($ceps as $key => $value) {
					if (strpos($zone_location->code, '...') !== false) {
						$ranges = explode('...', $value);
						if (count($ranges) == 2 && is_numeric($ranges[0]) && is_numeric($ranges[1])) {
							if ($cep_destinatario > (int) $ranges[0] && $cep_destinatario < (int) $ranges[1]) {
								$cep_destinatario_permitido = true;
							}
						}
						continue;
					}
					if (strpos($zone_location->code, '*') !== false) {
						$before_wildcard = strtok($zone_location->code, '*');
						$tamanho_string = strlen($before_wildcard);
						if (substr($cep_destinatario, 0, $tamanho_string) == $before_wildcard) {
							$cep_destinatario_permitido = true;
						}
					} else {
						if ($cep_destinatario == $zone_location->code) {
							$cep_destinatario_permitido = true;
						}
					}
				}
				break;
				case 'state':
				$tmp = explode(':', $zone_location->code);
				if ($tmp[0] == 'BR') {
					if (muhammadali_is_cep_from_state($cep_destinatario, $tmp[1])) {
						$cep_destinatario_permitido = true;
					}
				}
				break;
			}

		}

		foreach ($delivery_zone['shipping_methods'] as $key => $shipping_method) {
			if (get_class($shipping_method) ==  "WC_woocommerce_muhammadali") {
				if ($shipping_method->enabled == 'yes') {
					$metodos_de_entrega[$key] = $shipping_method;
				}
			}
		}
	}

	return $metodos_de_entrega;


}

function muhammadali_is_cep_from_state($cep, $estado) {

        $cep = substr($cep, 0, 5);
        $cep = (int)$cep;

        switch ($estado) {
        	case ('AC'):
        	if ($cep > 69900 && $cep < 69999)
        		return true;
        	break;
        	case ('AL'):
        	if ($cep > 57000 && $cep < 57999)
        		return true;
        	break;
        	case ('AP'):
        	if ($cep > 68900 && $cep < 68999)
        		return true;
        	break;
        	case ('AM'):
        	if ($cep > 69400 && $cep < 69899)
        		return true;
        	break;
        	case ('BA'):
        	if ($cep > 40000 && $cep < 48999)
        		return true;
        	break;
        	case ('CE'):
        	if ($cep > 60000 && $cep < 63999)
        		return true;
        	break;
        	case ('CE'):
        	if ($cep > 60000 && $cep < 63999)
        		return true;
        	break;
        	case ('DF'):
        	if ($cep > 70000 && $cep < 73699)
        		return true;
        	break;
        	case ('ES'):
        	if ($cep > 29000 && $cep < 29999)
        		return true;
        	break;
        	case ('GO'):
        	if ($cep > 72800 && $cep < 76799)
        		return true;
        	break;
        	case ('MA'):
        	if ($cep > 65000 && $cep < 65999)
        		return true;
        	break;
        	case ('MT'):
        	if ($cep > 78000 && $cep < 78899)
        		return true;
        	break;
        	case ('MS'):
        	if ($cep > 79000 && $cep < 79999)
        		return true;
        	break;
        	case ('MG'):
        	$debug[] = 'MG';
        	if ($cep > 30000 && $cep < 39999)
        		return true;
        	break;
        	case ('PA'):
        	if ($cep > 66000 && $cep < 68899)
        		return true;
        	break;
        	case ('PB'):
        	if ($cep > 58000 && $cep < 58999)
        		return true;
        	break;
        	case ('PR'):
        	if ($cep > 80000 && $cep < 87999)
        		return true;
        	break;
        	case ('PE'):
        	if ($cep > 50000 && $cep < 56999)
        		return true;
        	break;
        	case ('PI'):
        	if ($cep > 64000 && $cep < 64999)
        		return true;
        	break;
        	case ('RJ'):
        	if ($cep > 20000 && $cep < 28999)
        		return true;
        	break;
        	case ('RN'):
        	if ($cep > 59000 && $cep < 59999)
        		return true;
        	break;
        	case ('RS'):
        	if ($cep > 90000 && $cep < 99999)
        		return true;
        	break;
        	case ('RO'):
        	if ($cep > 78900 && $cep < 78999)
        		return true;
        	break;
        	case ('RR'):
        	if ($cep > 69300 && $cep < 69389)
        		return true;
        	break;
        	case ('SC'):
        	if ($cep > 88000 && $cep < 89999)
        		return true;
        	break;
        	case ('SP'):
        	if ($cep > 01000 && $cep < 19999)
        		return true;
        	break;
        	case ('SE'):
        	if ($cep > 49000 && $cep < 49999)
        		return true;
        	break;
        	case ('TO'):
        	if ($cep > 77000 && $cep < 77995)
        		return true;
        	break;
        	default:
        	return false;
        }
    }

    ?>