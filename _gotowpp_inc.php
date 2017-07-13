<?php

define ( 'GOTOWP_PERSONAL_PLUGIN_PATH', plugin_dir_path ( __FILE__ ) );
define ( 'GOTOWP_PERSONAL_PLUGIN_URL', plugin_dir_url ( __FILE__ ) );
define ( 'GOTOWP_PERSONAL_PLUGIN_SLUG', 'gotowp-g2w' );

$webinarErrors = new WP_Error ();

function gotowp_personal_install() {
    
    if (! function_exists ( 'curl_exec' )) {
        deactivate_plugins ( __FILE__ );
        wp_die ( "Sorry, but you can't run this plugin, it requires curl." );
    }
    
    global $wpdb;
    global $charset_collate;
    
    $webinar_table = $wpdb->prefix . "gtwbundle_webinars";
    
    if ($wpdb->get_var ( "SHOW TABLES LIKE '$webinar_table'" ) != $webinar_table) {
        
        $sql = "CREATE TABLE IF NOT EXISTS $webinar_table (
		id int(11) NOT NULL AUTO_INCREMENT,
		postid int(11) NOT NULL,
		type varchar(10) DEFAULT NULL,
		firstName varchar(30) DEFAULT NULL,
		lastName varchar(30) DEFAULT NULL,
		email varchar(50) DEFAULT NULL,
		source varchar(50) DEFAULT NULL,
		webinar_id varchar(50) NOT NULL,
		amount varchar(50) NOT NULL,
		formdata longtext NOT NULL,
		postdata longtext NOT NULL,
		payment_response longtext NOT NULL,
		payment_type varchar(30) DEFAULT NULL,
		status TINYINT(1) NOT NULL,
		PRIMARY KEY (id)
		) $charset_collate;";
        
        require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta ( $sql );
    }
    
    
    if (get_option ( 'gotowp_premium_organizer_key' ) === false) {
        add_option ( 'gotowp_premium_organizer_key', '', '', 'yes' );
    }
    
    if (get_option ( 'gotowp_premium_access_token' ) === false) {
        add_option ( 'gotowp_premium_access_token', '', '', 'yes' );
    }
    
    if (get_option ( 'gotowp_premium_payment_mode' ) === false) {
        add_option ( 'gotowp_premium_payment_mode', '', '', 'yes' );
    }
    
    if (get_option ( 'gotowp_premium_payment_email' ) === false) {
        add_option ( 'gotowp_premium_payment_email', '', '', 'yes' );
    }
    
    if (get_option ( 'gotowp_premium_payment_return_url' ) === false) {
        add_option ( 'gotowp_premium_payment_return_url', '', '', 'yes' );
    }
    
    if (get_option ( 'gotowp_premium_authorizenet_api_login_id' ) === false) {
        add_option ( 'gotowp_premium_authorizenet_api_login_id', '', '', 'yes' );
    }
    
    if (get_option ( 'gotowp_premium_authorizenet_transaction_key' ) === false) {
        add_option ( 'gotowp_premium_authorizenet_transaction_key', '', '', 'yes' );
    }
    
    if (get_option ( 'gotowp_premium_authorizenet_mode' ) === false) {
        add_option ( 'gotowp_premium_authorizenet_mode', '', '', 'yes' );
    }
}


function gotowp_personal_deactivation_func() {
    flush_rewrite_rules ();
}


add_filter ( 'plugin_action_links', 'gotowp_personal_plugin_action_links', 10, 2 );
function gotowp_personal_plugin_action_links($links, $file) {
    if ($file != GOTOWP_PERSONAL_PLUGIN_BASENAME){ return $links;}
    $settings_link = '<a href="' . menu_page_url ( 'gotowp_personal', false ) . '">' . esc_html ( __ ( 'Settings', 'gotowp_personal' ) ) . '</a>';
    array_unshift ( $links, $settings_link );
    return $links;
}


class GotowppreDateTime extends DateTime {
    public function setTimestamp($timestamp) {
        $date = getdate ( ( int ) $timestamp );
        $this->setDate ( $date ['year'], $date ['mon'], $date ['mday'] );
        $this->setTime ( $date ['hours'], $date ['minutes'], $date ['seconds'] );
    }
    public function getTimestamp() {
        return $this->format ( 'U' );
    }
}



function gotowp_personal_can_show_payment($amount){
    $show_payment = false;
    
    $payment_email = get_option ( 'gotowp_premium_payment_email', '' );
    $authorizenet_api_login_id= get_option ( 'gotowp_premium_authorizenet_api_login_id', '' );
    $authorizenet_transaction_key= get_option ( 'gotowp_premium_authorizenet_transaction_key', '' );
    
    if ($amount <= 0 || ($payment_email == '' && ($authorizenet_api_login_id=='' || $authorizenet_transaction_key==''))) {
        $show_payment = false;
    }else{
        $show_payment = true;
    }
    
    return $show_payment;
    
}

function gotowp_personal_enqueue_scripts() {
    global $post;
    $webinar_option_key = 'gotowp_premium_webinar_shop_page';
    $webinar_shop_page = get_option ( $webinar_option_key );
    
    if (is_object($post) && gotowp_personal_has_shortcode ( 'register_webinar' ) ||  $post->ID == $webinar_shop_page) {
        if (! wp_script_is ( 'jquery' )) {
            wp_enqueue_script ( 'jquery', GOTOWP_PERSONAL_PLUGIN_URL . 'javascripts/jquery-1.8.3.min.js' );
        }
        wp_enqueue_script ( 'validation_js', GOTOWP_PERSONAL_PLUGIN_URL . 'javascripts/jquery.validate.min.js', array ('jquery'     ) );
        wp_enqueue_script ( 'front_js', GOTOWP_PERSONAL_PLUGIN_URL . 'javascripts/front.js', array (    'jquery' ) );
    }
}

add_action ( 'wp_enqueue_scripts', 'gotowp_personal_enqueue_scripts' );
add_action ( 'wp_enqueue_scripts', 'gotowp_personal_enqueue_styles' );

function gotowp_personal_enqueue_styles() {
    global $post;
    
    $webinar_option_key = 'gotowp_premium_webinar_shop_page';
    $webinar_shop_page = get_option ( $webinar_option_key );
    
    if (is_object($post) && gotowp_personal_has_shortcode ( 'register_webinar' ) || $post->ID == $webinar_shop_page) {
        wp_enqueue_style ( 'gotowp_personal_public_css', GOTOWP_PERSONAL_PLUGIN_URL . 'public.css' );
    }
}

add_action ( 'admin_init', 'gotowp_personal_plugin_admin_init' );
function gotowp_personal_plugin_admin_init() {
    if (isset ( $_GET ['webinar_action'] ) && trim ( $_GET ['webinar_action'] ) == 'update_webinars') {
        $response = gotowp_personal_update_webinars ();
        if ($response) {
            echo "webinars updated successfully";
        }
    }
}

add_action ( 'admin_menu', 'gotowp_personal_admin_menu_func' );
function gotowp_personal_admin_menu_func() {
    $page = add_options_page ( 'GoToWP G2W Registration', 'GoToWP Personal', 'manage_options', 'gotowp_personal', 'gotowp_personal_add_records' );
}

add_action ( 'admin_enqueue_scripts', 'gotowp_personal_plugin_admin_enqueue_scripts_func' );
function gotowp_personal_plugin_admin_enqueue_scripts_func() {
    $screen = get_current_screen ();
    
    if ('settings_page_gotowp_personal' == $screen->id) {
        if (! wp_script_is ( 'jquery' )) {
            wp_enqueue_script ( 'jquery', GOTOWP_PERSONAL_PLUGIN_URL . 'javascripts/jquery-1.8.3.min.js' );
        }
        wp_enqueue_script ( 'validation_js', GOTOWP_PERSONAL_PLUGIN_URL . 'javascripts/jquery.validate.min.js', array ('jquery'     ) );
        wp_register_script ( 'tab_js', GOTOWP_PERSONAL_PLUGIN_URL . 'tabs/js/jquery.easytabs.min.js', array ('jquery' ), '', true );
        wp_enqueue_script( 'clipboard',GOTOWP_PERSONAL_PLUGIN_URL.'javascripts/clipboard.min.js');
        wp_register_script ( 'gotowp_personal_admin_js', GOTOWP_PERSONAL_PLUGIN_URL . 'admin.js', array (    'jquery' ), '', true );
        
        wp_register_style ( 'tab_css', GOTOWP_PERSONAL_PLUGIN_URL . 'tabs/css/mstabs.css' );
        wp_register_style ( 'gotowp_personal_admin_css', GOTOWP_PERSONAL_PLUGIN_URL . 'admin.css' );
        
        wp_enqueue_script ( 'tab_js' );
        wp_enqueue_script ( 'gotowp_personal_admin_js' );
        wp_localize_script ( 'ajax_request', 'MyAjax', array ('ajaxurl' => admin_url ( 'admin-ajax.php' )) );
        
        wp_enqueue_style ( 'tab_css' );
        wp_enqueue_style ( 'gotowp_personal_admin_css' );
    }
}


function gotowp_personal_add_source_field(){
    global $wpdb;
    global $charset_collate;
    $webinar_table = $wpdb->prefix . "gtwbundle_webinars";
    $table_result = $wpdb->query( $wpdb->prepare( "SHOW TABLES LIKE '%s'",$webinar_table));
    if($table_result ){
        $src_result = $wpdb->query("SHOW COLUMNS FROM $webinar_table LIKE '%source%'");
        if(empty($src_result) || !$src_result){
            $sql = "ALTER TABLE $webinar_table ADD source VARCHAR(50) AFTER email";
            $res = $wpdb->query("ALTER TABLE $webinar_table ADD source VARCHAR(50) NOT NULL AFTER email");
            if($res){
                
            }
        }
    }
}



add_action ( 'init', 'gotowp_personal_save_before_payment' );

function gotowp_personal_save_before_payment() {
    
    gotowp_personal_add_source_field();
    
    global $webinarErrors;
    if (isset ( $_REQUEST ['action'] ) && trim ( $_REQUEST ['action'] ) == 'registerwebinar' && trim ( $_REQUEST ['webinarid'] ) != '') {
        
        if(!gotowp_personal_is_captcha_enabled() || gotowp_form_is_validate_captcha($_POST['captcha_code'])){
            
            unset($_POST['captcha_code']);
            
            $webinarid = trim ( $_REQUEST ['webinarid'] );
            $organizer_key = trim ( get_option ( 'gotowp_premium_organizer_key' ) );
            $access_token = trim ( get_option ( 'gotowp_premium_access_token' ) );
            
            $gtw_url = "https://api.citrixonline.com/G2W/rest/organizers/" . $organizer_key . "/webinars/" . $webinarid . "/registrants";
            
            $headers = array (
                "HTTP/1.1",
                "Accept: application/json",
                "Accept: application/vnd.citrix.g2wapi-v1.1+json",
                "Content-Type: application/json",
                "Authorization: OAuth oauth_token=$access_token"
            );
            
            $curl = curl_init ();
            curl_setopt ( $curl, CURLOPT_POST, 0 );
            curl_setopt ( $curl, CURLOPT_HTTPHEADER, $headers );
            curl_setopt ( $curl, CURLOPT_URL, $gtw_url );
            curl_setopt ( $curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0 );
            curl_setopt ( $curl, CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, 1 );
            $response = curl_exec ( $curl );
            curl_close ( $curl );
            
            $request = gotowp_personal_json_decode ( $response );
            $errors = array ();
            $error_count = 0;
            
            
            
            
            
            if (isset ( $request->errorCode )) {
                $msg = 'This was some error please try again';
                $errors [] = $msg;
                $webinarErrors->add ( 'broke', $msg );
                $error_count ++;
            } else {
                $emails = array ();
                foreach ( $request as $val ) {
                    $emails [] = $val->email;
                }
                
                if (isset ( $_REQUEST ['email'] ) && in_array ( $_REQUEST ['email'], $emails )) {
                    $msg = 'This Email is already registered with this webinar';
                    $errors [] = $msg;
                    $webinarErrors->add ( 'broke', $msg );
                    $error_count ++;
                } else {
                    $webinar_fields = array ();
                    
                    $webinarid = trim ( esc_attr ( $_POST ['webinarid'] ) );
                    $registration_fields = gotowp_personal_get_registration_fields ( $webinarid );
                    $fields = $registration_fields->fields;
                    
                    foreach ( $fields as $fld ) {
                        if (! isset ( $_POST [$fld->field] ) || $_POST [$fld->field] == '') {
                            if ($fld->required) {
                                $error_count ++;
                            }
                        }
                        
                        $webinar_fields [$fld->field] = $_POST [$fld->field];
                    }
                    
                    
                    
                    if (isset ( $registration_fields->questions ) && count ( $registration_fields->questions ) > 0) {
                        
                        $responses = array ();
                        
                        foreach ( $registration_fields->questions as $row ) :
                        
                        $field_name = $row->questionKey;
                        $field_name = trim ( $field_name );
                        $value = $_POST [$field_name];
                        
                        if (! $value) {
                            $value = '';
                        }
                        
                        $question = array ();
                        $question ['questionKey'] = $row->questionKey;
                        if (isset ( $row->answers )) {
                            $question ['answerKey'] = $value;
                        } else {
                            $question ['responseText'] = $value;
                        }
                        $responses [] = $question;
                        endforeach
                        ;
                        
                        $webinar_fields ['responses'] = $responses;
                    }
                    
                    
                    
                    
                }
            }
            
            if ($error_count == 0) {
                
                global $wpdb;
                $webinar_table = $wpdb->prefix . "gtwbundle_webinars";
                $postid = $_POST ['postid'];
                $type = $_POST ['type'];
                
                $item_name1 = $_POST ['item_name'];
                
                $amount = esc_attr ( $_POST ['amount'] );
                
                if(isset($_POST ['source'])){
                    $source = trim(esc_attr ( $_POST ['source'] ));
                }else{
                    $source = '';
                }
                
                $return_page_url = false;
                if(isset($_POST['returnpageid'])){
                    $return_page_url   = get_permalink(trim(esc_attr($_POST['returnpageid'])));
                }
                
                
                if (isset ( $_POST ['payment_method'] ) && $amount > 0) {
                    $payment_type = $_POST ['payment_method'];
                    $status = 0;
                } else {
                    $payment_type = 'free';
                    $status = 1;
                }
                
                $post_arr = $_POST;
                
                /*             if ($type == 'list') {
                 unset ( $post_arr ['webinars_list'] );
                 unset ( $post_arr ['webinars_list'] );
                 }
                 
                 if (isset ( $post_arr ['payment_method'] )) {
                 unset ( $post_arr ['payment_method'] );
                 }
                 
                 unset ( $post_arr ['type'] );
                 unset ( $post_arr ['amount'] );
                 unset ( $post_arr ['action'] );
                 unset ( $post_arr ['submit'] );
                 unset ( $post_arr ['postid'] );
                 unset ( $post_arr ['webinarid'] ); */
                
                $webinar_fields['source']  =   $source;
                
                $formdata = json_encode ( $webinar_fields );
                
                $payment_response = '';
                
                $data = array (
                    'postid' => esc_attr ( $_POST ['postid'] ),
                    'type' => esc_attr ( $_POST ['type'] ),
                    'firstname' => esc_attr ( $_POST ['firstName'] ),
                    'lastname' => esc_attr ( $_POST ['lastName'] ),
                    'email' => esc_attr ( $_POST ['email'] ),
                    'source' => esc_attr ( $_POST ['source'] ),
                    'webinar_id' => esc_attr ( $_POST ['webinarid'] ),
                    'amount' => $amount,
                    'formdata' => $formdata,
                    'postdata' => json_encode ( $_POST ),
                    'payment_response' => $payment_response,
                    'payment_type' => $payment_type,
                    'status' => $status
                );
                
                $row_count= $wpdb->insert ( $webinar_table, $data );
                
                $lastid = $wpdb->insert_id;
                
                $_SESSION ['lastid'] = $lastid;
                
                $show_payment = gotowp_personal_can_show_payment($amount);
                
                if($row_count){
                    
                    if (!$show_payment) {
                        webinar_curl_registration ( $lastid );
                        custom_wp_redirect ($return_page_url);
                    }
                    
                    else if (isset ( $_REQUEST ['payment_method'] ) && $_REQUEST ['payment_method'] == 'pp') {
                        $payment_mode = trim ( get_option ( 'gotowp_premium_payment_mode' ) );
                        $payment_email = trim ( get_option ( 'gotowp_premium_payment_email' ) );
                        $site_url = get_option ( 'home' );
                        
                        if ($payment_mode == 'LIVE') {
                            $urls = 'https://www.paypal.com/cgi-bin/webscr';
                        } else {
                            $urls = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
                        }
                        
                        $currency_code = get_option ( 'gotowp_premium_currency_code' );
                        
                        /*
                         * global $wp_rewrite;
                         *
                         * if($wp_rewrite->using_permalinks()){
                         * $return_url=$site_url.'/return_action/paypal';
                         * $notify_url=$site_url.'/ipn_action/paypal';
                         * }else{
                         * $return_url=$site_url.'&return_action=paypal';
                         * $notify_url=$site_url.'&ipn_action=paypal';
                         * }
                         */
                        
                        if (get_option ( 'gotowp_premium_payment_return_url' )) {
                            $payment_return_url = trim ( get_option ( 'gotowp_premium_payment_return_url' ) );
                        } else {
                            $payment_return_url = trailingslashit ( $site_url ) . '?return_action=paypal';
                        }
                        
                        if (get_option ( 'gotowp_premium_paypal_ipn_url' )) {
                            $notify_url = trim ( get_option ( 'gotowp_premium_paypal_ipn_url' ) );
                        } else {
                            $notify_url = trailingslashit ( $site_url ) . '?ipn_action=paypal';
                        }
                        
                        if (! filter_var ( $payment_return_url, FILTER_VALIDATE_URL )) {
                            $payment_return_url = ( int ) $payment_return_url;
                            if ($payment_return_url > 0) {
                                $payment_return_url = get_permalink ( $payment_return_url );
                            }
                        }
                        
                        $return_page_url = false;
                        if(isset($_POST['returnpageid'])){
                            $return_page_url   = get_permalink(trim(esc_attr($_POST['returnpageid'])));
                            $payment_return_url = $return_page_url;
                        }
                        
                        // <input type="hidden" name="item_number" value="' . $webinarid . '">
                        
                        $paypalForm = '<form action="' . $urls . '" method="post" name="webinarpayment">
                                <input type="hidden" name="cmd" value="_xclick">
                                <input type="hidden" name="business" value="' . $payment_email . '">
                                <input type="hidden" name="item_name" value="' . stripslashes ( htmlentities ( $item_name1, ENT_QUOTES ) ) . '">
                                <input type="hidden" name="amount" value="' . $amount . '">
                                <input type="hidden" name="currency_code" value="' . $currency_code . '">
                                <input type="hidden" name="return" value="' . $payment_return_url . '">
                                <input type="hidden" name="custom" value="' . $lastid . '" />
                                <input type="hidden" name="rm" value="2" />
                                <input type="hidden" name="no_note" value="0">
                                <input type="hidden" name="custom" value=' . $lastid . ' >
                                <input type="hidden" name="notify_url" value="' . $notify_url . '" >
                                </form>';
                        
                        $paypalForm .= "<script>document.webinarpayment.submit();</script>";
                        
                        echo $paypalForm;
                    }
                }
                
                
                
            }
            
        }else{
            $msg = '<div class="error-msg">wrong captcha code, please use correct one</div>';
            $webinarErrors->add ( 'broke', $msg );
        }
        
    }
}


