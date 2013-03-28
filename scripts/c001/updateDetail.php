<?php
    require('/var/www/scripts/class/Curl.php');
	require('/var/www/scripts/class/phpQuery.php');

    $curl = new Curl();
	
	$company_id = '001';
	$main_url = 'http://www.urbanoutfitters.com';
	$company_name = 'Urban Outfitters';

	$db = new mysqli('localhost', 'jmul', '54321');
	if($db->connect_errno)
	{
		print "Connect failed " . $mysqli->connect_error;
		exit(0);
	}

	// Create the sub category lookup
	$sub_categories = array(
		'W_APP_DRESSES' => '1',
		'W_APP_SWEATERS' => '2',
		'W_TOPS' => '3',
		'W_OUTERWEAR' => '4',
		'W_BOTTOMS' => '5',
		'W_APP_VINTAGE' => '6',
		'W_LOUNGE' => '7',
		'W_INTIMATES' => '8',
		'W_APP_SWIMWEAR' => '9',
		'WOMENS_SHOES' => '10',
		'W_BEAUTY' => '11',
		'WOMENS_ACCESSORIES' => '12',
		'M_APP_SWEATERS' => '13',
		'M_TOPS' => '14',
		'M_OUTERWEAR' => '15',
		'M_BOTTOMS' => '16',
		'M_VINTAGE' => '17',
		'MENS_SHOES' => '18',
		'MENS_ACCESSORIES' => '19',
		'A_DEC_BEDDING' => '20',
		'A_DECORATE' => '21',
		'A_RUGPILCUR' => '22',
		'A_FURN_WALL' => '23',
		'A_FURN_FURNITURE' => '24',
		'A_FURN_DINNERWARE' => '25',
		'A_FURN_BATH' => '26',
		'A_VINTAGE' => '27',
		'A_MEDIA_GADGETS' => '28',
		'A_ENT_BOOKS_BOOK' => '29',
		'A_ENT_BOOKS_STATIONERY' => '30',
		'APARTMENT_MEDIA' => '31',
		'APARTMENT_MUSIC' => '32',
		'A_ENT_GAMES' => '33');

	// Figure out active table
	$query = "SELECT `table_name` FROM `c" . $company_id . "`.`active` WHERE `active`='2' LIMIT 1";
	$result = $db->query($query);
	$table_name = $result->fetch_object()->table_name;
	$result->close();

	// Get all products
	$query = "SELECT * FROM `c" . $company_id . "`.`" . $table_name . "`";
	$brand_ids = array();
	if($result = $db->query($query))
	{
		$count = 1;
		$total = $result->num_rows;
		while($row = $result->fetch_assoc())
		{
			print " - Updating product id " . $row['id'] . " ... " . $count . " out of " . $total . "\n"; 
			$html = $curl->get($row['product_url']);
			$pq = phpQuery::newDocumentHTML($html);

			$brand = $pq->find('meta[itemprop=brand]')->attr('content');
		
			// Get the brand id
			if(!isset($brand_ids[$brand]))
			{
				if($brand_id_result = $db->query("SELECT `id` FROM `global`.`brand` WHERE `name` LIKE '" . $brand . "' LIMIT 1"))
				{
					if($brand_id_result->num_rows == 0)
					{
						$brand_id_result->close();
						$insert_brand = $db->query("INSERT INTO `global`.`brand` (`name`) VALUES ('" . $db->real_escape_string($brand) . "')");
						if(!$insert_brand)
						{
							print "Fail to insert to table\n";
							exit(0);
						}
						$brand_id = $db->insert_id;
					}
					else
					{
						$brand_row = $brand_id_result->fetch_object();
						$brand_id = $brand_row->id;
						$brand_id_result->close();
					}
					$brand_ids[$brand] = $brand_id;
				}
			}
			else
			{
				$brand_id = $brand_ids[$brand];
			}
			if(!$brand_id)
			{
				print "Can't find the brand id\n";
				exit(0);
			}

			$sub_category = $pq->find('meta[name=defaultParent]')->attr('content');

			// Next is figure out the sub category id
			if(isset($sub_categories[$sub_category]))
			{
				$sub_category_id = $sub_categories[$sub_category];
			}
			else
			{
				// Determine the "Other" sub_category_id
				switch($row['parent_category_id'])
				{
					case 1:
						$sub_category_id = 34; // women 
						break;
					case 2:
						$sub_category_id = 35; // men
						break;
					case 3:
						$sub_category_id = 36; // apt
						break;
					default:
						$sub_category_id = 0;
						break;
				}
			}

			// Update the brand_id and the sub_category_id
			$update_result = $db->query("UPDATE `c" . $company_id . "`.`" . $table_name . "` SET `brand_id`='" . $brand_id . "', `sub_category_id`='" . $sub_category_id . "' WHERE `id`='" . $row['id'] . "' LIMIT 1");
			if(!$update_result)
			{
				print "Unable to update the brand id and the sub category id\n" . $db->error . "\n";
				die();
			}

			$timeLimit = rand(0, 5);
			print "\t - Waiting " . $timeLimit . " s\n";
			sleep($timeLimit);
			$count++;
		}
		$result->free();
	}
	else
	{
		print "Error retrieving the product \n" . $db->error . "\n";
	}

	$db->close();
	exit(0);
?>
