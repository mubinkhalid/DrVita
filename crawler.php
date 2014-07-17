<?php
	error_reporting(0);
	require_once('parser.php');
	$scrapper = new scrap_drvita();
	$codes = $scrapper->get('input.csv');
	$inserted = file("crawled_products.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach($codes as $code){
		if (!(in_array($code['UPC'], $inserted)))
		$scrapper->getOffers($code);
	}
	array('SKU' => 'RNW58567', 'UPC' => '631257158567');
	class scrap_drvita{
		public $error_file;
		public $line;
		public $use_sku;
		public function get($fileName)
		{
			$csv = new parseCSV();
			$csv->auto($fileName);
			if (count($csv->data)) {
				return $csv->data;
			} else
				return false;
		} 
		public function __construct(){
			//here will be some default data
			$this->error_file  = 'Error_Log.txt';
			$this->line = '_________________________________________________________________________________';
			$this->use_sku = false;
		}
		private function build_url($code){
			return "http://www.drvita.com/search/_/N-7t5?Ntt=$code&Dy=1&Nty=1&Ns=product.numberSold%7C1&N=7t5";
		}
		//function will take an array as input that will contain Product UPC, SKU, NAME as Associate array
		public function getOffers($array){
point:
			if($this->use_sku){
				$url = $this->build_url($array['SKU']);
				$this->use_sku = false;
			}else{
				$url = $this->build_url($array['UPC']);
			}
			/* will go to that URL and get the offer/s */
			$res = json_decode($this->spider($url), true);
			if(count($res)<1){
				file_put_contents($this->error_file, "Failed to Connect the server While requesting the following code.  UPC: $array[UPC]".PHP_EOL."Severity => Fatal".PHP_EOL.$this->line.PHP_EOL, FILE_APPEND);
				continue;
			}
			$res = $res['query']['results']['body']['div'];
			$p_url = '';
			if(isset($res[2]['div']['a']['href']))
			$p_url = 'http://www.drvita.com'. ($res[2]['div']['a']['href']);
			else
			$p_url = 'http://www.drvita.com'. ($res[2]['div'][1]['a']['href']);
			if($res && count($res) > 1){
				// There is some data that have to be parsed.
				$page = json_decode($this->spider($p_url), true);
				$page = $page['query']['results']['body']['div'];
				if($page && count($page) > 1){
					//check wethrer it returned some prodect or not?
					//If do, then ok, otherwise call it using SKU/PC
					
					if($this->checkPage($page)){
						$this->getProductInformation($page, $p_url, $array['UPC']);
					}
					else{
						$this->use_sku = true;
						goto point;
					}
				}
				file_put_contents("crawled_products.txt", $array['UPC'] . PHP_EOL, FILE_APPEND);
			}
			else{
				file_put_contents($this->error_file, "Failed to Connect the server While requesting the following code.  UPC: $array[UPC]".PHP_EOL."Severity => Fatal".PHP_EOL.$this->line.PHP_EOL, FILE_APPEND);

			}
		}
		private function checkPage($page){
			if(isset($page[3]['div'][1]['div'][1]['h1'])){
				return true;
			}
			else{
				return false;
			}
		}
		private function save_image($url){
			$path = "images/" . $this->name($url);
			$contents = file_get_contents($url);
			if(!file_put_contents($path, $contents)){
				return false;
			}else{
				return true;
			} 
		}
		private function name($path){
			$img_link = (parse_url($path));
			return str_replace('/', 'M', $img_link['path']);
			
		}
		public function getProductInformation($page, $url, $code){
			if(!isset($page[3]['div'][1]['div'][1]['h1'])){
				file_put_contents($this->error_file, "Failed to Drab Information While requesting the following code.  UPC: $code".PHP_EOL."Severity => Fatal".PHP_EOL.$this->line.PHP_EOL, FILE_APPEND);
				continue;
			}
			$data['title'] = ($page[3]['div'][1]['div'][1]['h1']);
			$path = ($page[3]['div'][1]['div'][0]['table']['tr']['td'][1]['div']['img']['src']);
			$data['image'] = $this->name($path);
			if(!$this->save_image($path)){
				file_put_contents($this->error_file, "Image downloading failed for the following code.  UPC: $code".PHP_EOL."Severity => Warning".PHP_EOL.$this->line.PHP_EOL, FILE_APPEND);
			}
			$data['in_stock'] = ($page[3]['div'][1]['div'][1]['p'][0]['content']);
			$data['UPC'] = trim(str_replace('UPC', '', (str_replace(':', '', ($page[3]['div'][1]['div'][1]['p'][1]['content'])))));
			$data['SKU'] = trim(str_replace('SKU', '', (str_replace(':', '', ($page[3]['div'][1]['div'][1]['p'][2]['content'])))));
			$weight = explode('(', $page[3]['div'][1]['div'][1]['p'][3]['content']);
			$data['shipping_weight'] = trim($weight[0]);
			if(isset($page[3]['div'][1]['div'][2]['form'][0]['table']['tr']['td'][1]['table']['tr']))
				$price = $page[3]['div'][1]['div'][2]['form'][0]['table']['tr']['td'][1]['table']['tr'];
			else{
				file_put_contents($this->error_file, "Price Capture failed for the following code.  UPC: $code".PHP_EOL."Severity => Serious".PHP_EOL.$this->line.PHP_EOL, FILE_APPEND);
			}
			$data['retail_price'] = $price[0]['td'][1]['span']['content'];
			$data['price'] = $price[1]['td'][1]['span'];
			$data['discount'] = $price[2]['td'][1]['p'];
			$data['url'] = $url;
			$this->put_data($data);
			$data = array();
		}
		private function put_data($line){
			$fp = fopen("./data.csv", 'a');

		//	foreach ($data as $line)
			{
				
				if (isset($line['title']))
				{
					$val['Title'] = $line['title'];
				} else
				{
					$val['Title'] = "Nill";
				}
				if (isset($line['UPC']))
				{
					$val['UPC'] = $line['UPC'];
				} else
				{
					$val['UPC'] = "Nill";
				}
				if (isset($line['SKU']))
				{
					$val['SKU'] = $line['SKU'];
				} else
				{
					$val['SKU'] = "Nill";
				}
				if (isset($line['price']))
				{
					$val['Price'] = $line['price'];
				} else
				{
					$val['Price'] = "Nill";
				}
				if (isset($line['retail_price']))
				{
					$val['retail_price'] = $line['retail_price'];
				} else
				{
					$val['retail_price'] = "Nill";
				}
				if (isset($line['discount']))
				{
					$val['discount'] = $line['discount'];
				} else
				{
					$val['discount'] = "Nill";
				}
				if (isset($line['in_stock']))
				{
					$val['Availability'] = $line['in_stock'];
				} else
				{
					$val['Availability'] = "Nill";
				}

				if (isset($line['shipping_weight']))
				{
					$val['shipping_weight'] = $line['shipping_weight'];
				} else
				{
					$val['shipping_weight'] = "Nill";
				}
				if (isset($line['url']))
				{
					$val['url'] = $line['url'];
				} else
				{
					$val['url'] = "Nill";
				}
				if (isset($line['image']))
				{
					$val['Image'] = $line['image'];
				} else
				{
					$val['Image'] = "Nill";
				}
				if ($val['Title'] !== "Nill" && $val['Image'] !== "Nill" && $val['Price'] !==
					"Nill" && $val['url'] !== "Nill")
				{
					fputcsv($fp, $val);
					//print_r($val);
				}
			}
			fclose($fp);
		}
		public function getHistoricalInformation(){}
		public function spider($url)
		{
			/* function will take url as argument and will return that page as JvaScript Object Notation. */
			$yql_base_url = "http://query.yahooapis.com/v1/public/yql";
			$url = "select * from html where url = '$url'";

			$yql_query_url = $yql_base_url . "?q=" . urlencode($url);
			$yql_query_url .= "&format=json";
			$session = curl_init($yql_query_url);
			curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
			$json = curl_exec($session);
			curl_close($session);
			return $json;
		}
	}
	
?>