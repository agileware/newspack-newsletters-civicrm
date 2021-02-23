<?php
/**
 * Service Provider: CiviCRM Implementation
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

include 'class-newspack-newsletters-civicrm-mailing.php';

/**
 * Main Newspack Newsletters Class for CiviCRM ESP.
 */
final class Newspack_Newsletters_CiviCRM extends \Newspack_Newsletters_Service_Provider {

	/**
	 * Class constructor.
	 */
	public function __construct() {
//		if ( !civicrm_initialize() ) {
//			return new WP_Error(
//				'newspack_newsletters_civi_unavailable',
//				__( 'CiviCRM is not available.', 'newspack-newsletters' )
//			);
//		}
		$this->service    = 'civicrm';
		$this->controller = new Newspack_Newsletters_CiviCRM_Controller( $this );

		add_action( 'save_post_' . Newspack_Newsletters::NEWSPACK_NEWSLETTERS_CPT, [ $this, 'save' ], 10, 3 );
		add_action( 'transition_post_status', [ $this, 'send' ], 10, 3 );
		add_action( 'wp_trash_post', [ $this, 'trash' ], 10, 1 );

		parent::__construct( $this );
	}

	/**
	 * Get API credentials for service provider.
	 *
	 * @return Object Stored API credentials for the service provider.
	 */
	public function api_credentials() {
		return [
			'api_key'      => get_option( 'newspack_newsletters_civicrm_api_key', '5' ),
			'access_token' => get_option( 'newspack_newsletters_civicrm_api_access_token', '5' ),
		];
	}

	/**
	 * Check if provider has all necessary credentials set.
	 *
	 * @return Boolean Result.
	 */
	public function has_api_credentials() {
	  return true;
		return ! empty( $this->api_key() ) && ! empty( $this->access_token() );
	}

	/**
	 * Get API key for service provider.
	 *
	 * @return String Stored API key for the service provider.
	 */
	public function api_key() {
		$credentials = self::api_credentials();
		return $credentials['api_key'];
	}

	/**
	 * Get Access Token key for service provider.
	 *
	 * @return String Stored Access Token key for the service provider.
	 */
	public function access_token() {
		$credentials = self::api_credentials();
		return $credentials['access_token'];
	}

	/**
	 * Set the API credentials for the service provider.
	 *
	 * @param object $credentials API credentials.
	 */
	public function set_api_credentials( $credentials ) {
	  return true;
		if ( empty( $credentials['api_key'] ) || empty( $credentials['access_token'] ) ) {
			return new WP_Error(
				'newspack_newsletters_invalid_keys',
				__( 'Please input CiviCRM API key and access token.', 'newspack-newsletters' )
			);
		} else {
			$update_api_key      = update_option( 'newspack_newsletters_civicrm_api_key', $credentials['api_key'] );
			$update_access_token = update_option( 'newspack_newsletters_civicrm_api_access_token', $credentials['access_token'] );
			return $update_api_key && $update_access_token;
		}
	}

	public function get_mailing($post_id, $message) {
    $data = $this->retrieve($post_id);
    $data['message'] = $message;
    return \rest_ensure_response( $data );
  }

	/**
	 * Add group for a mailing, whether include or exclude
	 *
	 * @param string $post_id Campaign Id.
	 * @param string $group_id ID of the group.
	 * @return object|WP_Error API API Response or error.
	 */
	public function group( $post_id, $group_id, $type = null) {
		$civi_mailing_id = $this->retrieve_mailing_id( $post_id );
		$mailing = new Newspack_Newsletters_CiviCRM_Mailing($civi_mailing_id);
		$group = $mailing->addMailingGroup($group_id, $type);


    if ($group) {
      return $this->get_mailing($post_id, __( "Successfully added $type Group '$group'.", 'newspack-newsletters' ));
    } else {
      return new WP_Error(
        'newspack_newsletters_civicrm_error',
        "Could not create group $group_id",
      );
    }
	}

