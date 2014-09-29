<?php
/**
 * Relocate WordPress Page.
 */
define( 'WP_INSTALLING', true );

/**
 * Load WordPress Bootstrap and WP_Relocate class
 */
// the following line assumes a lot about where this plugin is
require (dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php');
require 'class-wp-relocate.php';

nocache_headers();

$step = isset( $_GET['step'] ) ? (int) $_GET['step'] : 0;

/**
 * Display relocate header.
 *
 * Based on WordPress installer (wp-admin/install.php)
 */
function display_header () {
	header( 'Content-Type: text/html; charset=utf-8' );
	?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml"
	<?php language_attributes(); ?>>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php _e( 'WordPress &rsaquo; Relocate' ); ?></title>
	<?php
	wp_admin_css( 'install', true );
	?>
</head>
<body class="wp-core-ui">
	<h1 id="logo">
		<a href="<?php echo esc_url( __( 'http://wordpress.org/' ) ); ?>"><?php _e( 'WordPress' ); ?></a>
	</h1>

<?php
} // end display_header()
function display_relocate_form ( $error = null ) {
	$old_site = get_option( 'siteurl' );
	if ( defined( 'RELOCATE' ) && RELOCATE ) {
		$old_site = isset( $_POST['old_site'] ) ? untrailingslashit( 
				trim( stripslashes( $_POST['old_site'] ) ) ) : $old_site;
		$allow_changing_old_site = true;
	} else {
		$allow_changing_old_site = false;
	}
	$new_site = isset( $_POST['new_site'] ) ? untrailingslashit( 
			trim( stripslashes( $_POST['new_site'] ) ) ) : '';
	$do_replace_options = isset( $_POST['do_replace'] ) ? (int) $_POST['do_replace']['options'] : 1;
	$do_replace_attachments = isset( $_POST['do_replace'] ) ? (int) $_POST['do_replace']['attachments'] : 1;
	$do_replace_content = isset( $_POST['do_replace'] ) ? (int) $_POST['do_replace']['content'] : 1;
	
	if ( ! is_null( $error ) ) {
		?>
	<p class="message"><?php printf( __( '<strong>ERROR</strong>: %s' ), $error ); ?></p>
	<?php } ?>
<form id="relocate" method="post" action="interface.php?step=2">
		<table class="form-table">
			<tr>
				<th scope="row"><label for="old_site">Old Site URL</label></th>
				<td><input name="old_site"
					<?php echo disabled($allow_changing_old_site, false); ?>
					type="text" id="old_site" size="40"
					value="<?php echo esc_attr( $old_site ); ?>" />
					<p>Automatically detected from the current site URL option.</p></td>
			</tr>
			<tr>
				<th scope="row"><label for="new_site">New Site URL</label></th>
				<td><input name="new_site" type="text" id="new_site" size="40"
					value="<?php echo esc_attr( $new_site ); ?>" />
					<p>Must be a valid URL without a trailing slash.</p></td>
			</tr>
			<tr>
				<th scope="row"><label for="do_replace">Replace</label></th>
				<td colspan="2">
					<div>
						<label> <input type="checkbox" name="do_replace[options]"
							value="1" <?php checked( $do_replace_options ); ?> /> Run
							replacement on all options.
						</label>
					</div>
					<div>
						<label> <input type="checkbox" name="do_replace[attachments]"
							value="1" <?php checked( $do_replace_attachments ); ?> /> Run
							replacement on attachment URLs.
						</label>
					</div>
					<div>
						<label> <input type="checkbox" name="do_replace[content]"
							value="1" <?php checked( $do_replace_content ); ?> /> Run
							replacement on URLs in post content.
						</label>
					</div>
					<p><span title="Not doing so can break your site, and should only be done to fix previous relocation problems">
						It is strongly recommended that you run replacement on all
						groups at once.</span></p>
				</td>
			</tr>
		</table>
		<p class="message">You should first update the DNS records and server
			configuration for your new domain and path before running this
			utility. See documentation.</p>
		<p class="step">
			<input type="submit" name="Submit" value="Relocate WordPress"
				class="button button-large" />
		</p>
	</form>
<?php
} // end display_relocate_form()

if ( !defined( 'RELOCATE' ) || !RELOCATE ) {
	/*
	 * Allow an administrator to bypass login restrictions and use this tool 
	 * regardless of access permissions by setting the RELOCATE constant. 
	 * In theory, WordPress's core functionality should recognize the RELOCATE 
	 * constant and swap the site URL to whatever the admin uses to access the 
	 * login page -- but we'll still bypass it here to make it easier to use.
	 */
	if ( ! is_user_logged_in() ) {
		display_header();
		die( 
				'<h1>Not Allowed</h1><p>You must be logged in to use the Relocate tool.</p>' .
						 '<p class="step"><a class="button button-large" href="' .
						 get_option( 'home' ) . '/">' . _( 'Continue' ) . '</a></p>' .
						 '</body></html>' );
	}
	if ( ! user_can( wp_get_current_user(), 'manage_options' ) ) {
		display_header();
		die( 
				'<h1>Not Allowed</h1><p>Your account does not have permission to use the Relocate tool.</p> ' .
						 '<p class="step"><a class="button button-large" href="' .
						 get_option( 'home' ) . '/">' . _( 'Continue' ) . '</a></p>' .
						 ' </body></html>' );
	}
}

switch ( $step ) {
	case 0:
	case 1:
		display_header();
		?>
<h1><?php _ex( 'Welcome', 'Howdy' ); ?></h1>
	<p>Welcome to the relocate process for changing the site URL of your
		WordPress installation! Just fill in the information below.</p>

	<h1><?php _e( 'Information needed' ); ?></h1>
	<p>Please provide the following information.</p>

<?php
		display_relocate_form();
		break;
	case 2:
		display_header();
		// fill in what we've gathered
		$old_site = get_option( 'siteurl' );
		if ( defined( 'RELOCATE' ) && RELOCATE ) {
			$old_site = isset( $_POST['old_site'] ) ? untrailingslashit( 
					trim( stripslashes( $_POST['old_site'] ) ) ) : $old_site;
		}
		$new_site = isset( $_POST['new_site'] ) ? untrailingslashit( 
				trim( stripslashes( $_POST['new_site'] ) ) ) : '';
		$do_replace_options = isset( $_POST['do_replace'] ) ? (int) $_POST['do_replace']['options'] : 1;
		$do_replace_attachments = isset( $_POST['do_replace'] ) ? (int) $_POST['do_replace']['attachments'] : 1;
		$do_replace_content = isset( $_POST['do_replace'] ) ? (int) $_POST['do_replace']['content'] : 1;
		
		$error = false;
		if ( empty( $new_site ) ) {
			display_relocate_form( 'You must provide a new site URL.' );
			$error = true;
		} elseif ( ! WP_Relocate::is_valid_siteurl( $old_site ) ) {
			// be extra careful with validating URLs
			display_relocate_form( 
					'The old site URL provided cannot be used in the replacement process.' );
			$error = true;
		} elseif ( ! WP_Relocate::is_valid_siteurl( $new_site ) ) {
			// be extra careful with validating URLs
			display_relocate_form( 
					'The new site URL provided cannot be used as a site URL.' );
			$error = true;
		}
		if ( ! $error ) {
			$Relocate = new WP_Relocate( $old_site, $new_site );
			$replace_result_options = $replace_result_attachments = $replace_result_content = array();
			if ( $do_replace_options )
				$replace_result_options = $Relocate->replace_options();
			if ( $do_replace_attachments )
				$replace_result_attachments = $Relocate->replace_attachments();
			if ( $do_replace_content )
				$replace_result_content = $Relocate->replace_post_content();
			?>
<h1><?php _e( 'Success!' ); ?></h1>

	<p>URLs have been replaced.</p>

	<table class="form-table install-success">
		<tr>
			<th>Options Processed</th>
			<td><?php echo count($replace_result_options); ?></td>
		</tr>
		<tr>
			<th>Attachments Processed</th>
			<td><?php echo count($replace_result_attachments); ?></td>
		</tr>
		<tr>
			<th>Post Bodies Processed</th>
			<td><?php echo count($replace_result_content); ?></td>
		</tr>
	</table>

	<p class="step">
		<a href="<?php echo $new_site; ?>/wp-login.php"
			class="button button-large"><?php _e( 'Log In' ); ?></a>
	</p>
<?php
		} // end if
		break;
} // end switch?>
<?php wp_print_scripts( 'user-profile' ); ?>
</body>
</html>