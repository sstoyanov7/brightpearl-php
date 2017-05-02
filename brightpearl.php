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

	function openCurl() {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
		return $ch;
	}

	function get($uri) {		
		$ch = $this->openCurl();
		curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $uri);
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
		$uri = '/warehouse-service/order/*/goods-note/goods-out/' . $this->id;
		$g = $this->api->get($uri);
		$g = json_decode($g, true);
		$g = $g['response'][$this->id];

		$this->printed = $g['status']['printed'];
		$this->picked = $g['status']['picked'];
		$this->packed = $g['status']['packed'];
		$this->shipped = $g['status']['shipped'];

		$shippingMethod = $g['shipping']['shippingMethodId'];
		$this->shippingMethod = $this->api->get('/warehouse-service/shipping-method/' . $shippingMethod);
		$this->shippingMethod = json_decode($this->shippingMethod, true)['response'][0]['name'];

		$this->orderId = $g['orderId'];
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

		$channel = $o['assignment']['current']['channelId'];
		$c = $this->api->get('/product-service/channel/' . $channel);
		$this->channel = json_decode($c, true)['response'][0]['name'];

		$this->date = $o['invoices'][0]['taxDate'];

		$this->currency = $o['currency']['orderCurrencyCode'];
		$this->baseValue = $o['totalValue']['baseTotal'];

		$this->rows = $o['orderRows'];
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