<?php
/**
 * CiviCRM ESP Service Controller.
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * API Controller for Newspack CiviCRM ESP service.
 */
class Newspack_Newsletters_CiviCRM_Controller extends Newspack_Newsletters_Service_Provider_Controller {
	/**
	 * Newspack_Newsletters_CiviCRM_Controller constructor.
	 *
	 * @param \Newspack_Newsletters_CiviCRM $constant_contact The service provider class.
	 */
	public function __construct( $constant_contact ) {
		$this->service_provider = $constant_contact;
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		parent::__construct( $constant_contact );
	}

	/**
	 * Register API endpoints unique to CiviCRM.
	 */
	public function register_routes() {

		// Register common ESP routes from \Newspack_Newsletters_Service_Provider_Controller::register_routes.
		parent::register_routes();

		//Retrieve mailing data
		\register_rest_route(
			$this->service_provider::BASE_NAMESPACE . $this->service_provider->service,
			'(?P<id>[\a-z]+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_retrieve' ],
				'permission_callback' => [ $this->service_provider, 'api_authoring_permissions_check' ],
				'args'                => [
					'id' => [
						'sanitize_callback' => 'absint',
						'validate_callback' => [ 'Newspack_Newsletters', 'validate_newsletter_id' ],
					],
				],
			]
		);

		//Send a test email
		\register_rest_route(
			$this->service_provider::BASE_NAMESPACE . $this->service_provider->service,
			'(?P<id>[\a-z]+)/test',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_test' ],
				'permission_callback' => [ $this->service_provider, 'api_authoring_permissions_check' ],
				'args'                => [
					'id'         => [
						'sanitize_callback' => 'absint',
						'validate_callback' => [ 'Newspack_Newsletters', 'validate_newsletter_id' ],
					],
					'test_email' => [
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		//Set the sender details
		\register_rest_route(
			$this->service_provider::BASE_NAMESPACE . $this->service_provider->service,
			'(?P<id>[\a-z]+)/sender',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'api_sender' ],
				'permission_callback' => [ $this->service_provider, 'api_authoring_permissions_check' ],
				'args'                => [
					'id'        => [
						'sanitize_callback' => 'absint',
						'validate_callback' => [ 'Newspack_Newsletters', 'validate_newsletter_id' ],
					],
					'from_name' => [
						'sanitize_callback' => 'sanitize_text_field',
					],
					'reply_to'  => [
						'sanitize_callback' => 'sanitize_email',
					],
				],
			]
		);

		//Set or remove an include group
		\register_rest_route(
			$this->service_provider::BASE_NAMESPACE . $this->service_provider->service,
			'(?P<id>[\a-z]+)/group-include/(?P<group_id>[\a-z]+)',
			[
				'methods'             => \WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'api_group_include' ],
				'permission_callback' => [ $this->service_provider, 'api_authoring_permissions_check' ],
				'args'                => [
					'id'      => [
						'sanitize_callback' => 'absint',
						'validate_callback' => [ 'Newspack_Newsletters', 'validate_newsletter_id' ],
					],
					'list_id' => [
						'sanitize_callback' => 'esc_attr',
					],
				],
			]
		);

		//Set or remove an exclude group
		\register_rest_route(
			$this->service_provider::BASE_NAMESPACE . $this->service_provider->service,
			'(?P<id>[\a-z]+)/group-exclude/(?P<group_id>[\a-z]+)',
			[
				'methods'             => \WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'api_group_exclude' ],
				'permission_callback' => [ $this->service_provider, 'api_authoring_permissions_check' ],
				'args'                => [
					'id'      => [
						'sanitize_callback' => 'absint',
						'validate_callback' => [ 'Newspack_Newsletters', 'validate_newsletter_id' ],
					],
					'list_id' => [
						'sanitize_callback' => 'esc_attr',
					],
				],
			]
		);
	}

	/**
	 * Get campaign data.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return WP_REST_Response|mixed API response or error.
	 */
	public function api_retrieve( $request ) {
		$response = $this->service_provider->retrieve( $request['id'] );
		return \rest_ensure_response( $response );
	}

	/**
	 * Test campaign.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return WP_REST_Response|mixed API response or error.
	 */
	public function api_test( $request ) {
		$emails = explode( ',', $request['test_email'] );
		foreach ( $emails as &$email ) {
			$email = sanitize_email( trim( $email ) );
		}
		$response = $this->service_provider->test(
			$request['id'],
			$emails
		);
		return \rest_ensure_response( $response );
	}

	/**
	 * Set the sender name and email for the campaign.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return WP_REST_Response|mixed API response or error.
	 */
	public function api_sender( $request ) {
		$response = $this->service_provider->sender(
			$request['id'],
			$request['from_name'],
			$request['reply_to']
		);
		return \rest_ensure_response( $response );
	}

	/**
	 * Set include groups for a mailing
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return WP_REST_Response|mixed API response or error.
	 */
	public function api_group_include( $request ) {
		if ( 'DELETE' === $request->get_method() ) {
			$response = $this->service_provider->unset_group(
				$request['id'],
				$request['group_id'],
				'Include'
			);
		} else {
			$response = $this->service_provider->group(
				$request['id'],
				$request['group_id'],
				'Include'
			);
		}
		return \rest_ensure_response( $response );
	}

	/**
	 * Set exclude groups for a mailing
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return WP_REST_Response|mixed API response or error.
	 */
	public function api_group_exclude( $request ) {
		if ( 'DELETE' === $request->get_method() ) {
			$response = $this->service_provider->unset_group(
				$request['id'],
				$request['group_id'],
				'Exclude'
			);
		} else {
			$response = $this->service_provider->group(
				$request['id'],
				$request['group_id'],
				'Exclude'
			);
		}
		return \rest_ensure_response( $response );
	}
}
