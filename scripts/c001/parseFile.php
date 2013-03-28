<?php
	require('/var/www/scripts/class/phpQuery.php');
	$company_id = '001';
	$main_url = 'http://www.urbanoutfitters.com';
	$company_name = 'Urban Outfitters';

	$files = glob('files/*.html');
	$db = new mysqli('localhost', 'jmul', '54321');
	if($db->connect_errno)
	{
		print "Connect failed " . $mysqli->connect_error;
		exit(0);
	}

	$count = 1;
	$table_name = date('Ymd');
	foreach($files as $file)
	{
		$parent_category = explode('_', basename($file));
		$parent_category = $parent_category[0];
		switch($parent_category)
		{
			case 'apt':
				$parent_category_id = 3;
				break;
			case 'women':
				$parent_category_id = 1;
				break;
			case 'men':
				$parent_category_id = 2;
				break;
			default:
				break;
		}

		print " - Working on category: " . $parent_category . " ... file #" . $count . " out of " . count($files) . "\n";
		$pq = phpQuery::newDocumentFileHTML($file);
		$productSql = array();	
		foreach($pq->find('div.category-product') as $catProduct)
		{
			$name = pq($catProduct)->find('div.category-product-description')->children('h2')->children('a')->html();
			$promo_price = pq($catProduct)->find('div.category-product-description')->children('h3.price')->children('span.price-list')->html();
			$original_price = pq($catProduct)->find('div.category-product-description')->children('h3.price')->children('span.price-sale')->html();
			$main_img = pq($catProduct)->find('div.category-product-media')->children('p.category-product-image')->children('a')->children('img')->attr('src');
			$product_url = $main_url . pq($catProduct)->find('div.category-product-description')->children('h2')->children('a')->attr('href');
			$url_elements = parse_url($product_url);
			parse_str($url_elements['query'], $params);
			$sku = $params['id'];
			$productSql[] = "('" . $db->real_escape_string($sku) . "','" . $db->real_escape_string($name) . "','" . $parent_category_id . "','" . _fixPrice($original_price) . "','" . _fixPrice($promo_price) . "','" . $db->real_escape_string($main_img) . "','" . $db->real_escape_string($product_url) . "')";
		}
		
		$insert = $db->query("INSERT INTO `c" . $company_id . "`.`" . $table_name . "` (`sku`, `name`, `parent_category_id`, `original_price`, `sale_price`, `main_img_url`, `product_url`) VALUES " . implode(', ', $productSql));
		if(!$insert)
		{
			print "Fail to insert to table\n" . $db->error. "\n";
			exit(0);
		}

		// Increment the counter
		$count++;
	}
	$db->close();
	exit(0);

	function _fixPrice($input)
	{
		return number_format(floatval(preg_replace("/[^-0-9\.]/","",$input)), 2);
	}
?>
