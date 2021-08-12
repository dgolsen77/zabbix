<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CControllerServiceUpdate extends CController {

	protected function checkInput(): bool {
		$fields = [
			'serviceid' =>					'required|db services.serviceid',
			'name' =>						'required|db services.name|not_empty',
			'parent_serviceids' =>			'array_db services.serviceid',
			'problem_tags' =>				'array',
			'sortorder' =>					'required|db services.sortorder|ge 0|le 999',
			'algorithm' =>					'required|db services.algorithm|in '.implode(',', [ZBX_SERVICE_STATUS_CALC_SET_OK, ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ONE, ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL]),
			'advanced_configuration' =>		'in 1',
			'status_rules' =>				'array',
			'propagation_rule' =>			'in '.implode(',', array_keys(CServiceHelper::getStatusPropagationNames())),
			'propagation_value_number' =>	'int32',
			'propagation_value_status' =>	'int32',
			'weight'					=>	'string',
			'showsla' =>					'in 1',
			'goodsla' =>					'string',
			'times' =>						'array',
			'tags' =>						'array',
			'child_serviceids' =>			'array_db services.serviceid'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			if ($this->getInput('weight', '') !== '') {
				$validator = new CNewValidator([
					'weight' => $this->getInput('weight')
				], [
					'weight' => 'int32|ge 0|le 1000000'
				]);

				foreach ($validator->getAllErrors() as $error) {
					info($error);
				}

				$ret = !$validator->isErrorFatal() && !$validator->isError();
			}
		}

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['errors' => getMessages()->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_MONITORING_SERVICES)
				|| !$this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SERVICES)) {
			return false;
		}

		return (bool) API::Service()->get([
			'output' => [],
			'serviceids' => $this->getInput('serviceid')
		]);
	}

	protected function doAction(): void {
		$service = [
			'showsla' => $this->hasInput('showsla') ? SERVICE_SHOW_SLA_ON : SERVICE_SHOW_SLA_OFF,
			'tags' => [],
			'problem_tags' => [],
			'parents' => [],
			'children' => [],
			'times' => $this->getInput('times', []),
			'status_rules' => []
		];

		$this->getInputs($service, ['serviceid', 'name', 'algorithm', 'sortorder', 'goodsla']);

		foreach ($this->getInput('tags', []) as $tag) {
			if ($tag['tag'] === '' && $tag['value'] === '') {
				continue;
			}

			$service['tags'][] = $tag;
		}

		if ($service['algorithm'] != ZBX_SERVICE_STATUS_CALC_SET_OK) {
			foreach ($this->getInput('problem_tags', []) as $problem_tag) {
				if ($problem_tag['tag'] === '' && $problem_tag['value'] === '') {
					continue;
				}

				$service['problem_tags'][] = $problem_tag;
			}
		}

		foreach ($this->getInput('parent_serviceids', []) as $serviceid) {
			$service['parents'][] = ['serviceid' => $serviceid];
		}

		foreach ($this->getInput('child_serviceids', []) as $serviceid) {
			$service['children'][] = ['serviceid' => $serviceid];
		}

		if ($this->hasInput('advanced_configuration')) {
			$this->getInputs($service, ['status_rules', 'propagation_rule']);

			switch ($this->getInput('propagation_rule', DB::getDefault('services', 'propagation_rule'))) {
				case ZBX_SERVICE_STATUS_INCREASE:
				case ZBX_SERVICE_STATUS_DECREASE:
					$service['propagation_value'] = $this->getInput('propagation_value_number', 0);
					break;

				case ZBX_SERVICE_STATUS_FIXED:
					$service['propagation_value'] = $this->getInput('propagation_value_status', 0);
					break;

				default:
					$service['propagation_value'] = 0;
					break;
			}

			$service['weight'] = $this->getInput('weight', '') !== '' ? $this->getInput('weight') : 0;
		}
		else {
			$service['propagation_rule'] = DB::getDefault('services', 'propagation_rule');
			$service['propagation_value'] = DB::getDefault('services', 'propagation_value');
			$service['weight'] = DB::getDefault('services', 'weight');
		}

		$result = API::Service()->update($service);

		if ($result) {
			$output = ['title' => _('Service updated')];

			if ($messages = CMessageHelper::getMessages()) {
				$output['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output = ['errors' => makeMessageBox(false, filter_messages(), CMessageHelper::getTitle())->toString()];
		}

		$this->setResponse((new CControllerResponseData(['main_block' => json_encode($output)]))->disableView());
	}
}