add_shortcode ( "register_webinar", 'gotowp_personal_registration_form_func' );
function gotowp_personal_registration_form_func($atts) {
    global $webinarErrors;
    extract ( shortcode_atts ( array (
        'webid' => '',
        'amount' => '',
        'pageid'=>'',
        'source' => '',
        'type' => 'single',
        'include_ids' => '',
        'exclude_ids' => '',
        'days' => '',
    ), $atts )
        );
    
    $webid = trim ( $webid );
    $date_title = '';
    $subject = '';
    $type1= trim($type);
    $source=trim($source);
    $pageid=trim($pageid);
    
    
    
    if(isset($atts['include_ids']) && trim($include_ids) !=''){
        $include_ids = explode(',',$include_ids);
    }else{
        $include_ids = array();
    }
    
    if(isset($atts['exclude_ids']) && trim($exclude_ids) !=''){
        $exclude_ids = explode(',',$exclude_ids);
    }else{
        $exclude_ids = array();
    }
    
    $days = (int) $days;
    
    
    if ($type1 == 'single' && $webid != '') {
        $webinar = gotowp_personal_get_webinar ( $webid );
        
        if (isset ( $webinar->webinarKey )) {
            $price_all = get_option ( 'gotowp_premium_webinar_price_all' );
            $webinar_price_check = get_option ( 'gotowp_premium_webinar_price_check' );
            
            if ($amount == '') {
                if ($webinar_price_check == 1) {
                    $price1 = $price_all;
                } else {
                    $web_key_price = 'gotowp_premium_webinar_price_' . $webid;
                    $price1 = get_option ( $web_key_price );
                }
                $amount = $price1;
            }
            
            $subject = $webinar->subject;
            $timezone_string = $webinar->timeZone;
            $startTime = new GotowppreDateTime ( $webinar->times [0]->startTime );
            $startTime->setTimezone ( new DateTimeZone ( $timezone_string ) );
            $endTime = new GotowppreDateTime ( $webinar->times [0]->endTime );
            $endTime->setTimezone ( new DateTimeZone ( $timezone_string ) );
            $date_title = "<b>Date and Time</b> <br/>" . $startTime->format ( 'D, M j, Y h:i A' );
            $sec_diff = $endTime->getTimestamp () - $startTime->getTimestamp ();
            if ($sec_diff > 60) {
                $date_title .= ' - ' . $endTime->format ( 'h:i A' );
            }
            $date_title .= $endTime->format ( ' T' );
        } else {
            return false;
        }
    }
    
    $paypal_email_entry = get_option ( 'gotowp_premium_payment_email' );
    $authorize_api_login = get_option ( 'gotowp_premium_authorizenet_api_login_id' );
    $authorize_transaction_key = get_option ( 'gotowp_premium_authorizenet_transaction_key' );
    
    if (isset ( $_REQUEST ['payment_method'] ) && $_REQUEST ['payment_method'] == 'cc' && $webinarErrors->get_error_message ( 'broke' ) == '') {
        
        $payment_return_url = get_option ( 'gotowp_premium_payment_return_url' );
        $return_page_url  = false;
        
        if(isset($_POST['returnpageid'])){
            $return_page_url   = get_permalink(trim(esc_attr($_POST['returnpageid'])));
            $payment_return_url = $return_page_url;
        }
        
        if (isset ( $_REQUEST ) && isset ( $_REQUEST ['credit_card'] ) && isset ( $_REQUEST ['cvv'] ) && isset ( $_REQUEST ['cardholder_first_name'] ) && isset ( $_REQUEST ['amount'] )) {
            
            extract ( $_REQUEST );
            $expiration_date = $expiration_year . '/' . $expiration_month;
            require_once GOTOWP_PERSONAL_PLUGIN_PATH . 'anet_php_sdk/AuthorizeNet.php';
            
            $AUTHORIZENET_API_LOGIN_ID = get_option ( 'gotowp_premium_authorizenet_api_login_id' );
            $AUTHORIZENET_TRANSACTION_KEY = get_option ( 'gotowp_premium_authorizenet_transaction_key' );
            $authorizenet_mode = get_option ( 'gotowp_premium_authorizenet_mode' );
            define ( "AUTHORIZENET_API_LOGIN_ID", "$AUTHORIZENET_API_LOGIN_ID" );
            define ( "AUTHORIZENET_TRANSACTION_KEY", "$AUTHORIZENET_TRANSACTION_KEY" );
            
            if ($authorizenet_mode == 'LIVE') {
                define ( "AUTHORIZENET_SANDBOX", false );
            } else {
                define ( "AUTHORIZENET_SANDBOX", true );
            }
            
            $transaction = new AuthorizeNetAIM ();
            // $transaction->setSandbox(AUTHORIZENET_SANDBOX);
            $transaction->setFields ( array (
                'amount' => $amount,
                'card_num' => $credit_card,
                'exp_date' => $expiration_date,
                'first_name' => $cardholder_first_name,
                'last_name' => $cardholder_last_name,
                'address' => $billing_address,
                'city' => $billing_city,
                'state' => $billing_state,
                'zip' => $billing_zip,
                'email' => $email,
                'card_code' => $cvv
            ) );
            
            $response = $transaction->authorizeAndCapture ();
            
            if ($response->approved) {
                
                $approve = $response->approved;
                $declined = $response->declined;
                $error = $response->error;
                $held = $response->held;
                $response_code = $response->response_code;
                $response_subcode = $response->response_subcode;
                $response_reason_code = $response->response_reason_code;
                $transaction_id = $response->transaction_id;
                $authorization_code = $response->authorization_code;
                $transaction_type = $response->transaction_type;
                $avs_response = $response->avs_response;
                $cavv_response = $response->cavv_response;
                $method = $response->method;
                $card_type = $response->card_type;
                $amount = $response->amount;
                
                $lastinsertid = $_REQUEST ['lastinsertid'];
                
                global $wpdb;
                $webinar_table = $wpdb->prefix . "gtwbundle_webinars";
                
                $payment_response = json_encode ( $response );
                
                $data = array (
                    'payment_response' => $payment_response,
                    'status' => 1
                );
                
                $where = array (
                    'id' => $lastinsertid
                );
                
                $wpdb->update ( $webinar_table, $data, $where );
                webinar_curl_registration ( $lastinsertid );
                custom_wp_redirect ($return_page_url);
            } else if ($response->declined) {
                $errors ['declined'] = 'Your credit card was declined by your bank. Please try another form of payment.';
                echo $response->response_reason_text;
            } else {
                $errors ['error'] = 'We encountered an error while processing your payment. Your credit card was not charged. Please try again or contact customer service to place your order.';
                echo $response->response_reason_text;
            }
        }
        credit_card_form ();
    }
    
    else {
        
        $show_payment = false;
        $subject_row = '';
        $date_row = '';
        
        if ($type1 == 'single') {
            $subject_row = '<tr class="gotowp-subject"><th colspan="2" class="tableheader subject">' . $subject . '</th></tr>';
            $date_row = '<tr class="gotowp-date"><td colspan="2" class="date">' . $date_title . '</td></tr>';
        }
        
        if ($type1 == 'single' && gotowp_personal_can_show_payment($amount)) {
            $show_payment = true;
        } elseif ($type1 == 'list') {
            $show_payment = gotowp_personal_can_show_payment(1);
        }
        
        
        
        $output = '';
        $output .= '<form class="webinar-'.$type1.'" name="webinarregistration" id="webinarregistration" action="" method="post" ><table><thead>';
        $output .= $webinarErrors->get_error_message ( 'broke' ) . ' <tr><th colspan="2">Register for a Webinar</th></tr>';
        $output .= $subject_row . '</thead>';
        $output .= '<tbody>' . $date_row;
        $output .= gotowp_personal_get_registration_form_rows ( $webid, $type1,$include_ids,$exclude_ids,$days );
        
        global $post;
        $output .= '<input type="hidden" name="source" value="'.$source.'" />';
        $output .= '<input type="hidden" name="postid"   value="' . $post->ID . '" />';
        $output .= '<input type="hidden" name="item_name"  id="item_name" value="' . $subject . '" />';
        $output .= '<input type="hidden" name="webinarid" id="webinarid"  value="' . $webid . '" />';
        
        $output .= '<input type="hidden" name="type" id="type"  value="' . $type1 . '" />';
        $output .= '<input type="hidden" name="amount" id="amount" value="' . $amount . '" />';
        
        // $output.='<td><input type="hidden" name="item_name" id="item_name" value="'.$item_name.'" /></td>';
        // $output.='<td><input type="hidden" name="item_number" id="item_number" value="'.$item_number.'" /></td>';
        
        $output.='<input type="hidden" name="returnpageid" id="returnpageid" value="'.$pageid.'" />';
        
        // $output .= '</tr>';
        
        if (isset ( $paypal_email_entry ) && isset ( $authorize_api_login ) && isset ( $authorize_transaction_key ) && $show_payment) :
        
        $output .= '<tr class="payment">';
        if (get_option ( 'gotowp_premium_payment_mode' ) != 'DISABLE') :
        $output .= '<td>Paypal<input type="radio" value="pp" name="payment_method" checked="checked" />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>';
        else:
        $output .= '<td></td>';
        
        endif;
        if (get_option ( 'gotowp_premium_authorizenet_mode' ) != 'DISABLE') :
        $auth_chk = '';
        if (get_option ( 'gotowp_premium_payment_mode' ) == 'DISABLE') {
            $auth_chk = 'checked="checked"';
        }
        $output .= '<td>Authorize.net<input type="radio" value="cc" name="payment_method"  ' . $auth_chk . '/></td>';
        else:
        $output .= '<td></td>';
        endif;
        $output .= ' </tr>';
        
        $amount_dis= $amount;
        
        $output .= '<tr class="tr-submit">
        <td><input type="hidden" name="action" value="registerwebinar" />Price: <span class="price-amount">'.$amount.'</span> '.get_option ( 'gotowp_premium_currency_code' ).'</td><td><input style="background:#6FAA55; color:#ffffff; font-weight:bold;" type="submit" name="submit"  value="Register Now"/></td></tr></table>
        </form>';
        
        else:
        
        $output .= '<tr>
        <td><input type="hidden" name="action" value="registerwebinar" /></td><td><input style="background:#6FAA55; color:#ffffff; font-weight:bold;" type="submit" name="submit"  value="Register Now"/></td></tr></table>
        </form>';
        
        endif;
        
        
        
        $ajax_url = admin_url ( 'admin-ajax.php' );
        
        $output .= '<script type="text/javascript">';
        
        $output .= 'jQuery(document).ready(function($){
            
                                var ajax_url="' . $ajax_url . '";
                            $("#webinarregistration").validate({
                                rules:{
                                    firstName:{required:true},
                                    lastName:{required:true},
                                    email:{required:true,email:true}
                                }
                            });
                                    
                              $("select#webinars_list").on("change",function(e){
                                   //alert(ajax_url);
                                   var curr_name=$("option:selected",this).text();
                                   var curr_val1=$.trim($("option:selected",this).val());
                                    
                                if(curr_name!=""){
                                   $("#webinarregistration #item_name").val(curr_name);
                                 }
                                 if(curr_val1==""){
                                      $("#webinarregistration tr.ajax").remove();
                                    
                                      $(".webinar-list .tr-submit").hide();
                                 }
                                    
                                 var price=$("option:selected",this).data("price");
                                    
                                 if(price == 0 || typeof price == "undefined"){
                                   $("#webinarregistration tr.payment").hide();
                                 }else{
                                     $("#webinarregistration tr.payment").show();
                                     $(".webinar-list .tr-submit .price-amount").text(price);
                                 }
                                 $("#webinarregistration #amount").val(price);
                                   $("#webinarregistration #item_name").val(curr_name);
                                 var curr_val=$(this).val();
                                      if(curr_val!=""){
                                           $("#webinarregistration #webinarid").val(curr_val);
                                    
                                            $.ajax({
                                                    url:ajax_url ,
                                                    async:false,
                                                    type:"POST",
                                                    data:{action:"gotowp_personal_get_registartion_fields_action",web_id:curr_val},
                                                    success:function(rData){
                                                          $("#webinarregistration tr.ajax").remove();
                                                          $("#webinars_list").closest("tr").after(rData);
                                                          $(".webinar-list .tr-submit").show();
                                                    }
                                                });
                                    
                                      }
                                    
                                    
                            });
                                    
                    });
            </script>';
        return $output;
    }
    ?>
<?php
}


add_action ( 'wp_ajax_gotowp_personal_message_action', 'gotowp_personal_message_action_call' );
function gotowp_personal_message_action_call() {
    $updated = false;

    $subject = trim ( esc_attr ( $_POST ['subject'] ) );
    $replyto = trim ( esc_attr ( $_POST ['replyto'] ) );
    $message = trim ( esc_attr ( $_POST ['message'] ) );

    if ($subject != '' && $message != '') {
        update_option ( 'gotowp_premium_subject', $subject );
        update_option ( 'gotowp_premium_message', $message );
        update_option ( 'gotowp_premium_replyto', $replyto );
        $updated = true;
    }

    if ($updated) {
        echo "yes";
    } else {
        echo "no";
    }

    die ();
}
function get_emails_options() {
    $all_options = wp_load_alloptions ();
    $my_options = array ();
    foreach ( $all_options as $name => $value ) {
        if (stristr ( $name, 'gotowp_premium_email_id_' ))
            $my_options [$name] = $value;
    }
    return $my_options;
}



function gotowp_personal_get_registration_form($id, $amount, $type) {
    global $webinarErrors;

    $webid = trim ( $id );
    $date_title = '';
    $subject = '';

    if ($type == 'single' && $webid != '') {
        $webinar = gotowp_personal_get_webinar ( $webid );

        if (isset ( $webinar->webinarKey )) {
            $price_all = get_option ( 'gotowp_premium_webinar_price_all' );
            $webinar_price_check = get_option ( 'gotowp_premium_webinar_price_check' );

            if ($amount == '') {
                if ($webinar_price_check == 1) {
                    $price1 = $price_all;
                } else {
                    $web_key_price = 'gotowp_premium_webinar_price_' . $webid;
                    $price1 = get_option ( $web_key_price );
                }
                $amount = $price1;
            }

                    $web_key_source = 'gotowp_premium_webinar_source_' . $webid;
                    $source = get_option ( $web_key_source );            

            $subject = $webinar->subject;
            $timezone_string = $webinar->timeZone;
            $startTime = new GotowppreDateTime ( $webinar->times [0]->startTime );
            $startTime->setTimezone ( new DateTimeZone ( $timezone_string ) );
            $endTime = new GotowppreDateTime ( $webinar->times [0]->endTime );
            $endTime->setTimezone ( new DateTimeZone ( $timezone_string ) );
            $date_title = "<b>Date and Time</b> <br/>" . $startTime->format ( 'D, M j, Y h:i A' );
            $sec_diff = $endTime->getTimestamp () - $startTime->getTimestamp ();
            if ($sec_diff > 60) {
                $date_title .= ' - ' . $endTime->format ( 'h:i A' );
            }
            $date_title .= $endTime->format ( ' T' );
        } else {
            return false;
        }
    }

    $show_payment = false;
    $subject_row = '';
    $date_row = '';

    if ($type == 'single') {
        $subject_row = '<div class="gotowp-subject row"><div colspan="2" class="tableheader th-cell subject">' . $subject . '</div></div>';
        $date_row = '<div class="gotowp-date row"><div colspan="2" class="date cell">' . $date_title . '</div></div>';
        $show_payment = true;
    } elseif ($type == 'list') {
        $show_payment = true;
    }

    $show_payment = gotowp_personal_can_show_payment($amount);

    $output = '<div class="webinar-item">';

    $output .= '<div class="table"><div class="body">';
    $output .= $webinarErrors->get_error_message ( 'broke_'.$webinar->webinarKey );
    $output .= $subject_row;
    $output .= $date_row;
    $output .= '<div class="row"><div colspan="2" class="th-cell"><a href="javascript:;" class="register">Register</a></div></div></div></div>';

    $output .= '<form name="webinarregistration_' . $webid . '" class="webinarregistration" id="webinarregistration_' . $webid . '" action="" method="post" ><div class="table">';
    $output .= '<div class="tbody">' . gotowp_personal_get_form_rows ( $webid, $type );

    global $post;

    $output .= '<div class="row hidden"><input type="hidden" name="postid"   value="' . $post->ID . '" />';
    $output .= '<input type="hidden" name="source"   value="' . $source . '" />';
    $output .= '<input type="hidden" name="item_name"  id="item_name" value="' . $subject . '" />';
    $output .= '<input type="hidden" name="group_wp"  id="group_wp" value="webinar" />';
    $output .= '<input type="hidden" name="webinarid" id="webinarid"  value="' . $webid . '" />';

    $output .= '<input type="hidden" name="type" id="type"  value="' . $type . '" />';
    $output .= '<input type="hidden" name="amount" id="amount" value="' . $amount . '" /></div>';

    $output .= '';

    $paypal_email_entry = get_option ( 'gotowp_premium_payment_email' );
    $authorize_api_login = get_option ( 'gotowp_premium_authorizenet_api_login_id' );
    $authorize_transaction_key = get_option ( 'gotowp_premium_authorizenet_transaction_key' );

    if ($show_payment) :

    if (isset ( $paypal_email_entry ) && isset ( $authorize_api_login ) && isset ( $authorize_transaction_key )) :

    $output .= '<div class="payment row">';
    if (get_option ( 'gotowp_premium_payment_mode' ) != 'DISABLE') :
    $output .= '<div class="label cell">Paypal</div><div class="value cell"><input type="radio" value="pp" name="payment_method" checked="checked" /></div>';

    endif;
    if (get_option ( 'gotowp_premium_authorizenet_mode' ) != 'DISABLE') :
    $auth_chk = '';
    if (get_option ( 'gotowp_premium_payment_mode' ) == 'DISABLE') {
        $auth_chk = 'checked="checked"';
    }
    $output .= '<div class="label cell">Authorize.net</div><div class="value cell"><input type="radio" value="cc" name="payment_method"  ' . $auth_chk . '/></div>';

    endif;
    $output .= ' </div>';


    endif;


    endif;

    $output .= '<div class="row">
            <div class="cell"><input type="hidden" name="action" value="register_webinar" /></div>
                    <div class="cell"><input style="background:#6FAA55; color:#ffffff; font-weight:bold;" type="submit" name="submit"  value="Register Now"/></div></div></div>
            </form>';

    $ajax_url = admin_url ( 'admin-ajax.php' );

    $output .= '<script type="text/javascript">';

    $output .= 'jQuery(document).ready(function($){
                          var ajax_url="' . $ajax_url . '";
                            $("#webinarregistration_'.$webid.'").validate({
                                errorElement:"p",
                                rules:{
                                    firstName:{required:true},
                                    lastName:{required:true},
                                    email:{required:true,email:true}
                                }
                            });

                        });
                </script>';

    $output .= '</div></div>';

    return $output;
    ?>
