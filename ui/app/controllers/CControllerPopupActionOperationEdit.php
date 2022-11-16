<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CControllerPopupActionOperationEdit extends CController {

	protected function checkInput(): bool {
		$fields = [
			'eventsource' =>	'required|db actions.eventsource|in '.implode(',', [
									EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
									EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
								]),
			'recovery' =>		'db operations.recovery',
			'actionid' =>		'db actions.actionid',
			'operation' =>		'array',
			'operationid' =>	'db operations.operationid',
			'operationtype' =>	'db operations.operationtype',
			'data' =>			'array',
			'row_index' =>		'int32'
		];

		$ret = $this->validateInput($fields) && $this->validateInputConstraints();

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function validateInputConstraints(): bool {
		$eventsource = $this->getInput('eventsource');
		$recovery = $this->getInput('recovery');
		$allowed_operations = getAllowedOperations($eventsource);

		if (!array_key_exists($recovery, $allowed_operations)) {
			error(_('Unsupported operation.'));
			return false;
		}

		return true;
	}

	protected function checkPermissions(): bool {
		if ($this->getUserType() >= USER_TYPE_ZABBIX_ADMIN) {
			if (!$this->getInput('actionid', '0')) {
				return true;
			}

			return (bool) API::Action()->get([
				'output' => [],
				'actionids' => $this->getInput('actionid'),
				'editable' => true
			]);
		}

		return false;
	}

	protected function doAction(): void {
		$operation = $this->getInput('data', []) + $this->defaultOperationObject();
		$eventsource = (int) $this->getInput('eventsource');
		$recovery = (int) $this->getInput('recovery');
		$operation_types = $this->popupConfigOperationTypes($operation, $eventsource, $recovery)['options'];

		foreach ($operation_types as $type) {
			$operation_type[$type['value']] = $type['name'];
		}

		$media_types = API::MediaType()->get(['output' => ['mediatypeid', 'name', 'status']]);
		CArrayHelper::sort($media_types, ['name']);
		$media_types = array_values($media_types);
		$operation['row_index'] = $this->getInput('row_index', -1);

		$operation_data = $this->getData($operation);

		if (array_key_exists('user_group', $operation_data)) {
			$operation['opmessage_grp'] = $operation_data['user_group'];
		}

		if (array_key_exists('users', $operation_data)) {
			$operation['opmessage_usr'] = $operation_data['users'];
		}

		if (array_key_exists('opcommand_hst', $operation)) {
			foreach ($operation['opcommand_hst'] as &$opcommand_hst) {
				if ($opcommand_hst['hostid'] == 0) {
					$opcommand_hst = ['hostid' => 0];
				}
				else {
					$opcommand_hst = $operation_data['opcommand_hst'][0];
				}
			}
			unset($opcommand_hst);
		}

		if (array_key_exists('opcommand_grp', $operation_data)) {
			$operation['opcommand_grp'] = $operation_data['opcommand_grp'];
		}

		if (array_key_exists('opgroup', $operation_data)) {
			$operation['opgroup'] = $operation_data['opgroup'];
		}

		if (array_key_exists('optemplate', $operation_data)) {
			$operation['optemplate'] = $operation_data['optemplate'];
		}

		$data = [
			'eventsource' => $eventsource,
			'actionid' => $this->getInput('actionid', []),
			'recovery' => $recovery,
			'operation' => $operation,
			'operation_types' => $operation_type,
			'mediatype_options' => $media_types
		];

		$this->setResponse(new CControllerResponseData($data));
	}

	/**
	 * Returns necessarry operation 'element' data for operation popup multiselects.
	 *
	 * @param array $operation  Operation object.
	 *
	 * @return array
	 */
	private function getData(array $operation): array {
		$result = [];

		if ($operation['opmessage_grp']) {
			$user_groups = API::UserGroup()->get([
				'output' => ['usrgrpid', 'name'],
				'usrgrpids' => array_column($operation['opmessage_grp'], 'usrgrpid'),
			]);
			CArrayHelper::sort($user_groups, ['name']);

			$result['user_group'] = array_values($user_groups);
		}

		if ($operation['opmessage_usr']) {
			$users = API::User()->get([
				'output' => ['userid', 'username', 'name', 'surname'],
				'userids' => array_column($operation['opmessage_usr'], 'userid'),
			]);

			$fullnames = [];

			foreach ($users as $user) {
				$fullnames[$user['userid']] = getUserFullname($user);
				$user['name'] = $fullnames[$user['userid']];

				$result['users'][] = $user;
			}
		}

		if ($operation['opcommand_hst']) {
			$host = API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => array_column($operation['opcommand_hst'], 'hostid')
			]);
			CArrayHelper::sort($host, ['name']);

			$result['opcommand_hst'] = array_values($host);
		}

		if ($operation['opcommand_grp']) {
			$group = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => array_column($operation['opcommand_grp'], 'groupid')
			]);
			CArrayHelper::sort($group, ['name']);

			$result['opcommand_grp'] = array_values($group);
		}

		if ($operation['opgroup']) {
			$group = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => array_column($operation['opgroup'], 'groupid')
			]);
			CArrayHelper::sort($group, ['name']);

			$result['opgroup'] = array_values($group);
		}

		if ($operation['optemplate']) {
			$template = API::Template()->get([
				'output' => ['templateid', 'name'],
				'templateids' => array_column($operation['optemplate'], 'templateid')
			]);
			CArrayHelper::sort($template, ['name']);

			$result['optemplate'] = array_values($template);
		}

		return $result;
	}

	/**
	 * Returns default Operation.
	 *
	 * @return array
	 */
	private function defaultOperationObject(): array {
		return [
			'opmessage_usr' => [],
			'opmessage_grp' => [],
			'opmessage' => [
				'subject' => '',
				'message' => '',
				'mediatypeid' => '0',
				'default_msg' => '1'
			],
			'operationtype' => '0',
			'esc_step_from' => '1',
			'esc_step_to' => '1',
			'esc_period' => '0',
			'opcommand_hst' => [],
			'opcommand_grp' => [],
			'evaltype' => (string) CONDITION_EVAL_TYPE_AND_OR,
			'opconditions' => [],
			'opgroup' => [],
			'optemplate' => [],
			'opinventory' => [
				'inventory_mode' => (string) HOST_INVENTORY_MANUAL
			],
			'opcommand' => [
				'scriptid' => '0'
			]
		];
	}

	/**
	 * Returns "operation type" configuration fields for given operation in given source.
	 *
	 * @param array $operation  Operation object.
	 * @param int $eventsource  Action event source.
	 * @param int $recovery     Action operation mode. Possible values:
	 *                          ACTION_OPERATION, ACTION_RECOVERY_OPERATION, ACTION_UPDATE_OPERATION
	 *
	 * @return array
	 */
	private function popupConfigOperationTypes(array $operation, int $eventsource, int $recovery): array {
		$operation_type_options = [];
		$scripts_allowed = false;

		// First determine if scripts are allowed for this action type.
		foreach (getAllowedOperations($eventsource)[$recovery] as $operation_type) {
			if ($operation_type == OPERATION_TYPE_COMMAND) {
				$scripts_allowed = true;

				break;
			}
		}

		// Then remove Remote command from dropdown list.
		foreach (getAllowedOperations($eventsource)[$recovery] as $operation_type) {
			if ($operation_type == OPERATION_TYPE_COMMAND) {
				continue;
			}

			$operation_type_options[] = [
				'value' => 'cmd['.$operation_type.']',
				'name' => operation_type2str($operation_type)
			];
		}

		if ($scripts_allowed) {
			$db_scripts = API::Script()->get([
				'output' => ['scriptid', 'name'],
				'filter' => ['scope' => ZBX_SCRIPT_SCOPE_ACTION]
			]);

			if ($db_scripts) {
				CArrayHelper::sort($db_scripts, ['name']);

				foreach ($db_scripts as $db_script) {
					$operation_type_options[] = [
						'value' => 'scriptid['.$db_script['scriptid'].']',
						'name' => $db_script['name']
					];
				}
			}
		}

		return [
			'options' => $operation_type_options,
			'selected' => ($operation['opcommand']['scriptid'] == 0)
				? 'cmd['.$operation['operationtype'].']'
				: 'scriptid['.$operation['opcommand']['scriptid'].']'
		];
	}
}
