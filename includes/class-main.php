<?php
namespace Mihdan\Likefy;

class Main {
	private $base = 'https://pv.pjtsu.com/v1';
	private $config;
	private $config_name = 'mihdan_likefy_config';
	private $object_id;


	public function __construct() {
		$this->init_hooks();
	}

	public function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );
	}

	public static function load_textdomain() {
		load_plugin_textdomain( 'pageviews', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}

	public function template_redirect() {
		if ( is_singular() ) {
			$this->object_id = get_the_ID();
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'pageviews', array( $this, 'placeholder' ), 10, 1 );
		if ( ! current_theme_supports( 'pageviews' ) ) {
			add_action( 'the_content', array( $this, 'compat_the_content' ) );
			add_action( 'wp_head', array( $this, 'compat_wp_head' ) );
		}
	}

	public function placeholder( $key = null ) {
		if ( empty( $key ) ) {
			$key = $this->object_id;
		}

		echo $this->get_placeholder( $key );
	}

	public function get_placeholder( $key ) {
		return sprintf( '<span class="mihdan-likefy-placeholder" data-key="%s">%s</span>', esc_attr( $key ), apply_filters( 'mihdan_likefy_placeholder_preload', '' ) );
	}

	public function compat_the_content( $content ) {
		$key      = $this->object_id;
		$content .= '<div class="mihdan-likefy-wrapper"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1792 1792"><path d="M588.277,896v692.375H280.555V896H588.277z M1049.86,630.363v958.012h-307.72V630.363H1049.86z M1511.446,203.625v1384.75h-307.725V203.625H1511.446z"/></svg>' . $this->get_placeholder( $key ) . '</div>';
		return $content;
	}
	/**
	 * Compat styles.
	 */
	public static function compat_wp_head() {
		?>
		<style>
			.mihdan-likefy-wrapper { height: 16px; line-height: 16px; font-size: 11px; clear: both; }
			.mihdan-likefy-wrapper svg { width: 16px; height: 16px; fill: #aaa; float: left; margin-right: 2px; }
			.mihdan-likefy-wrapper span { float: left; }
		</style>
		<?php
	}

	/**
	 * Front-end scripts.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'jquery' );
		add_action( 'wp_footer', array( __CLASS__, 'wp_footer' ) );
	}
	/**
	 * Output async script in footer.
	 */
	public function wp_footer() {
		$account = $this->get_account_key();

		if ( empty( $account ) ) {
			return;
		}

		$config = array(
			'account'   => $account,
			'object_id' => $this->object_id,
			'base'      => $this->base,
		);

		$suffix  = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$version = apply_filters( 'mihdan_likefy_script_version_param', '?v=' . MIHDAN_LIKEFY_VERSION );
		?>
		<script>
			var mihdan_likefy_config = <?php echo json_encode( $config ); ?>;
			<?php do_action( 'mihdan_likefy_before_js', $config ); ?>
			(function(){
				var js = document.createElement('script'); js.type = 'text/javascript';
				js.async = true;
				js.src = '<?php echo esc_js( plugins_url( 'app' . $suffix. '.js' . $version, __FILE__ ) ); ?>';
				var s = document.getElementsByTagName('script')[0];
				s.parentNode.insertBefore(js, s);
			})();
		</script>
		<?php
	}

	/**
	 * Update configuration.
	 *
	 * @param array $config New configuration.
	 */
	public function update_config( $config ) {
		update_option( $this->config_name, $config );
		$this->config = $config;
	}

	/**
	 * Return a configuration array.
	 *
	 * @return array
	 */
	public function get_config() {
		if ( isset( $this->config ) ) {
			return $this->config;
		}

		$defaults = array(
			'account' => '',
			'secret'  => '',
		);

		$this->config = wp_parse_args( get_option( $this->config_name, array() ), $defaults );
		return $this->config;
	}

	/**
	 * Get the account key
	 *
	 * @return string The account key.
	 */
	public function get_account_key() {
		$config = $this->get_config();
		if ( ! empty( $config['account'] ) ) {
			return $config['account'];
		}

		// Don't attempt to re-register more frequently than once every 12 hours.
		$can_register = true;

		if ( ! empty( $config['register-error'] ) && time() - $config['register-error'] < 12 * HOUR_IN_SECONDS ) {
			$can_register = false;
		}

		// Obtain a new account key if necessary.
		if ( empty( $config['account'] ) && $can_register ) {
			$config['register-error'] = time();
			$this->update_config( $config );
			$request = wp_remote_post( $this->base . '/register' );
			if ( ! is_wp_error( $request ) && wp_remote_retrieve_response_code( $request ) == 200 ) {
				$response          = json_decode( wp_remote_retrieve_body( $request ) );
				$config['account'] = $response->account;
				$config['secret']  = $response->secret;
				unset( $config['register-error'] );
				$this->update_config( $config );
			}
		}
		return $config['account'];
	}
}