<?php
/*
Plugin Name: WPML GeoIP Browser Language Redirect
Plugin URI: http://khromov.se
Description: Redirects users to their appropriate languages intelligently by utilizing the MaxMind GeoIP database
Version: 1.1
Author: khromov
Author URI: http://khromov.se
License: GPL2
*/



// http://wordpress.stackexchange.com/questions/100023/settings-api-with-arrays-example
//

class WPML_GeoIP_Browser_Language_Redirect
{
	/** Initialize and add actions */
	function __construct()
	{
		//Init script
		add_action('wp_print_scripts', array(&$this, 'enqueue_scripts'), 100);

		//Register AJAX endpoint
		add_filter('query_vars', array(&$this, 'register_vars'));
		add_action('wp', array(&$this, 'register_endpoints'));
		//template_redirect is suh-low
		//pre_get_posts is pretty close
		//wp too

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_page_test_plugin_1' ) );
			$this->digest_post_data();
		}
	}


	function digest_post_data(){

		if( isset( $_POST['language_mappings'] )  ){


			//$this->beautiful_print_r( $_POST );

			$location = 'options-general.php?page=wpml_geo_redirect_settings';

			// Put whatever comes as country-language code combo into an array
			$data_for_option = array();

			foreach( $_POST['language_mappings'] as $val ){

				if( $val['country'] != 'country_code_ddown_label' ) {


					if(	array_key_exists ( $val['country']  , $data_for_option ) ){
						$this->redirect_us( $location . '&duplicate_key' );
					}

					$data_for_option[ $val['country'] ] = $val['language'];

				}
			}


			// Remove any country code marked for removal
			if( isset( $_POST['remove_country_code'] ) ){
				foreach( $_POST['remove_country_code'] as $country_code ){
					unset( $data_for_option[$country_code] );
				}
			}


			// Save in DB and redirect
			update_option( 'wpml_geo_redirect_default_language' , trim( $_POST['default_redirect_language'] ) );
			update_option( 'wpml_geo_redirect_language_mappings' , $data_for_option );

			$this->redirect_us( $location . '&success' );
		}

	}


	public function redirect_us( $location ){
		header("Location: $location");
		exit();
	}


	public function add_admin_page_test_plugin_1(){

		add_options_page(
			'WPML GEO Redirect',
			'WPML GEO Redirect',
			'manage_options',
			'wpml_geo_redirect_settings',
			array( $this, 'test_plugin_1_admin_page' ) );
	}



	function display_language_code_dropdown( $table_row=0 , $lang_code_param='' , $is_default=false ){

		global $sitepress_settings;

		$args['skip_missing'] = intval($sitepress_settings['automatic_redirect'] == 1);
		$languages = apply_filters( 'wpml_active_languages', null, $args );

		if( $is_default ){
			$select_name    = 'default_redirect_language';
		}
		else {
			$select_name    = 'language_mappings[' . $table_row . '][language]';
			//$output         = '<select style="font-size:1.2em;height:inherit;" name="' . $select_name . '" >';
		}

		$output         = '<select name="' . $select_name . '" >';

		foreach( $languages as $language ) {

			$selected = $this->selected_html( $language['code'] , $lang_code_param );

			$output .= "<option {$selected} >{$language['code']}</option>";
		}

		$output .= "</select>";

		return $output;
	}


	function display_mm_country_code_dropdown( $table_row=0 , $country_code_param='' ){

		$mm_country_codes = array(
			'AP','EU','AD','AE','AF','AG','AI','AL','AM','CW','AO','AQ','AR','AS','AT','AU','AW','AZ','BA','BB','BD','BE','BF','BG',
			'BH','BI','BJ','BM','BN','BO','BR','BS','BT','BV','BW','BY','BZ','CA','CC','CD','CF','CG','CH','CI','CK','CL','CM','CN',
			'CO','CR','CU','CV','CX','CY','CZ','DE','DJ','DK','DM','DO','DZ','EC','EE','EG','EH','ER','ES','ET','FI','FJ','FK','FM',
			'FO','FR','SX','GA','GB','GD','GE','GF','GH','GI','GL','GM','GN','GP','GQ','GR','GS','GT','GU','GW','GY','HK','HM','HN',
			'HR','HT','HU','ID','IE','IL','IN','IO','IQ','IR','IS','IT','JM','JO','JP','KE','KG','KH','KI','KM','KN','KP','KR','KW',
			'KY','KZ','LA','LB','LC','LI','LK','LR','LS','LT','LU','LV','LY','MA','MC','MD','MG','MH','MK','ML','MM','MN','MO','MP',
			'MQ','MR','MS','MT','MU','MV','MW','MX','MY','MZ','NA','NC','NE','NF','NG','NI','NL','NO','NP','NR','NU','NZ','OM','PA',
			'PE','PF','PG','PH','PK','PL','PM','PN','PR','PS','PT','PW','PY','QA','RE','RO','RU','RW','SA','SB','SC','SD','SE','SG',
			'SH','SI','SJ','SK','SL','SM','SN','SO','SR','ST','SV','SY','SZ','TC','TD','TF','TG','TH','TJ','TK','TM','TN','TO','TL',
			'TR','TT','TV','TW','TZ','UA','UG','UM','US','UY','UZ','VA','VC','VE','VG','VI','VN','VU','WF','WS','YE','YT','RS','ZA',
			'ZM','ME','ZW','AX','GG','IM','JE','BL','MF','BQ','SS'
		);

		asort( $mm_country_codes );


		$select_name    = 'language_mappings[' . $table_row . '][country]';
		//$output         = '<select style="font-size:1.2em;height:inherit;" name="' . $select_name . '" >';
		$output         = '<select name="' . $select_name . '" >';
		$output         .= "<option value='country_code_ddown_label'>Country Code</option>";



		foreach( $mm_country_codes as $country_code ) {

			$selected = $this->selected_html( $country_code , $country_code_param );

			$output .= "<option {$selected} >{$country_code}</option>";
		}

		$output .= "</select>";

		return $output;
	}


	function selected_html( $value_1 , $value_2 ){
		return $selected = ($value_1 == $value_2 ? 'selected="selected"' : null);
	}


	public function test_plugin_1_admin_page(){

		global $sitepress_settings;

		/* default
		$language_mappings = array(
			'SE' => 'sv', //Sweden
			'NO' => 'nb', //Norway
			'FI' => 'fi', //Finland
			'DK' => 'da', //Denmark
			'US' => 'en', //USA
			'CA' => 'en'  //Canada
		);
		*/


		$language_mappings      = get_option( 'wpml_geo_redirect_language_mappings' );
		$default_language       = get_option( 'wpml_geo_redirect_default_language' );

		//$this->beautiful_print_r( $language_mappings );

		//$args['skip_missing']   = intval($sitepress_settings['automatic_redirect'] == 1);
		// $languages              = apply_filters( 'wpml_active_languages', null, $args );
		//$lang_num               = count( $languages );
		?>


		<div class="wrap">
			<div id="icon-plugins" class="icon32"></div>
			<h2>WPML GEO Redirect</h2>


			<?php
			if( isset($_GET['duplicate_key'] ) ){
				?>
				<div class="notice notice-error is-dismissible">
					<p>There has been an error. Country codes need to be unique.</p>
				</div>
				<?php
			}
			elseif( isset( $_GET['success'] )) {
				?>
				<div class="notice notice-success is-dismissible">
					<p>Data was updated.</p>
				</div>
				<?php
			}
			?>


			<form method="post" action="options-general.php?page=wpml_geo_redirect_settings" id="options_form" >
				<?php
				echo '<table class="form-table" >';
				echo "<tr>
						<td>Country Code / Language Code</td>
						<td width='83%'>Remove</td>
					</tr>";


				$table_row = 0;

				foreach( $language_mappings as $country_code => $lang_code){

					echo '<tr valign="top">';
					echo '<td>';
					echo $this->display_mm_country_code_dropdown( $table_row , $country_code );
					echo "<strong> => </strong>";
					echo $this->display_language_code_dropdown( $table_row , $lang_code );
					echo '</td>';
					echo '<td>';
					echo '<input type="checkbox" name="remove_country_code['.$table_row.']" value="' . $country_code . '">';
					echo '</td>';
					echo '</tr>';

					$table_row++;
				}

				echo '<tr valign="top" style="border-top: dotted black 2px;">';
				echo '<td>';
				echo $this->display_mm_country_code_dropdown( $table_row );
				echo "<strong> => </strong>";
				echo $this->display_language_code_dropdown( $table_row );
				echo '</td>';
				echo '<td>';
				echo "&nbsp;";
				echo '</td>';
				echo '</tr>';

				echo '<tr valign="top" style="border-top: dotted black 2px;">';
				echo '<td colspan="2">';
				echo "Any other place on this planet";
				echo "<strong> => </strong>";
				echo $this->display_language_code_dropdown( $table_row , $default_language , true );
				echo '</td>';
				echo '</tr>';


				echo "</table>";

				wp_nonce_field( 'test_plugin_1', 'test_plugin_1_form_nonce' );
				submit_button();
				?>
			</form>


		</div>
		<?php
	}


	function beautiful_var_dump( $things_to_dump ){
		echo "<pre style='margin-left: 200px;'>";
		var_dump( $things_to_dump );
		echo "</pre>";
	}
	function beautiful_print_r( $things_to_printr ){
		echo "<pre style='margin-left: 200px;'>";
		print_r( $things_to_printr );
		echo "</pre>";
	}