<?php
}


if(!function_exists('custom_print_r')):
    function custom_print_r($data){
      echo '<pre>';
        print_r($data);
      echo '</pre>';
    }
endif;


add_action ( 'init', 'gotowp_personal_registration_form_process' );

function gotowp_personal_registration_form_process() {
    global $webinarErrors;
    // extract(shortcode_atts(array( 'id'=>'','amount'=>'','type'=>'single'), $atts));
    if (isset ( $_POST ['group_wp'] ) && $_POST ['group_wp'] == 'webinar' && isset ( $_POST ['action'] ) && trim ( $_POST ['action'] ) == 'register_webinar') {
           $webid = trim ( esc_attr ( $_POST ['webinarid'] ) );
           if(!gotowp_personal_is_captcha_enabled() || gotowp_form_is_validate_captcha($_POST['captcha_code'])){
                    unset($_POST['captcha_code']);
                    $errors = array ();
                    $error_count = 0;
                    $amount = trim ( esc_attr ( $_POST ['amount'] ) );
                    $type = trim ( esc_attr ( $_POST ['type'] ) );
                    $email = trim ( esc_attr ( $_POST ['email'] ) );
                    $source=trim(esc_attr($_POST['source']));
                 
                    $emails = gotowp_personal_get_registrants_fields($webid,'email');

                    if($emails && in_array($email,$emails) )
                    {
                        $webinarErrors->add('broke_'.$webid, $email.' Email is already registered with this webinar');
                        $error_count++;
                    }else{
                        $webinarid = trim ( esc_attr ( $_POST ['webinarid'] ) );
                        $registration_fields = gotowp_personal_get_registration_fields ( $webinarid );
                        $curl_post_data=array();
                        foreach($registration_fields->fields as $fld):
                            if (! isset ( $_POST [$fld->field] ) || $_POST [$fld->field] == '') {
                                if ($fld->required) {
                                    $error_count ++;
                                }
                            }
                            $curl_post_data [$fld->field] = $_POST [$fld->field];
                        endforeach;

                        $responses=array();

                        if(isset($registration_fields->questions) && count($registration_fields->questions) > 0){
                            foreach($registration_fields->questions as $fld):

                                if (! isset ( $_POST [$fld->questionKey] ) || $_POST [$fld->questionKey] == '') {
                                    if ($fld->required) {
                                        $error_count ++;
                                    }
                                }
                                $value = $_POST [$fld->questionKey];
                                if(!$value) {
                                    $value='';
                                }
                                $question=array();
                                $question['questionKey']=$fld->questionKey;
                                if(isset($fld->answers)){
                                    $question['answerKey']=$value;
                                }else{
                                    $question['responseText']=$value;
                                }
                                $responses[]=$question;
                            endforeach;
                            $curl_post_data['responses']=$responses;
                        }

                    }


                    if ($error_count == 0) {
                        global $wpdb;
                        $webinar_table = $wpdb->prefix . "gtwbundle_webinars";
                        $postid = $_POST ['postid'];
                        $item_name1 = $_POST ['item_name'];
                        if (isset ( $_POST ['payment_method'] ) && $amount > 0) {
                            $payment_type = $_POST ['payment_method'];
                            $status = 0;
                        } else {
                            $payment_type = 'free';
                            $status = 1;
                        }
                        $return_page_url = false;
                        if(isset($_POST['returnpageid'])){
                            $return_page_url   = get_permalink(trim(esc_attr($_POST['returnpageid'])));
                        }                         

                        $post_arr = $_POST;
                        $curl_post_data['source'] = $source;
                        $formdata = json_encode ( $curl_post_data );
                        $payment_response = '';



                        $data = array (
                                'postid' => esc_attr ( $_POST ['postid'] ),
                                'type' => esc_attr ( $_POST ['type'] ),
                                'firstname' => esc_attr ( $_POST ['firstName'] ),
                                'lastname' => esc_attr ( $_POST ['lastName'] ),
                                'email' => esc_attr ( $_POST ['email'] ),
                                'webinar_id' => esc_attr ( $_POST ['webinarid'] ),
                                'amount' => $amount,
                                'formdata' => $formdata,
                                'postdata' => json_encode ( $_POST ),
                                'payment_response' => $payment_response,
                                'payment_type' => $payment_type,
                                'status' => $status
                        );

                        $wpdb->insert ( $webinar_table, $data );
                        $lastid = $wpdb->insert_id;
                        $_SESSION ['lastid'] = $lastid;

                        if ($amount == 0) {
                            webinar_curl_registration ( $lastid );
                            custom_wp_redirect ($return_page_url);
                        }
                        else {
                            $payment_method = trim ( esc_attr ( $_POST ['payment_method'] ) );
                            if ($payment_method == 'cc') {
                                global $credit_form_data;
                                $credit_form_data = gotowp_get_credit_card_form ( $amount, $lastid );
                                add_filter ( 'the_content', 'webinar_credit_card_form_the_content_cb' );
                            }
                            if ($payment_method == 'pp') {
                                global $paypal_form_data;
                                $paypal_form_data = gotowp_get_paypal_payment_form ( $item_name1, $webinarid, $amount, $lastid );
                                add_filter ( 'the_content', 'webinar_paypal_form_the_content_cb' );
                            }
                        }
                    }

         }else{
                 $msg = '<div class="error-msg">wrong captcha code, please use correct one</div>';
                 $webinarErrors->add('broke_'.$webid, $msg);
           }

   }


    if (isset ( $_POST ['lastinsertid'] ) &&  ($_POST ['lastinsertid'] > 0)  && isset ( $_POST ['credit_card'] ) && isset ( $_POST ['cvv'] ) && isset ( $_POST ['cardholder_first_name'] ) && isset ( $_POST ['amount'] ) && isset ( $_POST ['group_wp'] ) && $_POST ['group_wp'] == 'webinar' && isset ( $_POST ['payment_method'] ) && $_POST ['payment_method'] == 'cc' && $webinarErrors->get_error_message ( 'broke' ) == '') {

            $payment_return_url = get_option ( 'gotowp_premium_payment_return_url' );
            $return_page_url = false;
            if(isset($_POST['returnpageid'])){
                $return_page_url   = get_permalink(trim(esc_attr($_POST['returnpageid'])));
                $payment_return_url = $return_page_url;
            }

            extract ( $_REQUEST );
            $expiration_date = $expiration_year . '/' . $expiration_month;
            require_once GOTOWP_PERSONAL_PLUGIN_PATH . 'anet_php_sdk/AuthorizeNet.php';

            $AUTHORIZENET_API_LOGIN_ID = get_option ( 'gotowp_premium_authorizenet_api_login_id' );
            $AUTHORIZENET_TRANSACTION_KEY = get_option ( 'gotowp_premium_authorizenet_transaction_key' );
            $authorizenet_mode = get_option ( 'gotowp_premium_authorizenet_mode' );
            define ( "AUTHORIZENET_API_LOGIN_ID", "$AUTHORIZENET_API_LOGIN_ID" );
            define ( "AUTHORIZENET_TRANSACTION_KEY", "$AUTHORIZENET_TRANSACTION_KEY" );

            if ($authorizenet_mode == 'LIVE') {
                define ( "AUTHORIZENET_SANDBOX", false );
            } else {
                define ( "AUTHORIZENET_SANDBOX", true );
            }

            $transaction = new AuthorizeNetAIM ();
            // $transaction->setSandbox(AUTHORIZENET_SANDBOX);
            $transaction->setFields ( array (
                    'amount' => $amount,
                    'card_num' => $credit_card,
                    'exp_date' => $expiration_date,
                    'first_name' => $cardholder_first_name,
                    'last_name' => $cardholder_last_name,
                    'address' => $billing_address,
                    'city' => $billing_city,
                    'state' => $billing_state,
                    'zip' => $billing_zip,
                    'email' => $email,
                    'card_code' => $cvv
            ) );

            $response = $transaction->authorizeAndCapture ();

            if ($response->approved) {
                $approve = $response->approved;
                $declined = $response->declined;
                $error = $response->error;
                $held = $response->held;
                $response_code = $response->response_code;
                $response_subcode = $response->response_subcode;
                $response_reason_code = $response->response_reason_code;
                $transaction_id = $response->transaction_id;
                $authorization_code = $response->authorization_code;
                $transaction_type = $response->transaction_type;
                $avs_response = $response->avs_response;
                $cavv_response = $response->cavv_response;
                $method = $response->method;
                $card_type = $response->card_type;
                $amount = $response->amount;
                $lastinsertid = $_REQUEST ['lastinsertid'];
                global $wpdb;
                $webinar_table = $wpdb->prefix . "gtwbundle_webinars";
                $payment_response = json_encode ( $response );
                $data = array (
                        'payment_response' => $payment_response,
                        'status' => 1
                );
                $where = array (
                        'id' => $lastinsertid
                );
                $wpdb->update ( $webinar_table, $data, $where );
                webinar_curl_registration ( $lastinsertid );
                custom_wp_redirect ($return_page_url);
            } else if ($response->declined) {
                $errors ['declined'] = 'Your credit card was declined by your bank. Please try another form of payment.';
                echo $response->response_reason_text;
            } else {
                $errors ['error'] = 'We encountered an error while processing your payment. Your credit card was not charged. Please try again or contact customer service to place your order.';
                echo $response->response_reason_text;
            }
    }
}








function gotowp_get_credit_card_form($amount, $last_id) {
    $credit_form = '';

    $credit_form .= '<form name="creditcardpayment" id="creditcardpayment" action="" method="post">
                <table>
                <tr>
                    <td><label for="credit_card">Credit Card Number</label></td>
                    <td><input type="text" name="credit_card" id="credit_card" autocomplete="off" maxlength="19" value=""></td>
                </tr>
                <tr>
                    <td><label for="expiration_month">Expiration Date</label></td>
                    <td><select name="expiration_month" id="expiration_month">
                            <option value=""> </option>';

    $month_arr = range ( 1, 12 );
    foreach ( $month_arr as $monthkey ) :
        $credit_form .= '<option value="' . $monthkey . '">' . $monthkey . '</option>';
    endforeach
    ;

    $credit_form .= '</select>
                        <select name="expiration_year" id="expiration_year">
                            <option value=""> </option>';

    $year = date ( "Y" );
    $year_arr = range ( $year, $year + 20 );
    foreach ( $year_arr as $yearkey ) :
        $credit_form .= '<option value="' . $yearkey . '">' . $yearkey . '</option>';
    endforeach
    ;

    $credit_form .= '</select>
                    </td>
                </tr>

                <tr>
                    <td><label for="cvv">Security Code</label></td>
                    <td><input type="text" name="cvv" id="cvv" autocomplete="off" value="" maxlength="4"></td>
                </tr>

                <tr>
                    <td><label for="cardholder_first_name">First Name</label></td>
                    <td><input type="text" name="cardholder_first_name" id="cardholder_first_name" maxlength="30" value=""></td>
                </tr>
                <tr>
                    <td><label for="cardholder_last_name">Last Name</label></td>
                    <td><input type="text" name="cardholder_last_name" id="cardholder_last_name" maxlength="30" value=""></td>
                </tr>
                <tr>
                    <td><label for="billing_address">Billing Address</label></td>
                    <td><input type="text" name="billing_address" id="billing_address" maxlength="45" value=""></td>
                </tr>
                <tr>
                    <td><label for="billing_address2">Suite/Apt #</label></td>
                    <td><input type="text" name="billing_address2" id="billing_address2" maxlength="45" value=""></td>
                </tr>
                <tr>
                    <td><label for="billing_city">City</label></td>
                    <td><input type="text" name="billing_city" id="billing_city" maxlength="25" value=""></td>
                </tr>
                <tr>
                    <td><label for="billing_state">State</label></td>
                    <td><select id="billing_state" name="billing_state">
                    <option value=""> </option>
                    <option value="AL">Alabama</option>
                    <option value="AK">Alaska</option>
                    <option value="AZ">Arizona</option>
                    <option value="AR">Arkansas</option>
                    <option value="CA">California</option>
                    <option value="CO">Colorado</option>
                    <option value="CT">Connecticut</option>
                    <option value="DE">Delaware</option>
                    <option value="DC">District Of Columbia</option>
                    <option value="FL">Florida</option>
                    <option value="GA">Georgia</option>
                    <option value="HI">Hawaii</option>
                    <option value="ID">Idaho</option>
                    <option value="IL">Illinois</option>
                    <option value="IN">Indiana</option>
                    <option value="IA">Iowa</option>
                    <option value="KS">Kansas</option>
                    <option value="KY">Kentucky</option>
                    <option value="LA">Louisiana</option>
                    <option value="ME">Maine</option>
                    <option value="MD">Maryland</option>
                    <option value="MA">Massachusetts</option>
                    <option value="MI">Michigan</option>
                    <option value="MN">Minnesota</option>
                    <option value="MS">Mississippi</option>
                    <option value="MO">Missouri</option>
                    <option value="MT">Montana</option>
                    <option value="NE">Nebraska</option>
                    <option value="NV">Nevada</option>
                    <option value="NH">New Hampshire</option>
                    <option value="NJ">New Jersey</option>
                    <option value="NM">New Mexico</option>
                    <option value="NY">New York</option>
                    <option value="NC">North Carolina</option>
                    <option value="ND">North Dakota</option>
                    <option value="OH">Ohio</option>
                    <option value="OK">Oklahoma</option>
                    <option value="OR">Oregon</option>
                    <option value="PA">Pennsylvania</option>
                    <option value="RI">Rhode Island</option>
                    <option value="SC">South Carolina</option>
                    <option value="SD">South Dakota</option>
                    <option value="TN">Tennessee</option>
                    <option value="TX">Texas</option>
                    <option value="UT">Utah</option>
                    <option value="VT">Vermont</option>
                    <option value="VA">Virginia</option>
                    <option value="WA">Washington</option>
                    <option value="WV">West Virginia</option>
                    <option value="WI">Wisconsin</option>
                    <option value="WY">Wyoming</option>
                     <option value="IN">Delhi</option>
                    </select>
                    </td>
                </tr>
                <tr>
                <td><label for="billing_zip">Zip Code</label></td>
                <td><input type="text" name="billing_zip" id="billing_zip" maxlength="6" value=""></td>
                </tr>

                <tr>
                <td><label for="email">Email Address</label></td>
                <td><input type="text" name="email" id="email" maxlength="50" value=""></td>
                </tr>

                <tr>
                <td>
                    <input type="submit" value="Pay">
                    <input type="hidden" name="group_wp" value="webinar"/>
                    <input type="hidden" name="payment_method" value="cc"/>
                    <input type="hidden" name="amount"  value="' . $amount . '" />
                    <input type="hidden" name="lastinsertid" value="' . $last_id . '" />
                </td>
                </tr>

                </table>
                </form>';

    $credit_form .= '<script type="text/javascript">
    jQuery(document).ready(function($){

        $("#creditcardpayment").validate({
            rules:  {
                        credit_card      :{required:true,number:true,digits:true},
                        expiration_month :{required:true,number:true,digits:true},
                        expiration_year  :{required:true,number:true,digits:true},
                        cvv              :{required:true,number:true,digits:true},
                        cardholder_first_name :{required:true,number:false,digits:false},
                        cardholder_last_name  :{required:true,number:false,digits:false},
                        billing_address  :{required:true},
                        billing_city     :{required:true},
                        billing_state    :{required:true},
                        billing_zip      :{required:true,number:true,digits:true},
                        email            :{email:true}
                    }
            });

    });
    </script>';

    return $credit_form;
}


add_action ( 'wp_ajax_gotowp_personal_get_registartion_fields_action', 'gotowp_personal_get_registartion_fields_action_call' );
add_action ( 'wp_ajax_nopriv_gotowp_personal_get_registartion_fields_action', 'gotowp_personal_get_registartion_fields_action_call' );
function gotowp_personal_get_registartion_fields_action_call() {
    $web_id = trim ( $_POST ['web_id'] );
    $output = gotowp_personal_ajax_get_registration_form_rows ( $web_id );
    echo $output;
    die ();
}


function gotowp_personal_delete_webinars() {
    $response = gotowp_personal_get_webinars ();
    $webinars = gotowp_personal_json_decode ( $response );

    foreach ( $webinars as $webinar ) :
    $web_key = $webinar->webinarKey;
    $web_key = trim ( $web_key );
    $web_option_key = 'gotowp_premium_webinar%' . $web_key;
    gotowp_personal_delete_option ( trim ( $web_option_key ) );
    endforeach;
}

function gotowp_personal_delete_option($pattern) {
    global $wpdb;
    $wpdb->query ( "DELETE FROM $wpdb->options WHERE option_name like '" . $pattern . "'" );
}


function gotowp_personal_json_decode($json, $assoc = false, $depth = 512, $options = 0) {
    // search and remove comments like /* */ and //
    $json = preg_replace ( "#(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t]//.*)|(^//.*)#", '', $json );

    if (version_compare ( phpversion (), '5.4.0', '>=' ) && version_compare ( phpversion (), '5.5', '<' )) {
        $json = json_decode ( $json, $assoc, $depth, JSON_BIGINT_AS_STRING );
    } elseif (version_compare ( phpversion (), '5.3.0', '>=' )) {
        $json = preg_replace ( '/("\w+"):(\d+)/', '\\1:"\\2"', $json );
        $json = json_decode ( $json, $assoc, $depth );
    } else {
        $json = preg_replace ( '/("\w+"):(\d+)/', '\\1:"\\2"', $json );
        $json = json_decode ( $json, $assoc );
    }

    return $json;
}


function gotowp_personal_plugin_proccess_paypal_ipn($wp) {
    $dir = plugin_dir_path ( __FILE__ );

     $curlversion = curl_version();

     if(stripos($curlversion['ssl_version'],'openssl')){
       require_once GOTOWP_PERSONAL_PLUGIN_PATH . DIRECTORY_SEPARATOR .'paypal'. DIRECTORY_SEPARATOR .'vendor'. DIRECTORY_SEPARATOR .'autoload.php';
       $listener = new \wadeshuler\paypalipn\IpnListener();
     }else{
       require_once $dir . '/paypal_ipn/ipnlistener.php';
       $listener = new IpnListener ();
       $listener->force_ssl_v3 = false;
     }

    // tell the IPN listener to use the PayPal test sandbox

    if (trim ( get_option ( 'gotowp_premium_payment_mode' ) ) != 'LIVE') {
        $listener->use_sandbox = true;
    }


    // try to process the IPN POST
    try {
        $listener->requirePostMethod ();
        $verified = $listener->processIpn ();
    } catch ( Exception $e ) {
        error_log ( $e->getMessage () );
        exit ( 0 );
    }

    if ($verified) {
    //  $transactionRawData = $listener->getRawPostData();      // raw data from PHP input stream
    //  $transactionData = $listener->getPostData();            // POST data array

        global $wpdb;
        $payment_email = trim ( get_option ( 'gotowp_premium_payment_email' ) );
        $currency_code = trim ( get_option ( 'gotowp_premium_currency_code' ) );

        $errmsg = ''; // stores errors from fraud checks

        // 1. Make sure the payment status is "Completed"
        if ($_POST ['payment_status'] != 'Completed') {
            // simply ignore any IPN that is not completed
            exit ( 0 );
        }

        // 4. Make sure the currency code matches
        if ($_POST ['mc_currency'] != $currency_code) {
            $errmsg .= "'mc_currency' does not match: ";
            $errmsg .= $_POST ['mc_currency'] . "\n";
        }

        $txn_id = $_POST['txn_id'];
        $paypal_transactions = get_option ( 'gotowp_premium_paypal_transactions' );
        if($paypal_transactions){
          $paypal_transactions = explode('$',$paypal_transactions);
        }else{
          $paypal_transactions= array();
        }

        if(in_array($txn_id,$paypal_transactions)){
          $errmsg .= "'Transaction with id' ".$txn_id." already processed: ";
        }


        if (! empty ( $errmsg )) {
            // manually investigate errors from the fraud checking
            $body = "IPN failed fraud checks: \n$errmsg\n\n";
            $body .= $listener->getTextReport ();
        } else {

            global $wpdb;
            $webinar_table = $wpdb->prefix . "gtwbundle_webinars";

            $payment_response = json_encode ( $_REQUEST );
            extract ( $_REQUEST );

            $data = array (
                    'payment_response' => $payment_response,
                    'status' => 1
            );

            $where = array (
                    'id' => $custom
            );

            $wpdb->update ( $webinar_table, $data, $where );

            $info = array ();
            $info ['customer_name'] = $first_name . ' ' . $last_name;
            $info ['customer_email'] = $payer_email;
            webinar_curl_registration ( $custom );
        }
    } else {
        $errors = $listener->getErrors();
        // manually investigate the invalid IPN
    }
}


