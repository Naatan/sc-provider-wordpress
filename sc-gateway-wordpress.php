<?php

/*
Plugin Name: Social Connect - Wordpress.com Gateway
Plugin URI: http://wordpress.org/extend/plugins/social-connect/
Description: Allows you to login / register with Wordpress.com - REQUIRES Social Connect plugin
Version: 0.10
Author: Brent Shepherd, Nathan Rijksen
Author URI: http://wordpress.org/extend/plugins/social-connect/
License: GPL2
 */

require_once dirname(__FILE__) . '/openid.php';

class SC_Gateway_Wordpress 
{
	
	static function init()
	{
		add_action('social_connect_button_list',array('SC_Gateway_Wordpress','render_button'));
	}
	
	static function call()
	{
		if ( !isset($_GET['call']) OR !in_array($_GET['call'], array('connect','callback')))
		{
			return;
		}
		
		call_user_func(array('SC_Gateway_Wordpress', $_GET['call']));
	}
	
	static function render_button()
	{
		$image_url = plugins_url() . '/' . basename( dirname( __FILE__ )) . '/button.png';
		?>
		<a href="javascript:void(0);" title="WordPress.com" class="social_connect_login_wordpress"><img alt="Wordpress.com" src="<?php echo $image_url ?>" /></a>
		
		<div id="social_connect_wordpress_auth" style="display: none;">
			<input type="hidden" name="redirect_uri" value="<?php echo( SOCIAL_CONNECT_PLUGIN_URL . '/call.php?call=connect&gateway=wordpress' ); ?>" />
		</div>
		
		<div class="social_connect_wordpress_form" title="WordPress" style="display: none;">
			<p><?php _e( 'Enter your WordPress.com blog URL', 'social_connect' ); ?></p><br/>
			<p><span>http://</span> <input class="wordpress_blog_url" size="15" value=""/><span>.wordpress.com</span></p>
			</p><a href="javascript:void(0);" class="social_connect_wordpress_proceed"><?php _e( 'Proceed', 'social_connect' ); ?></a></p>
		</div>
		
		<script type="text/javascript">
		(jQuery(function($) {
			var _social_connect_wordpress_form = $($('.social_connect_wordpress_form')[0]);
			_social_connect_wordpress_form.dialog({ autoOpen: false, modal: true, dialogClass: 'social-connect-dialog', resizable: false, maxHeight: 400, width:350, maxWidth: 600 });

			var _do_wordpress_connect = function(e) {
				var wordpress_auth = $('#social_connect_wordpress_auth');
				var redirect_uri = wordpress_auth.find('input[type=hidden][name=redirect_uri]').val();
				var context = $(e.target).parents('.social_connect_wordpress_form')[0];
				var blog_name = $('.wordpress_blog_url', context).val();
				var blog_url = "http://" + blog_name + ".wordpress.com";
				redirect_uri = redirect_uri + "&wordpress_blog_url=" + encodeURIComponent(blog_url);
				
				window.open(redirect_uri,'','scrollbars=yes,menubar=no,height=400,width=800,resizable=yes,toolbar=no,status=no');
			};
			
			$(".social_connect_login_wordpress").click(function() {
				_social_connect_wordpress_form.dialog('open');
			});
			
			$(".social_connect_wordpress_proceed").click(_do_wordpress_connect);
		}));
		</script>
		
		<?php
	}
	
	static function connect()
	{
		$openid             = new LightOpenID;
		$openid->identity   = urldecode($_GET['wordpress_blog_url']);
		$openid->required   = array('namePerson', 'namePerson/friendly', 'contact/email');
		$openid->returnUrl  = SOCIAL_CONNECT_PLUGIN_URL . '/call.php?gateway=wordpress&call=callback';
		header('Location: ' . $openid->authUrl());
	}
	
	static function callback()
	{

		if($_GET['openid_mode'] == 'cancel')
		{
			return _e( 'You have cancelled this login. Please close this window and try again.', 'social-connect' );
		}
	
		$openid             = new LightOpenID;
		$openid->returnUrl  = SOCIAL_CONNECT_PLUGIN_URL . '/call.php?gateway=wordpress&call=callback';
		
		try
		{
			if ( !$openid->validate())
			{
				return _e('validation failed', 'social-connect');
			}
		}
			catch(ErrorException $e)
		{
			echo $e->getMessage();
			return;
		}

		$wordpress_id   = $openid->identity;
		$attributes     = $openid->getAttributes();
		$email          = isset($attributes['contact/email']) ? $attributes['contact/email'] : '';
		$name           = isset($attributes['namePerson']) ? $attributes['namePerson'] : '';
		$signature      = SC_Utils::generate_signature($wordpress_id);
		
		if($email == '')
		{
			return _e( 'You need to share your email address when prompted at wordpress.com. Please close this window and try again.', 'social-connect' );;
		}

		?>
		<html>
		<head>
		<script>
		function init() {
			window.opener.wp_social_connect({
				'action' : 'social_connect', 'social_connect_provider' : 'wordpress',
				'social_connect_signature' : '<?php echo $signature ?>',
				'social_connect_openid_identity' : '<?php echo $wordpress_id ?>',
				'social_connect_email' : '<?php echo $email ?>',
				'social_connect_name' : '<?php echo $name ?>'
			});
			window.close();
		}
		</script>
		</head>
		<body onload="init();"></body>
		</html>      
		<?php
	}
	
	static function process_login()
	{
		$redirect_to            = SC_Utils::redirect_to();
		$provider_identity      = $_REQUEST[ 'social_connect_openid_identity' ];
		$provided_signature     = $_REQUEST[ 'social_connect_signature' ];
		
		SC_Utils::verify_signature( $provider_identity, $provided_signature, $redirect_to );
		
		$email  = $_REQUEST[ 'social_connect_email' ];
		$name   = $_REQUEST[ 'social_connect_name' ];
		
		if (empty($name))
		{
			$names      = explode('@',$email);
			$name       = $names[0];
			$first_name  = $names[0];
			$last_name  = '';
		}
			else
		{
			$names      = explode(' ',$name);
			$first_name  = array_shift($names);
			$last_name  = implode(' ',$names);
		}
		
		return (object) array(
			'provider_identity' => $provider_identity,
			'email'             => $email,
			'first_name'         => $first_name,
			'last_name'         => $last_name,
			'profile_url'        => '',
			'name'              => $name,
			'user_login'        => strtolower( $first_name.$last_name )
		);
	}
	
}

SC_Gateway_Wordpress::init();