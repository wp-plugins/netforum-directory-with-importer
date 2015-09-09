<?php
/**
 * Plugin Name: fusionSpan Netforum Directory with Importer
 * Plugin URI: http://fusionspan.com
 * Description: Allows users to import netforum data and display netFORUM directories
 * Author: Gayathri Kher
 * Version: 1.0.9
 */

if (!defined('ABSPATH')) wp_die("Script should not be called directly");

class fs_netforum_plugin {
	private $updateFlag = true;
	static $table_shortcode = "member_table";
	static $json_shortcode = "member_table_url";
	static $database_version = "1.3";
	
	public function __construct(){
		add_action('admin_menu', array($this, 'fs_plugin_menu'));
		register_activation_hook(__FILE__, array($this, 'fs_install'));
		add_action( 'wp_enqueue_scripts', array($this,'javascript_table_load'));
		add_shortcode( self::$table_shortcode , array($this, 'json_table'));
		add_shortcode(self::$json_shortcode, array($this,'pure_json'));
		add_action( 'admin_enqueue_scripts', array($this, 'add_jquery_ui'));
		register_deactivation_hook(__FILE__, array($this, 'remove_json_pass'));
	}

	
	function javascript_table_load(){
		global $post;
		//add to header if shortcode is on page
		if( is_a($post, 'WP_Post') && (has_shortcode($post->post_content, self::$table_shortcode) || has_shortcode($post->post_content, self::$json_shortcode))){
			wp_enqueue_script('jquery');
			wp_enqueue_script('fsnet_tableDisplay',plugins_url('js/tableDisplay.js',__FILE__));
			
			wp_enqueue_script('fsnet_dataTablesMin','//cdn.datatables.net/1.10.8/js/jquery.dataTables.js');
			wp_enqueue_style('fsnet_dataTablesMin_css','https://cdn.datatables.net/1.10.8/css/jquery.dataTables.min.css');
			wp_enqueue_style('fsnet_dataTablesThemeRollerMin_css',plugins_url('css/jquery.dataTables_themeroller.min.css',__FILE__));
		} 
	}
	
	function remove_json_pass(){
		delete_option('fsnet_json_key');
	}
	
	/**
	 * Function that allows draggables for the import menu
	 * @param unknown $hook
	 */
	function add_jquery_ui($hook) {
		if( strpos($hook,'fs_netforum_plugin')=== false )
			return;
		wp_enqueue_script('jquery-ui-sortable');
		wp_enqueue_style('fsnet_import_style',plugins_url("css/importTable.css",__FILE__));
	}
	