add_action ( 'wp_ajax_gotowp_personal_new_email_action', 'gotowp_personal_new_email_action_call' );
function gotowp_personal_new_email_action_call() {
    $added = false;

    $email_new = trim ( esc_attr ( $_POST ['email_new'] ) );

    $my_options = array ();
    $my_options = get_emails_options ();

    if ($email_new != '' && is_email ( $email_new ) && ! in_array ( $email_new, $my_options )) {
        if (get_option ( 'gotowp_premium_new_email_count' )) {
            $email_count = get_option ( 'gotowp_premium_new_email_count' );
            $email_count = $email_count + 1;
        } else {
            $email_count = 1;
        }

        update_option ( 'gotowp_premium_new_email_count', $email_count );
        $new_id = 'gotowp_premium_email_id_' . $email_count;
        update_option ( $new_id, $email_new );
        $added = true;
    }

    if ($added) {
        $ret_arr = array (
                'email_id' => $new_id,
                'email_val' => $email_new,
                'flag' => 'yes'
        );
        echo json_encode ( $ret_arr );
    } else {
        $ret_arr2 = array (
                'flag' => 'no'
        );
        echo json_encode ( $ret_arr2 );
    }

    die ();
}

add_action ( 'wp_ajax_gotowp_personal_remove_email_action', 'gotowp_personal_remove_email_action_call' );
function gotowp_personal_remove_email_action_call() {
    $removed = false;

    $email_opt_id = trim ( esc_attr ( $_POST ['email_opt_id'] ) );
    $my_options = array ();
    $my_options = get_emails_options ();

    if (array_key_exists ( $email_opt_id, $my_options )) {
        if (delete_option ( $email_opt_id )) {
            $removed = true;
        }
    }

    if ($removed) {
        echo "yes";
    } else {
        echo "no";
    }

    die ();
}

function webinar_curl_registration($id) {
    global $wpdb;
    $id = ( int ) $id;
    $webinar_table = $wpdb->prefix . "gtwbundle_webinars";
    $webinar_table = trim ( $webinar_table );
    $sql = "SELECT * FROM $webinar_table WHERE id='" . $id . "' ";
    $results = $wpdb->get_row ( $sql, ARRAY_A );

    if ($results ['status'] == 1) {
        $webinar_id = trim ( $results ['webinar_id'] );
        $organizer_key = trim ( get_option ( 'gotowp_premium_organizer_key' ) );
        $access_token = trim ( get_option ( 'gotowp_premium_access_token' ) );

        $url = 'https://api.citrixonline.com/G2W/rest/organizers/' . $organizer_key . '/webinars/' . $webinar_id . '/registrants';
        $curl = curl_init ( $url );

        $formdata = $results ['formdata'];

        $headers = array (
                        "HTTP/1.1",
                        "Accept: application/vnd.citrix.g2wapi-v1.1+json",
                        "Content-Type: application/json",
                        "Authorization: OAuth oauth_token=" . $access_token
                );


        $myOptions = array (
                CURLOPT_POST => true,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_POSTFIELDS => $formdata,
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HTTPHEADER => $headers
        );


        curl_setopt_array ( $curl, $myOptions );
        $curl_response = curl_exec ( $curl );




        $registrant = gotowp_personal_json_decode ( $curl_response );


        if(isset($registrant->registrantKey)){
              gotowp_personal_update_webinars();
        }


        if(isset($registrant->registrantKey) && !isset($registrant->description)){

            gotowp_personal_update_registrants($webinar_id);
            $payment_type = trim($results ['payment_type']);

          if($payment_type == 'pp'){
               $payment_response = gotowp_personal_json_decode($results['payment_response']);
               $txn_id = $payment_response->txn_id;
              $paypal_transactions = get_option ( 'gotowp_premium_paypal_transactions' );
              if($paypal_transactions){
                $paypal_transactions = explode('$',$paypal_transactions);
              }else{
                $paypal_transactions = array();
              }
              $paypal_transactions[] = $txn_id;
              $paypal_transactions = implode('$',$paypal_transactions);
              update_option ( 'gotowp_premium_paypal_transactions', $paypal_transactions);
           }


            $emails = get_emails_options ();
            $subject = trim ( get_option ( 'gotowp_premium_subject' ) );
            $message = trim ( get_option ( 'gotowp_premium_message' ) );

            $webinar_response = gotowp_personal_get_webinar ( $webinar_id );
            $webinar_data = $webinar_response;
            $webinar_name = $webinar_data->subject;

            $form_arr = gotowp_personal_json_decode ( $formdata );

            $your_name = 'GotoWebinar';
            $your_email = get_option ( 'gotowp_premium_replyto' );
            $customer_name = $form_arr->firstName . ' ' . $form_arr->lastName;
            $customer_email = $form_arr->email;

            $constant_contact_enable = get_option ( 'gotowp_premium_webinar_constant_contact_enable' );
            $constant_contact_enable = ( int ) $constant_contact_enable;
            if ($constant_contact_enable == 1) {
                gotowp_personal_constant_contact_add_contact ( $customer_email, $form_arr->firstName, $form_arr->lastName );
            }

            $mailchimp_enable = get_option ( 'gotowp_personal_webinar_mailchimp_enable' );
            $mailchimp_enable = ( int ) $mailchimp_enable;
            if ($mailchimp_enable == 1) {
                gotowp_personal_mailchimp_add_contact ( $customer_email, $form_arr->firstName, $form_arr->lastName );
            }

            $eol = PHP_EOL;

            $uid = md5 ( uniqid ( time () ) );
            $headers = "From: " . $your_name . " <" . $your_email . ">".$eol;
            $headers .= "Reply-To: " . $your_email . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/mixed; boundary=\"" . $uid . "\"".$eol;
            $headers .= "This is a multi-part message in MIME format.".$eol;
            $headers .= "--" . $uid . "\r\n";
            $headers .= "Content-type:text/html; charset=iso-8859-1".$eol;
            $headers .= "Content-Transfer-Encoding: 7bit".$eol;
            $headers .= "--" . $uid . $eol;
            $headers .= 'Content-type: text/html; charset=utf-8' . $eol;

            $message1 = "<p>" . $message . "</p>".$eol;
            $message1 .= "<p style='margin:0px;'><b>Webinar Name: </b>" . $webinar_name . ".</p>".$eol;
            $message1 .= "<p style='margin:0px;'><b>Customer Name: </b>" . $customer_name . ".</p>".$eol;
            $message1 .= "<p style='margin:0px;'><b>Customer Email: </b>" . $customer_email . ".</p>".$eol;

            $subject = "=?UTF-8?B?" . base64_encode ( $subject ) . "?=";

            foreach ( $emails as $emaile ) {
                mail ( $emaile, $subject, $message1, $headers );
            }

        }else{

        }
    }
}



function gotowp_personal_ajax_get_registration_form_rows($webid) {
    global $webinarErrors;
    $webid = trim ( $webid );
    $output = '';
    $registration_fields = gotowp_personal_get_registration_fields ( $webid );
    $webinar = gotowp_personal_get_webinar ( $webid );

    if (isset ( $registration_fields->fields ) && count ( $registration_fields->fields ) > 0) {
        foreach ( $registration_fields->fields as $row ) :
        $class = '';
        if ($row->required) {
            $class = 'required';
        }
        if ($row->field == 'email') {
            $class = $class . ' email';
        }

        $output .= '<tr class="ajax gotowp-' . $row->field . '"><td >' . ucwords ( preg_replace ( '/(?=([A-Z]))/', ' ${2}', $row->field ) ) . '</br>';

        if (isset ( $row->answers )) {
            $output .= '
                        <select name="' . $row->field . '" id="' . $row->field . '" class="gotowp-select ' . $class . '">
                        <option selected="selected" value="">--Select--</option>';

            foreach ( $row->answers as $opt ) :
            $output .= ' <option value="' . $opt . '">' . $opt . '</option>';
            endforeach
            ;

            $output .= '</select>';
        } else {
            $output .= '<input class="gotowp-input-text ' . $class . '" type="text" size=20  name="' . $row->field . '" id="' . $row->field . '" />';
        }

        $output .= '</td></tr>';
        endforeach
        ;


           if (isset ( $registration_fields->questions ) && count ( $registration_fields->questions ) > 0) {


                foreach ( $registration_fields->questions as $row ) :
                            $class = '';
                            if ($row->required) {
                                $class = 'required';
                            }
                           if (strtolower($row->question) == 'email') {
                                $class .= ' email';
                            }

                    $label = $row->question;
                    $field_name = $row->questionKey;

                $output .= '<tr class="ajax row gotowp-' . $field_name . '"><td class="cell">' . ucwords ( preg_replace ( '/(?=([A-Z]))/', ' ${2}', $label ) ) . '</br>';


                        $class .= ' wp-goto-' . $row->questionKey . ' form-row-wide';
                        if (isset ( $row->answers )) {
                                $output .= '<select name="' . $row->questionKey . '" id="' . $row->questionKey . '" class="gotowp-select ' . $class . '">
                                              <option selected="selected" value="">--Select--</option>';                        
                            $options = array ();

                            foreach ( $row->answers as $opt ) :
                                $options [$opt->answerKey] = $opt->answer;
                                $output .= ' <option value="' . $opt->answerKey . '">' . $opt->answer . '</option>';
                            endforeach;
                                $output .= '</select>';
                        } else {

                         $class .= ' wp-goto-' . $row->questionKey . ' form-row-wide';

                        if ($row->type =='shortAnswer') {
                                $type = 'text';
                                $output .= '<input class="gotowp-input-text ' . $class . '" type="'.$type.'" size=20  name="' . $field_name . '" id="' . $field_name . '" />';

                        } else {
                              $type = 'textarea';
                              $output .='<textarea class="gotowp-input-text ' . $class . '" name="' . $field_name . '" id="' . $field_name . '" cols="30" rows="5"></textarea>';
                        }


                        }
                   $output .= '</td></tr>';
                endforeach
                ;
            }






    } else {
        $output .= '<tr class="ajax gotowp-firstName"><td >First Name</td><td>';
        $output .= '<input class="gotowp-input-text required" type="text" size=20  name="firstName" id="firstName" />';
        $output .= '<tr class="ajax gotowp-lastName"><td >Last Name</td><td>';
        $output .= '<input class="gotowp-input-text required " type="text" size=20  name="lastName" id="lastName" />';
        $output .= '<tr class="ajax gotowp-email"><td >Email</td><td>';
        $output .= '<input class="gotowp-input-text required email" type="text" size=20  name="email" id="email" />';
    }

    if(gotowp_personal_is_captcha_enabled()){
        $output .= '<tr class="ajax gotowp-captcha"><td >'.gotowp_form_get_captcha($webid).'</td></tr>';
    }



    return $output;
}

