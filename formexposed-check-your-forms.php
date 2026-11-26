<?php
/**
 * Plugin Name:       formexposed – check your forms
 * Plugin URI:        https://formexposed.com/
 * Description:       Shows the contact form protection of all your MainWP child sites from formexposed.com: rating, risk score, last check, problems and blocked bots, with the report one click away.
 * Version:           1.1.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            LW IT Solutions
 * Author URI:        https://www.lukaswojcik.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       formexposed-check-your-forms
 *
 * For agencies with a formexposed Business plan. Reads GET /api/v1/domains with the agency's API key
 * and matches the result with the child sites of MainWP by host name. Without MainWP it works as a
 * plain overview under Settings -> formexposed portfolio. The API key stays in this dashboard (option,
 * not autoloaded) and goes only to the formexposed API (https://formexposed.com, or the address a
 * developer sets with FORMEXPOSED_MAINWP_API in wp-config.php); the child site URLs never leave this site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Another copy of this plugin is already loaded: stop here.
if ( defined( 'FORMEXPOSED_MAINWP_FILE' ) ) {
	return;
}

define( 'FORMEXPOSED_MAINWP_FILE', __FILE__ );
define( 'FORMEXPOSED_MAINWP_VERSION', '1.1.1' );

class Formexposed_MainWP {

	const OPT_KEY   = 'formexposed_mainwp_key';
	const TRANSIENT = 'formexposed_mainwp_domains';
	const MSG       = 'formexposed_mainwp_msg';
	const API       = 'https://formexposed.com';

	private $child_key = false;

	public function __construct() {
		add_filter( 'mainwp_getextensions', array( $this, 'extension' ) );
		if ( false !== apply_filters( 'mainwp_activated_check', false ) ) {
			$this->mainwp_on();
		} else {
			add_action( 'mainwp_activated', array( $this, 'mainwp_on' ) );
		}
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_formexposed_mainwp_key', array( $this, 'save_key' ) );
		add_action( 'admin_post_formexposed_mainwp_refresh', array( $this, 'refresh' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( FORMEXPOSED_MAINWP_FILE ), array( $this, 'action_links' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'styles' ) );
	}

	/**
	 * Address of the formexposed API: https://formexposed.com. A developer can point the extension at
	 * another formexposed installation by defining FORMEXPOSED_MAINWP_API in wp-config.php as an HTTPS
	 * address (scheme and host, no path); any other value is ignored.
	 */
	public static function api() {
		if ( defined( 'FORMEXPOSED_MAINWP_API' ) && is_string( FORMEXPOSED_MAINWP_API ) ) {
			$p = wp_parse_url( trim( FORMEXPOSED_MAINWP_API ) );
			if ( is_array( $p ) && isset( $p['scheme'], $p['host'] ) && 'https' === strtolower( $p['scheme'] )
				&& ! isset( $p['user'] ) && ! isset( $p['pass'] ) && ! isset( $p['query'] ) && ! isset( $p['fragment'] )
				&& ( ! isset( $p['path'] ) || '' === trim( $p['path'], '/' ) ) ) {
				return 'https://' . strtolower( $p['host'] ) . ( isset( $p['port'] ) ? ':' . (int) $p['port'] : '' );
			}
		}
		return self::API;
	}

	/** Host name of the API, for the texts on the page. */
	private static function api_host() {
		return (string) wp_parse_url( self::api(), PHP_URL_HOST );
	}

	/** MainWP lists us as an extension. */
	public function extension( $extensions ) {
		$extensions[] = array(
			'plugin'   => FORMEXPOSED_MAINWP_FILE,
			'api'      => 'formexposed-mainwp',
			'mainwp'   => false,
			'callback' => array( $this, 'page' ),
			'name'     => 'formexposed',
		);
		return $extensions;
	}

	public function mainwp_on() {
		$e               = apply_filters( 'mainwp_extension_enabled_check', FORMEXPOSED_MAINWP_FILE );
		$this->child_key = is_array( $e ) && isset( $e['key'] ) ? $e['key'] : false;
	}

	private static function mainwp_active() {
		return false !== apply_filters( 'mainwp_activated_check', false );
	}

	/** Without MainWP: a page under Settings. */
	public function menu() {
		if ( ! self::mainwp_active() ) {
			add_options_page( __( 'formexposed portfolio', 'formexposed-check-your-forms' ), __( 'formexposed portfolio', 'formexposed-check-your-forms' ), 'manage_options', 'formexposed-mainwp', array( $this, 'page' ) );
		}
	}

	private function page_url() {
		if ( ! self::mainwp_active() ) {
			return admin_url( 'options-general.php?page=formexposed-mainwp' );
		}
		return admin_url( 'admin.php?page=' . self::page_slug() );
	}

	/** The page slug: formexposed-mainwp under Settings, else the MainWP extension page. */
	private static function page_slug() {
		if ( ! self::mainwp_active() ) {
			return 'formexposed-mainwp';
		}
		// MainWP derives the extension page from the plugin folder name, e.g. formexposed-check-your-forms -> Extensions-Formexposed-Check-Your-Forms.
		$folder = dirname( plugin_basename( FORMEXPOSED_MAINWP_FILE ) );
		return 'Extensions-' . str_replace( ' ', '-', ucwords( str_replace( '-', ' ', $folder ) ) );
	}

	/**
	 * Colours of the summary and the ratings, only on the page of this extension.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function styles( $hook_suffix ) {
		$end = '_page_' . strtolower( self::page_slug() );
		if ( strtolower( substr( (string) $hook_suffix, -strlen( $end ) ) ) !== $end ) {
			return;
		}
		$css = '.formexposed-mainwp-summary{float:none;margin:8px 0 12px}'
			. '.formexposed-mainwp-scroll{overflow-x:auto}';
		foreach ( self::levels() as $level => $lv ) {
			$css .= '.formexposed-mainwp-level-' . sanitize_html_class( $level ) . '{color:' . $lv[0] . '}';
		}
		$css .= '.formexposed-mainwp-level-none{color:#50575e}';
		wp_register_style( 'formexposed-mainwp', false, array(), FORMEXPOSED_MAINWP_VERSION );
		wp_enqueue_style( 'formexposed-mainwp' );
		wp_add_inline_style( 'formexposed-mainwp', $css );
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( $this->page_url() ) . '">' . esc_html__( 'Settings', 'formexposed-check-your-forms' ) . '</a>' );
		return $links;
	}

	private static function message( $kind, $text ) {
		set_transient( self::MSG, array( $kind, $text ), 120 );
	}

	public function save_key() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'formexposed-check-your-forms' ) );
		}
		check_admin_referer( 'formexposed_mainwp_key' );
		$key = isset( $_POST['fxk'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['fxk'] ) ) ) : '';
		delete_transient( self::TRANSIENT );
		if ( ! empty( $_POST['remove'] ) ) {
			delete_option( self::OPT_KEY );
			self::message( 'success', __( 'The API key was removed.', 'formexposed-check-your-forms' ) );
		} elseif ( '' === $key ) {
			self::message( 'warning', __( 'Enter an API key first.', 'formexposed-check-your-forms' ) );
		} elseif ( ! preg_match( '/^fxk_[a-f0-9]{40}$/', $key ) ) {
			self::message( 'error', __( 'This is not a formexposed API key (fxk_ followed by 40 characters).', 'formexposed-check-your-forms' ) );
		} else {
			$test = $this->fetch( $key );
			if ( is_wp_error( $test ) ) {
				self::message( 'error', __( 'The key was not saved:', 'formexposed-check-your-forms' ) . ' ' . $test->get_error_message() );
			} else {
				update_option( self::OPT_KEY, $key, false );
				set_transient( self::TRANSIENT, $test, 10 * MINUTE_IN_SECONDS );
				/* translators: %d: number of domains */
				self::message( 'success', sprintf( _n( 'Connected. formexposed.com knows %d site of your account.', 'Connected. formexposed.com knows %d sites of your account.', count( $test ), 'formexposed-check-your-forms' ), count( $test ) ) );
			}
		}
		wp_safe_redirect( $this->page_url() );
		exit;
	}

	public function refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'formexposed-check-your-forms' ) );
		}
		check_admin_referer( 'formexposed_mainwp_refresh' );
		delete_transient( self::TRANSIENT );
		wp_safe_redirect( $this->page_url() );
		exit;
	}

	/** One request to GET /api/v1/domains. Returns the list or WP_Error with a sentence a person understands. */
	private function fetch( $key ) {
		$r = wp_remote_get( self::api() . '/api/v1/domains', array(
			'timeout' => 10,
			'headers' => array(
				'Authorization' => 'Bearer ' . $key,
				'Accept'        => 'application/json',
			),
		) );
		if ( is_wp_error( $r ) ) {
			/* translators: %s: error message */
			return new WP_Error( 'http', sprintf( __( 'formexposed.com could not be reached (%s).', 'formexposed-check-your-forms' ), $r->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		$j    = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		if ( 200 === $code && is_array( $j ) && isset( $j['data'] ) && is_array( $j['data'] ) ) {
			$list = array();
			foreach ( $j['data'] as $d ) {
				if ( is_array( $d ) ) {
					$list[] = self::clean_domain( $d );
				}
			}
			return $list;
		}
		$err = is_array( $j ) && isset( $j['error']['code'] ) && is_string( $j['error']['code'] ) ? sanitize_key( $j['error']['code'] ) : '';
		$err = '' !== $err ? $err : 'http_' . $code;
		switch ( $err ) {
			case 'unauthorized':
				return new WP_Error( 'api', __( 'formexposed.com does not accept this API key. It may have been revoked - create a new one under Agency → API.', 'formexposed-check-your-forms' ) );
			case 'plan':
				return new WP_Error( 'api', __( 'The API is part of the Business plan. The account of this key has no Business plan (any more).', 'formexposed-check-your-forms' ) );
			case 'rate_limited':
				return new WP_Error( 'api', __( 'Too many requests to formexposed.com in the last minute. Please try again shortly.', 'formexposed-check-your-forms' ) );
			default:
				/* translators: %s: error code */
				return new WP_Error( 'api', sprintf( __( 'formexposed.com answered with an error: %s', 'formexposed-check-your-forms' ), $err ) );
		}
	}

	/**
	 * One domain of the API answer, with only the fields this page uses, each sanitized:
	 * hostname, label, level (one of levels() or ''), score, problems, bots_blocked_30d (number or null),
	 * last_check and report_url.
	 */
	private static function clean_domain( array $d ) {
		$number = static function ( $v ) {
			return is_numeric( $v ) ? absint( $v ) : null;
		};
		$level  = isset( $d['level'] ) && is_string( $d['level'] ) ? sanitize_key( $d['level'] ) : '';
		return array(
			'hostname'         => isset( $d['hostname'] ) && is_string( $d['hostname'] ) ? sanitize_text_field( $d['hostname'] ) : '',
			'label'            => isset( $d['label'] ) && is_string( $d['label'] ) ? sanitize_text_field( $d['label'] ) : '',
			'level'            => isset( self::levels()[ $level ] ) ? $level : '',
			'score'            => isset( $d['score'] ) ? $number( $d['score'] ) : null,
			'problems'         => isset( $d['problems'] ) ? $number( $d['problems'] ) : null,
			'bots_blocked_30d' => isset( $d['bots_blocked_30d'] ) ? $number( $d['bots_blocked_30d'] ) : null,
			'last_check'       => isset( $d['last_check'] ) && is_string( $d['last_check'] ) ? sanitize_text_field( $d['last_check'] ) : '',
			'report_url'       => isset( $d['report_url'] ) && is_string( $d['report_url'] ) ? esc_url_raw( $d['report_url'], array( 'https' ) ) : '',
		);
	}

	/** The domains of the account, 10 minutes in a transient. Returns array or WP_Error. */
	public function domains() {
		$key = (string) get_option( self::OPT_KEY );
		if ( '' === $key ) {
			return new WP_Error( 'no_key', __( 'Enter your formexposed API key first.', 'formexposed-check-your-forms' ) );
		}
		$c = get_transient( self::TRANSIENT );
		if ( is_array( $c ) ) {
			return $c;
		}
		$d = $this->fetch( $key );
		if ( ! is_wp_error( $d ) ) {
			set_transient( self::TRANSIENT, $d, 10 * MINUTE_IN_SECONDS );
		}
		return $d;
	}

	/** The child sites of MainWP (id, name, url) - or none without MainWP. */
	public function sites() {
		if ( ! $this->child_key ) {
			return array();
		}
		$s     = apply_filters( 'mainwp_getsites', FORMEXPOSED_MAINWP_FILE, $this->child_key, null );
		$sites = array();
		foreach ( is_array( $s ) ? $s : array() as $site ) {
			if ( is_array( $site ) ) {
				$sites[] = array(
					'name' => isset( $site['name'] ) && is_scalar( $site['name'] ) ? sanitize_text_field( (string) $site['name'] ) : '',
					'url'  => isset( $site['url'] ) && is_string( $site['url'] ) ? esc_url_raw( $site['url'] ) : '',
				);
			}
		}
		return $sites;
	}

	public static function host_of( $url ) {
		$h = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
		return preg_replace( '/^www\./', '', $h );
	}

	/** Rows: every child site, with its formexposed domain if there is one; then the formexposed domains without a child site. */
	public function rows( $domains, $sites ) {
		$by_host = array();
		foreach ( (array) $domains as $d ) {
			if ( isset( $d['hostname'] ) && '' !== (string) $d['hostname'] ) {
				$by_host[ preg_replace( '/^www\./', '', strtolower( (string) $d['hostname'] ) ) ] = $d;
			}
		}
		$rows = array();
		foreach ( (array) $sites as $s ) {
			$h      = self::host_of( isset( $s['url'] ) ? $s['url'] : '' );
			$rows[] = array(
				'name' => isset( $s['name'] ) && '' !== (string) $s['name'] ? (string) $s['name'] : $h,
				'host' => $h,
				'd'    => isset( $by_host[ $h ] ) ? $by_host[ $h ] : null,
			);
			unset( $by_host[ $h ] );
		}
		foreach ( $by_host as $h => $d ) {
			$rows[] = array(
				'name'    => ! empty( $d['label'] ) ? (string) $d['label'] : $h,
				'host'    => $h,
				'd'       => $d,
				'only_fe' => true,
			);
		}
		return $rows;
	}

	/** Level key -> colour and words. */
	public static function levels() {
		return array(
			'exposed'             => array( '#b32d2e', __( 'Exposed', 'formexposed-check-your-forms' ) ),
			'partially_protected' => array( '#996800', __( 'Partially protected', 'formexposed-check-your-forms' ) ),
			'protected'           => array( '#00750f', __( 'Protected', 'formexposed-check-your-forms' ) ),
			'no_form'             => array( '#50575e', __( 'No contact form found', 'formexposed-check-your-forms' ) ),
		);
	}

	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'formexposed-check-your-forms' ) );
		}
		if ( $this->child_key ) {
			do_action( 'mainwp_pageheader_extensions', FORMEXPOSED_MAINWP_FILE );
		}
		$msg = get_transient( self::MSG );
		delete_transient( self::MSG );
		$domains = $this->domains();
		$rows    = is_wp_error( $domains ) ? array() : $this->rows( $domains, $this->sites() );
		$levels  = self::levels();
		$count   = array( 'exposed' => 0, 'partially_protected' => 0, 'protected' => 0, 'missing' => 0 );
		foreach ( $rows as $r ) {
			if ( ! $r['d'] ) {
				++$count['missing'];
			} elseif ( isset( $r['d']['level'], $count[ (string) $r['d']['level'] ] ) ) {
				++$count[ (string) $r['d']['level'] ];
			}
		}
		$has_key = '' !== (string) get_option( self::OPT_KEY );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'formexposed portfolio', 'formexposed-check-your-forms' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Contact form protection of your sites, from formexposed.com. The list is kept for 10 minutes.', 'formexposed-check-your-forms' ); ?></p>
			<?php if ( is_array( $msg ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( in_array( $msg[0], array( 'success', 'warning', 'error' ), true ) ? $msg[0] : 'info' ); ?> is-dismissible"><p><?php echo esc_html( $msg[1] ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! is_wp_error( $domains ) ) : ?>
			<ul class="subsubsub formexposed-mainwp-summary">
				<li><strong class="formexposed-mainwp-level-exposed"><?php echo esc_html( number_format_i18n( $count['exposed'] ) ); ?></strong> <?php esc_html_e( 'exposed', 'formexposed-check-your-forms' ); ?> |</li>
				<li><strong class="formexposed-mainwp-level-partially_protected"><?php echo esc_html( number_format_i18n( $count['partially_protected'] ) ); ?></strong> <?php esc_html_e( 'partially protected', 'formexposed-check-your-forms' ); ?> |</li>
				<li><strong class="formexposed-mainwp-level-protected"><?php echo esc_html( number_format_i18n( $count['protected'] ) ); ?></strong> <?php esc_html_e( 'protected', 'formexposed-check-your-forms' ); ?> |</li>
				<li><strong><?php echo esc_html( number_format_i18n( $count['missing'] ) ); ?></strong> <?php esc_html_e( 'child sites not in formexposed', 'formexposed-check-your-forms' ); ?></li>
			</ul>
			<div class="formexposed-mainwp-scroll">
			<table class="widefat striped">
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'Site', 'formexposed-check-your-forms' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Rating', 'formexposed-check-your-forms' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Last check', 'formexposed-check-your-forms' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Problems', 'formexposed-check-your-forms' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Bots blocked (30 days)', 'formexposed-check-your-forms' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Report', 'formexposed-check-your-forms' ); ?></span></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<?php $d = $r['d']; ?>
					<tr>
						<td><strong><?php echo esc_html( $r['name'] ); ?></strong><br><span class="description"><?php echo esc_html( $r['host'] ); ?><?php echo ! empty( $r['only_fe'] ) ? ' · ' . esc_html__( 'not in MainWP', 'formexposed-check-your-forms' ) : ''; ?></span></td>
						<?php if ( ! $d ) : ?>
							<td colspan="5"><?php esc_html_e( 'Not in formexposed yet. Add it under Agency → Import on formexposed.com, or run on the site: wp formexposed connect fxc_...', 'formexposed-check-your-forms' ); ?></td>
						<?php else : ?>
							<?php $known = isset( $d['level'], $levels[ (string) $d['level'] ] ); ?>
							<?php $lv = $known ? $levels[ (string) $d['level'] ] : array( '#50575e', __( 'Not checked yet', 'formexposed-check-your-forms' ) ); ?>
							<td><strong class="<?php echo esc_attr( 'formexposed-mainwp-level-' . ( $known ? sanitize_html_class( (string) $d['level'] ) : 'none' ) ); ?>"><?php echo esc_html( $lv[1] ); ?></strong><?php echo isset( $d['score'] ) && null !== $d['score'] ? ' · ' . esc_html( (int) $d['score'] . '/100' ) : ''; ?></td>
							<td><?php echo ! empty( $d['last_check'] ) ? esc_html( wp_date( get_option( 'date_format' ), (int) strtotime( (string) $d['last_check'] ) ) ) : '–'; ?></td>
							<td><?php echo isset( $d['problems'] ) && null !== $d['problems'] ? esc_html( (int) $d['problems'] ) : '–'; ?></td>
							<td><?php echo isset( $d['bots_blocked_30d'] ) && null !== $d['bots_blocked_30d'] ? esc_html( number_format_i18n( (int) $d['bots_blocked_30d'] ) ) : '–'; ?></td>
							<td>
							<?php if ( ! empty( $d['report_url'] ) && 0 === strpos( (string) $d['report_url'], self::api() . '/' ) ) : ?>
								<a href="<?php echo esc_url( (string) $d['report_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Report', 'formexposed-check-your-forms' ); ?><span class="screen-reader-text"> <?php echo esc_html( $r['host'] ); ?> <?php esc_html_e( '(opens in a new tab)', 'formexposed-check-your-forms' ); ?></span></a>
							<?php endif; ?>
							</td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No sites yet.', 'formexposed-check-your-forms' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
			</div>
			<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=formexposed_mainwp_refresh' ), 'formexposed_mainwp_refresh' ) ); ?>"><?php esc_html_e( 'Refresh now', 'formexposed-check-your-forms' ); ?></a></p>
			<?php elseif ( $has_key ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html( $domains->get_error_message() ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'API key', 'formexposed-check-your-forms' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="formexposed_mainwp_key">
				<?php wp_nonce_field( 'formexposed_mainwp_key' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="fxm-key"><?php esc_html_e( 'formexposed API key', 'formexposed-check-your-forms' ); ?></label></th>
						<td>
							<input id="fxm-key" type="password" name="fxk" class="regular-text code" autocomplete="off" spellcheck="false" placeholder="<?php echo $has_key ? esc_attr__( 'saved - enter a new one to replace it', 'formexposed-check-your-forms' ) : 'fxk_...'; ?>">
							<p class="description">
								<?php
								echo wp_kses(
									sprintf(
										/* translators: 1: link "Agency → API", 2: host name of the formexposed API, normally formexposed.com */
										esc_html__( 'Create a key on formexposed.com under %1$s (Business plan). The key is checked before it is saved; it is sent only to %2$s.', 'formexposed-check-your-forms' ),
										'<a href="' . esc_url( self::api() . '/agency?tab=api' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Agency → API', 'formexposed-check-your-forms' ) . '</a>',
										esc_html( self::api_host() )
									),
									array(
										'a' => array(
											'href'   => true,
											'target' => true,
											'rel'    => true,
										),
									)
								);
								?>
							</p>
						</td>
					</tr>
				</table>
				<p>
					<button class="button button-primary"><?php echo $has_key ? esc_html__( 'Check and replace the key', 'formexposed-check-your-forms' ) : esc_html__( 'Check and save the key', 'formexposed-check-your-forms' ); ?></button>
					<?php if ( $has_key ) : ?>
						<button class="button" name="remove" value="1"><?php esc_html_e( 'Remove the key', 'formexposed-check-your-forms' ); ?></button>
					<?php endif; ?>
				</p>
			</form>
		</div>
		<?php
		if ( $this->child_key ) {
			do_action( 'mainwp_pagefooter_extensions', FORMEXPOSED_MAINWP_FILE );
		}
	}
}

new Formexposed_MainWP();
