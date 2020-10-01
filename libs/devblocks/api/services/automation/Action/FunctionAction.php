<?php
namespace Cerb\AutomationBuilder\Action;

use AutomationTrigger_UiFunction;
use CerbAutomationPolicy;
use DAO_Automation;
use DevblocksDictionaryDelegate;
use DevblocksPlatform;
use Exception_DevblocksAutomationError;

class FunctionAction extends AbstractAction {
	const ID = 'function';
	
	/**
	 * @param DevblocksDictionaryDelegate $dict
	 * @param array $node_memory
	 * @param CerbAutomationPolicy $policy
	 * @param string|null $error
	 * @return string|false
	 */
	function activate(DevblocksDictionaryDelegate $dict, array &$node_memory, CerbAutomationPolicy $policy, string &$error=null) {
		$validation = \DevblocksPlatform::services()->validation();
		$automator = DevblocksPlatform::services()->automation();
		
		$params = $this->node->getParams($dict);
		
		$inputs = $params['inputs'] ?? [];
		$output = $params['output'];
		
		try {
			// Params validation
			
			$validation->addField('name', 'name:')
				->string()
				->setRequired(true)
			;
			
			$validation->addField('inputs', 'inputs:')
				->array()
			;
			
			$validation->addField('output', 'output:')
				->string()
				->setRequired(true)
			;
			
			if(false === ($validation->validateAll($params, $error)))
				throw new Exception_DevblocksAutomationError($error);
			
			// Policy
			
			$action_dict = DevblocksDictionaryDelegate::instance([
				'node' => [
					'id' => $this->node->getId(),
					'type' => self::ID,
				],
				'name' => $params['name'],
				'inputs' => $inputs,
				'output' => $output,
			]);
			
			if (!$policy->isAllowed(self::ID, $action_dict)) {
				$error = sprintf(
					"The automation policy does not permit the `function:` action."
				);
				throw new Exception_DevblocksAutomationError($error);
			}
			
			if (false == ($automation = DAO_Automation::getByUri($params['name'], AutomationTrigger_UiFunction::ID))) {
				throw new Exception_DevblocksAutomationError(sprintf('Function (%s) must be a ui.function automation', $params['name']));
			}
			
			$initial_state = [
				'inputs' => $inputs,
			];
			
			if (false == ($automation_results = $automator->executeScript($automation, $initial_state, $error))) {
				throw new Exception_DevblocksAutomationError($error);
			}
			
			// Check exit code
			$exit_code = $automation_results->get('__exit');
			
			if ('error' == $exit_code) {
				$error = $automation_results->getKeyPath('__return.error', '');
				throw new Exception_DevblocksAutomationError($error);
			}
			
			$end_state = $automation_results->get('__return');
			
			if ($output) {
				$dict->set($output, $end_state);
			}
			
		} catch (Exception_DevblocksAutomationError $e) {
			$error = $e->getMessage();
			
			if (null != ($event_error = $this->node->getChild($this->node->getId() . ':on_error'))) {
				if ($output) {
					$dict->set($output, [
						'error' => $error,
					]);
				}
				
				return $event_error->getId();
			}
			
			return false;
		}
		
		if (null != ($event_success = $this->node->getChild($this->node->getId() . ':on_success'))) {
			return $event_success->getId();
		}
		
		return $this->node->getParent()->getId();
	}
}