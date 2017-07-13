<?php
/*
 * Plugin Name: GoToWP Personal
 * Plugin URI: http://www.gotowp.com/
 * Description: A WordPress plugin that handles payments and registration for GoToWebinar and GoToTraining
 * Version: 3.1.0
 * Author: GoToWP.com
 * Author URI: http://www.gotowp.com/
 * Support: http://wordpress.org/support/plugin/gotowp
 */

register_activation_hook ( __FILE__, 'gotowp_personal_install' );
register_deactivation_hook ( __FILE__, 'gotowp_personal_deactivation_func' );
if (! defined ( 'GOTOWP_PERSONAL_PLUGIN_BASENAME' ))
	define ( 'GOTOWP_PERSONAL_PLUGIN_BASENAME', plugin_basename ( __FILE__ ) );
	require_once dirname(__FILE__) . '/_gotowpp_inc.php';
	