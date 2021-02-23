<?php

/**
 * Class Newspack_Newsletters_CiviCRM_Mailing
 * Just to make using the IDE a bit nicer
 */
class Newspack_Newsletters_CiviCRM_Mailing {

	/**
	 * @var int ID of Civi Mailing
	 */
	public $id;

	/**
	 * @var array ID and names of the include groups
	 */
	public $include_groups = [];

	/**
	 * @var array IDs and names of the exclude groups
	 */
	public $exclude_groups = [];

	/**
	 * @var int ID of the base (unsubscribe) group
	 */
	public $base_group;

	/**
	 * @var array Array from API3
	 */
	public $mailing_array;

	/**
	 * @var int Count of recipients
	 */
	public $recipientCount;


	public function __construct( $id = null ) {
		if ( $id ) {
			$this->id = $id;
			$this->getMailing();
		}
	}

	public function submit() {
	  try {
      $result = civicrm_api3('Mailing', 'submit', [
        'id'                 => $this->id,
        'approval_status_id' => "Approved",
        'scheduled_date'     => date('YmdHis'),
        'approval_date'      => date('YmdHis'),
      ]);
    } catch (CiviCRM_API3_Exception $e) {
      $this->logError($e->getMessage());
	    return false;
    }

    if ($result['is_error']) {
      $this->logError($result);
      return false;
    }

    return true;
	}

	public function sendTest( $to ) {
		$count = 0;
		try {
			foreach ( $to as $recipient ) {
				$result = civicrm_api3( 'Mailing', 'send_test', [
					'mailing_id' => $this->id,
					'test_email' => $recipient,
				] );
				$count += 1;
			}
		} catch ( CiviCRM_API3_Exception $e ) {
			$this->logError($e->getMessage());
			return false;
		}

		return $count;
	}

	public function saveSetting( $settingName, $settingValue ) {
		try {
			$params = [
				'id'         => $this->id,
				$settingName => $settingValue,
			];
			$result = civicrm_api3( 'Mailing', 'create', $params);
		} catch ( CiviCRM_API3_Exception $e ) {
			$this->logError($e->getMessage() . "\r\n" . print_r($params,true));
			return false;
		}

		$this->$settingName = $settingValue;
		$this->mailing_array[$settingName] = $settingValue;
	}

	public function addMailingGroup( $mailingGroupID, $type ) {
		try {
			$result = civicrm_api3( 'MailingGroup', 'create', [
				'mailing_id'   => $this->id,
				'entity_table' => "civicrm_group",
				'entity_id'    => $mailingGroupID,
				'group_type'   => $type,
			] );
			if ($type == 'Include') {
				$this->include_groups[] = $result;
			} elseif ($type == 'Exclude') {
				$this->exclude_groups[] = $result;
			}
		} catch ( CiviCRM_API3_Exception $e ) {
			$this->logError($e->getMessage());
			return false;
		}

		//get group title
    return $this->getGroupTitle($mailingGroupID);
	}

	public function removeMailingGroup( $mailingGroupID, $type ) {
		try {
			$result = civicrm_api3( 'MailingGroup', 'getsingle', [
				'mailing_id'   => $this->id,
				'entity_id'    => $mailingGroupID,
				'group_type'   => $type,
			] );
			$resultRemoval = civicrm_api3( 'MailingGroup', 'delete', [
				'id' => $result['id']
			] );
			if ($type == 'Include') {
				foreach ($this->include_groups as $key => $grp) {
					if ($grp['id'] == $mailingGroupID) {
						unset($this->include_groups[$key]);
						break;
					}
				}
			} elseif ($type == 'Exclude') {
				foreach ($this->exclude_groups as $key => $grp) {
					if ($grp['id'] == $mailingGroupID) {
						unset($this->exclude_groups[$key]);
						break;
					}
				}			}
		} catch ( CiviCRM_API3_Exception $e ) {
			$this->logError($e->getMessage());
			return false;
		}

    //get group title
    return $this->getGroupTitle($mailingGroupID);
	}


	private function getMailing() {
		civicrm_initialize();
		try {
			$this->mailing_array = civicrm_api3( 'Mailing', 'getsingle', [
				'return' => ["id", "name", "from_name", "from_email", "template_type", "subject", "body_text", "body_html", "url_tracking", "forward_replies", "scheduled_date", "location_type_id", "email_selection_method", "open_tracking", "created_id"],
				'id'                   => $this->id,
				//'template_type'    => 'newspack',
			] );
			foreach ( $this->mailing_array as $key => $value ) {
				$this->$key = $value;
			}
			$this->getMailingGroups();
			$this->getRecipientCount();

			return true;
		} catch ( CiviCRM_API3_Exception $e ) {
			$this->logError($e->getMessage());
			return false;
		}
	}

	private function getRecipientCount() {
		CRM_Mailing_BAO_Mailing::getRecipients($this->id);
		$count = civicrm_api3('MailingRecipients', 'getcount', ['mailing_id' => $this->id]);
		$this->recipientCount = $count;
	}

	private function getMailingGroups() {
		$result = civicrm_api3( 'MailingGroup', 'get', [
			'mailing_id' => $this->id,
		] )['values'];
		foreach ( $result as $mailingGroup ) {
			if ( $mailingGroup['group_type'] == 'Include' ) {
				$this->include_groups[] = $mailingGroup;
			} elseif ( $mailingGroup['group_type'] == 'Exclude' ) {
				$this->exclude_groups[] = $mailingGroup;
			} elseif ( $mailingGroup['group_type'] == 'Base' ) {
				$this->base_group = $mailingGroup;
			}
		}
	}

	private function logError($message) {
		$dbt    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 0);
		$source = isset($dbt[1]['function']) ? $dbt[1]['function'] : NULL;
		$source = isset($dbt[1]['class']) ? $dbt[1]['class'] . '::' . $source : $source;
		$source = isset($dbt[1]['line']) ? $source . ' // ' . $dbt[1]['line'] : $source;
		$message = $source . ' // ' . $message;
		Civi::log()->debug($message);
		error_log($message);
		throw new Exception($message);
	}

	private function getGroupTitle($id) {
    $group = \Civi\Api4\Group::get(FALSE)
      ->addSelect('title')
      ->addWhere('id', '=', $id)
      ->execute()
      ->first();
    return $group['title'];
  }

}