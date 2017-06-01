<?php

class API {
	function __construct($dataCentre, $accountId, $bpAppRef, $bpAccountToken) {
		if ($dataCentre == "euw1") {
			$this->baseUrl = "https://ws-eu1.brightpearl.com/public-api/" . $accountId;
		}
		elseif ($dataCentre == "use1" || $dataCentre == "est" or dataCentre == "cst") {
			$this->baseUrl = "https://ws-use.brightpearl.com/public-api/" . $accountId;
		}
		elseif (dataCentre == "usw1" or dataCentre == "pst" or dataCentre == "mst") {
			$this->baseUrl = "https://ws-usw.brightpearl.com/public-api/" . $accountId;
		}

		$this->headers = ["brightpearl-app-ref: $bpAppRef", "brightpearl-account-token: $bpAccountToken"];

	}

	function openCurl($type) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if ($type == 'get') {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
		}
		else {
			$headers = $this->headers;
			array_push($headers, "Content-Type: application/json");
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);	
		}
		return $ch;
	}

	function get($uri) {		
		$ch = $this->openCurl();
		curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $uri);
		$r = curl_exec($ch);
		curl_close($ch);
		return $r;
	}

	function post($uri, $data) {
		$ch = $this->openCurl();
		curl_setopt($ch, CURLOPT_URL, $uri);
		//curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$r = curl_exec($ch);
		curl_close($ch);
		return $r;
	}

	function put($uri, $data) {
		$ch = $this->openCurl();
		curl_setopt($ch, CURLOPT_URL, $uri);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$r = curl_exec($ch);
		curl_close($ch);
		return $r;
	}

	function delete($uri, $data) {
		$ch = $this->openCurl();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$r = curl_exec($ch);
		curl_close($ch);
		return $r;
	}
}


class GoodsOut {
	function __construct($api, $goodsOutId) {
		$this->api = $api;
		$this->id = $goodsOutId;
	}

	function getInfo() {
		$uri = "/warehouse-service/order/*/goods-note/goods-out/" . $this->id;
		$g = $this->api->get($uri);
		$g = json_decode($g, true);
		$g = $g['response'][$this->id];

		$this->printed = $g['status']['printed'];
		$this->picked = $g['status']['picked'];
		$this->packed = $g['status']['packed'];
		$this->shipped = $g['status']['shipped'];

		$this->shippingMethodId = $g['shipping']['shippingMethodId'];
		$this->shippingMethod = $this->api->get('/warehouse-service/shipping-method/' . $this->shippingMethodId);
		$this->shippingMethod = json_decode($this->shippingMethod, true)['response'][0]['name'];
		
		// $this->weight = $g['shipping']['weight'];

		$this->orderId = $g['orderId'];

		$uri = "/order-service/order/" . $this->orderId;
		$o = $this->api->get($uri);
		$o = json_decode($o, true)['response'][0];

		$this->value = $o['totalValue']['baseTotal'];
		$this->country = $o['parties']['delivery']['countryIsoCode'];
	}

	function action($eventCode) {
		$uri = "/warehouse-service/goods-note/goods-out/" . $this->id . "/event";

		$pl = ["events" => [["eventCode" => $eventCode, "occured" => date('c'), "eventOwnerId" => 4]]];
		$pl = json_encode($pl);

		$this->api->post($uri, $pl);
	}

	function ship() {
		$this->action("SHW");
	}

	function pack() {
		$this->action("PAC");
	}

	function unpack() {
		$this->action("UPA");
	}

	function setShippingMethod($methodId) {
		$uri = "/warehouse-service/goods-note/goods-out/" . $this->id;

		$pl = ["shipping" => ["shippingMethodId" => $methodId]];
		$pl = json_encode($pl);

		$this->api->put($uri, $pl);
	}
}



class Order {
	function __construct($api, $orderId) {
		$this->api = $api;
		$this->id = $orderId;
	}