add_action ( 'wp_ajax_gotowp_personal_edit_email_action', 'gotowp_personal_edit_email_action_call' );
function gotowp_personal_edit_email_action_call() {
    $edited = false;

    $email_opt_id = trim ( esc_attr ( $_POST ['email_opt_id'] ) );
    $new_email_val = trim ( esc_attr ( $_POST ['new_email_val'] ) );

    $my_options = array ();
    $my_options = get_emails_options ();

    if ($new_email_val != '' && is_email ( $new_email_val ) && array_key_exists ( $email_opt_id, $my_options )) {
        if (update_option ( $email_opt_id, $new_email_val )) {
            $edited = true;
        }
    }

    if ($edited) {
        echo "yes";
    } else {
        echo "no";
    }

    die ();
}
function gotowp_personal_add_records() {

    if (isset ( $_POST ['action'] ) && trim ( $_POST ['action'] ) == 'savepayment') {
        $paypal_mode = trim ( $_REQUEST ['payment_mode'] );
        $authorize_mode = trim ( $_REQUEST ['authorize_payment_mode'] );
        update_option ( 'gotowp_premium_currency_code', trim ( $_REQUEST ['currency_code'] ) );
        update_option ( 'gotowp_premium_payment_email', trim ( $_REQUEST ['payment_email'] ) );
        update_option ( 'gotowp_premium_paypal_ipn_url', trim ( $_REQUEST ['paypal_ipn_url'] ) );
        update_option ( 'gotowp_premium_payment_return_url', trim ( $_REQUEST ['payment_return_url'] ) );
        update_option ( 'gotowp_premium_authorizenet_api_login_id', trim ( $_REQUEST ['authorizenet_api_login_id'] ) );
        update_option ( 'gotowp_premium_authorizenet_transaction_key', trim ( $_REQUEST ['authorizenet_transaction_key'] ) );

        update_option ( 'gotowp_premium_payment_mode', trim($_REQUEST ['payment_mode'] ));
        update_option ( 'gotowp_premium_authorizenet_mode', trim($_REQUEST ['authorize_payment_mode']) );

        // if ($paypal_mode == 'DISABLE' && $authorize_mode == 'DISABLE') {
        //     global $webinarErrors;
        //     $webinarErrors->add ( 'payment', 'You can not disable both payment option' );
        // } else {
        //     update_option ( 'gotowp_premium_payment_mode', $_REQUEST ['payment_mode'] );
        //     update_option ( 'gotowp_premium_authorizenet_mode', $_REQUEST ['authorize_payment_mode'] );
        // }
    }


    if (isset ( $_POST ['constant_contact_submit'] )) {
        if (isset ( $_POST ['constant_contact_enable'] )) {
            update_option ( 'gotowp_premium_webinar_constant_contact_enable', 1 );
        } else {
            delete_option ( 'gotowp_premium_webinar_constant_contact_enable' );
        }
        if (isset ( $_POST ['constant_contact_username'] ) && trim ( $_POST ['constant_contact_username'] ) != '') {
            update_option ( 'gotowp_premium_webinar_constant_contact_username', trim ( $_POST ['constant_contact_username'] ) );
        }
        if (isset ( $_POST ['constant_contact_password'] ) && trim ( $_POST ['constant_contact_password'] ) != '') {
            update_option ( 'gotowp_premium_webinar_constant_contact_password', trim ( $_POST ['constant_contact_password'] ) );
        }
        if (isset ( $_POST ['constant_contact_api_key'] ) && trim ( $_POST ['constant_contact_api_key'] ) != '') {
            update_option ( 'gotowp_premium_webinar_constant_contact_api_key', trim ( $_POST ['constant_contact_api_key'] ) );
        }
        if (isset ( $_POST ['constant_contact_email_list'] ) && trim ( $_POST ['constant_contact_email_list'] ) != '') {
            update_option ( 'gotowp_premium_webinar_constant_contact_email_list', trim ( $_POST ['constant_contact_email_list'] ) );
        }
    }

    if (isset ( $_POST ['mailchimp_submit'] )) {
        if (isset ( $_POST ['mailchimp_enable'] )) {
            update_option ( 'gotowp_premium_webinar_mailchimp_enable', 1 );
        } else {
            delete_option ( 'gotowp_premium_webinar_mailchimp_enable' );
        }
        if (isset ( $_POST ['mailchimp_username'] ) && trim ( $_POST ['mailchimp_username'] ) != '') {
            update_option ( 'gotowp_premium_webinar_mailchimp_username', trim ( $_POST ['mailchimp_username'] ) );
        }
        if (isset ( $_POST ['mailchimp_password'] ) && trim ( $_POST ['mailchimp_password'] ) != '') {
            update_option ( 'gotowp_premium_webinar_mailchimp_password', trim ( $_POST ['mailchimp_password'] ) );
        }
        if (isset ( $_POST ['mailchimp_api_key'] ) && trim ( $_POST ['mailchimp_api_key'] ) != '') {
            update_option ( 'gotowp_premium_webinar_mailchimp_api_key', trim ( $_POST ['mailchimp_api_key'] ) );
        }
        if (isset ( $_POST ['mailchimp_email_list'] ) && trim ( $_POST ['mailchimp_email_list'] ) != '') {
            update_option ( 'gotowp_premium_webinar_mailchimp_email_list', trim ( $_POST ['mailchimp_email_list'] ) );
        }
    }


    if (isset ( $_POST ['action'] ) && trim ( $_POST ['action'] ) == 'savewebinar') {
        delete_option ( 'gotowp_premium_organizer_key' );
        delete_option ( 'gotowp_premium_access_token' );
        $platform_type = '';
        $layout_type = '';
        if(isset($_REQUEST ['woo_platform_type'])){
        	$platform_type = trim ( esc_attr ( $_REQUEST ['woo_platform_type'] ) );
        }
        if(isset($_REQUEST ['webinar_shop_layout_type'])){
        	$layout_type = trim ( esc_attr ( $_REQUEST ['webinar_shop_layout_type'] ) );
        }
        
        
        update_option ( 'gotowp_premium_webinar_page_layout_type', $layout_type );
        update_option ( 'gotowp_premium_organizer_key', trim ( $_REQUEST ['organizer_key'] ) );
        update_option ( 'gotowp_premium_access_token', trim ( $_REQUEST ['access_token'] ) );
        update_option ( 'gotowp_premium_woo_platform_type', $platform_type );

        if (isset ( $_REQUEST ['g2w_enable_captcha'] )) {
            update_option ( 'gotowp_premium_webinar_enable_captcha', 'yes' );
        } else {
            delete_option ( 'gotowp_premium_webinar_enable_captcha' );
        }


    }

    if (isset ( $_POST ['webinarpriceall_submit'] )) {
        if (isset ( $_POST ['check_webinar_price'] )) {
            update_option ( 'gotowp_premium_webinar_price_check', 1 );
        } else {
            delete_option ( 'gotowp_premium_webinar_price_check' );
        }
        update_option ( 'gotowp_premium_webinar_price_all', trim ( $_POST ['webinar_price_all'] ) );
    }


    if (isset ( $_POST ['webinarprice_submit'] )) {
        $webinars = gotowp_personal_json_decode ( gotowp_personal_get_webinars () );
        foreach ( $webinars as $webi ) :
        $web_key = $webi->webinarKey;
        $web_key = trim ( $web_key );
        $web_option_key = 'gotowp_premium_webinar_price_' . $web_key;
        $web_source_key = 'gotowp_premium_webinar_source_' . $web_key;
        update_option ( $web_option_key, trim ( esc_attr ( $_POST [$web_option_key] ) ) );
        update_option ( $web_source_key, trim ( esc_attr ( $_POST [$web_source_key] ) ) );
        endforeach
        ;
    }


    if (isset ( $_POST ['action'] ) && trim ( $_POST ['action'] ) == 'gotowp_personal_update_webinars_form') {
        $response = gotowp_personal_update_webinars ();
        $webinars_arr = gotowp_personal_json_decode ( $response );
        _e ( 'Webinar forms updated successfully' );
    }


    if (isset ( $_POST ['action'] ) && trim ( $_POST ['action'] ) == 'gotowp_personal_checkout_webinars') {
        $webinar_option_key = 'gotowp_personal_webinar_shop_page';
                $webinar_shop_page = get_option ( $webinar_option_key );



        if(!$webinar_shop_page || FALSE === get_post_status( $webinar_shop_page )) {


            $new_page_title = 'Webinars';
            $new_page_content = '';
            $new_page_template = ''; // ex. template-custom.php. Leave blank if you don't want a custom page template.
            $page_check = get_page_by_title ( $new_page_title );

            $new_page = array (
                    'post_type' => 'page',
                    'post_title' => $new_page_title,
                    'post_content' => $new_page_content,
                    'post_status' => 'publish',
                    'post_author' => 1
            );

            $new_page_id = wp_insert_post ( $new_page );

            if($new_page_id){
                update_post_meta ( $new_page_id, '_webinars_shop_page', 'yes' );
                update_option ( $webinar_option_key, $new_page_id );
                update_option ( 'gotowp_premium_webinar_page_layout_type', 'columns2' );
            }

        }
    }



    $g2w_enable_captcha_checked = '';
    if (gotowp_personal_is_captcha_enabled()) {
        $g2w_enable_captcha_checked = 'checked="checked"';
    }


    ?>

<div class="wrap">

<?php global $webinarErrors; $payment_error= $webinarErrors->get_error_message('payment');?>

<?php if(!empty($payment_error)): ?>
    <div class="error"><?php echo $payment_error; ?></div>
<?php endif;?>

<?php

    if (get_option ( 'gotowp_premium_paypal_ipn_url' )) {
        $paypal_ipn_url = trim ( get_option ( 'gotowp_premium_paypal_ipn_url' ) );
    } else {
        $site_url = get_option ( 'home' );
        $ipn_action_url = trailingslashit ( $site_url ) . '?ipn_action=paypal';
        $paypal_ipn_url = trim ( $ipn_action_url );
    }

    ?>


<div id="tab-container">

		<div id="gotowp-admin-header">
			<div class="gotowp-logo">
				<?php 
            		echo '<img src="'.GOTOWP_PERSONAL_PLUGIN_URL .'/assets/img/gotowp-logo.png'.'"/>';
					?>
			</div>
		</div><!-- end gotowp-admin-header -->


        <ul class="tabholder"><!-- removed class tabsPanel from ul Feb. 3, 2017 -->
            <li><a href="#tab1_content">GoToWebinar Settings</a></li>
            <li><a href="#tab2_content">Email Notifications</a></li>
            <li><a href="#tab3_content">Payment Settings</a></li>
            <li><a href="#tab4_content">Email Providers</a></li>
        </ul>


    <div id="tab1_content" class="tabsCntents">
            
            <div class="formholder">
            <form name="adminsettings" id="adminsettings" action="" method="post">
                
                <div class="gotowpsection">
                    <h2><?php _e('Citrix Key & Token'); ?></h2>
                    <div class="desc">
                    	<p>Visit <a href="http://app.gotowp.com" target="_blank">app.gotowp.com</a> then login with your Citrix details to retrieve your Organizer Key and Access Token</p>
                    </div>
                    <ul>
                        <li><?php _e('Organizer Key'); ?></li>
                        <li><input type="text" size=40    value="<?php echo trim(get_option('gotowp_premium_organizer_key')); ?>" name="organizer_key" id="organizer_key" /></li>
                    </ul>
                    <ul>
                        <li><?php _e('Access Token'); ?></li>
                        <li><input type="text" size=40 value="<?php echo trim(get_option('gotowp_premium_access_token')); ?>" name="access_token" id="access_token" /></li>
                    </ul>

                    <ul>
                        <li><?php _e('Platform Type'); ?></li>
                        <li><select name="woo_platform_type" id="woo_platform_type">
                                <option value="GLOBAL"
                                    <?php if(trim(get_option('gotowp_premium_woo_platform_type'))=='GLOBAL') echo "selected"?>>GLOBAL</option>
                                <option value="LEGACY"
                                    <?php if(trim(get_option('gotowp_premium_woo_platform_type'))=='LEGACY')    echo "selected"?>>LEGACY</option>
                        </select></li>
                    </ul>
                    
                    <div class="savebtn">
                		<input type="hidden" name="action" value="savewebinar" />
						<input class="submit_btn" type="submit" name="submit" value="<?php _e('Save Details') ?>" />
					</div>
					
                </div><!-- end gotowpsection -->
                
                <?php
                     $webinar_option_key = 'gotowp_premium_webinar_shop_page';
                     $webinar_shop_page = get_option ( $webinar_option_key );
                 ?>
                <?php if($webinar_shop_page && FALSE !== get_post_status( $webinar_shop_page )): ?>
                <div class="gotowpsection">
                	<h2><?php _e('Standalone Shop Options'); ?></h2> 
                    <ul>
                        <li><?php _e('Shop Page Layout Type'); ?></li>
                        <li><select name="webinar_shop_layout_type" id="webinar_shop_layout_type">
                                <option value="columns2" <?php if(trim(get_option('gotowp_premium_webinar_page_layout_type'))=='columns2') echo "selected"?>>1/2</option>
                                <option value="columns3" <?php if(trim(get_option('gotowp_premium_webinar_page_layout_type'))=='columns3') echo "selected"?>>1/3</option>
                                <option value="columns4" <?php if(trim(get_option('gotowp_premium_webinar_page_layout_type'))=='columns4') echo "selected"?>>1/4</option>
                        </select></li>
                    </ul>      
                    <div class="savebtn">
                        <input type="hidden" name="action" value="savewebinar" />
                        <input class="submit_btn" type="submit" name="submit" value="<?php _e('Save Details') ?>" />
                    </div>                                 
                </div><!-- end gotowpsection -->
                 <?php endif; ?>

                <div class="gotowpsection">
                    <h2><?php _e('Captcha Security'); ?></h2>
                    <div class="desc">	
                    	<p>This will add a CAPTCHA security field to forms generated using the shortcode.</p>
                    </div>
                    <ul>
                        <li><?php _e('Enable Captcha'); ?></li>
                        <li><input type="checkbox" size="45" <?php echo $g2w_enable_captcha_checked; ?> value="1" name="g2w_enable_captcha" id="g2w_enable_captcha" /></li>
                    </ul>
                    
                    <div class="savebtn">
                		<input type="hidden" name="action" value="savewebinar" />
						<input class="submit_btn" type="submit" name="submit" value="<?php _e('Save Options') ?>" />
					</div>
                
                 </div><!-- end gotowpsection -->

                
                
            </form>
            </div><!-- end form holder -->

    <?php
    $organizer_key = trim ( get_option ( 'gotowp_premium_organizer_key' ) );
    $access_token = trim ( get_option ( 'gotowp_premium_access_token' ) );
    ?>

   <?php if($organizer_key !='' && $access_token !=''): ?>

    <?php
        $site_url = get_option ( 'siteurl' );
        $webinar_price_check = get_option ( 'gotowp_premium_webinar_price_check' );
        $checked = '';
        if ($webinar_price_check == 1) {
            $checked = 'checked="checked"';
        }

        ?>
		<div class="formholder">
            <form name="webinarprices" id="webinarprices" action="<?php echo admin_url('options-general.php').'?page=gotowp_personal';?>" method="post">
                <div class="gotowpsection">
                    <h2><?php _e('Universal Price Setting'); ?></h2>
                    <div class="desc">
					<p>If you want to set the same price for all your webinars, simply check the box below, specify the price, then click save.</p>
                    </div>
                    <ul>
                        <li><?php _e('Use Universal Price for Webinar'); ?></li>
                        <li><input <?php echo $checked; ?> type="checkbox" size="68" value="<?php echo get_option('gotowp_premium_webinar_price_check'); ?>" name="check_webinar_price" id="check_webinar_price" /></li>
                    </ul>
                    <ul>
                        <li><?php _e('Price'); ?></li>
                        <li><input class="number" type="text" size="10" style="text-align: center;" value="<?php echo get_option('gotowp_premium_webinar_price_all'); ?>" name="webinar_price_all" id="webinar_price_all" /></li>
                    </ul>
                    <div class="savepricebtn">
							<input type="hidden" name="action" value="webinarpriceall" />
							<input type="submit" class="submit_btn" name="webinarpriceall_submit" id="webinarpriceall_submit" value="<?php _e('Save Universal Price') ?>" />
						</div><!-- end savebtn -->
                </div><!-- end gotowpsection -->

              </form>            
        </div><!-- end formholder -->
			<div class="formholder">  
              <form name="gtwbundle_webinars_form" id="gtwbundle_webinars_form" action="<?php echo admin_url('options-general.php').'?page=gotowp_personal';?>" method="post">
				<div class="gotowpsection">

                  
                  		<h2><?php _e('List of Webinars'); ?></h2>
                        <div class="desc">
                        	<p>If you have created webinars in your GoToWebinar account AND you've successfully connected to your Citrix account with the Key and Token above, the list of webinars will show below. To cut down on processing and increase speed, GoToWP caches your webinar information in WordPress. If <strong>you've recently made changes in GoToWebinar</strong> and want to sync those changes with WordPress, please <strong>click the "Sync with GoToWebinar" button below</strong>. This will pull in the latest data from your GoToWebinar account and recache it in WordPress.</p>                    		
	
									<div class="syncbtn">
										<ul>
											<li><input type="hidden" name="action" value="gotowp_personal_update_webinars_form" /></li>
											<li><input id="update_webinars" class="submit_btn" style="" type="submit" name="submit" value="<?php _e('Sync with GoToWebinar') ?>" /></li>
										</ul>
									</div><!-- end savebtn -->	  
									
								
                        </div><!-- end desc -->

                        </div>

                        </form>
                        </div>


            <div class="formholder">  
              <form name="gtwbundle_webinars_form" id="gtwbundle_webinars_form" action="<?php echo admin_url('options-general.php').'?page=gotowp_personal';?>" method="post">
                <div class="gotowpsection">

                  <?php $webinars = gotowp_personal_json_decode(gotowp_personal_get_webinars());  ?>
                  
                  <?php if($webinars && !isset($webinars->errorCode) && !isset($webinars->int_err_code)):    ?>
                        
						<div class="webinar-container">
                            <div class="webinar-list-heading">
                                <div class="col name"><?php _e('Name'); ?></div>
                                <!--<div class="col"><?php _e('Webinar ID'); ?></div>-->
                                <div class="col"><?php _e('Date'); ?></div>
                                <div class="col"><?php _e('Description'); ?></div>
                                <div class="col"><?php _e('# of registrations'); ?></div>
                                <div class="col"><?php _e('Price'); ?></div>
                                <div class="col"><?php _e(''); ?></div>
                            </div><!-- webinar-list-heading -->

                              <?php

                                foreach ( $webinars as $webinar ) :

                                    $webinar_key = $webinar->webinarKey;
                                    $webinar_key = trim ( $webinar_key );
                                    $webinar_option_key = 'gotowp_premium_webinar_price_' . $webinar_key;
                                    $price1 = get_option ( $webinar_option_key );
                                    $webinar_source_key = 'gotowp_premium_webinar_source_' . $webinar_key;
                                    $source = get_option ( $webinar_source_key );                                    
                                    $resi_count = gotowp_personal_get_registrants_count($webinar_key);
                                    $subject = $webinar->subject;
                                    $timezone_string = $webinar->timeZone;
                                    $startTime = new GotowppreDateTime ( $webinar->times [0]->startTime );
                                    $startTime->setTimezone ( new DateTimeZone ( $timezone_string ) );
                                    $endTime = new GotowppreDateTime ( $webinar->times [0]->endTime );
                                    $endTime->setTimezone ( new DateTimeZone ( $timezone_string ) );
                                    $date_title = $startTime->format ( 'D, M j, Y h:i A' );
                                    $sec_diff = $endTime->getTimestamp () - $startTime->getTimestamp ();
                                    if ($sec_diff > 60) {
                                        $date_title .= ' - ' . $endTime->format ( 'h:i A' );
                                    }
                                    $date_title .= $endTime->format ( ' T' );

                                    ?>

                                            <div class="webinar-list-details" data-webid="<?php echo $webinar_key; ?>" data-type="webinar">
                                            	<div class="details-main">
                                            		<div class="col name"><?php echo $subject; ?></div>
													<!--<div class="col webinat_id"><?php echo $webinar_key; ?></div>-->
													<div class="col date"><?php echo $date_title; ?></div>
													<div class="col description"><?php echo $webinar->description; ?></div>
                                            	</div><!-- end details-main -->
                                            	<div class="details-sub">
													<div class="col count"># of Registrations: <?php echo $resi_count; ?></div>
													<div class="col price">
                                                		Price: <input type="text" size="7" class="number" value="<?php echo $price1; ?>" name="<?php echo $webinar_option_key; ?>" id="<?php echo $webinar_option_key; ?>" />
													</div>
                                                    <div class="col source">
                                                        Source: <input type="text" size="20" class="number" value="<?php echo $source; ?>" name="<?php echo $webinar_source_key; ?>" id="<?php echo $webinar_source_key; ?>" />
                                                    </div>                                                    
													<div class="col webinar-shortcode">
                                                		<a class="copyshortcode" href="javascript:void(0);">Copy Shortcode</a>
													</div>   
                                            	</div><!-- end details-sub -->                                         
											</div><!-- end webinar-list-details -->

                                <?php endforeach; ?>
						</div><!-- webinar-container -->
						
						<div class="savepricebtn">
							<input type="hidden" name="action" value="webinarprice" />
							<input type="submit" class="submit_btn" name="webinarprice_submit" id="webinarprice_submit" value="<?php _e('Save Prices') ?>" />
						</div><!-- end savebtn -->
           
                  
                  
                 <?php endif; ?>
            </div>
            </form>
            
		</div><!-- end formholder -->
            
			
		<div class="clear-fix"></div>

		
		<div class="gotowpsection">
			<h2>Optional Shortcuts</h2>
			<div class="desc">
				<p>To help you get up and running as quickly as possible, we've included the shortcut buttons below, <strong>intended for one-time initial use only</strong>.</p>
			</div>
		
			<div class="optional-shortcuts">
			
			<div class="option-one-all">
				
				<form name="webinars_checkout_form" id="webinars_checkout_form" class="width_auto" action="<?php echo admin_url('options-general.php').'?page=gotowp_personal';?>" method="post">
					<p>Clicking this will create a Page called "Shop" and which will display all the webinars from the above list on that page. After you've clicked, an option to display the webinars in 1/2, 1/3 or 1/4 layout will appear in the "Standalone Shop Options" section above.</p>
					<div class="savebtn">
                	<ul>
                    	<?php
                        	$webinar_option_key = 'gotowp_premium_webinar_shop_page';
                        	$webinar_shop_page = get_option ( $webinar_option_key );
                        ?>
                        <?php if(!$webinar_shop_page || FALSE === get_post_status( $webinar_shop_page )): ?>
                              	<li><input type="hidden" name="action" value="gotowp_personal_checkout_webinars" /></li>
                                <li><input id="checkout_webinars" class="submit_btn" type="submit" name="submit" value="<?php _e('Create Standalone Webinar Shop') ?>" /></li>
                        <?php else: ?>
                              	<li><input id="checkout_webinars" class="submit_btn btn-light" style="" type="button" name="submit" value="<?php _e('Shop is Created') ?>" /></li>
                        <?php endif; ?>
					</ul>
					</div><!-- end savebtn -->
					
				 </form>
				 </div><!-- end option-one -->
				 
				 

<?php endif; ?>
				 
			</div><!-- end optional-shortcuts -->
         
        </div><!-- end gotowpsection --> 
			
			
			<div class="gotowpsection">
            	<h2><?php _e('Sample Shortcode Usage to Create a Registration Form on a Page or Post'); ?></h2>
				<div class="desc">
					<p>Below you will find examples of the shortcode to add a registration form for your webinars on any Page or Post. Note: the below is provided for example purposes only, and we recommend you use the Copy Shortcode button next to each webinar above, or the GoToWP button in the Page or Post editor to ensure the shortcode is being inserted correctly.</p>
				</div>
				<ul>
                    	<li><?php _e('Single Webinar'); ?><br /></li>
						<li><input style="width: 475px;" type="text" size=40 value="[register_webinar webid=xxxxxxxxxxxx amount=25.00 type=single]" name="shortcode" /></li>
				</ul>
				<ul>
                    	<li><?php _e('List of Webinars'); ?><br /></li>
						<li><input style="width: 475px;" type="text" size=40 value="[register_webinar type=list]" name="shortcode" /></li>
				
				</ul>
				<p>To see other options please visit <a href="http://gotowp.supportico.us/release-note/version-1-0-2-1/" target="_blank">Release Note</a> on shortcode options.</p>
			</div><!-- end gotowpsection -->
 
 </div><!-- end tab1_content -->




        <div id="tab2_content" class="tabsCntents">

            <div class="message_updated_success success msgs">Message updated successfully</div>
            <div class="message_updated_error error msgs">There was some error 2</div>
			
			<div class="formholder">
				<div class="gotowpsection">
					<h2><?php _e('Subject & Message Settings'); ?></h2>
					<div class="desc">
						<p>If you are selling webinars using the shortcode, you can setup email notifications for people in your organization below. When a webinar is purchased, those added to the list below will receive the email containing info you've outlined below. (Note: if you're using Woo Commerce integration, you can ignore this section, as Woo Commerce handles order emails separately in their <a href="wp-admin/admin.php?page=wc-settings&tab=email">Email Settings</a> tab.</p>
                    </div>
					
					<form name="messagesettings" id="messagesettings" action="" method="post">
					<ul>
                        <li><?php _e('Subject'); ?></li>
                        <li><input type="text" size="68" value="<?php echo get_option('gotowp_premium_subject'); ?>" name="subject_name" id="subject_name" /></li>
                    </ul>
                    <ul>
                        <li><?php _e('Reply To'); ?></li>
                        <li><input type="text" size="68" value="<?php echo get_option('gotowp_premium_replyto'); ?>" name="replyto" id="replyto" /></li>
                    </ul>
                    <ul>
                        <li><?php _e('Message'); ?></li>
                        <li><textarea rows="7" cols="65" name="message" id="message"><?php echo get_option('gotowp_premium_message'); ?></textarea></li>
                    </ul>
             

                <div class="savebtn">
                       	<input type="hidden" name="action" value="save_message" />
                        <!--<input type="button" name="message_submit" id="message_submit" class="submit_btn" value="<?php _e('Save Message') ?>" /> old button style -->
                        <input type="button" name="message_submit" id="message_submit" class="submit_btn" value="<?php _e('Save Message') ?>" />
                </div>
                
					</form>
				</div><!-- end gotowpsection -->
            </div><!-- end formholder -->

            <div class="new_email_added_success success msgs">Email Added successfully</div>
            <div class="new_email_removed_success success msgs">Email Removed successfully</div>
            <div class="new_email_edited_success success msgs">Email Edited successfully</div>
            <div class="new_email_added_error error msgs">There was some error 2</div>

			<div class="formholder">
			<div class="gotowpsection">
				<h2><?php _e('Email Addresses That Will Receive Notification'); ?></h2>
				<div class="desc">
					<p>If you are selling webinars using the shortcode, you can setup email notifications for people in your organization below. When a webinar is purchased, those added to the list below will receive the email containing info you've outlined below. (Note: if you're using Woo Commerce integration, you can ignore this section, as Woo Commerce handles order emails separately in their <a href="wp-admin/admin.php?page=wc-settings&tab=email">Email Settings</a> tab.</p>
                </div>
            <form name="emailsettings" id="emailsettings" action="" method="post">
                 <ul id="addnewemail">
                      <li class="error-msgs"></li>
                      <li><?php _e('Add Email Address'); ?></li>
                      <li><input type="text" size="41" value="" name="email_new" id="email_new" />
                            <input type="button" name="email_new_submit" id="email_new_submit" value="+ <?php _e('Add') ?>" /></li>
                  </ul>

                  <ul id="emailaddresses">
                    <?php $email_options=get_emails_options(); $i=1; foreach($email_options as $name => $value ):?>
                         <li>
                            <input type="text" size="41" value="<?php echo $value; ?>" name="<?php echo $name; ?>" id="<?php echo $name; ?>" />
                            <input type="button" name="edit_email_submit" class="edit_email_submit" value="<?php _e('Save') ?>" />
                            <input type="button" name="remove_email_submit" class="remove_email_submit" value="<?php _e('Remove') ?>" />                         
                         </li>
                         <?php $i++; endforeach;?>
                  </ul>




				<div class="clear-fix" style="height: 20px;"></div>
            </form>
            </div><!-- end gotowpsection -->
            </div><!-- end formholder -->
        </div>

        <div id="tab3_content" class="tabsCntents">

			<div class="formholder">
				<div class="gotowpsection">

				<h2>Payment Settings</h2>
				<div class="desc">
					<p>If you're selling webinars with the shortcode, specify the payment settings below. If you're using Woo Commerce integration, setup your desired payment gateways on their <a href="wp-admin/admin.php?page=wc-settings&tab=checkout">Checkout</a> tab.</p>
                </div>
	            <form name="adminsettings" id="adminsettings" action="" method="post">
	            				
				<div class="option-one">
				<h3><?php _e('Paypal Details'); ?></h3>
                <ul>
                    <li><?php _e('Paypal Email'); ?></li>
                    <li><input type="text" size=40 value="<?php echo trim(get_option('gotowp_premium_payment_email')); ?>" name="payment_email" id="payment_email" /></li>
                </ul>
                <ul>
                    <li><?php _e('Paypal Mode'); ?></li>
                    <li><select name="payment_mode" id="payment_mode">
                                <option value="LIVE" <?php if(get_option('gotowp_premium_payment_mode')=='LIVE')    echo "selected"?>>LIVE</option>
                                <option value="SANDBOX" <?php if(get_option('gotowp_premium_payment_mode')=='SANDBOX') echo "selected"?>>SANDBOX</option>
                                <option value="DISABLE" <?php if(get_option('gotowp_premium_payment_mode')=='DISABLE') echo "selected"?>>DISABLE</option>
                        </select></li>
                 </ul>
                 <ul>
                     <li><?php _e('Currency Code'); ?></li>
                         <?php $curr_currency = get_option('gotowp_premium_currency_code'); ?>
                     <li><select name="currency_code" id="currency_code">
                                <option value="USD" <?php if($curr_currency =='USD') echo "selected"; ?>>US Dollar</option>
                                <option value="EUR" <?php if($curr_currency =='EUR') echo "selected"; ?>>Euro</option>
                                <option value="CAD" <?php if($curr_currency =='CAD') echo "selected"; ?>>Canadian Dollar</option>
                                <option value="GBP" <?php if($curr_currency =='GBP') echo "selected"; ?>>British Pound</option>
                                <option value="NOK" <?php if($curr_currency =='NOK') echo "selected"; ?>>Norwegian Krone</option>
                                <option value="SEK" <?php if($curr_currency =='SEK') echo "selected"; ?>>Swedish Krona</option>
								                <option value="NZD" <?php if($curr_currency =='NZD') echo "selected"; ?>>New Zealand Dollar</option>
                        </select></li>
                 </ul>
                 <ul>
                 	<li><?php _e('use LIVE for live mode and SANDBOX for sandbox mode');?></li>
                 </ul>
                 <ul>
                    <li><?php _e('Paypal IPN Url'); ?></li>
                    <li><input type="text" size=40 value="<?php echo $paypal_ipn_url; ?>" name="paypal_ipn_url" id="paypal_ipn_url" /></li>
                 </ul>
                 <ul>
                    <li><?php _e('try above paypal ipn url if it does not shows 404 its correct one else provide correct url');?></td>
                  </ul>
				  </div><!-- end option-one -->
                
                <div class="option-two">
                    <h3><?php _e('Authorize.net Details'); ?></h3>
                    
                    <ul>
                        <li><?php _e('API Login Id'); ?></li>
                        <li><input type="text" size=40 value="<?php echo get_option('gotowp_premium_authorizenet_api_login_id'); ?>" name="authorizenet_api_login_id" id="authorizenet_api_login_id" /></li>
                    </ul>
                    <ul>
                        <li><?php _e('Transaction Key'); ?></li>
                        <li><input type="text" size=40 value="<?php echo get_option('gotowp_premium_authorizenet_transaction_key'); ?>" name="authorizenet_transaction_key" id="authorizenet_transaction_key" /></li>
                    </ul>
                    <ul>
                        <li><?php _e('Authorize.net Mode'); ?></li>
                        <li><select name="authorize_payment_mode" id="authorize_payment_mode">
                                <option value="LIVE" <?php if(get_option('gotowp_premium_authorizenet_mode')=='LIVE')    echo "selected"?>>LIVE</option>
                                <option value="SANDBOX" <?php if(get_option('gotowp_premium_authorizenet_mode')=='SANDBOX') echo "selected"?>>SANDBOX</option>
                                <option value="DISABLE" <?php if(get_option('gotowp_premium_authorizenet_mode')=='DISABLE') echo "selected"?>>DISABLE</option>
                        </select></li>
                    </ul>
                    <ul>
                        <li><?php _e('use LIVE for live mode and SANDBOX for sandbox mode');?></li>
                    </ul>
                </div><!-- end option-two -->
                <div class="clear-fix"></div>
                <hr/>
                <div class="savebtn" style="padding-top: 20px;">
                    <input type="hidden" name="action" value="savepayment" />
                        <input class="submit_btn" type="submit" name="submit" value="<?php _e('Save Payment Details') ?>" />				                				</div><!-- end savebtn -->
                </div><!-- end gotowpsection -->
                
                
                <div class="gotowpsection">
                    <h2><?php _e('Thank You Page'); ?></h2>
                    <div class="desc">
					<p>When using the shortcode to sell webinars, you can redirect your customers to a specific page after they successfully complete the checkout process. Simply enter the Page ID or full URL of the page you want to redirect them to below.</p>
                </div>
                    <ul>
                        <li><?php _e('Thank You Page (Page ID # or Full URL)'); ?></li>
                        <li><input type="text" size=60 value="<?php echo get_option('gotowp_premium_payment_return_url'); ?>" name="payment_return_url" /></li>
                    </ul>
                
                <div class="savebtn">
                    <input type="hidden" name="action" value="savepayment" />
                        <input class="submit_btn" type="submit" name="submit" value="<?php _e('Save Details') ?>" />				                				</div><!-- end savebtn -->
                </div><!-- end gotowpsection -->
            </form>
            	
			
            </div><!-- end formholder -->

        </div>

        <div id="tab4_content" class="tabsCntents">

         <?php
    $constant_contact_username = trim ( get_option ( 'gotowp_premium_webinar_constant_contact_username' ) );
    $constant_contact_password = trim ( get_option ( 'gotowp_premium_webinar_constant_contact_password' ) );
    $constant_contact_api_key = trim ( get_option ( 'gotowp_premium_webinar_constant_contact_api_key' ) );
    $constant_contact_email_list = trim ( get_option ( 'gotowp_premium_webinar_constant_contact_email_list' ) );
    $constant_contact_enable = get_option ( 'gotowp_premium_webinar_constant_contact_enable' );
    $constant_contact_enable = ( int ) $constant_contact_enable;
    $checked = '';
    if ($constant_contact_enable == 1) {
        $checked = 'checked="checked"';
    }
    ?>
			<div class="formholder">
				<div class="gotowpsection">
				<h2>Email Providers</h2>
				<div class="desc">
					<p>If you're selling webinars with the shortcode, you can connect to your MailChimp or Constant Contact account to automatically add those email addresses to your lists when customers complete their order.</p>
                </div>
               <div class="option-one">
				   <form name="constant_contact_form" id="constant_contact_form" action="" method="post">
               	
                    <h3><?php _e('Constact Contact'); ?></h3>
                    
                    <ul>
                        <li><?php _e('Enable Constant Contact'); ?></li>
                        <li><input <?php echo $checked; ?> type="checkbox" size="68" value="<?php echo get_option('gotowp_premium_webinar_constant_contact_enable'); ?>" name="constant_contact_enable" id="constant_contact_enable" /></li>
                    </ul>
                    <ul>
                        <li><?php _e('Username'); ?></li>
                        <li><input class="required" type="text" size="45" value="<?php echo $constant_contact_username; ?>" name="constant_contact_username" id="constant_contact_username" /></li>
                    </ul>
                    <ul>
                        <li><?php _e('Password'); ?></li>
                        <li><input class="required" type="text" size="45" value="<?php echo $constant_contact_password; ?>" name="constant_contact_password" id="constant_contact_password" /></li>
                    </ul>
                    <ul>
                        <li><?php _e('Api Key'); ?></li>
                        <li><input class="required" type="text" size="45" value="<?php echo $constant_contact_api_key; ?>" name="constant_contact_api_key" id="constant_contact_api_key" /></li>
                    </ul>
                    <ul>
                        <li><?php _e('Email List'); ?></li>
                        <li><input class="required" type="text" size="45" value="<?php echo $constant_contact_email_list; ?>" name="constant_contact_email_list" id="constant_contact_email_list" /></li>
                    </ul>
                    <ul>
                        <li><input type="hidden" name="action" value="constant_contact_data" /></td>
                        <td><input type="submit" class="submit_btn" name="constant_contact_submit" id="constant_contact_submit" value="<?php _e('Save Constant Contact Details') ?>" /></td>
                    </ul>
                </div>

            </form>
			
         <?php
    $mailchimp_username = trim ( get_option ( 'gotowp_premium_webinar_mailchimp_username' ) );
    $mailchimp_password = trim ( get_option ( 'gotowp_premium_webinar_mailchimp_password' ) );
    $mailchimp_api_key = trim ( get_option ( 'gotowp_premium_webinar_mailchimp_api_key' ) );
    $mailchimp_email_list = trim ( get_option ( 'gotowp_premium_webinar_mailchimp_email_list' ) );
    $mailchimp_enable = get_option ( 'gotowp_premium_webinar_mailchimp_enable' );
    $mailchimp_enable = ( int ) $mailchimp_enable;
    $checked = '';
    if ($mailchimp_enable == 1) {
        $checked = 'checked="checked"';
    }
    ?>
			<div class="option-two">
            <form name="mailchimp_form" id="mailchimp_form" action="" method="post">
                	<h3><?php _e('Mailchimp'); ?></h3>
                    <ul>
                        <li><?php _e('Enable Mailchimp'); ?></li>
                        <li><input <?php echo $checked; ?> type="checkbox" size="68" value="<?php echo get_option('gotowp_premium_webinar_mailchimp_enable'); ?>" name="mailchimp_enable" id="mailchimp_enable" /></li>
                    </ul>
                    <ul>
                        <li><?php _e('Username'); ?></li>
                        <li><input class="required" type="text" size="45" value="<?php echo $mailchimp_username; ?>" name="mailchimp_username" id="mailchimp_username" /></li>
                    </ul>
                    <ul>
                        <li><?php _e('Password'); ?></li>
                        <li><input class="required" type="text" size="45" value="<?php echo $mailchimp_password; ?>" name="mailchimp_password" id="mailchimp_password" /></li>
                    </ul>
                    <ul>
                        <li><?php _e('Api Key'); ?></li>
                        <li><input class="required" type="text" size="45" value="<?php echo $mailchimp_api_key; ?>" name="mailchimp_api_key" id="mailchimp_api_key" /></li>
                    </ul>
                    <ul>
                        <li><?php _e('Email List'); ?></li>
                        <li><input class="required" type="text" size="45" value="<?php echo $mailchimp_email_list; ?>" name="mailchimp_email_list" id="mailchimp_email_list" /></li>
                    </ul>
                    <ul>
                        <li><input type="hidden" name="action" value="mailchimp_data" /></li>
                        <li><input type="submit" class="submit_btn" name="mailchimp_submit" id="mailchimp_submit" value="<?php _e('Save MailChimp Details') ?>" /></li>
                    </ul>
                </div><!-- end option-two -->

            </form>
			
			</div><!-- end gotowpsection -->
			</div><!-- end formholder -->

        </div>

    </div>

</div>

<?php



}


