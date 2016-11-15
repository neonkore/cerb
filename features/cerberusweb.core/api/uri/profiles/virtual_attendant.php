<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2016, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerb.io/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://cerb.io	    http://webgroup.media
***********************************************************************/

class PageSection_ProfilesVirtualAttendant extends Extension_PageSection {
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$visit = CerberusApplication::getVisit();
		$translate = DevblocksPlatform::getTranslationService();
		$active_worker = CerberusApplication::getActiveWorker();
		
		$response = DevblocksPlatform::getHttpResponse();
		$stack = $response->path;
		@array_shift($stack); // profiles
		@array_shift($stack); // virtual_attendant
		$id = array_shift($stack); // 123

		@$id = intval($id);
		
		if(null == ($virtual_attendant = DAO_VirtualAttendant::get($id))) {
			DevblocksPlatform::redirect(new DevblocksHttpRequest(array('search','virtual_attendant')));
			return;
		}
		$tpl->assign('virtual_attendant', $virtual_attendant);
	
		// Tab persistence
		
		$point = 'profiles.virtual_attendant.tab';
		$tpl->assign('point', $point);
		
		if(null == (@$tab_selected = $stack[0])) {
			$tab_selected = $visit->get($point, '');
		}
		$tpl->assign('tab_selected', $tab_selected);
	
		// Properties
			
		$properties = array();
		
		$properties['owner'] = array(
			'label' => mb_ucfirst($translate->_('common.owner')),
			'type' => Model_CustomField::TYPE_LINK,
			'value' => $virtual_attendant->owner_context_id,
			'params' => [
				'context' => $virtual_attendant->owner_context,
			],
		);
		
		$properties['is_disabled'] = array(
			'label' => mb_ucfirst($translate->_('common.disabled')),
			'type' => Model_CustomField::TYPE_CHECKBOX,
			'value' => $virtual_attendant->is_disabled,
		);
		
		$properties['created'] = array(
			'label' => mb_ucfirst($translate->_('common.created')),
			'type' => Model_CustomField::TYPE_DATE,
			'value' => $virtual_attendant->created_at,
		);
		
		$properties['updated'] = array(
			'label' => mb_ucfirst($translate->_('common.updated')),
			'type' => Model_CustomField::TYPE_DATE,
			'value' => $virtual_attendant->updated_at,
		);
	
		// Custom Fields

