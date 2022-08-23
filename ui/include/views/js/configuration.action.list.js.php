<?php
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


/**
 * @var CView $this
 */

?>

<script>
	const view = new class {
		init({eventsource}) {
			this.eventsource = eventsource;
			this._initActions();
		}

		_initActions() {
			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-action-create')) {
					this._edit({eventsource: this.eventsource})
				}
				else if (e.target.classList.contains('js-action-edit')) {
					this._edit({eventsource: this.eventsource, actionid: e.target.attributes.actionid.nodeValue})
				}
				else if (e.target.classList.contains('js-massdelete-action')) {
					this._massDeleteActions(e.target)
				}
			})
		}

		_edit(parameters = {}) {
			const overlay = this._openActionPopup(parameters);

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				postMessageOk(e.detail.title);

				if ('messages' in e.detail) {
					postMessageDetails('success', e.detail.messages);
				}
				location.href = location.href;
			});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.actionDelete, {once: true});
		}

		actionDelete(e) {
			const data = e.detail;

			if ('success' in data) {
				postMessageOk(data.success.title);

				if ('messages' in data.success) {
					postMessageDetails('success', data.success.messages);
				}
			}

			uncheckTableRows('actionForm');
			location.href = location.href;
		}

		_openActionPopup(parameters = {}) {
			return PopUp('popup.action.edit', parameters, {
				dialogueid: 'action-edit',
				dialogue_class: 'modal-popup-large'
			});
		}

		_massDeleteActions(target) {
			const confirm_text =<?= json_encode(_('Delete selected actions?')) ?>;
			if (!confirm(confirm_text)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'action.delete');
			curl.setArgument('eventsource', this.eventsource);

			target.classList.add('is-loading');

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: urlEncodeData({g_actionid: Object.keys(chkbxRange.getSelectedIds())})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

					}

					location.href = location.href;
				})
				.catch(() => {
					clearMessages();

					const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]);

					addMessage(message_box);
				})
				.finally(() => {
					uncheckTableRows('g_actionid');
				});
		}
	};
</script>