function gotowp_personal_is_captcha_enabled(){
    $g2w_enable_captcha = get_option ( 'gotowp_premium_webinar_enable_captcha' );
    if ($g2w_enable_captcha && $g2w_enable_captcha == 'yes') {
        return true;
    }
    return false;
}


function gotowp_personal_get_registration_form_rows($webid, $type1,$include_ids,$exclude_ids,$days ) {
    global $webinarErrors;
    $output = '';
    $webid = trim ( $webid );

    if ($type1 == 'single') {
        $registration_fields = gotowp_personal_get_registration_fields ( $webid );
        $webinar = gotowp_personal_get_webinar ( $webid );
    }

    if ($type1 == 'list') {
        $price = get_option ( 'gotowp_premium_webinar_price_all' );
        $webinar_price_check = get_option ( 'gotowp_premium_webinar_price_check' );
        $webinars = gotowp_personal_json_decode ( gotowp_personal_get_webinars () );

        $is_series = false;
        $url_to_check='';


        if($webid && trim($webid) !=''){
            $webinar = gotowp_personal_get_webinar ( $webid );
            if($webinar && strpos($webinar->registrationUrl,'https://attendee.gotowebinar.com/rt/') !== false){
              $is_series = true;$url_to_check=$webinar->registrationUrl;
            }
        }



        $options = '';
        $webinar_count = 0;
        $days = (int) $days;
        $currdatetime= new GotowppreDateTime ();
        $currtimestamp = $currdatetime->getTimestamp();

        if($days){
          $currdatetime->modify('+ '.$days.'days');
          $maxtimestamp =  $currdatetime->getTimestamp();
        }


        foreach ( $webinars as $webi ) {

            $web_key = $webi->webinarKey;
            $web_key1 = trim ( $web_key );

            if($is_series && $url_to_check != $webi->registrationUrl){
              continue;
            }

            $subject = $webi->subject;
            $timezone_string = $webi->timeZone;
            $startTime = new GotowppreDateTime ( $webi->times [0]->startTime );
            $startTime->setTimezone ( new DateTimeZone ( $timezone_string ) );
            $endTime = new GotowppreDateTime ( $webi->times [0]->endTime );
            $endTime->setTimezone ( new DateTimeZone ( $timezone_string ) );

            $webstarttimestamp = $startTime->getTimestamp();
            $webendtimestamp = $endTime->getTimestamp();

            if(($currtimestamp > $webendtimestamp) ||(!empty($include_ids) && !in_array($web_key1,$include_ids)) ||  (!empty($exclude_ids) && in_array($web_key1,$exclude_ids)) || ($days && $webstarttimestamp > $maxtimestamp)){
              continue;
            }

            if ($webinar_price_check == 1) {
                $price = get_option ( 'gotowp_premium_webinar_price_all' );
            } else {
                $web_key_price = 'gotowp_premium_webinar_price_' . $web_key1;
                $price = get_option ( $web_key_price );
            }

            $date_title = $startTime->format ( 'F j, Y, \a\t h:iA T' );
            $webi_title = $date_title . ' : ' . $subject;
            $options .= '<option data-price="' . $price . '" value="' . $web_key1 . '">' . $webi_title . '</option>';
            $webinar_count++;

        }

        if($webinar_count){
          $output .= '<tr><td style="min-width: 65px;" class="label">Webinars</td><td class="value"><select class="required" name="webinars_list" id="webinars_list">';
          $output .= '<option value="">Select Webinar</option>';
          $output .= $options;
          $output .= '</select></td></tr>';
        }else{
          $output .= 'Sorry no webinar';
        }




    } else {

        if (isset ( $registration_fields->fields ) && count ( $registration_fields->fields ) > 0) {
            foreach ( $registration_fields->fields as $row ) :
            $class = '';
            if ($row->required) {
                $class = 'required';
            }
            if ($row->field == 'email') {
                $class .= ' email';
            }

            $output .= '<tr class="gotowp-' . $row->field . '"><td >' . ucwords ( preg_replace ( '/(?=([A-Z]))/', ' ${2}', $row->field ) ) . '</br>';

            if (isset ( $row->answers )) {
                $output .= '
                        <select name="' . $row->field . '" id="' . $row->field . '" class="gotowp-select ' . $class . '">
                        <option selected="selected" value="">--Select--</option>';

                foreach ( $row->answers as $opt ) :
                $output .= ' <option value="' . $opt . '">' . $opt . '</option>';
                endforeach
                ;

                $output .= '</select>';
            } else {
                $output .= '<input class="gotowp-input-text ' . $class . '" type="text" size=20  name="' . $row->field . '" id="' . $row->field . '" />';
            }

            $output .= '</td></tr>';
            endforeach
            ;


                   if (isset ( $registration_fields->questions ) && count ( $registration_fields->questions ) > 0) {


                        foreach ( $registration_fields->questions as $row ) :
                                    $class = '';
                                    if ($row->required) {
                                        $class = 'required';
                                    }
                                   if (strtolower($row->question) == 'email') {
                                        $class .= ' email';
                                    }

                            $label = $row->question;
                            $field_name = $row->questionKey;

                        $output .= '<tr class="row gotowp-' . $field_name . '"><td class="cell">' . ucwords ( preg_replace ( '/(?=([A-Z]))/', ' ${2}', $label ) ) . '</br>';


                                $class .= ' wp-goto-' . $row->questionKey . ' form-row-wide';
                                if (isset ( $row->answers )) {
                                        $output .= '<select name="' . $row->questionKey . '" id="' . $row->questionKey . '" class="gotowp-select ' . $class . '">
                                                      <option selected="selected" value="">--Select--</option>';                        
                                    $options = array ();

                                    foreach ( $row->answers as $opt ) :
                                        $options [$opt->answerKey] = $opt->answer;
                                        $output .= ' <option value="' . $opt->answerKey . '">' . $opt->answer . '</option>';
                                    endforeach;
                                        $output .= '</select>';
                                } else {

                                 $class .= ' wp-goto-' . $row->questionKey . ' form-row-wide';

                                if ($row->type =='shortAnswer') {
                                        $type = 'text';
                                        $output .= '<input class="gotowp-input-text ' . $class . '" type="'.$type.'" size=20  name="' . $field_name . '" id="' . $field_name . '" />';

                                } else {
                                      $type = 'textarea';
                                      $output .='<textarea class="gotowp-input-text ' . $class . '" name="' . $field_name . '" id="' . $field_name . '" cols="30" rows="5"></textarea>';
                                }


                                }
                           $output .= '</td></tr>';
                        endforeach
                        ;
                    }


        } else {
            $output .= '<tr class="gotowp-firstName"><td >First Name</td><td>';
            $output .= '<input class="gotowp-input-text required" type="text" size=20  name="firstName" id="firstName" />';
            $output .= '<tr class="gotowp-lastName"><td >Last Name</td><td>';
            $output .= '<input class="gotowp-input-text required " type="text" size=20  name="lastName" id="lastName" />';
            $output .= '<tr class="gotowp-email"><td >Email</td><td>';
            $output .= '<input class="gotowp-input-text required email" type="text" size=20  name="email" id="email" />';
        }
    }

    if ($type1 == 'single' && gotowp_personal_is_captcha_enabled()) {
       $output .= gotowp_form_get_captcha($webid, 'tr');
    }


    return $output;
}