	function getInfo() {
		$o = $this->api->get('/order-service/order/' . $this->id);
		$o = json_decode($o, true)['response'][0];
		
		$this->status = $o['orderStatus']['name'];

		$this->channelId = $o['assignment']['current']['channelId'];
		$c = $this->api->get('/product-service/channel/' . $this->channelId);
		$this->channelName = json_decode($c, true)['response'][0]['name'];

		$this->date = $o['invoices'][0]['taxDate'];
		$this->currency = $o['currency']['orderCurrencyCode'];
		$this->baseValue = $o['totalValue']['baseTotal'];
		$this->warehouse = $o['warehouseId'];
		$this->shipped = $o['shippingStatusCode'];
		$this->fulfilled = $o['stockStatusCode'];
		$this->allocated = $o['allocationStatusCode'];
		$this->payment = $o['orderPaymentStatus'];
		$this->taxDate = $o['invoices'][0]['taxDate'];

		$this->rows = $o['orderRows'];
		$this->getProductInfo();

		$this->shippingMethod = $o['delivery']['shippingMethodId'];


	}

	function getProductInfo() {
		$rows = $this->rows;
		$this->products = [];

		foreach ($rows as $row) {
			$nominalCode = $row['nominalCode'];
			$name = $row['productName'];

			if ($nominalCode == "4000" && $name != "Reconciliation") {
				$productId = $row['productId'];

				$product = new Product($this->api, $productId);
				$product->getInfo();

				if (array_key_exists('productSku', $row)) {
					$product->sku = $row['productSku'];
				}
				
				$product->qty = $row['quantity']['magnitude'];
				
				array_push($this->products, $product);
			}
		}
	}

	function setStatus($status) {
		$pl = ["orderStatusId" => $status, "orderNote" => ["text" => "Order status updated"]];
		$pl = json_encode($pl);

		$uri = '/order-service/order/' . $this->id . '/status';

		$this->api->put($uri, $pl);
	}

	function addNote($content) {
		$uri = "/order-service/order/". $this->id . "/note";

		$pl = ["text" => $content, "addedOn" => date('c')];
		$pl = json_encode($pl);

		return $this->api->post($uri, $pl);
	}

	function invoice() {
		$uri = "/order-service/sales-order/" . $this->id . "/close";
		
		$pl = (object) null;
		$pl = json_encode($pl);

		$i = $this->api->post($uri, $pl);
		
		$this->setStatus(4);
	}

	function fulfil() {
		$uri = "/warehouse-service/order/" . $this->id . "/goods-note/goods-out";

		$rows = $this->rows;

		$products = [];

		foreach ($rows as $key => $row) {
			$productId = $row['productId'];
			$rowId = $key;
			$qty = $row['quantity']['magnitude'];
			
			$r = [
				"productId" => $productId,
				"salesOrderRowId" => $rowId,
				"quantity" => intval($qty)
			];
			
			array_push($products, $r);
		}

		$pl = [
			"warehouses" => [
				[
					"releaseDate" => date('c'),
					"warehouseId" => 6,
					"transfer" => false,
					"products" => $products
				]
			],
			"priority" => false,
			"shippingMethodId" => $this->shippingMethod
		];

		$pl = json_encode($pl);

		$r = $this->api->post($uri, $pl);

		$this->setStatus(62);
		$this->addNote('Order fulfilled');

	}

}



class Product {
	function __construct($api, $productId) {
		$this->api = $api;
		$this->id = $productId;
	}

	function getInfo() {
		$p = $this->api->get('/product-service/product/' . $this->id);
		$p = json_decode($p, true)['response'][0];

		$this->isBundle = $p['composition']['bundle'];
		$this->name = $p['salesChannels'][0]['productName'];

		if (array_key_exists('barcode', $p['identity'])) {
			$this->barcode = $p['identity']['barcode'];
		}

		if (array_key_exists('mpn', $p['identity'])) {
			$this->mpn = $p['identity']['mpn'];
		}
		else {
			$this->mpn = "";
		}

		$c = $this->api->get('/product-service/product/' . $this->id .'/custom-field');
		$c = json_decode($c, true)['response'];

		if (array_key_exists('PCF_PARCTYP', $c)) {
			$this->parcelType = $c['PCF_PARCTYP']['value'];
		}
		if (array_key_exists('PCF_PACKTYPE', $c)) {
			$packagingType = $c['PCF_PACKTYPE']['value'];
			$pos = strpos($packagingType, " - ");
			$this->packagingType = substr($packagingType, 0, $pos);
		}
	}
}

?>