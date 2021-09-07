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


const MESSAGE_TYPE_SUCCESS = 'success';

const host_popup = {
	ZBX_STYLE_ZABBIX_HOST_POPUPEDIT: 'js-edit-host',
	ZBX_STYLE_ZABBIX_HOST_POPUPCREATE: 'js-create-host',

	/**
	 * General entry point to be called on pages that need host popup functionality.
	 */
	init() {
		this.initActionButtons();

		this.original_url = location.href;
	},

	/**
	 * Sets up listeners for elements marked to start host edit/create popup.
	 */
	initActionButtons() {
		document.querySelectorAll('.'+this.ZBX_STYLE_ZABBIX_HOST_POPUPCREATE).forEach((element) => {
			element.addEventListener('click', (e) => {
				const host_data = (typeof e.target.dataset.hostgroups !== 'undefined')
					? {groupids: JSON.parse(e.target.dataset.hostgroups)}
					: {};

				this.edit(host_data);

				const url = new Curl('zabbix.php', false);
				url.setArgument('action', 'host.edit');
				host_data.groupids.forEach((g) => url.setArgument('groupids['+g+']', g));
				history.pushState({}, '', url.getUrl());
			});
		});

		document.querySelectorAll('.'+this.ZBX_STYLE_ZABBIX_HOST_POPUPEDIT).forEach((element) => {
			element.addEventListener('click', (e) => {
				const url = new Curl(e.target.href, false);
				this.edit({hostid: url.getArgument('hostid')});
				history.pushState({}, '', url.getUrl());
				e.preventDefault();
			});
		});
	},

	/**
	 * Sets up and opens host edit popup.
	 *
	 * @param {object} host_data              Host data used to initialize host form.
	 * @param {string} host_data['hostid']    ID of host to edit.
	 * @param {array}  host_data['groupids']  Host groups to pre-fill when creating new host.
	 */
	edit(host_data = {}) {
		const overlay = PopUp('popup.host.edit', host_data, 'host_edit', document.activeElement);

		overlay.$dialogue.addClass('sticked-to-top');

		overlay.xhr
			.then(function () {
				$('#host-tabs', overlay.$dialogue).on('tabsactivate change', () => overlay.centerDialog());

				overlay.$dialogue.find('#host-clone').on('click', host_popup.cloneBtnClickHandler('clone', overlay));
				overlay.$dialogue.find('#host-full_clone').on('click', host_popup.cloneBtnClickHandler('full_clone', overlay));

				overlay.$dialogue.find('form').on('formSubmitted', (e) =>
					handle_hostaction_response(e.detail, e.target)
				);
			})
			.catch(() => {
				overlay.setProperties({
					content: makeMessageBox('bad', [t('S_UNEXPECTED_SERVER_ERROR')], null, false, false)
				});
			});

		overlay.$dialogue[0].addEventListener('overlay.close', () => {
			history.replaceState({}, '', this.original_url);
		}, {once: true});
	},

	/**
	* Supplies a handler for in-popup clone button click with according action.
	*
	* @param {string}   operation_type Either 'clone' or 'full_clone'.
	* @param {Overlay}  Overlay object.
	*
	* @return {callable}             Click handler.
	*/
	cloneBtnClickHandler(operation_type, overlay) {
		return function() {
			const url = new Curl(null, false);
			url.setArgument(operation_type, 1);

			let params = {...host_edit.getCloneData(overlay.$dialogue.find('form')[0])};
			params[operation_type] = 1;

			overlay = PopUp('popup.host.edit', params, 'host_edit');
			overlay.xhr.then(function () {
				overlay.$dialogue.find('form').on('formSubmitted', (e) => {
					handle_hostaction_response(e.detail, e.target)
				});
			});

			history.replaceState({}, '', url.getUrl());
		};
	}
};

/**
 * Handles host deletion from list, popup or fullscreen form.
 *
 * @param {HTMLFormElement} host_form           Host form if called from popup/fullscreen.
 * Null assumes delete from list.
 * @param {HTMLInputElement} host_form{#hostid} Input expected to be present when passing form.
 *
 * @return {bool} Always false, to prevent button submit.
 */
function hosts_delete(host_form = null) {
	const curl = new Curl('zabbix.php');
	let ids = [],
		parent = null;

	if (host_form !== null) {
		ids = [host_form.querySelector('#hostid').value];
	}
	else {
		parent = document.querySelector('[name="hosts"]');
		ids = getFormFields(parent).ids;
	}

	curl.setArgument('action', 'host.massdelete');
	curl.setArgument('hostids', ids);

	fetch(curl.getUrl(), {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
		body: urlEncodeData(curl.getUrl())
	})
		.then((response) => handle_hostaction_response(response, host_form));

	return false;
}

/**
 * Show error/success messages from host actions, refreshes originator pages/lists on success.
 *
 * @param {Promise} response Fetch promise.
 * @param {string|undefined} response{error} More "deep"/automated errors, from e.g. permissions, maintenance checks.
 * @param {string|undefined} response{errors} Controller-level failures, validation errors.
 * @param {string|undefined} response{script_inline} Additional JavaScript to inject into page.
 * @param {HTMLFormElement|JQueryElement|null} host_form Host form, if called from within, null for mass actions.
 */
async function handle_hostaction_response(response, host_form = null) {
	try {
		response = await response.text();
		response = JSON.parse(response);
	}
	catch (error) {
		response = {
			errors: makeMessageBox('bad', [t('S_UNEXPECTED_SERVER_ERROR')], null, true, false)[0]
		};
	}

	const overlay = overlays_stack.end();

	if ('script_inline' in response) {
		jQuery('head').append(response.script_inline);
	}

	overlay && overlay.unsetLoading();

	if ('errors' in response) {
		if (typeof overlay !== 'undefined') {
			overlay.$dialogue.find('.msg-bad, .msg-good').remove();
			jQuery(response.errors).insertBefore(host_form);
		}
		else {
			clearMessages();
			addMessage(response.errors);
		}
	}
	else {
		if (typeof overlay !== 'undefined') {
			// Original url/state restored after dialog close.
			overlayDialogueDestroy(overlay.dialogueid);
		}
		else {
			if (typeof host_popup.original_url === 'undefined') {
				let curl = new Curl('zabbix.php', false);

				curl.setArgument('action', 'host.list');
				host_popup.original_url = curl.getUrl();
			}

			history.replaceState({}, '', host_popup.original_url);
		}

		chkbxRange.checkObjectAll(chkbxRange.pageGoName, false);
		chkbxRange.clearSelectedOnFilterChange();

		const filter_btn = document.querySelector('[name=filter_apply]');

		if (filter_btn !== null) {
			clearMessages();
			addMessage(makeMessageBox('good', response.messages, response.title, true, false));

			filter_btn.click();
		}
		else {
			postMessageOk(response.title);
			if ('messages' in response) {
				postMessageDetails('success', response.messages);
			}

			location.replace(host_popup.original_url);
		}
	}
}