function gotowp_personal_get_registration_fields($web_key) {
    $web_key = trim ( $web_key );
    $webinar_option_key = 'gotowp_premium_webinar_form_id_' . $web_key;
    $webinar_option_key = trim ( $webinar_option_key );

    if (get_option ( $webinar_option_key ) !== false) {
        $response = get_option ( $webinar_option_key );
        $form_obj = gotowp_personal_json_decode ( $response );
        if (! isset ( $form_obj->fields )) {
            $response = gotowp_personal_update_registration_fields ( $web_key );
        } else {
            return $form_obj;
        }
    } else {
        $response = gotowp_personal_update_registration_fields ( $web_key );
    }
    $request = gotowp_personal_json_decode ( $response );
    return $request;
}

function custom_wp_redirect($page_url =  false) {

    if(!$page_url){    
    $payment_return_url = trim ( get_option ( 'gotowp_premium_payment_return_url' ) );

    if ($payment_return_url) {
        if (! filter_var ( $payment_return_url, FILTER_VALIDATE_URL )) {
            $payment_return_url = ( int ) $payment_return_url;
            if ($payment_return_url > 0) {
                $payment_return_url = get_permalink ( $payment_return_url );
            }
        }
    } else {
        $payment_return_url = get_option ( 'siteurl' );
    }
   }   else{
      $payment_return_url = $page_url;
   } 

    echo '<script type="text/javascript">
          <!--
             window.location="' . $payment_return_url . '";
          //-->
          </script>';

    // wp_redirect( $payment_return_url ); exit;
}
function gotowp_personal_plugin_query_vars($qvars) {
  $qvars[] = 'ipn_action';
  $qvars[] = 'return_action';
  return $qvars;
}
add_filter ( 'query_vars', 'gotowp_personal_plugin_query_vars' );

add_action ( 'init', 'gotowp_personal_plugin_rewrite_rules' );
function gotowp_personal_plugin_rewrite_rules() {
    add_rewrite_rule ( 'ipn_action/paypal/?$', 'index.php?ipn_action=paypal', 'top' );
    add_rewrite_rule ( 'return_action/paypal/?$', 'index.php?return_action=paypal', 'top' );
}
function gotowp_personal_plugin_parse_request($wp) {
    if (array_key_exists ( 'ipn_action', $wp->query_vars ) && $wp->query_vars ['ipn_action'] == 'paypal') {
        gotowp_personal_plugin_proccess_paypal_ipn ( $wp );
    }  elseif (array_key_exists ( 'return_action', $wp->query_vars ) && $wp->query_vars ['return_action'] == 'paypal') {
        gotowp_personal_plugin_proccess_paypal_return ( $wp );
    }
}
add_action ( 'parse_request', 'gotowp_personal_plugin_parse_request' );
function gotowp_personal_mailchimp_add_contact($email, $first_name, $last_name) {
    if (get_option ( 'gotowp_premium_webinar_mailchimp_username' ) !== false && get_option ( 'gotowp_premium_webinar_mailchimp_password' ) !== false && get_option ( 'gotowp_premium_webinar_mailchimp_api_key' ) !== false && get_option ( 'gotowp_premium_webinar_mailchimp_email_list' ) !== false)

    {
        $mailchimp_username = trim ( get_option ( 'gotowp_premium_webinar_mailchimp_username' ) );
        $mailchimp_password = trim ( get_option ( 'gotowp_premium_webinar_mailchimp_password' ) );
        $mailchimp_api_key = trim ( get_option ( 'gotowp_premium_webinar_mailchimp_api_key' ) );
        $mailchimp_email_list_id = trim ( get_option ( 'gotowp_premium_webinar_mailchimp_email_list' ) );
        $list_id = $mailchimp_email_list_id;

        require_once GOTOWP_PERSONAL_PLUGIN_PATH . 'inc/MCAPI.class.php';
        $api = new MCAPI ( $mailchimp_api_key );
        $merge_vars = array (
                'FNAME' => $first_name,
                'LNAME' => $last_name
        );

        if ($api->listSubscribe ( $list_id, $email, $merge_vars, 'html', false ) === true) {
            // return 'Success! Check your email to confirm sign up.';
        } else {
            // return 'Error: ' . $api->errorMessage;
        }
    }
}


function gotowp_personal_update_registration_fields($web_key) {
    $web_key = trim ( $web_key );
    $organizer_key = trim ( get_option ( 'gotowp_premium_organizer_key' ) );
    $access_token = trim ( get_option ( 'gotowp_premium_access_token' ) );

    if ($organizer_key != '' && $access_token != '') {
        $webinar_option_key = 'gotowp_premium_webinar_form_id_' . $web_key;
        $webinar_option_key = trim ( $webinar_option_key );
        delete_option ( $webinar_option_key );

        $gtw_url = "https://api.citrixonline.com/G2W/rest/organizers/" . $organizer_key . "/webinars/" . $web_key . "/registrants/fields";
        $headers = array (
                "HTTP/1.1",
                "Accept: application/json",
                "Accept: application/vnd.citrix.g2wapi-v1.1+json",
                "Content-Type: application/json",
                "Authorization: OAuth oauth_token=$access_token"
        );
        $curl = curl_init ();
        curl_setopt ( $curl, CURLOPT_POST, 0 );
        curl_setopt ( $curl, CURLOPT_HTTPHEADER, $headers );
        curl_setopt ( $curl, CURLOPT_URL, $gtw_url );
        curl_setopt ( $curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0 );
        curl_setopt ( $curl, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, 1 );
        $response = curl_exec ( $curl );
        $res_obj = gotowp_personal_json_decode ( $response );
        curl_close ( $curl );
        if ($response && isset ( $res_obj->fields )) {
            update_option ( $webinar_option_key, $response, '', 'yes' );
            return $response;
        } else {
            return false;
        }
    } else {
        return false;
    }
}
function gotowp_personal_update_webinars_registration_fields($curl_response) {
    if ($curl_response) {
        $webinars = gotowp_personal_json_decode ( $curl_response );
        foreach ( $webinars as $webinar ) {
            $registration_url = $webinar->registrationUrl;
            $web_key = str_replace ( 'https://attendee.gotowebinar.com/register/', '', $registration_url );
            $web_key = trim ( $web_key );
            gotowp_personal_update_registration_fields ( $web_key );
            gotowp_personal_update_webinar ( $web_key );
            gotowp_personal_update_registrants($web_key);
        }
    }
}
function gotowp_personal_update_webinars() {
    $organizer_key = trim ( get_option ( 'gotowp_premium_organizer_key' ) );
    $access_token = trim ( get_option ( 'gotowp_premium_access_token' ) );

    if ($organizer_key != '' && $access_token != '') {

        $url = 'https://api.citrixonline.com/G2W/rest/organizers/' . $organizer_key . '/upcomingWebinars';
        $curl = curl_init ( $url );

        $headers = array (
                "HTTP/1.1",
                "Accept: application/json",
                "Content-Type: application/json",
                "Authorization: OAuth oauth_token=$access_token"
        );


        $myOptions = array (
                CURLOPT_POST => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HTTPHEADER => $headers
        );

        curl_setopt_array ( $curl, $myOptions );
        $curl_response = curl_exec ( $curl );
        $resp_arr = gotowp_personal_json_decode ( $curl_response );

          if ($curl_response) {
            update_option ( 'gotowp_premium_webinars_option', $curl_response );
            if(!isset($resp_arr->errorCode)){
                gotowp_personal_update_webinars_registration_fields ( $curl_response );
            }
            

            return $curl_response;
        } else {
            return false;
        }
    } else {
        return false;
    }
}


function gotowp_personal_constant_contact_add_contact($email, $first_name, $last_name) {
    if (get_option ( 'gotowp_premium_webinar_constant_contact_username' ) !== false && get_option ( 'gotowp_premium_webinar_constant_contact_password' ) !== false && get_option ( 'gotowp_premium_webinar_constant_contact_api_key' ) !== false && get_option ( 'gotowp_premium_webinar_constant_contact_email_list' ) !== false)

    {
        $constant_contact_username = trim ( get_option ( 'gotowp_premium_webinar_constant_contact_username' ) );
        $constant_contact_password = trim ( get_option ( 'gotowp_premium_webinar_constant_contact_password' ) );
        $constant_contact_api_key = trim ( get_option ( 'gotowp_premium_webinar_constant_contact_api_key' ) );
        $constant_contact_email_list = trim ( get_option ( 'gotowp_premium_webinar_constant_contact_email_list' ) );

        require_once GOTOWP_PERSONAL_PLUGIN_PATH . 'inc/cc_oauth/Authentication.php';
        require_once GOTOWP_PERSONAL_PLUGIN_PATH . 'inc/cc_oauth/Collections.php';
        require_once GOTOWP_PERSONAL_PLUGIN_PATH . 'inc/cc_oauth/Components.php';
        require_once GOTOWP_PERSONAL_PLUGIN_PATH . 'inc/cc_oauth/ConstantContact.php';
        $Datastore = new CTCTDataStore ();
        $ConstantContact = new ConstantContact ( 'basic', $constant_contact_api_key, $constant_contact_username, $constant_contact_password );
        $lists = $ConstantContact->getLists ();
        $nextLists = "";

        do {
            if ($nextLists != "") {
                $Lists = $nextLists;
                $nextLists = $ConstantContact->getLists ( $Lists ['nextLink'] );
            } else if ($nextLists == "") {
                $nextLists = $ConstantContact->getLists ();
                $Lists = $nextLists;
            }

            foreach ( $Lists ['lists'] as $list ) {
                if ($list->name == $constant_contact_email_list)
                    $cc_daily_deals_list_id = $list->id;
            }
        } while ( $Lists ['nextLink'] != false );

        $search = $ConstantContact->searchContactsByEmail ( $email );

        if ($search == false) {
            $Contact = new Contact ();
            $Contact->emailAddress = $email;
            $Contact->firstName = $first_name;
            $Contact->lastName = $last_name;
            $Contact->lists = array (
                    $cc_daily_deals_list_id
            );
            $NewContact = $ConstantContact->addContact ( $Contact );
            if ($NewContact->status == 'Active') {
            }
        } else {

        }
    } else {
    }
}
function gotowp_personal_plugin_proccess_paypal_return($wp) {
    $site_url = get_option ( 'siteurl' );
    wp_redirect ( $site_url );
    exit ();
}
function gotowp_personal_has_shortcode($shortcode) {
    global $post;
    $found = false;
    if (function_exists ( 'has_shortcode' )) {
        if (is_object($post) && has_shortcode ( $post->post_content, $shortcode )) {
            $found = TRUE;
        }
    } else {
        if (gotowp_custom_has_shortcode ( $shortcode )) {
            $found = TRUE;
        }
    }
    return $found;
}
function gotowp_personal_custom_has_shortcode() {
    global $post;
    $found = false;
    if (! $shortcode) {
        return $found;
    }
    if (stripos ( get_the_content (), '[' . $shortcode ) !== FALSE) {
        $found = TRUE;
    }
    return $found;
}



function gotowp_personal_get_webinars() {
    if (get_option ( 'gotowp_premium_webinars_option' ) !== false) {
        $webinars_option = get_option ( 'gotowp_premium_webinars_option' );
        if ($webinars_option) {
            update_option ( 'gotowp_premium_webinars_option', $webinars_option );
        }
    } else {
        $webinars_option = gotowp_personal_update_webinars ();
        if ($webinars_option) {
            update_option ( 'gotowp_premium_webinars_option', $webinars_option );
        }
    }
    return $webinars_option;
}
function gotowp_personal_get_webinar($webinarKey) {
    $webinarKey = trim ( $webinarKey );

    if ($webinarKey != '' && ! empty ( $webinarKey )) {
        $organizer_key = trim ( get_option ( 'gotowp_premium_organizer_key' ) );
        $access_token = trim ( get_option ( 'gotowp_premium_access_token' ) );

        if ($organizer_key != '' && $access_token != '') {
            $webinar_option_key = 'gotowp_premium_webinar_id_' . $webinarKey;
            $webinar_option_key = trim ( $webinar_option_key );

            if (get_option ( $webinar_option_key ) !== false) {
                $response = get_option ( $webinar_option_key );
                $webinar_obj = gotowp_personal_json_decode ( $response );
                if (! isset ( $webinar_obj->webinarKey )) {
                    $response = gotowp_personal_update_webinar ( $webinarKey );
                } else {
                    return $webinar_obj;
                }
            } else {
                $response = gotowp_personal_update_webinar ( $webinarKey );
            }

            $request = gotowp_personal_json_decode ( $response );
            return $request;
        } else {
            return false;
        }
    } else {
        return false;
    }
}
function gotowp_personal_update_webinar($webinarKey) {
    $webinarKey = trim ( $webinarKey );
    $organizer_key = trim ( get_option ( 'gotowp_premium_organizer_key' ) );
    $access_token = trim ( get_option ( 'gotowp_premium_access_token' ) );

    if ($organizer_key != '' && $access_token != '') {

        $webinar_option_key = 'gotowp_premium_webinar_id_' . $webinarKey;
        $webinar_option_key = trim ( $webinar_option_key );
        delete_option ( $webinar_option_key );

        // https://api.citrixonline.com/G2W/rest/organizers/{organizerKey}/webinars/{webinarKey}
        $url = 'https://api.citrixonline.com/G2W/rest/organizers/' . $organizer_key . '/webinars/' . $webinarKey;
        $curl = curl_init ( $url );

        $headers = array (
                "HTTP/1.1",
                "Accept: application/json",
                "Content-Type: application/json",
                "Authorization: OAuth oauth_token=$access_token"
        );

        $myOptions = array (
                CURLOPT_POST => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HTTPHEADER => $headers
        );
        curl_setopt_array ( $curl, $myOptions );
        $curl_response = curl_exec ( $curl );
        curl_close ( $curl );
        $webinar_obj = gotowp_personal_json_decode ( $curl_response );
        if (isset ( $webinar_obj->webinarKey )) {
            update_option ( $webinar_option_key, $curl_response, '', 'yes' );
            $response = $curl_response;
            return $curl_response;
        } else {
            return false;
        }
    } else {
        return false;
    }
}


function gotowp_personal_update_registrants($webinarKey) {
    $webinarKey = trim ( $webinarKey );
    $organizer_key = trim ( get_option ( 'gotowp_premium_organizer_key' ) );
    $access_token = trim ( get_option ( 'gotowp_premium_access_token' ) );

    if ($organizer_key != '' && $access_token != '') {

        $webinar_option_key = 'gotowp_premium_webinar_registrants_' . $webinarKey;
        $webinar_option_key = trim ( $webinar_option_key );
        delete_option ( $webinar_option_key );
        // https://api.citrixonline.com/G2W/rest/organizers/{organizerKey}/webinars/{webinarKey}/registrants
        $url = 'https://api.citrixonline.com/G2W/rest/organizers/' . $organizer_key . '/webinars/' . $webinarKey .'/registrants';
        $curl = curl_init ( $url );

        $headers = array (
                "HTTP/1.1",
                "Accept: application/json",
                "Content-Type: application/json",
                "Authorization: OAuth oauth_token=$access_token"
        );

        $myOptions = array (
                CURLOPT_POST => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HTTPHEADER => $headers
        );
        curl_setopt_array ( $curl, $myOptions );
        $curl_response = curl_exec ( $curl );
        curl_close ( $curl );
        update_option ( $webinar_option_key, $curl_response, '', 'yes' );
        return $curl_response;
    } else {
        return false;
    }
}


function gotowp_personal_get_webinar_price($webinarKey) {
    $webinarKey = trim ( $webinarKey );
    $price = false;
    if ($webinarKey != '' && ! empty ( $webinarKey )) {
      $webinar_price_check = get_option ( 'gotowp_premium_webinar_price_check' );
        if ($webinar_price_check == 1) {
            $price = get_option ( 'gotowp_premium_webinar_price_all' , false );
        } else {
            $web_key_price = 'gotowp_premium_webinar_price_' . $webinarKey;
            $price = get_option ( $web_key_price, false );
        }     
        return $price; 
    } else {
        return false;
    }
}


function gotowp_personal_get_registrants($webinarKey) {
    $webinarKey = trim ( $webinarKey );
    if ($webinarKey != '' && ! empty ( $webinarKey )) {
        $webinar_option_key = 'gotowp_premium_webinar_registrants_' . $webinarKey;
        $webinar_option_key = trim ( $webinar_option_key );
        if (get_option ( $webinar_option_key ) !== false) {
            $response = get_option ( $webinar_option_key );
        } else {
            $response = gotowp_personal_update_registrants ( $webinarKey );
        }
        $registrants_obj = gotowp_personal_json_decode ( $response );
        return $registrants_obj;
    } else {
        return false;
    }
}


function gotowp_personal_get_registrants_fields($webinarKey,$field='email') {
    $webinarKey = trim ( $webinarKey );
    if ($webinarKey != '' && ! empty ( $webinarKey )) {
        $registrants= gotowp_personal_get_registrants($webinarKey);
        if($registrants && count($registrants) > 0){
            $data = wp_list_pluck($registrants,$field);
            return $data;
        }else{
            return false;
        }

    } else {
        return false;
    }
}