////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////




	/** Unload old browser redirect and add new one **/
	function enqueue_scripts()
	{
		global $sitepress, $sitepress_settings;

		//De-register old script
		wp_deregister_script('wpml-browser-redirect');

		//Register new one
		wp_enqueue_script('wpml-browser-redirect', plugins_url('js/browser-redirect-geoip.js', __FILE__) , array('jquery', 'jquery.cookie'));

		$args['skip_missing'] = intval($sitepress_settings['automatic_redirect'] == 1);

		//Build multi language urls array
		$languages = apply_filters( 'wpml_active_languages', null, $args );
		$language_urls = array();
		foreach($languages as $language)
			$language_urls[$language['language_code']] = $language['url'];

		//print_r($languages);

		//Cookie parameters
		$http_host = $_SERVER['HTTP_HOST'] == 'localhost' ? '' : $_SERVER['HTTP_HOST'];
		$cookie = array(
			'name' => '_icl_visitor_lang_js',
			'domain' => (defined('COOKIE_DOMAIN') && COOKIE_DOMAIN? COOKIE_DOMAIN : $http_host),
			'path' => (defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/'),
			'expiration' => $sitepress_settings['remember_language']
		);


		// Send params to javascript
		$params = array(
			'ajax_url' => plugins_url('ajax.php', __FILE__),
			'cookie'            => $cookie,
			'pageLanguage'      => defined('ICL_LANGUAGE_CODE')? ICL_LANGUAGE_CODE : get_bloginfo('language'),
			'languageUrls'      => $language_urls,
		);

		//Let's add the data!
		wp_localize_script('wpml-browser-redirect', 'wpml_browser_redirect_params', $params);
	}

	/**
	 * Register vars
	 *
	 * @param $vars
	 * @return array
	 */
	function register_vars($vars)
	{
		$vars[] = 'wpml_geoip';
		return $vars;
	}

	/**
	 * Checks for our magic var and performs actual work.
	 */
	function register_endpoints()
	{
		if(intval(get_query_var('wpml_geoip')) == 1)
		{
			include('WPML_GeoIP_IPResolver.class.php');
			$ipr = new WPML_GeoIP_IPResolver();

			$ipr->set_json_header();
			if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
				$tmp_ip_array = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
				$tmp_ip = $tmp_ip_array[0];
			} else {
				$tmp_ip = $_SERVER['REMOTE_ADDR'];
			}
			echo $ipr->ip_to_wpml_country_code($tmp_ip);
			die();
		}
	}
}

$wpml_gblr = new WPML_GeoIP_Browser_Language_Redirect();
