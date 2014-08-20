<?php	
	require_once "../../../wp-config.php";
	
	global $wpdb;
	
	
	//decode information
	if(isset($_GET['data'])&&isset($_GET['iv'])){
		
		$pass = get_option('fsnet_json_key');
		
		$plaintext = openssl_decrypt($_GET['data'], "aes128", $pass ,0,base64_decode($_GET['iv']));
		//echo $plaintext;
		if(strpos($plaintext, "datain") !== false){
			$in_arr = explode("|",$plaintext);
			$time_in = $in_arr[1];
			if(time() - (60*5) > $time_in){
				echo "[]";
				die();
			}
			
			$and_flg = false;
			$limit = "";
			$limit_num = -1;
			$where = " WHERE";
			$sel_fields ="";
			for($i = 2; $i < count($in_arr); $i++){
				$ex_arr = explode(":", $in_arr[$i]);
				if(strcmp($ex_arr[0], "limit") == 0){
					if($ex_arr[1] > -1){
						$limit = " LIMIT %d";
						$limit_num = $ex_arr[1];
					}
				}else if(strcmp($ex_arr[0], "display_members_only") == 0){
					
					if($ex_arr[1] == 1){
						if($and_flg){
							$where .= " AND";
						}
						$where .= " (is_member = 1 OR recv_benefits = 1)";
						$and_flg = true;
					}
				}else if(strcmp($ex_arr[0], "ignore_do_not_display_online") == 0){
					
					if($ex_arr[1] == 0){
						if($and_flg){
							$where .= " AND";
						}
						$where .= " do_not_publish_online=0";
						$and_flg = true;
					}
				}else if(strcmp($ex_arr[0], "display_fields") == 0){
					$mapping = array(
							"first name" => "name",
							"last name" => "last_name",
							"middle name" => "middle_name",
							"title" => "title",
							"email" => "email",
							"city" => "city",
							"state" => "state",
							"address1" => "address1",
							"address2" => "address2",
							"address3" => "address3",
							"country" => "country",
							"organization" => "employment_org"
					);
					$fields = explode(",", $ex_arr[1]);
					foreach($fields as $fcurr){
						$sel_fields.=$mapping[trim($fcurr)].",";
					}
					$sel_fields = rtrim($sel_fields, ",");
				}
			}
			 $query =  "SELECT " . $sel_fields . " FROM {$wpdb->prefix}fsnet_user_master".
				 $where . $limit;
			//echo $query;
			$res = [];
			if($limit_num > -1){
				$res = $wpdb -> get_results(
					$wpdb -> prepare ( $query, $limit_num)
				);
			}else{
				$res = $wpdb -> get_results($query);
				
			}
			echo json_encode($res);
		}
		
		
	}
	//echo json_encode($wpdb -> get_results("SELECT * FROM {$wpdb -> prefix}fsnet_user_master WHERE 1"));

?>