	/**
	 * Unset group for a mailing, whether include or exclude.
	 *
	 * @param string $post_id Campaign Id.
	 * @param string $group_id ID of the group.
	 * @return object|WP_Error API API Response or error.
	 */
	public function unset_group( $post_id, $group_id, $type) {

		$civi_mailing_id = $this->retrieve_mailing_id( $post_id );
		$mailing = new Newspack_Newsletters_CiviCRM_Mailing($civi_mailing_id);
		$group = $mailing->removeMailingGroup($group_id, $type);

		if ($group) {
      return $this->get_mailing($post_id, __( "Successfully removed $type Group '$group'.", 'newspack-newsletters' ));
    } else {
      return new WP_Error(
        'newspack_newsletters_civicrm_error',
        "Could not remove group $group_id",
      );
    }

	}

	/**
	 * Retrieve a campaign.
	 * A campaign is this context is the mailing and any extra information Newspack needs - like the mailing groups
	 *
	 * @param integer $post_id Numeric ID of the Newsletter post.
	 * @return object|WP_Error API Response or error.
	 */
	public function retrieve( $post_id ) {
		if ( ! $this->has_api_credentials() ) {
			return [];
		}
		$transient       = sprintf( 'newspack_newsletters_error_%s_%s', $post_id, get_current_user_id() );
		$persisted_error = get_transient( $transient );
		if ( $persisted_error ) {
			delete_transient( $transient );
			return new WP_Error(
				'newspack_newsletters_civicrm_error',
				$persisted_error
			);
		}

		$civi_mailing_id = $this->retrieve_mailing_id( $post_id );
		$mailing = new Newspack_Newsletters_CiviCRM_Mailing($civi_mailing_id);

		//get all groups
		$groups = \Civi\Api4\Group::get(FALSE)
		                          ->addWhere('is_active', '=', TRUE)
		                          ->execute();

		return [
			'groups'      => $groups,
			'include_groups' => $mailing->include_groups,
			'exclude_groups' => $mailing->exclude_groups,
			'mailing'     => $mailing->mailing_array,
			'campaign'    => $mailing->mailing_array, //duplicate added at last minute as the send button looks for this data
			'campaign_id' => $civi_mailing_id,
			'recipient_count' => $mailing->recipientCount,
      'scheduled_date' => $mailing->mailing_array['scheduled_date'],
			//'message' => 'Debug: Successfully retrieved mailing details'
		];

	}

	/**
	 * Set sender data.
	 *
	 * @param string $post_id Numeric ID of the campaign.
	 * @param string $from_name Sender name.
	 * @param string $reply_to Reply to email address.
	 * @return object|WP_Error API Response or error.
	 */
	public function sender( $post_id, $from_name, $reply_to ) {
		$civi_mailing_id = $this->retrieve_mailing_id( $post_id );
		$mailing = new Newspack_Newsletters_CiviCRM_Mailing($civi_mailing_id);
		$mailing->saveSetting('from_email', $reply_to);
		$mailing->saveSetting('from_name', $from_name);

    return $this->get_mailing($post_id, __( "Successfully updated sender details.", 'newspack-newsletters' ));

  }

	/**
	 * Send test email or emails.
	 *
	 * @param integer $post_id Numeric ID of the Newsletter post.
	 * @param array   $emails Array of email addresses to send to.
	 * @return object|WP_Error API Response or error.
	 */
	public function test( $post_id, $emails ) {
		try {
			$civi_mailing_id = $this->retrieve_mailing_id( $post_id );
			$mailing = new Newspack_Newsletters_CiviCRM_Mailing($civi_mailing_id);
			$count = $mailing->sendTest($emails);

			if (!$count) {
				throw new Exception('No emails sent');
			}

			$message = sprintf(
			// translators: Message after successful test email.
				__( "CiviCRM test sent successfully to $count emails: %s.", 'newspack-newsletters' ),
				implode( ', ', $emails )
			);

      return $this->get_mailing($post_id, __( $message, 'newspack-newsletters' ));

		} catch ( Exception $e ) {
			return new WP_Error(
				'newspack_newsletters_civicrm_error',
				$e->getMessage()
			);
		}

	}

