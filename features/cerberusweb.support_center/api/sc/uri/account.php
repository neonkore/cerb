<?php
class UmScAccountController extends Extension_UmScController {
	function isVisible() {
		$umsession = UmPortalHelper::getSession();
		$active_contact = $umsession->getProperty('sc_login', null);
		return !empty($active_contact);
	}
	
	function renderSidebar(DevblocksHttpResponse $response) {
		$tpl = DevblocksPlatform::getTemplateService();
		
        $login_handler = DAO_CommunityToolProperty::get(UmPortalHelper::getCode(), UmScApp::PARAM_LOGIN_HANDLER, '');
		$tpl->assign('login_handler', $login_handler);
		
		$tpl->display("devblocks:cerberusweb.support_center:portal_".UmPortalHelper::getCode() . ":support_center/account/sidebar_menu.tpl");
	}
	
	function writeResponse(DevblocksHttpResponse $response) {
		$tpl = DevblocksPlatform::getTemplateService();
		$path = $response->path;
		
		@array_shift($path); // account
		
		// Login handler
        $login_handler = DAO_CommunityToolProperty::get(UmPortalHelper::getCode(), UmScApp::PARAM_LOGIN_HANDLER, '');
		$tpl->assign('login_handler', $login_handler);
		
		// Scope
		$umsession = UmPortalHelper::getSession();
		$active_contact = $umsession->getProperty('sc_login', null);
		
		// [TODO] Do this globally?
		$tpl->assign('active_contact', $active_contact);
		
		@$module = array_shift($path);
		
		switch($module) {
//			case 'preferences':
//				$tpl->display("devblocks:cerberusweb.support_center:portal_".UmPortalHelper::getCode() . ":support_center/account/preferences/index.tpl");
//				break;
				
			case 'password':
				$tpl->display("devblocks:cerberusweb.support_center:portal_".UmPortalHelper::getCode() . ":support_center/account/password/index.tpl");
				break;
				
			case 'delete':
				$tpl->display("devblocks:cerberusweb.support_center:portal_".UmPortalHelper::getCode() . ":support_center/account/delete/index.tpl");
				break;
				
			default:
			case 'email':
				@$id = array_shift($path);
				
				if('confirm' == $id) {
					@$email = DevblocksPlatform::importGPC($_REQUEST['email'],'string','');
					@$confirm = DevblocksPlatform::importGPC($_REQUEST['confirm'],'string','');
					
					$tpl->assign('email', $email);
					$tpl->assign('confirm', $confirm);
					
					$tpl->display("devblocks:cerberusweb.support_center:portal_".UmPortalHelper::getCode() . ":support_center/account/email/confirm.tpl");
				
				// Security check
				} elseif(empty($id) || null == ($address = DAO_Address::lookupAddress(urldecode(str_replace(array('_at_','_dot_'),array('%40','.'),$id)), false)) || $address->contact_person_id != $active_contact->id) {
					list($addresses, $null) = DAO_Address::search(
						array(),
						array(
							new DevblocksSearchCriteria(SearchFields_Address::CONTACT_PERSON_ID,'=',$active_contact->id),
						),
						-1,
						0,
						null,
						null,
						false
					);
					$tpl->assign('addresses', $addresses);
					
					$tpl->display("devblocks:cerberusweb.support_center:portal_".UmPortalHelper::getCode() . ":support_center/account/email/index.tpl");
					
				// Display all emails
				} else {
					// Addy
					$tpl->assign('address',$address);
			
					$address_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_ADDRESS);
					$tpl->assign('address_custom_fields', $address_fields);
			
					if(null != ($address_field_values = DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_ADDRESS, $address->id))
						&& is_array($address_field_values))
						$tpl->assign('address_custom_field_values', array_shift($address_field_values));
					
					// Org
					if(!empty($address->contact_org_id) && null != ($org = DAO_ContactOrg::get($address->contact_org_id))) {
						$tpl->assign('org',$org);
						
						$org_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_ORG);
						$tpl->assign('org_custom_fields', $org_fields);
						
						if(null != ($org_field_values = DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_ORG, $org->id))
							&& is_array($org_field_values))
							$tpl->assign('org_custom_field_values', array_shift($org_field_values));
					}
					
					// Show fields		
					if(null != ($show_fields = DAO_CommunityToolProperty::get(UmPortalHelper::getCode(), 'account.fields', null))) {
						$tpl->assign('show_fields', @json_decode($show_fields, true));
					}
					
					$tpl->display("devblocks:cerberusweb.support_center:portal_".UmPortalHelper::getCode() . ":support_center/account/email/display.tpl");
				}
				
				break;
				
			case 'openid':
				@$id = array_shift($path);
				
				// If checking an authorization
				if($id == 'authorize' && isset($_REQUEST['openid_mode']) && 'cancel' != DevblocksPlatform::importGPC($_REQUEST['openid_mode'],'string','')) {
					$openid = DevblocksPlatform::getOpenIDService();
					
					try {
						if($openid->validate($_REQUEST)) {
							@$openid_claimed_id = DevblocksPlatform::importGPC($_REQUEST['openid_claimed_id'],'string','');

							if(empty($openid_claimed_id))
								throw new Exception("No OpenID identity was discovered.");
							
							if(null != ($contact_id = DAO_OpenIdToContactPerson::getContactIdByOpenId($openid_claimed_id)))
								throw new Exception("This OpenID identity is already linked to an account.");
							
							// Link to contact
							DAO_OpenIdToContactPerson::addOpenId($openid_claimed_id, $active_contact->id);
							
						} else {
							throw new Exception("Authorization failed.");
						}
					
					} catch (Exception $e) {
						$tpl->assign('error', $e->getMessage());
					}
					
				// Display (Securely)
				} elseif(!empty($id) && null != ($openid = DAO_OpenIdToContactPerson::getOpenIdByHash($id)) && $openid->contact_person_id == $active_contact->id) {
					$tpl->assign('openid', $openid);
					$tpl->display("devblocks:cerberusweb.support_center:portal_".UmPortalHelper::getCode() . ":support_center/account/openid/display.tpl");
					return;
				}
				
				$openids = DAO_OpenIdToContactPerson::getOpenIdsByContact($active_contact->id);
				$tpl->assign('openids', $openids);
				
				$tpl->display("devblocks:cerberusweb.support_center:portal_".UmPortalHelper::getCode() . ":support_center/account/openid/index.tpl");
				break;
		}
	}

	function doEmailUpdateAction() {
		$tpl = DevblocksPlatform::getTemplateService();
		$umsession = UmPortalHelper::getSession();
		$active_contact = $umsession->getProperty('sc_login', null);
		
		if(null == ($contact = DAO_ContactPerson::get($active_contact->id)))
			return;
			
		// Security
		@$id = DevblocksPlatform::importGPC($_POST['id'],'integer',0);
		if(null == ($address = DAO_Address::get($id)) || $address->contact_person_id != $contact->id)
			return;
		
		@$action = DevblocksPlatform::importGPC($_POST['action'],'string','');
		
		switch($action) {
			case 'remove':
				// Can't remove primary email
				if($contact->email_id != $address->id)
					DAO_Address::update($address->id,array(
						DAO_Address::CONTACT_PERSON_ID => 0,
					));
				break;
				
			default:
				$customfields = DAO_CustomField::getAll();
				
				// Compare editable fields
				$show_fields = array();
				if(null != ($show_fields = DAO_CommunityToolProperty::get(UmPortalHelper::getCode(), 'account.fields', null)))
					@$show_fields = json_decode($show_fields, true);
				
				if(!empty($address)) {
					$contact_fields = array();
					$contact_customfields = array();
					$addy_fields = array();
					$addy_customfields = array();
					$org_fields = array();
					$org_customfields = array();
					
					if(isset($_POST['is_primary']) && !empty($_POST['is_primary'])) {
						$contact_fields[DAO_ContactPerson::EMAIL_ID] = $address->id;
						// [TODO] This could be done better
						$active_contact->email_id = $address->id;
						$umsession->setProperty('sc_login', $active_contact);
					}
					
					if(is_array($show_fields))
					foreach($show_fields as $field_name => $visibility) {
						if(2 != $visibility)
							continue;
						
						@$val = DevblocksPlatform::importGPC($_POST[$field_name],'string','');
							
						// Handle specific fields
						switch($field_name) {
							// Addys
							case 'addy_first_name':
								$addy_fields[DAO_Address::FIRST_NAME] = $val;
								break;
							case 'addy_last_name':
								$addy_fields[DAO_Address::LAST_NAME] = $val;
								break;
								
							// Orgs
							case 'org_name':
								if(!empty($val))
									$org_fields[DAO_ContactOrg::NAME] = $val; 
								break;
							case 'org_street':
								$org_fields[DAO_ContactOrg::STREET] = $val;
								break;
							case 'org_city':
								$org_fields[DAO_ContactOrg::CITY] = $val;
								break;
							case 'org_province':
								$org_fields[DAO_ContactOrg::PROVINCE] = $val;
								break;
							case 'org_postal':
								$org_fields[DAO_ContactOrg::POSTAL] = $val;
								break;
							case 'org_country':
								$org_fields[DAO_ContactOrg::COUNTRY] = $val;
								break;
							case 'org_phone':
								$org_fields[DAO_ContactOrg::PHONE] = $val;
								break;
							case 'org_website':
								$org_fields[DAO_ContactOrg::WEBSITE] = $val;
								break;
								
							// Custom fields
							default:
								// Handle array posts
								if(isset($_POST[$field_name]) && is_array($_POST[$field_name])) {
									$val = array();
									foreach(array_keys($_POST[$field_name]) as $idx)
										$val[] = DevblocksPlatform::importGPC($_POST[$field_name][$idx], 'string', '');
								}
								
								// Address
								if('addy_custom_'==substr($field_name,0,12)) {
									$field_id = intval(substr($field_name,12));
									$addy_customfields[$field_id] = $val;
									
								// Org
								} elseif('org_custom_'==substr($field_name,0,11)) {
									$field_id = intval(substr($field_name,11));
									$org_customfields[$field_id] = $val;
								}
								break;
						}
					}
					
					// Contact Person
					if(!empty($contact_fields))
						DAO_ContactPerson::update($contact->id, $contact_fields);
		//			if(!empty($contact_customfields))
		//				DAO_CustomFieldValue::formatAndSetFieldValues(CerberusContexts::CONTEXT_CONTACT_PERSON, $contact->id, $contact_customfields, true, false, false);
						
					// Addy
					if(!empty($addy_fields))
						DAO_Address::update($address->id, $addy_fields);
					if(!empty($addy_customfields))
						DAO_CustomFieldValue::formatAndSetFieldValues(CerberusContexts::CONTEXT_ADDRESS, $address->id, $addy_customfields, true, false, false);
					
					// Org
					if(!empty($org_fields) && !empty($address->contact_org_id))
						DAO_ContactOrg::update($address->contact_org_id, $org_fields);
					if(!empty($org_customfields) && !empty($address->contact_org_id))
						DAO_CustomFieldValue::formatAndSetFieldValues(CerberusContexts::CONTEXT_ORG, $address->contact_org_id, $org_customfields, true, false, false);
				}
				
				$tpl->assign('account_success', true);
				break;
		}
				
		DevblocksPlatform::setHttpResponse(new DevblocksHttpResponse(array('portal',UmPortalHelper::getCode(),'account','email')));
	}
	
	function doPasswordUpdateAction() {
		@$change_password = DevblocksPlatform::importGPC($_REQUEST['change_password'],'string','');
		@$change_password2 = DevblocksPlatform::importGPC($_REQUEST['change_password2'],'string','');
		
		$umsession = UmPortalHelper::getSession();
		$active_contact = $umsession->getProperty('sc_login', null);
		$url_writer = DevblocksPlatform::getUrlService();
		$tpl = DevblocksPlatform::getTemplateService();

		try {
			if(empty($active_contact) || empty($active_contact->id))
				throw new Exception("Your session is invalid.");
			
			if(empty($change_password) || empty($change_password2))
				throw new Exception("Your password cannot be blank.");
			
			if(0 != strcmp($change_password, $change_password2))
				throw new Exception("Your passwords do not match.");
				
			// Change password?
			$salt = CerberusApplication::generatePassword(8);
			$fields = array(
				DAO_ContactPerson::AUTH_SALT => $salt,
				DAO_ContactPerson::AUTH_PASSWORD => md5($salt.md5($change_password)),
			);
			DAO_ContactPerson::update($active_contact->id, $fields);
			
			$tpl->assign('success', true);
			
		} catch(Exception $e) {
			$tpl = DevblocksPlatform::getTemplateService();
			$tpl->assign('error', $e->getMessage());
			
		}
		
		DevblocksPlatform::setHttpResponse(new DevblocksHttpResponse(array('portal',UmPortalHelper::getCode(),'account','password')));
	}
	
	function doEmailAddAction() {
		$umsession = UmPortalHelper::getSession();
		$active_contact = $umsession->getProperty('sc_login', null);
		$tpl = DevblocksPlatform::getTemplateService();
		$url_writer = DevblocksPlatform::getUrlService();
		
		try {
			if(null == ($contact = DAO_ContactPerson::get($active_contact->id)))
				return;
	
			@$add_email = DevblocksPlatform::importGPC($_REQUEST['add_email'],'string','');
			if(empty($add_email) || null == ($address = DAO_Address::lookupAddress($add_email, true)))
				throw new Exception("The email address you provided is invalid.");
	
			// Is this address already assigned
			if(!empty($address->contact_person_id)) {
				// [TODO] Or awaiting confirmation
				throw new Exception("The email address you provided is already assigned to an account.");
			}
			
			// If available, send confirmation email w/ link
			$fields = array(
				DAO_ConfirmationCode::CONFIRMATION_CODE => CerberusApplication::generatePassword(8),
				DAO_ConfirmationCode::NAMESPACE_KEY => 'support_center.email.confirm',
				DAO_ConfirmationCode::META_JSON => json_encode(array(
					'contact_id' => $contact->id,
					'address_id' => $address->id,
				)),
				DAO_ConfirmationCode::CREATED => time(),
			);
			DAO_ConfirmationCode::create($fields);

			// Quick send
			$msg = sprintf(
				"%s?email=%s&confirm=%s",
				$url_writer->write('c=account&a=email&o=confirm', true),
				urlencode($address->email),
				urlencode($fields[DAO_ConfirmationCode::CONFIRMATION_CODE])
			);
			CerberusMail::quickSend($address->email,"Please confirm your email address", $msg);
			
		} catch (Exception $e) {
			$tpl->assign('error', $e->getMessage());

			DevblocksPlatform::setHttpResponse(new DevblocksHttpResponse(array('portal',UmPortalHelper::getCode(),'account','email')));
			return;
		}
		
		DevblocksPlatform::setHttpResponse(new DevblocksHttpResponse(array('portal',UmPortalHelper::getCode(),'account','email','confirm')));
		//DevblocksPlatform::redirect(new DevblocksHttpResponse(array('account','email')));
		//exit;
	}
	
	function doEmailConfirmAction() {
		@$email = DevblocksPlatform::importGPC($_REQUEST['email'],'string','');
		@$confirm = DevblocksPlatform::importGPC($_REQUEST['confirm'],'string','');

		// Verify session
		$umsession = UmPortalHelper::getSession();
		$active_contact = $umsession->getProperty('sc_login', null);

		try {
			if(null == ($contact = DAO_ContactPerson::get($active_contact->id)))
				throw new Exception("Your session has expired.");
			
			// Lookup code
			if(null == ($code = DAO_ConfirmationCode::getByCode('support_center.email.confirm', $confirm)))
				throw new Exception("Your confirmation code is invalid.");
				
			// Compare session
			if(!isset($code->meta['contact_id']) || $contact->id != $code->meta['contact_id'])
				throw new Exception("Your confirmation code is invalid.");
				
			// Compare email addy
			if(!isset($code->meta['address_id'])  
				|| null == ($address = DAO_Address::get($code->meta['address_id'])) 
				|| 0 != strcasecmp($address->email,$email))
				throw new Exception("Your confirmation code is invalid.");
				
			// Pass + associate
			DAO_ConfirmationCode::delete($code->id);
			DAO_Address::update($address->id,array(
				DAO_Address::CONTACT_PERSON_ID => $contact->id,
			));
			
			$address_uri = urlencode(str_replace(array('@','.'),array('_at_','_dot_'),$address->email));
			
			DevblocksPlatform::setHttpResponse(new DevblocksHttpResponse(array('portal',UmPortalHelper::getCode(),'account','email',$address_uri)));
			return;
			
		} catch(Exception $e) {
			$tpl = DevblocksPlatform::getTemplateService();
			$tpl->assign('error', $e->getMessage());

			DevblocksPlatform::setHttpResponse(new DevblocksHttpResponse(array('portal',UmPortalHelper::getCode(),'account','email','confirm')));
			return;
		}
		
		DevblocksPlatform::setHttpResponse(new DevblocksHttpResponse(array('portal',UmPortalHelper::getCode(),'account','email')));
	}
	
	function doOpenIdAddAction() {
		$umsession = UmPortalHelper::getSession();
		$active_contact = $umsession->getProperty('sc_login', null);
		
		try {
			if(null == ($contact = DAO_ContactPerson::get($active_contact->id)))
				throw new Exception("Your session has expired.");
	
			$openid = DevblocksPlatform::getOpenIDService();
			$url_writer = DevblocksPlatform::getUrlService();
			
			@$openid_url = DevblocksPlatform::importGPC($_REQUEST['openid_url'],'string','');
			
			if(false == ($auth_url = $openid->getAuthUrl($openid_url, $url_writer->write('c=account&o=openid&r=authorize',true))))
				throw new Exception("You provided an invalid OpenID identity.");
			
			header("Location: " . $auth_url);
			exit;

			// [TODO] Check if the OpenID is already assigned
			
//			@$add_email = DevblocksPlatform::importGPC($_REQUEST['add_email'],'string','');
//			if(empty($add_email) || null == ($address = DAO_Address::lookupAddress($add_email, true)))
//				throw new Exception("The email address you provided is invalid.");
	
			// Is this address already assigned
//			if(!empty($address->contact_person_id)) {
//				// [TODO] Or awaiting confirmation
//				throw new Exception("The email address you provided is already assigned to an account.");
//			}
			
		} catch (Exception $e) {
			$tpl = DevblocksPlatform::getTemplateService();
			$tpl->assign('error', $e->getMessage());

			DevblocksPlatform::setHttpResponse(new DevblocksHttpResponse(array('portal',UmPortalHelper::getCode(),'account','openid')));
			return;
		}
	}
	
	function doOpenIdUpdateAction() {
		$tpl = DevblocksPlatform::getTemplateService();
		$umsession = UmPortalHelper::getSession();
		$active_contact = $umsession->getProperty('sc_login', null);
		
		if(null == ($contact = DAO_ContactPerson::get($active_contact->id)))
			return;
			
		// Security
		@$hash_key = DevblocksPlatform::importGPC($_POST['hash_key'],'string','');
		if(null == ($openid = DAO_OpenIdToContactPerson::getOpenIdByHash($hash_key)) || $openid->contact_person_id != $contact->id)
			return;
		
		@$action = DevblocksPlatform::importGPC($_POST['action'],'string','');
		
		switch($action) {
			case 'remove':
				DAO_OpenIdToContactPerson::deleteByOpenId($openid->openid_claimed_id);
				break;
		}
		
		DevblocksPlatform::setHttpResponse(new DevblocksHttpResponse(array('portal',UmPortalHelper::getCode(),'account','openid')));
	}
	
	function doDeleteAction() {
		@$captcha = DevblocksPlatform::importGPC($_REQUEST['captcha'], 'string', '');
		
		$umsession = UmPortalHelper::getSession();
		$active_contact = $umsession->getProperty('sc_login', null);
		$tpl = DevblocksPlatform::getTemplateService();
		$url_writer = DevblocksPlatform::getUrlService();
		
		try {
			// Load the contact account
			if(null == ($contact = DAO_ContactPerson::get($active_contact->id)))
				throw new Exception("Your request could not be processed at this time.");
				
			// Compare the captcha
			$compare_captcha = $umsession->getProperty('write_captcha', '');
			if(0 != strcasecmp($captcha, $compare_captcha))
				throw new Exception("Your text did not match the image.");
				
			// Release OpenIDs
			DAO_OpenIdToContactPerson::deleteByContactPerson($contact->id);
			
			// Delete the contact account
			DAO_ContactPerson::delete($contact->id);
			unset($contact);
				
			// Clear the session
			$umsession->destroy();
			
			// Response
			header("Location: " . $url_writer->write('', true));
			exit;
			
		} catch(Exception $e) {
			$tpl->assign('error', $e->getMessage());
		}
		
		DevblocksPlatform::setHttpResponse(new DevblocksHttpResponse(array('portal',UmPortalHelper::getCode(),'account','delete')));
	}
	
	function configure(Model_CommunityTool $instance) {
		$tpl = DevblocksPlatform::getTemplateService();

		if(null != ($show_fields = DAO_CommunityToolProperty::get($instance->code, 'account.fields', null))) {
			$tpl->assign('show_fields', @json_decode($show_fields, true));
		}
		
		$address_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_ADDRESS);
		$tpl->assign('address_custom_fields', $address_fields);

		$org_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_ORG);
		$tpl->assign('org_custom_fields', $org_fields);
		
		$types = Model_CustomField::getTypes();
		$tpl->assign('field_types', $types);
		
		$tpl->display("devblocks:cerberusweb.support_center::portal/sc/config/module/account.tpl");
	}
	
	function saveConfiguration(Model_CommunityTool $instance) {
        @$aFields = DevblocksPlatform::importGPC($_POST['fields'],'array',array());
        @$aFieldsVisible = DevblocksPlatform::importGPC($_POST['fields_visible'],'array',array());

        $fields = array();
        
        if(is_array($aFields))
        foreach($aFields as $idx => $field) {
        	$mode = $aFieldsVisible[$idx];
        	if(!is_null($mode))
        		$fields[$field] = intval($mode);
        }
        
        DAO_CommunityToolProperty::set($instance->code, 'account.fields', json_encode($fields));
	}	
};