		@$values = array_shift(DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT, $virtual_attendant->id)) or array();
		$tpl->assign('custom_field_values', $values);
		
		$properties_cfields = Page_Profiles::getProfilePropertiesCustomFields(CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT, $values);
		
		if(!empty($properties_cfields))
			$properties = array_merge($properties, $properties_cfields);
		
		// Custom Fieldsets

		$properties_custom_fieldsets = Page_Profiles::getProfilePropertiesCustomFieldsets(CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT, $virtual_attendant->id, $values);
		$tpl->assign('properties_custom_fieldsets', $properties_custom_fieldsets);
		
		// Link counts
		
		$properties_links = array(
			CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT => array(
				$virtual_attendant->id => 
					DAO_ContextLink::getContextLinkCounts(
						CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT,
						$virtual_attendant->id,
						array(CerberusContexts::CONTEXT_CUSTOM_FIELDSET)
					),
			),
		);
		
		$tpl->assign('properties_links', $properties_links);
		
		// Properties
		
		$tpl->assign('properties', $properties);
			
		// Macros
		
		$macros = DAO_TriggerEvent::getReadableByActor(
			$active_worker,
			'event.macro.virtual_attendant'
		);
		$tpl->assign('macros', $macros);

		// Tabs
		$tab_manifests = Extension_ContextProfileTab::getExtensions(false, CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT);
		$tpl->assign('tab_manifests', $tab_manifests);
		
		// Template
		$tpl->display('devblocks:cerberusweb.core::profiles/virtual_attendant.tpl');
	}
	
	function savePeekJsonAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'], 'integer', 0);
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'], 'string', '');
		@$do_delete = DevblocksPlatform::importGPC($_REQUEST['do_delete'], 'string', '');
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		if(!$active_worker || !$active_worker->is_superuser)
			return false;
		
		// Model
		
		header('Content-Type: application/json; charset=utf-8');
		
		try {
			if(!empty($id) && !empty($do_delete)) { // Delete
				DAO_VirtualAttendant::delete($id);
				
				echo json_encode(array(
					'status' => true,
					'id' => $id,
					'view_id' => $view_id,
				));
				return;
				
			} else {
				@$name = DevblocksPlatform::importGPC($_REQUEST['name'], 'string', '');
				@$owner = DevblocksPlatform::importGPC($_REQUEST['owner'], 'string', '');
				@$is_disabled = DevblocksPlatform::importGPC($_REQUEST['is_disabled'], 'integer', 0);
				@$allowed_events = DevblocksPlatform::importGPC($_REQUEST['allowed_events'], 'string', '');
				@$itemized_events = DevblocksPlatform::importGPC($_REQUEST['itemized_events'], 'array', array());
				@$allowed_actions = DevblocksPlatform::importGPC($_REQUEST['allowed_actions'], 'string', '');
				@$itemized_actions = DevblocksPlatform::importGPC($_REQUEST['itemized_actions'], 'array', array());
				
				$is_disabled = DevblocksPlatform::intClamp($is_disabled, 0, 1);
				
				if(empty($name))
					throw new Exception_DevblocksAjaxValidationError("The 'Name' field is required.", 'name');
				
				// Owner
			
				$owner_ctx = '';
				@list($owner_ctx, $owner_ctx_id) = explode(':', $owner, 2);
				
				// Make sure we're given a valid ctx
				
				switch($owner_ctx) {
					case CerberusContexts::CONTEXT_APPLICATION:
					case CerberusContexts::CONTEXT_ROLE:
					case CerberusContexts::CONTEXT_GROUP:
					case CerberusContexts::CONTEXT_WORKER:
						break;
						
					default:
						$owner_ctx = null;
				}
				
				if(empty($owner_ctx))
					throw new Exception_DevblocksAjaxValidationError("A valid 'Owner' is required.");
				
				// Permissions
				
				$params = array(
					'events' => array(
						'mode' => $allowed_events,
						'items' => $itemized_events,
					),
					'actions' => array(
						'mode' => $allowed_actions,
						'items' => $itemized_actions,
					),
				);
				
				// Create or update
				
				if(empty($id)) { // New
					$fields = array(
						DAO_VirtualAttendant::CREATED_AT => time(),
						DAO_VirtualAttendant::UPDATED_AT => time(),
						DAO_VirtualAttendant::NAME => $name,
						DAO_VirtualAttendant::IS_DISABLED => $is_disabled,
						DAO_VirtualAttendant::OWNER_CONTEXT => $owner_ctx,
						DAO_VirtualAttendant::OWNER_CONTEXT_ID => $owner_ctx_id,
						DAO_VirtualAttendant::PARAMS_JSON => json_encode($params),
					);
					
					if(false == ($id = DAO_VirtualAttendant::create($fields)))
						throw new Exception_DevblocksAjaxValidationError("Failed to create a new record.");
					
					if(!empty($view_id) && !empty($id))
						C4_AbstractView::setMarqueeContextCreated($view_id, CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT, $id);
					
				} else { // Edit
					$fields = array(
						DAO_VirtualAttendant::UPDATED_AT => time(),
						DAO_VirtualAttendant::NAME => $name,
						DAO_VirtualAttendant::IS_DISABLED => $is_disabled,
						DAO_VirtualAttendant::OWNER_CONTEXT => $owner_ctx,
						DAO_VirtualAttendant::OWNER_CONTEXT_ID => $owner_ctx_id,
						DAO_VirtualAttendant::PARAMS_JSON => json_encode($params),
					);
					DAO_VirtualAttendant::update($id, $fields);
					
				}
	
				if($id) {
					// Custom fields
					@$field_ids = DevblocksPlatform::importGPC($_REQUEST['field_ids'], 'array', array());
					DAO_CustomFieldValue::handleFormPost(CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT, $id, $field_ids);
					
					// Avatar image
					@$avatar_image = DevblocksPlatform::importGPC($_REQUEST['avatar_image'], 'string', '');
					DAO_ContextAvatar::upsertWithImage(CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT, $id, $avatar_image);
				}
				
				echo json_encode(array(
					'status' => true,
					'id' => $id,
					'label' => $name,
					'view_id' => $view_id,
				));
				return;
			}
			
		} catch (Exception_DevblocksAjaxValidationError $e) {
			echo json_encode(array(
				'status' => false,
				'error' => $e->getMessage(),
				'field' => $e->getFieldName(),
			));
			return;
			
		} catch (Exception $e) {
			echo json_encode(array(
				'status' => false,
				'error' => 'An error occurred.',
			));
			return;
		}
	}
	
	function showBehaviorsTabAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'],'integer',0);
		@$point = DevblocksPlatform::importGPC($_REQUEST['point'],'string','');
		
		$active_worker = CerberusApplication::getActiveWorker();
		$tpl = DevblocksPlatform::getTemplateService();

		if(empty($id))
			return;
		
		if(null == ($va = DAO_VirtualAttendant::get($id)))
			return;
		
		$defaults = C4_AbstractViewModel::loadFromClass('View_TriggerEvent');
		$defaults->id = 'bot_behaviors';
		$defaults->is_ephemeral = false;
		$defaults->view_columns = [
			SearchFields_TriggerEvent::EVENT_POINT,
			SearchFields_TriggerEvent::UPDATED_AT,
			SearchFields_TriggerEvent::IS_DISABLED,
			SearchFields_TriggerEvent::IS_PRIVATE,
		];
		$defaults->renderSortBy = SearchFields_TriggerEvent::TITLE;
		$defaults->renderSortAsc = true;
		
		$view = C4_AbstractViewLoader::getView($defaults->id, $defaults);
		$view->addParamsRequired([
			new DevblocksSearchCriteria(SearchFields_TriggerEvent::VIRTUAL_ATTENDANT_ID, '=', $va->id),
		], true);
		$tpl->assign('view', $view);
		
		$tpl->display('devblocks:cerberusweb.core::internal/views/search_and_view.tpl');
	}
	
	function showScheduledBehaviorsTabAction() {
		@$va_id = DevblocksPlatform::importGPC($_REQUEST['va_id'],'integer',0);
		@$point = DevblocksPlatform::importGPC($_REQUEST['point'],'string','');
		
		$active_worker = CerberusApplication::getActiveWorker();
		$tpl = DevblocksPlatform::getTemplateService();

		// Admins can see all owners at once
		if(empty($va_id) && !$active_worker->is_superuser)
			return;

		// [TODO] ACL

		$defaults = C4_AbstractViewModel::loadFromClass('View_ContextScheduledBehavior');
		$defaults->id = 'va_schedbeh_' . $va_id;
		$defaults->is_ephemeral = true;
		
		$view = C4_AbstractViewLoader::getView($defaults->id, $defaults);

		if(empty($va_id) && $active_worker->is_superuser) {
			$view->addParamsRequired(array(), true);
			
		} else {
			$view->addParamsRequired(array(
				'_privs' => array(
					DevblocksSearchCriteria::GROUP_AND,
					new DevblocksSearchCriteria(SearchFields_ContextScheduledBehavior::BEHAVIOR_VIRTUAL_ATTENDANT_ID, '=', $va_id),
				)
			), true);
		}
		
		$tpl->assign('view', $view);
		
		// Template
		
		$tpl->display('devblocks:cerberusweb.core::internal/views/search_and_view.tpl');
	}
	
	function viewExploreAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		
		$active_worker = CerberusApplication::getActiveWorker();
		$url_writer = DevblocksPlatform::getUrlService();
		
		// Generate hash
		$hash = md5($view_id.$active_worker->id.time());
		
		// Loop through view and get IDs
		$view = C4_AbstractViewLoader::getView($view_id);
		$view->setAutoPersist(false);

		// Page start
		@$explore_from = DevblocksPlatform::importGPC($_REQUEST['explore_from'],'integer',0);
		if(empty($explore_from)) {
			$orig_pos = 1+($view->renderPage * $view->renderLimit);
		} else {
			$orig_pos = 1;
		}

		$view->renderPage = 0;
		$view->renderLimit = 250;
		$pos = 0;
		
		do {
			$models = array();
			list($results, $total) = $view->getData();

			// Summary row
			if(0==$view->renderPage) {
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'title' => $view->name,
					'created' => time(),
//					'worker_id' => $active_worker->id,
					'total' => $total,
					'return_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $url_writer->writeNoProxy('c=search&type=virtual_attendant', true),
					'toolbar_extension_id' => 'cerberusweb.contexts.virtual.attendant.explore.toolbar',
				);
				$models[] = $model;
				
				$view->renderTotal = false; // speed up subsequent pages
			}
			
			if(is_array($results))
			foreach($results as $opp_id => $row) {
				if($opp_id==$explore_from)
					$orig_pos = $pos;
				
				$url = $url_writer->writeNoProxy(sprintf("c=profiles&type=virtual_attendant&id=%d-%s", $row[SearchFields_VirtualAttendant::ID], DevblocksPlatform::strToPermalink($row[SearchFields_VirtualAttendant::NAME])), true);
				
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'id' => $row[SearchFields_VirtualAttendant::ID],
					'url' => $url,
				);
				$models[] = $model;
			}
			
			DAO_ExplorerSet::createFromModels($models);
			
			$view->renderPage++;
			
		} while(!empty($results));
		
		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('explore',$hash,$orig_pos)));
	}
};
