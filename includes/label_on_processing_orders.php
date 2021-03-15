<?php 
add_action('woocommerce_order_status_processing','muhammadali_order_processing');
function only_numbers($val){
    	return preg_replace("/[^0-9]/","",$val);
    }
function muhammadali_order_processing($order_id){

	$order = wc_get_order( $order_id );
	$shp_main_data = current($order->get_shipping_methods());
	if (strpos($shp_main_data->get_method_id(),"woocommerce_muhammadali")===false) return;
	$meta_data = [];
	foreach($shp_main_data->get_meta_data() as $meta){
		$meta_data[$meta->key] = $meta->value;
	}

	$id = $meta_data['_muhammadali_id'];
	$shipping_type = $meta_data['_type_send'];
	$token = $meta_data['_muhammadali_token'];

	if ($order->has_shipping_address()){
		$destinatario_nome = $order->get_formatted_shipping_full_name();
		$destinatario_cnpjCpf = "bazinga!";
		$destinatario_endereco = $order->get_shipping_address_1() ;
		$destinatario_numero = 0;
		$destinatario_complemento = $order->get_shipping_address_2();
		$destinatario_bairro = "";
		$destinatario_cidade = $order->get_shipping_city();
		$destinatario_uf = $order->get_shipping_state();
		$destinatario_cep = $order->get_shipping_postcode();
		$destinatario_celular = $order->get_billing_phone();	
	} else {
		$destinatario_nome = $order->get_formatted_billing_full_name();
		$destinatario_cnpjCpf = "bazinga!";
		$destinatario_endereco = $order->get_billing_address_1() ;
		$destinatario_numero = 0;
		$destinatario_complemento =  $order->get_billing_address_2();
		$destinatario_bairro = "";
		$destinatario_cidade = $order->get_billing_city();
		$destinatario_uf = $order->get_billing_state();
		$destinatario_cep = $order->get_billing_postcode();
		$destinatario_celular = $order->get_billing_phone();			
	}

	$total_value=$order->get_subtotal();

	$total_weight = 0;
	$titles = [];
	$volumes = [];

	$items = $order->get_items();

	foreach($items as $item){
		$prod = $item->get_product();
		$weight = wc_get_weight($prod->get_weight(),'kg');
		$volume = [			
			'height' => wc_get_dimension($prod->get_height(),'cm'),
			'length' => wc_get_dimension($prod->get_length(),'cm'),
			'width' => wc_get_dimension($prod->get_width(),'cm'),
			'weight' => $weight,
		];
		$total_weight += $weight;
		$volumes[] = $volume;
		$titles[] = $prod->get_title();
	}

	$lista_de_produtos = implode(",",$titles);
	
	$label_data = [
		'_id' => $id,
		'content' => $lista_de_produtos,
		'total_weight' => $total_weight,
		'total_value' => $total_value,
		'tipo_envio' => $shipping_type,
		'recipient' => [
			'nome' => $destinatario_nome,
			'cnpjCpf' => only_numbers($destinatario_cnpjCpf),
			'address' => $destinatario_endereco,
			'number' => $destinatario_numero,
			'complement' => $destinatario_complemento,
			'district' => $destinatario_bairro,
			'cidade' => $destinatario_cidade,
			'uf' => $destinatario_uf,
			'cep' => only_numbers($destinatario_cep),
			'celular' => only_numbers($destinatario_celular)
		],
		'volume' => $volumes,
		'order' => $order->get_order_number(),
		'origem' => 'woocommerce-muhammadali',
		'email' => $order->get_billing_email()
	];

	$meta_group = $order->get_meta_data();
	if (count($meta_group)>0){		
		foreach($meta_group as $meta){
			if ($meta->key=="_billing_persontype") $persontype = $meta->value;
			if ($meta->key=="_billing_cpf") $cpf = only_numbers($meta->value);
			if ($meta->key=="_billing_cnpj") $cnpj = only_numbers($meta->value);
			if ($meta->key=="_billing_number") $billing_number = $meta->value;
			if ($meta->key=="_billing_neighborhood") $billing_neighborhood = $meta->value;
			if ($meta->key=="_billing_cellphone") $billing_cellphone = only_numbers($meta->value);
			if ($meta->key=="_shipping_number") $shipping_number = $meta->value;
			if ($meta->key=="_shipping_neighborhood") $shipping_neighborhood = $meta->value;
		}
		if (isset($persontype)){
			if ($persontype==1){
				$label_data['recipient']['cnpjCpf'] = $cpf;
			} else {
				$label_data['recipient']['cnpjCpf'] = $cnpj;
			}
		}
		if ($order->has_shipping_address()){
			if (isset($shipping_number)) $label_data['recipient']['number'] = $shipping_number;
			if (isset($shipping_neighborhood)) $label_data['recipient']['district'] = $shipping_neighborhood;
		} else {
			if (isset($billing_number)) $label_data['recipient']['number'] = $billing_number;
			if (isset($billing_neighborhood)) $label_data['recipient']['district'] = $billing_neighborhood;			
		}
		if (isset($billing_cellphone) && trim($billing_cellphone)!="") $label_data['recipient']['celular'] = $billing_cellphone;
	}
	
   	$muhammadali = new Muhammadali($token);   	
   	$return = $muhammadali->send_labels($label_data);
   	if (isset($return->error)){ $order->add_order_note($return->message); return;}
   	$order->add_order_note($return->data->message);
}
 ?>