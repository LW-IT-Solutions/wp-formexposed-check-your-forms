<?php
/**
 * formexposed for MainWP - uninstall: the API key, the cached list and the message go,
 * on every site of a multisite network.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/** Everything of the extension on the current site. */
function formexposed_mainwp_uninstall_site() {
	delete_option( 'formexposed_mainwp_key' );
	delete_transient( 'formexposed_mainwp_domains' );
	delete_transient( 'formexposed_mainwp_msg' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $formexposed_mainwp_site ) {
		switch_to_blog( (int) $formexposed_mainwp_site );
		formexposed_mainwp_uninstall_site();
		restore_current_blog();
	}
} else {
	formexposed_mainwp_uninstall_site();
}