	/**
	 * Synchronize post with Civi Mailing.
	 *
	 * @param WP_POST $post Post to synchronize.
	 * @return object|null API Response or error.
	 * @throws Exception Error message.
	 */
	public function sync( $post ) {
		if (!civicrm_initialize()) {
			return new WP_Error(
				'newspack_newsletters_missing_api_key',
				__( 'No CiviCRM API key available.', 'newspack-newsletters' )
			);
		}

		try {
			$civi_mailing_id = get_post_meta( $post->ID, 'civi_mailing_id', true );
			$renderer       = new Newspack_Newsletters_Renderer();
			$content        = $renderer->render_html_email( $post );
			if ( $civi_mailing_id ) {

				$mailing = new Newspack_Newsletters_CiviCRM_Mailing($civi_mailing_id);
				$mailing->saveSetting('subject', $post->post_title);
				$mailing->saveSetting('body_html', $content);

				return [
					'campaign_result' => $mailing->mailing_array,
				];

			} else {

				$mailing                     = [];
				$mailing['name']             = __( 'Newspack Newsletters', 'newspack-newsletters' ) . ' ' . uniqid();
				$mailing['subject']          = $post->post_title;
				$mailing['from_name']        = 'Test';
				$mailing['from_email']       = 'test@test.com';
				$mailing['body_html']        = $content;
				//$mailing['template_type']    = 'newspack';

				$mailing = civicrm_api3('Mailing', 'create', $mailing);

				update_post_meta( $post->ID, 'civi_mailing_id', $mailing['id'] );

				return [
					'campaign_result' => $mailing,
				];
			}

		} catch ( Exception $e ) {
			$transient = sprintf( 'newspack_newsletters_error_%s_%s', $post->ID, get_current_user_id() );
			set_transient( $transient, $e->getMessage(), 45 );
			return;
		}

	}

	/**
	 * Update Mailing after post save.
	 *
	 * @param string  $post_id Numeric ID of the Newspack campaign.
	 * @param WP_Post $post The complete post object.
	 * @param boolean $update Whether this is an existing post being updated or not.
	 */
	public function save( $post_id, $post, $update ) {
		$status = get_post_status( $post_id );
		if ( 'trash' === $status ) {
			return;
		}
		$this->sync( $post );
	}

	public function list($post_id, $list_id) {
		return $this->group($post_id, $list_id);
	}

	/**
	 * Send a campaign.
	 *
	 * @param string  $new_status New status of the post.
	 * @param string  $old_status Old status of the post.
	 * @param WP_POST $post Post to send.
	 */
	public function send( $new_status, $old_status, $post ) {
		$post_id = $post->ID;

		// Only run if the current service provider is CiviCRM.
		if ( 'civicrm' !== get_option( 'newspack_newsletters_service_provider', false ) ) {
			return;
		}

    if ( 'publish' === $new_status && 'publish' !== $old_status ) {
      $civi_mailing_id = $this->retrieve_mailing_id($post_id);
      $mailing         = new Newspack_Newsletters_CiviCRM_Mailing($civi_mailing_id);
      return $mailing->submit();
    }

		return true;
	}

	/**
	 * After Newsletter post is deleted, clean up by deleting corresponding Civi mailing.
	 *
	 * @param string $post_id Numeric ID of the campaign.
	 */
	public function trash( $post_id ) {
		if ( Newspack_Newsletters::NEWSPACK_NEWSLETTERS_CPT !== get_post_type( $post_id ) ) {
			return;
		}
		$civi_mailing_id = get_post_meta( $post_id, 'civi_mailing_id', true );
		if ( ! $civi_mailing_id ) {
			return;
		}

		$api_key = $this->api_key();
		if ( ! $api_key ) {
			return;
		}
		try {
			$cc       = new ConstantContact( $this->api_key() );
			$campaign = $cc->emailMarketingService->getCampaign( $this->access_token(), $civi_mailing_id ); //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( $campaign && 'DRAFT' === $campaign->status ) {
				$result = $cc->emailMarketingService->deleteCampaign( $this->access_token(), $civi_mailing_id ); //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				delete_post_meta( $post_id, 'civi_mailing_id', $civi_mailing_id );
			}
		} catch ( Exception $e ) {
			return; // Fail silently.
		}
	}

	/**
	 * Convenience method to retrieve the CiviCRM Mailing ID for a post or throw an error.
	 *
	 * @param string $post_id Numeric ID of the campaign.
	 * @return string CiviCRM Mailing ID.
	 * @throws Exception Error message.
	 */
	public function retrieve_mailing_id( $post_id ) {
		$civi_mailing_id = get_post_meta( $post_id, 'civi_mailing_id', true );
		if ( ! $civi_mailing_id ) {
			throw new Exception( __( 'CiviCRM Mailing ID not found.', 'newspack-newsletters' ) );
		}
		return $civi_mailing_id;
	}

}