	function pure_json($atts){
		$short_attrs = shortcode_atts( array(
				'limit' => -1,
				'display_members_only' => 1,
				'ignore_do_not_display_online' => 0,
				'display_fields' => 'first name, last name, title, email, city, state'
		), $atts);
	
	
		$keys = array_keys($short_attrs);
		$plaintext = "datain|".time();
		foreach($keys as $curr){
			$plaintext .= "|".$curr.":".$short_attrs[$curr];
		}
		$vals = implode(":", $short_attrs);
		$iv_size = openssl_cipher_iv_length("aes128");
	
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		$pass = get_option('fsnet_json_key');
		$ciphertext = openssl_encrypt($plaintext, "aes128", $pass, 0, $iv);
	
		$ciphertext= urlencode($ciphertext);
		return plugins_url('memberInfoToJson.php', __FILE__)."?data=".$ciphertext.'&iv='. urlencode(base64_encode($iv));
	}
	
function json_table($atts) {
		global $wpdb;
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
		$short_attrs = shortcode_atts( array(
        'limit' => -1,
		'display_members_only' => 1,
		'display_fields' => 'first name, last name, title, email, city, state',
		'ignore_do_not_display_online' => 0,
		'thead_color' => '#A1A0A0',
		'tfoot_color' => '#A1A0A0',
		'color' => '-1'
    	), $atts );
			
		$andFlag = false;
		$do_not_disp = "";
		if($short_attrs['ignore_do_not_display_online'] == 0){
			$do_not_disp = "do_not_publish_online=0";
			$andFlag = true;
		}
		
		$results=[];
		$fields = explode(",", $short_attrs['display_fields']);
		$out_str ="";
		$rows_to_display ="";
		foreach($fields as $curr_string){
					$curr = trim($curr_string);
					$out_str .="<th>".ucwords($curr)."</th>";
					if(!isset($mapping[$curr])){
						echo '<p>Incorrect fields listed in shortcode display_fields. Please check documentation for correct fields.<br> Your fields were: '. $short_attrs['display_fields'] .'</p>';
						return;
					} 
					$rows_to_display .= $mapping[$curr] .",";
		}
		
		$rows_to_display = rtrim($rows_to_display, ",");
		
		if($short_attrs['limit'] < 0 && $short_attrs['display_members_only'] == 0){
			if($short_attrs['ignore_do_not_display_online'] == 0){
				$results = $wpdb->get_results("SELECT ".$rows_to_display." FROM {$wpdb -> prefix}fsnet_user_master WHERE " . $do_not_disp);
			}else{
				$results = $wpdb->get_results("SELECT ".$rows_to_display." FROM {$wpdb -> prefix}fsnet_user_master");
			}
			
		}else if($short_attrs['limit'] >= 0 && $short_attrs['display_members_only'] == 0){
			if($short_attrs['ignore_do_not_display_online'] == 0){
				$results = $wpdb->get_results ( 
					$wpdb->prepare("SELECT ".$rows_to_display." FROM {$wpdb -> prefix}fsnet_user_master WHERE {$do_not_disp} LIMIT %d",$short_attrs['limit']));	
			}else{
				$results = $wpdb->get_results (
						$wpdb->prepare("SELECT ".$rows_to_display." FROM {$wpdb -> prefix}fsnet_user_master LIMIT %d",$short_attrs['limit']));
			}
		}else if($short_attrs['limit'] < 0 && $short_attrs['display_members_only'] == 1){
			if($andFlag){
				$do_not_disp.= " AND";
			}
			$results = $wpdb->get_results("SELECT ".$rows_to_display." FROM {$wpdb -> prefix}fsnet_user_master WHERE {$do_not_disp} (is_member = 1 OR recv_benefits = 1)");		
			
		}else if ($short_attrs['limit'] >= 0 && $short_attrs['display_members_only'] == 1){
			if($andFlag){
				$do_not_disp.= " AND";
			}
			$results = $wpdb->get_results($wpdb->prepare
			("SELECT ".$rows_to_display." FROM {$wpdb -> prefix}fsnet_user_master WHERE {$do_not_disp} (is_member = 1 OR recv_benefits = 1) LIMIT %d",
			$short_attrs['limit']));	
		}
		
		$retVal = "<script>//<![CDATA[ 
			displayTable('". addcslashes(json_encode($results), "'")."','" . $rows_to_display . "');"
				."//]]>
				</script>";
		
		$color_head = "#A1A0A0";
		$color_foot = "#A1A0A0";
		if(strcmp($short_attrs['color'], '-1') != 0){
			$color_foot = $short_attrs['color'];
			$color_head = $short_attrs['color'];
		}else{
			$color_head = $short_attrs['thead_color'];
			$color_foot = $short_attrs['tfoot_color'];
		}
			
		$retVal .= '
<div class="container">
	<section>
		<table id="example1" class="display compact"
			cellspacing="0" width="100%">
			<thead style="background-color: '. $color_head .' ;">
			<tr>'
		
				.$out_str.
			
			'</tr>
			</thead>
			<tfoot style="background-color: '. $color_foot . ';">
				<tr>'
				.$out_str.
				'</tr>
			</tfoot>
		</table>
	</section>
</div>
';
	return $retVal;
				
	}
	
	function fs_plugin_menu() {
		add_management_page("Netforum Directory", "Netforum Directory",
		 "manage_options", "fs_netforum_plugin_import", array($this, "generate_plugin_page"));
	}
	
	function generate_plugin_page() {
		global $wpdb;
		
		if ( !current_user_can("manage_options")) {
			wp_die(__("You do not have sufficient permission to access this page"));
		}
		
		if(isset($_POST['process_import'])){
			$this -> process_import();			
		}else{
			$this->display_import_page();
		}	
	}
	
	private function display_import_page(){
		
		?>
<script>
		  jQuery(function() {
			jQuery( "#sorttable, #unused_sorttable" ).sortable({
			      connectWith: ".connectedSortable",
				  receive: function(event,ui){
						if(jQuery(ui.item).attr("id")==="keep"){
							jQuery(ui.sender).sortable('cancel');
						}
				  }
		    }).disableSelection();
		  });

		  jQuery(function(){jQuery("#import_frm" ).submit(function( event ) {
				var data ="";
				
		        jQuery("#sorttable li").each(function(i, value){
		           var p = jQuery(value).text().toLowerCase().replace(/\s+/g, "_");
		           data += p+",";
			            
		        });
		        jQuery("form > [name='process_import']").val(data.slice(0, -1));
			});
		  });
		  </script>

			<div class="used_sort_items">
				<h4>Add fields to be imported here, make sure they have the same order as that in the csv file.
				Red text means that field is required.</h4>
				<ul id="sorttable" class="connectedSortable">
					<li id="keep">Customer Key</li>
					<li>First Name</li>
					<li>Middle Name</li>
					<li>Last Name</li>
					<li>Email</li>
					<li>Title</li>
					<li>Name Prefix</li>
					<li>Name Suffix</li>
					<li>Address Line 1</li>
					<li>Address Line 2</li>
					<li>Address Line 3</li>
					<li>City</li>
					<li>State</li>
					<li>ZipCode</li>
					<li>Country</li>
					<li>Phone No</li>
					<li>Domain Name</li>
					<li>Employment Organisation</li>
					<li id="keep">Do Not Publish Online</li>
					<li id="keep">Customer Member Flag</li>
					<li id="keep">Receives Benefits Flag</li>
				</ul>
			</div>
			
			<div class="unused_sort_items">
			<h4>&nbsp;Drag unused items here!</h4>
				<ul id="unused_sorttable" class="connectedSortable">
				</ul>
			</div>

<form id="import_frm" class="import_form" name="import_form"
	enctype="multipart/form-data" method="post"
	<?php echo 'action ="'.str_replace( '%7E', '~', $_SERVER['REQUEST_URI']).'"';?>>
	<input type="hidden" name="process_import" value="true" /> <label
		for="file_name">Select File to Import:&nbsp;&nbsp;</label> <input name="filename"
		id="file_name" type="file" style="margin-top: 5px" /><br> <input
		id="import_but" type="submit" value="Import Files" />
</form>

<div id="mem_desc">
	<p>You can display a table on any page using the [member_table] shortcode
		eg: [member_table display_fields = "first name, last name, city,
		state, address1" limit=500 color=#CDCDCD]</p>
</div>

<?php 
	}
	
	private function process_import(){
		//import here
	
		global $wpdb;
		
		$links = array(
			"customer_key" => "external_id",
			"first_name" => "name",
			"middle_name" => "middle_name",
			"last_name" => "last_name",
			"email" => "email",
			"title" => "title",
			"name_prefix" => "name_prefix",
			"name_suffix" => "name_suffix",
			"address_line_1" => "address1",
			"address_line_2" => "address2",
			"address_line_3" => "address3",
			"city" => "city",
			"state" => "state",
			"zipcode" => "zipcode",
			"country" => "country",
			"phone_no" => "phone_no",
			"domain_name" => "domain_name",
			"employment_organisation" => "employment_org",
			"do_not_publish_online" => "do_not_publish_online",
			"customer_member_flag" => "is_member",
			"receives_benefits_flag" => "recv_benefits"
		);	

		$local_path = $this->process_file_in();
		if($local_path === -1){
			echo "Error uploading file";
			return;
		}

		$local_path = str_replace("\\", "/", $local_path);
		
		$import_string = "LOAD DATA LOCAL INFILE '{$local_path}'"
						." INTO TABLE`{$wpdb -> prefix}fsnet_user_master`"
						." FIELDS TERMINATED BY ','"
						." ENCLOSED BY '\"'"
						." LINES TERMINATED BY '\n'"
						." IGNORE 1 LINES ( ";
		
		if(!isset($_POST['process_import'])){
			echo '<div class="updated"><p><strong>An Error Occurred During Import. 1</strong></p></div>';
			return;
		}
		
		$input_vals = explode(",", $_POST['process_import']);
		
		foreach($input_vals as $curr){
			//echo $curr . "<br>";
			if(!isset($links[$curr])){
				echo '<div class="updated"><p><strong>An Error Occurred During Import. '.$links[$curr] .' is not valid</strong></p></div>';
				return;
			}
			
			$import_string .= "`".$links[$curr]."`,";
		}
		$import_string = rtrim($import_string, ",");
		$import_string .=" );";
		//echo $import_string;
		$max_time = ini_get('max_execution_time'); 
		set_time_limit(0);
		if($wpdb -> query($import_string) === false){
			echo "An error occured, please check the path of your file";
		}else{
			echo '<div class="updated"><p><strong>Items Imported</strong></p></div>';
			unset($_POST['process_import']);
			$this->generate_plugin_page();
		}
		set_time_limit($max_time);
		if($local_path !== -1){
			unlink($local_path);
		}
	}
	
	private function process_file_in(){
	
		if ($_FILES['filename']['error'] > 0){
			"An error occured, please try again";
				
			return -1;
		}else{
			$filename = $_FILES['filename']['name'];
			if(strcmp(pathinfo($filename, PATHINFO_EXTENSION),"csv") != 0){
				echo "Incorrect file type uploaded";
				return -1;
			}
				
			$filepath =  dirname(__FILE__)."/". $filename;
			if (move_uploaded_file($_FILES['filename']['tmp_name'], $filepath)){
				return $filepath;
			}
		}
		return -1;
	}
	
	/**
	 * Called when the plugin is activated
	 * This method checks if the wordpress database
	 * is correctly setup for the plugin to work properly.
	 * It checks if the necessary tables exsists, if not it 
	 * creates them.
	 */
	function fs_install(){
		global $wpdb;
		$chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
		$pass ="";
		$len = strlen($chars);
		for ($i = 0; $i < 12; $i++){
			$pass .= $chars[mt_rand(0, $len - 1)];
		}
		
		add_option('fsnet_json_key',$pass,"","no");
		
		if (is_numeric(get_option('fsnet_db_version_sync', 0)) && get_option('fsnet_db_version_sync') < self::$database_version){
		
			$wp_charset_collate = "";
			if(!empty($wpdb->charset)){
				$wp_charset_collate = "DEFAULT CHARACTER SET '{$wpdb->charset}'";
			}
			
			if(!empty($wpdb->collate)){
				$wp_charset_collate .= " COLLATE '{$wpdb->collate}'";
			}					
			
			$sql_users = "CREATE TABLE {$wpdb->prefix}fsnet_user_master (
			id int(11) NOT NULL AUTO_INCREMENT,
			external_id varchar(190) DEFAULT NULL,
			name varchar(190) DEFAULT NULL,
			email varchar(190) DEFAULT NULL,
			middle_name varchar(190) DEFAULT NULL,
			last_name varchar(190) DEFAULT NULL,
			title varchar(100) DEFAULT NULL,
			name_prefix varchar(190) DEFAULT NULL,
			name_suffix varchar(190) DEFAULT NULL,
			address1 varchar(190) DEFAULT NULL,
			address2 varchar(190) DEFAULT NULL,
			address3 varchar(190) DEFAULT NULL,
			city varchar(190) DEFAULT NULL,
			state varchar(190) DEFAULT NULL,
			zipcode varchar(190) DEFAULT NULL,
			country varchar(190) DEFAULT NULL,
			phone_no varchar(190) DEFAULT NULL,
			designation varchar(190) DEFAULT NULL,
			department varchar(190) DEFAULT NULL,
			domain_name varchar(190) DEFAULT NULL,
			employment_org varchar(190) DEFAULT NULL,
			position varchar(190) DEFAULT NULL,
			do_not_publish_online BOOLEAN NOT NULL DEFAULT FALSE,
			is_member BOOLEAN NOT NULL DEFAULT FALSE,
			recv_benefits BOOLEAN NOT NULL DEFAULT FALSE,
			cst_type varchar(80) DEFAULT 'Individual',
			UNIQUE KEY external_id (external_id),
			PRIMARY KEY  (id)
			) {$wp_charset_collate};";
			
			
			// sets up/changes the appropiate tables based on the code above
			require_once ABSPATH ."wp-admin/includes/upgrade.php";
			dbDelta($sql_users);
	 
		}

		//checks if the table was created and set the version if it is
		if($wpdb->get_var("SHOW TABLES LIKE '" . $wpdb -> prefix . "fsnet_user_master'") === $wpdb->prefix."fsnet_user_master") {
			update_option('fsnet_db_version_sync',self::$database_version);
		}else{
			delete_option('fsnet_db_version_sync');
		}
	}
			
}

new fs_netforum_plugin();
?>