function gotowp_personal_get_registrants_count($webinarKey) {
    $count = 0;
    $webinarKey = trim ( $webinarKey );
    if ($webinarKey != '' && ! empty ( $webinarKey )) {
        $webinar_option_key = 'gotowp_premium_webinar_registrants_' . $webinarKey;
        $webinar_option_key = trim ( $webinar_option_key );
        if (get_option ( $webinar_option_key ) !== false) {
            $registrants_obj = gotowp_personal_get_registrants($webinarKey);
            $count = count ( $registrants_obj );
        }
    }
    return $count;
}


function gotowp_personal_get_webinars_fields($field='webinarKey') {
    $res = gotowp_personal_get_webinars();
    $webinars = gotowp_personal_json_decode($res);
    if($webinars && !isset($webinars->errorCode) && !isset($webinars->int_err_code)){
		$webinars_id=wp_list_pluck($webinars, $field);
		return $webinars_id;
    }
    return false;
}




function gotowp_personal_custom_tinymce_plugin( $plugin_array ) {
  $plugin_array['mce_gotowp_button'] = GOTOWP_PERSONAL_PLUGIN_URL.'javascripts/mceplugin.js';
  return $plugin_array;
}

function gotowp_personal_register_mce_button( $buttons ) {
  array_push( $buttons, 'mce_gotowp_button' );
  return $buttons;
}


add_filter ( 'the_content', 'gotowp_webinar_the_content_cb' );
function gotowp_webinar_the_content_cb($content) {
    global $post;

    $webinar_option_key = 'gotowp_premium_webinar_shop_page';
    $webinar_shop_page = get_option ( $webinar_option_key );

    if (is_object($post) && $webinar_shop_page && $post->ID == $webinar_shop_page) {

        $webinars=gotowp_personal_json_decode(gotowp_personal_get_webinars());

        // $content = $content;

        $page_layout_type =get_option ( 'gotowp_premium_webinar_page_layout_type', 'columns2');

        $content .= '<div class="webinars '.$page_layout_type.'">';

        foreach ( $webinars as $webinar ) :
        $webinar_key = $webinar->webinarKey;
        $webinar_key = trim ( $webinar_key );
        $webinar_option_key = 'gotowp_premium_webinar_price_' . $webinar_key;
        $price1 = get_option ( $webinar_option_key );
        $content .= gotowp_personal_get_registration_form ( $webinar_key, $price1, 'single' );
        endforeach;

        $content .= '</div>';

        // $content = '[register_webinar webid=1145489170056064002 amount=0 type=single]';
        // $content .= '[register_webinar webid=4507626403100272641 amount=0 type=single]';
    }
    return $content;
}



function gotowp_personal_get_form_rows($webid, $type1) {
    global $webinarErrors;
    $output = '';
    $webid = trim ( $webid );


    if ($type1 == 'single') {
        $registration_fields = gotowp_personal_get_registration_fields ( $webid );
        $webinar = gotowp_personal_get_webinar ( $webid );
    }

    if (isset ( $registration_fields->fields ) && count ( $registration_fields->fields ) > 0) {
        foreach ( $registration_fields->fields as $row ) :
        $class = '';
        if ($row->required) {
            $class = 'required';
        }
        if ($row->field == 'email') {
            $class = $class . ' email';
        }

        $output .= '<div class="row gotowp-' . $row->field . '"><div class="cell">' . ucwords ( preg_replace ( '/(?=([A-Z]))/', ' ${2}', $row->field ) ) . '</div><div class="cell">';

        if (isset ( $row->answers )) {
            $output .= '
                        <select name="' . $row->field . '" id="' . $row->field . '" class="gotowp-select ' . $class . '">
                        <option selected="selected" value="">--Select--</option>';

            foreach ( $row->answers as $opt ) :
            $output .= ' <option value="' . $opt . '">' . $opt . '</option>';
            endforeach
            ;

            $output .= '</select>';
        } else {
            $output .= '<input class="gotowp-input-text ' . $class . '" type="text" size=20  name="' . $row->field . '" id="' . $row->field . '" />';
        }

        $output .= '</div></div>';
        endforeach
        ;



                   if (isset ( $registration_fields->questions ) && count ( $registration_fields->questions ) > 0) {


                        foreach ( $registration_fields->questions as $row ) :
                                    $class = '';
                                    if ($row->required) {
                                        $class = 'required';
                                    }
                                   if (strtolower($row->question) == 'email') {
                                        $class .= ' email';
                                    }

                            $label = $row->question;
                            $field_name = $row->questionKey;

                        $output .= '<div class="row gotowp-' . $field_name . '"><div class="cell">' . ucwords ( preg_replace ( '/(?=([A-Z]))/', ' ${2}', $label ) ) . '</div><div class="cell">';


                                $class .= ' wp-goto-' . $row->questionKey . ' form-row-wide';
                                if (isset ( $row->answers )) {
                                        $output .= '<select name="' . $row->questionKey . '" id="' . $row->questionKey . '" class="gotowp-select ' . $class . '">
                                                      <option selected="selected" value="">--Select--</option>';                        
                                    $options = array ();

                                    foreach ( $row->answers as $opt ) :
                                        $options [$opt->answerKey] = $opt->answer;
                                        $output .= ' <option value="' . $opt->answerKey . '">' . $opt->answer . '</option>';
                                    endforeach;
                                        $output .= '</select>';
                                } else {

                                 $class .= ' wp-goto-' . $row->questionKey . ' form-row-wide';

                                if ($row->type =='shortAnswer') {
                                        $type = 'text';
                                        $output .= '<input class="gotowp-input-text ' . $class . '" type="'.$type.'" size=20  name="' . $field_name . '" id="' . $field_name . '" />';

                                } else {
                                      $type = 'textarea';
                                      $output .='<textarea class="gotowp-input-text ' . $class . '" name="' . $field_name . '" id="' . $field_name . '" cols="30" rows="5"></textarea>';
                                }


                                }
                           $output .= '</div></div>';
                        endforeach
                        ;
                    }










    } else {
        $output .= '<div class="row gotowp-firstName"><div class="cell" >First Name</div><div class="cell">';
        $output .= '<input class="gotowp-input-text required" type="text" size=20  name="firstName" id="firstName" /></div></div>';
        $output .= '<div class="row gotowp-lastName"><div class="cell" >Last Name</div><div class="cell">';
        $output .= '<input class="gotowp-input-text required " type="text" size=20  name="lastName" id="lastName" /></div></div>';
        $output .= '<div class="row gotowp-email"><div class="cell" >Email</div><div class="cell">';
        $output .= '<input class="gotowp-input-text required email" type="text" size=20  name="email" id="email" /></div></div>';
    }

       // require_once GOTOWP_PERSONAL_PLUGIN_PATH.'inc/securimage/securimage.php';



     if ($type1 == 'single' && gotowp_personal_is_captcha_enabled()) {
        $output .= gotowp_form_get_captcha($webid);
     }


    return $output;
}




function gotowp_form_is_validate_captcha($captcha_code){
        $captcha_code = trim($captcha_code);
        include_once GOTOWP_PERSONAL_PLUGIN_PATH . 'inc/securimage/securimage.php';
        $securimage = new Securimage();
        if ($securimage->check($captcha_code) == false) {
              return false;
        }
      return true;
}


function gotowp_form_get_captcha($webid, $format ='div'){

       $image_id = 'captcha_image_'.$webid;
       $input_id = 'captcha_code_'.$webid;
       $refresh_alt = 'Refresh Image';
       $refresh_title = 'Refresh Image';
       $image_alt      ='CAPTCHA Image';


       $securimage_url =GOTOWP_PERSONAL_PLUGIN_URL.'inc/securimage';
       $show_url = $securimage_url . '/securimage_show.php?';
       $icon_url = GOTOWP_PERSONAL_PLUGIN_URL . 'inc/securimage/images/refresh.png';

       $img_tag = sprintf('<img class="captcha-refresh-img" height="32" width="32" src="%s" alt="%s" onclick="this.blur()" align="bottom" border="0" />', htmlspecialchars($icon_url), htmlspecialchars($refresh_alt));

         $output = '';

         $img_markup = '<img id="'.$image_id.'" src="'.GOTOWP_PERSONAL_PLUGIN_URL.'inc/securimage/securimage_show.php" alt="'.$image_alt.'" />';
         $inp_markup = '<input id="'.$input_id.'" class="gotowp-input-text required" type="text" name="captcha_code" size="20" maxlength="6" />';
         $refresh_markup = sprintf('<a class="captcha-refresh-link" tabindex="-1" style="border: 0" href="#" title="%s" onclick="document.getElementById(\'%s\').src = \'%s\' + Math.random(); this.blur(); return false">%s</a><br />', htmlspecialchars($refresh_title),$image_id,$show_url, $img_tag );


       if($format == 'div'){
            $output .= '<div class="row gotowp-captcha"><div class="cell" >Captcha</div><div class="cell" >'.$img_markup.'</div><div class="cell">';
            $output .= $inp_markup;
            $output .= $refresh_markup;
            $output .='</div></div>';
       }else{
            $output .= '<tr class="row gotowp-captcha-img"><td class="cell" >Captcha</td><td class="cell" >'.$img_markup.'</td></tr><tr class="row gotowp-captcha-input"><td class="cell">&nbsp;</td><td class="cell">';
            $output .= $inp_markup;
            $output .= $refresh_markup;
            $output .='</td></tr>';
       }
    return $output;
}



add_action ( 'wp_ajax_gotowp_personal_webdata_action', 'gotowp_personal_webdata_action_cb' );
function gotowp_personal_webdata_action_cb() {
     $ret_array = array();
     $wresponse=gotowp_personal_get_webinars();
     $webinars=gotowp_personal_json_decode($wresponse); 
     $webinars_data = false;
     $trainings_data = false;

     if($webinars && !isset($webinars->errorCode) && !isset($webinars->int_err_code)):    
        foreach ( $webinars as $webinar ) :
            $webinar_key = $webinar->webinarKey;
            $webinar_key = trim ( $webinar_key );
            $subject = $webinar->subject;
            $webinars_data[] = array('text' => $subject,'value' => $webinar_key);
        endforeach;
     endif;  

     $types = array();
     $types[] = array('text' => 'Single','value' => 'single');
     $types[] = array('text' => 'List','value' => 'list');

    $ret_array['types'] = $types;
    $ret_array['webinars'] = $webinars_data;
    wp_send_json($ret_array);

}


function webinar_credit_card_form_the_content_cb($content) {
    global $credit_form_data;
    $content = $credit_form_data . $content;
    return $content;
}
function webinar_paypal_form_the_content_cb($content) {
    global $paypal_form_data;
    $content = $paypal_form_data . $content;
    return $content;
}


function gotowp_get_paypal_payment_form($item_name1, $webinarid, $amount, $last_id) {

    $payment_mode = trim ( get_option ( 'gotowp_premium_payment_mode' ) );
    $payment_email = trim ( get_option ( 'gotowp_premium_payment_email' ) );
    $site_url = get_option ( 'home' );

    if ($payment_mode == 'LIVE') {
        $urls = 'https://www.paypal.com/cgi-bin/webscr';
    } else {
        $urls = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
    }

    $currency_code = get_option ( 'gotowp_premium_currency_code' );

    if (get_option ( 'gotowp_premium_payment_return_url' )) {
        $payment_return_url = trim ( get_option ( 'gotowp_premium_payment_return_url' ) );
    } else {
        $payment_return_url = trailingslashit ( $site_url ) . '?return_action=paypal';
    }

    if (get_option ( 'gotowp_personal_paypal_ipn_url' )) {
        $notify_url = trim ( get_option ( 'gotowp_premium_paypal_ipn_url' ) );
    } else {
        $notify_url = trailingslashit ( $site_url ) . '?ipn_action=paypal';
    }

    if (! filter_var ( $payment_return_url, FILTER_VALIDATE_URL )) {
        $payment_return_url = ( int ) $payment_return_url;
        if ($payment_return_url > 0) {
            $payment_return_url = get_permalink ( $payment_return_url );
        }
    }

    //                     <input type="hidden" name="item_number" value="' . $webinarid . '">

    $paypalForm = '<form action="' . $urls . '" method="post" name="webinar_payment">
                    <input type="hidden" name="cmd" value="_xclick">
                    <input type="hidden" name="business" value="' . $payment_email . '">
                    <input type="hidden" name="item_name" value="' . stripslashes ( htmlentities ( $item_name1, ENT_QUOTES ) ) . '">

                    <input type="hidden" name="amount" value="' . $amount . '">
                    <input type="hidden" name="currency_code" value="' . $currency_code . '">
                    <input type="hidden" name="return" value="' . $payment_return_url . '">
                    <input type="hidden" name="custom" value="' . $last_id . '" />
                    <input type="hidden" name="rm" value="2" />
                    <input type="hidden" name="no_note" value="0">
                    <input type="hidden" name="notify_url" value="' . $notify_url . '" >
                    </form>';

    $paypalForm .= "<script>document.webinar_payment.submit();</script>";

    return $paypalForm;


}


function gotowp_personal_custom_mce_button() {
  if ( !current_user_can( 'edit_posts' ) && !current_user_can( 'edit_pages' ) ) {
    return;
  }
  if ( 'true' == get_user_option( 'rich_editing' ) ) {
    add_filter( 'mce_external_plugins', 'gotowp_personal_custom_tinymce_plugin' );
    add_filter( 'mce_buttons', 'gotowp_personal_register_mce_button' );
  }  
}
add_action('admin_head', 'gotowp_personal_custom_mce_button');



function credit_card_form() {
    ?>

<form name="creditcardpayment" id="creditcardpayment" action=""    method="post">
    <table>
        <tr>
            <td><label for="credit_card">Credit Card Number</label></td>
            <td><input type="text" name="credit_card" id="credit_card" autocomplete="off" maxlength="19" value=""></td>
        </tr>
        <tr>
            <td><label for="expiration_month">Expiration Date</label></td>
            <td><select name="expiration_month" id="expiration_month">
                    <option value=""></option>
                        <?php $month_arr=range(1,12); ?>
                        <?php foreach($month_arr as $monthkey): ?>
                        <option value="<?php echo $monthkey; ?>"><?php echo $monthkey; ?></option>
                        <?php endforeach; ?>
                    </select> <select name="expiration_year"
                id="expiration_year">
                    <option value=""></option>
                        <?php $year=date('Y');$year_arr=range($year,$year+20); ?>
                        <?php foreach($year_arr as $yearkey): ?>
                        <option value="<?php echo $yearkey; ?>"><?php echo $yearkey; ?></option>
                        <?php endforeach; ?>
                    </select></td>
        </tr>

        <tr>
            <td><label for="cvv">Security Code</label></td>
            <td><input type="text" name="cvv" id="cvv" autocomplete="off" value="" maxlength="4"></td>
        </tr>

        <tr>
            <td><label for="cardholder_first_name">First Name</label></td>
            <td><input type="text" name="cardholder_first_name" id="cardholder_first_name" maxlength="30" value=""></td>
        </tr>
        <tr>
            <td><label for="cardholder_last_name">Last Name</label></td>
            <td><input type="text" name="cardholder_last_name" id="cardholder_last_name" maxlength="30" value=""></td>
        </tr>
        <tr>
            <td><label for="billing_address">Billing Address</label></td>
            <td><input type="text" name="billing_address" id="billing_address" maxlength="45" value=""></td>
        </tr>
        <tr>
            <td><label for="billing_address2">Suite/Apt #</label></td>
            <td><input type="text" name="billing_address2" id="billing_address2" maxlength="45" value=""></td>
        </tr>
        <tr>
            <td><label for="billing_city">City</label></td>
            <td><input type="text" name="billing_city" id="billing_city" maxlength="25" value=""></td>
        </tr>
        <tr>
            <td><label for="billing_state">State</label></td>
            <td><select id="billing_state" name="billing_state">
                    <option value=""></option>
                    <option value="AL">Alabama</option>
                    <option value="AK">Alaska</option>
                    <option value="AZ">Arizona</option>
                    <option value="AR">Arkansas</option>
                    <option value="CA">California</option>
                    <option value="CO">Colorado</option>
                    <option value="CT">Connecticut</option>
                    <option value="DE">Delaware</option>
                    <option value="DC">District Of Columbia</option>
                    <option value="FL">Florida</option>
                    <option value="GA">Georgia</option>
                    <option value="HI">Hawaii</option>
                    <option value="ID">Idaho</option>
                    <option value="IL">Illinois</option>
                    <option value="IN">Indiana</option>
                    <option value="IA">Iowa</option>
                    <option value="KS">Kansas</option>
                    <option value="KY">Kentucky</option>
                    <option value="LA">Louisiana</option>
                    <option value="ME">Maine</option>
                    <option value="MD">Maryland</option>
                    <option value="MA">Massachusetts</option>
                    <option value="MI">Michigan</option>
                    <option value="MN">Minnesota</option>
                    <option value="MS">Mississippi</option>
                    <option value="MO">Missouri</option>
                    <option value="MT">Montana</option>
                    <option value="NE">Nebraska</option>
                    <option value="NV">Nevada</option>
                    <option value="NH">New Hampshire</option>
                    <option value="NJ">New Jersey</option>
                    <option value="NM">New Mexico</option>
                    <option value="NY">New York</option>
                    <option value="NC">North Carolina</option>
                    <option value="ND">North Dakota</option>
                    <option value="OH">Ohio</option>
                    <option value="OK">Oklahoma</option>
                    <option value="OR">Oregon</option>
                    <option value="PA">Pennsylvania</option>
                    <option value="RI">Rhode Island</option>
                    <option value="SC">South Carolina</option>
                    <option value="SD">South Dakota</option>
                    <option value="TN">Tennessee</option>
                    <option value="TX">Texas</option>
                    <option value="UT">Utah</option>
                    <option value="VT">Vermont</option>
                    <option value="VA">Virginia</option>
                    <option value="WA">Washington</option>
                    <option value="WV">West Virginia</option>
                    <option value="WI">Wisconsin</option>
                    <option value="WY">Wyoming</option>
                    <option value="IN">Delhi</option>
            </select></td>
        </tr>
        <tr>
            <td><label for="billing_zip">Zip Code</label></td>
            <td><input type="text" name="billing_zip" id="billing_zip" maxlength="6" value=""></td>
        </tr>

        <tr>
            <td><label for="email">Email Address</label></td>
            <td><input type="text" name="email" id="email" maxlength="50" value=""></td>
        </tr>

        <tr>
            <td>
                <input type="submit" value="Pay"> <input type="hidden" name="payment_method" value="cc" />
                <input type="hidden" name="amount" value="<?php echo $_REQUEST['amount']; ?>" />
                <input type="hidden" name="lastinsertid" value="<?php echo $_SESSION['lastid']; ?>" />
            </td>
        </tr>

    </table>
</form>


<script type="text/javascript">
jQuery(document).ready(function($){

    $('#creditcardpayment').validate({
        rules:  {
                    credit_card      :{required:true,number:true,digits:true},
                    expiration_month :{required:true,number:true,digits:true},
                    expiration_year  :{required:true,number:true,digits:true},
                    cvv              :{required:true,number:true,digits:true},
                    cardholder_first_name :{required:true,number:false,digits:false},
                    cardholder_last_name  :{required:true,number:false,digits:false},
                    billing_address  :{required:true},
                    billing_city     :{required:true},
                    billing_state    :{required:true},
                    billing_zip      :{required:true,number:true,digits:true},
                    email            :{email:true}
                }
        });

});
</script>



<?php
}









