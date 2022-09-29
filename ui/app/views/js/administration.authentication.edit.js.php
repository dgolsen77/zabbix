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

		constructor() {
			this.form = null;
			this.db_authentication_type = null;
			this.allow_jit = null;
		}

		init({ldap_servers, ldap_default_row_index, db_authentication_type, saml_provision_groups,
				saml_provision_media
		}) {
			this.form = document.getElementById('authentication-form');
			this.db_authentication_type = db_authentication_type;
			this.saml_provision_status = document.getElementById('saml_provision_status');
			this.saml_provision_groups_table = document.getElementById('saml-group-table');
			this.saml_media_type_mapping_table = document.getElementById('saml-media-type-mapping-table');

			this._addEventListeners();
			this._addLdapServers(ldap_servers, ldap_default_row_index);
			this._renderProvisionGroups(saml_provision_groups);
			this._renderProvisionMedia(saml_provision_media);

			this.toggleSamlJitProvisioning(this.saml_provision_status.checked);
			this._initSortable();

			if (document.getElementById('scim-token-btn') !== null) {
				document.getElementById('scim-token-btn').addEventListener('click', this.showPasswordField);
			}

			if (document.getElementById('saml_auth_enabled') !== null
					&& !document.getElementById('saml_auth_enabled').checked) {
				$(this.saml_provision_groups_table).sortable('disable');
				this.disableTable(this.saml_provision_groups_table);
				this.disableTable(this.saml_media_type_mapping_table);
			}
		}

		_addEventListeners() {
			document
				.getElementById('ldap-servers')
				.addEventListener('click', (e) => {
					if (e.target.classList.contains('js-add')) {
						this.editLdapServer();
					}
					else if (e.target.classList.contains('js-edit')) {
						this.editLdapServer(e.target.closest('tr'));
					}
					else if (e.target.classList.contains('js-remove')) {
						const table = e.target.closest('table');
						const userdirectoryid_input = e.target.closest('tr')
							.querySelector('input[name$="[userdirectoryid]"]');

						if (userdirectoryid_input !== null) {
							const input = document.createElement('input');
							input.type = 'hidden';
							input.name = 'ldap_removed_userdirectoryids[]';
							input.value = userdirectoryid_input.value;
							this.form.appendChild(input);
						}

						e.target.closest('tr').remove();

						if (table.querySelector('input[name="ldap_default_row_index"]:checked') === null) {
							const default_ldap = table.querySelector('input[name="ldap_default_row_index"]');

							if (default_ldap !== null) {
								default_ldap.checked = true;
							}
						}
					}
				});

			document.getElementById('http_auth_enabled').addEventListener('change', (e) => {
				this.form.querySelectorAll('[name^=http_]').forEach(field => {
					if (!field.isSameNode(e.target)) {
						field.disabled = !e.target.checked;
					}
				});
			});

			if (document.getElementById('saml_auth_enabled') !== null) {
				document.getElementById('saml_auth_enabled').addEventListener('change', (e) => {
					this.form.querySelectorAll('.saml-enabled').forEach(field => {
						field.disabled = !e.target.checked;
					});

					if (e.target.checked) {
						$(this.saml_provision_groups_table).sortable('enable');
						this.enableTable(this.saml_provision_groups_table);
						this.enableTable(this.saml_media_type_mapping_table);
					}
					else {
						$(this.saml_provision_groups_table).sortable('disable');
						this.disableTable(this.saml_provision_groups_table);
						this.disableTable(this.saml_media_type_mapping_table);
					}
				});
			}

			this.saml_provision_status.addEventListener('change', (e) => {
				this.toggleSamlJitProvisioning(e.target.checked);
			});

			this.saml_provision_groups_table.addEventListener('click', (e) => {
				if (e.target.classList.contains('disabled')) {
					return;
				}
				else if (e.target.classList.contains('js-add')) {
					this.editSamlProvisionGroup();
				}
				else if (e.target.classList.contains('js-edit')) {
					this.editSamlProvisionGroup(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove')) {
					e.target.closest('tr').remove()
				}
				else if (e.target.classList.contains('js-enabled')) {
					this.toggleFallbackStatus(<?= GROUP_MAPPING_FALLBACK_OFF ?>);
				}
				else if (e.target.classList.contains('js-disabled')) {
					this.toggleFallbackStatus(<?= GROUP_MAPPING_FALLBACK_ON ?>);
				}
			});

			this.saml_media_type_mapping_table
				.addEventListener('click', (e) => {
					if (e.target.classList.contains('disabled')) {
						return;
					}
					else if (e.target.classList.contains('js-add')) {
						this.editSamlProvisionMedia();
					}
					else if (e.target.classList.contains('js-edit')) {
						this.editSamlProvisionMedia(e.target.closest('tr'));
					}
					else if (e.target.classList.contains('js-remove')) {
						e.target.closest('tr').remove()
					}
				});

			this.form.addEventListener('submit', (e) => {
				if (!this._authFormSubmit()) {
					e.preventDefault();
				}
			});
		}

		enableTable(table) {
			table.querySelectorAll('a, .drag-icon').forEach(element => {
				element.classList.remove('<?= ZBX_STYLE_DISABLED ?>');
			});
			table.querySelectorAll('button').forEach(button => {
				button.disabled = false;
			});
		}

		disableTable(table) {
			table.querySelectorAll('a, .drag-icon').forEach(element => {
				element.classList.add('<?= ZBX_STYLE_DISABLED ?>');
			});
			table.querySelectorAll('button').forEach(button => {
				button.disabled = true;
			});
		}

		toggleFallbackStatus(status) {
			const row = document.querySelector('[data-row_fallback]');
			const btn = row.querySelector('.btn-link');

			if (status == <?= GROUP_MAPPING_FALLBACK_ON ?>) {
				row.querySelector('[name$="[fallback_status]"]').value = status;
				btn.classList.replace('<?= ZBX_STYLE_RED ?>', '<?= ZBX_STYLE_GREEN?>');
				btn.classList.replace('js-disabled', 'js-enabled');
				btn.innerText = '<?= _('Enabled') ?>';
			}
			else {
				row.querySelector('[name$="[fallback_status]"]').value = status;
				btn.classList.replace('<?= ZBX_STYLE_GREEN ?>', '<?= ZBX_STYLE_RED?>');
				btn.classList.replace('js-enabled', 'js-disabled');
				btn.innerText = '<?= _('Disabled') ?>';
			}
		}

		_initSortable() {
			$(this.saml_provision_groups_table).sortable({
				items: 'tbody tr.sortable',
				axis: 'y',
				containment: 'parent',
				cursor: 'grabbing',
				handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				tolerance: 'pointer',
				opacity: 0.6,
				helper: function(e, ui) {
					for (let td of ui.find('>td')) {
						let $td = $(td);
						$td.attr('width', $td.width());
					}

					// When dragging element on safari, it jumps out of the table.
					if (SF) {
						// Move back draggable element to proper position.
						ui.css('left', (ui.offset().left - 2) + 'px');
					}

					return ui;
				},
				update: (e, ui) => {
					[...this.saml_provision_groups_table.querySelectorAll('tbody tr')].forEach((row, i) => {
						row.querySelector('[name^="saml_provision_groups"][name$="[sortorder]"]').value = i + 1;
					});
				},
				stop: function(e, ui) {
					ui.item.find('>td').removeAttr('width');
					ui.item.removeAttr('style');
				},
				start: function(e, ui) {
					$(ui.placeholder).height($(ui.helper).height());
				}
			});
		}

		_authFormSubmit() {
			const fields_to_trim = ['#http_strip_domains', '#saml_idp_entityid', '#saml_sso_url', '#saml_slo_url',
				'#saml_username_attribute', '#saml_sp_entityid', '#saml_nameid_format'
			];
			document.querySelectorAll(fields_to_trim.join(', ')).forEach((elem) => {
				elem.value = elem.value.trim();
			});

			const auth_type = document.querySelector('[name=authentication_type]:checked').value;
			const warning_msg = <?= json_encode(
				_('Switching authentication method will reset all except this session! Continue?')
			) ?>;

			return (auth_type == this.db_authentication_type || confirm(warning_msg));
		}

		_addLdapServers(ldap_servers, ldap_default_row_index) {
			for (const [row_index, ldap] of Object.entries(ldap_servers)) {
				ldap.row_index = row_index;
				ldap.is_default = (ldap.row_index == ldap_default_row_index) ? 'checked' : '';

				document
					.querySelector('#ldap-servers tbody')
					.appendChild(this._prepareServerRow(ldap));
			}
		}

		_renderProvisionGroups(saml_provision_groups) {
			for (const [row_index, saml_provision_group] of Object.entries(saml_provision_groups)) {
				saml_provision_group.row_index = row_index;
				saml_provision_group.sortorder = parseInt(row_index) + 1;

				this.saml_provision_groups_table
					.querySelector('tbody')
					.appendChild(this._renderProvisionGroupRow(saml_provision_group));
			}
		}

		_renderProvisionMedia(saml_provision_media) {
			for (const [row_index, saml_media] of Object.entries(saml_provision_media)) {
				saml_media.row_index = row_index;

				this.saml_media_type_mapping_table
					.querySelector('tbody')
					.appendChild(this._renderProvisionMediaRow(saml_media));
			}
		}

		editSamlProvisionGroup(row = null) {
			let popup_params;
			let row_index = 0;
			let sortorder;
			let status = <?= GROUP_MAPPING_FALLBACK_OFF ?>;
			const fallback_row = document.querySelector('[data-row_fallback]');

			if (row !== null) {
				row_index = row.dataset.row_index;
				let is_fallback = row.querySelector(`[name="saml_provision_groups[${row_index}][is_fallback]"`).value;
				let user_groups = row.querySelectorAll(
					`[name="saml_provision_groups[${row_index}][user_groups][][usrgrpid]"`
				);

				popup_params = {
					usrgrpid: [...user_groups].map(usrgrp => usrgrp.value),
					roleid: row.querySelector(`[name="saml_provision_groups[${row_index}][roleid]"`).value,
					is_fallback: is_fallback
				};
				if (is_fallback == <?= GROUP_MAPPING_REGULAR ?>) {
					popup_params.name = row.querySelector(`[name="saml_provision_groups[${row_index}][name]"`).value;
				}
				else {
					status = row.querySelector(`[name="saml_provision_groups[${row_index}][fallback_status]"`).value;
				}
				sortorder = row.querySelector(`[name="saml_provision_groups[${row_index}][sortorder]"`).value;
			}
			else {
				while (this.saml_provision_groups_table.querySelector(`[data-row_index="${row_index}"]`) !== null) {
					row_index++;
				}

				sortorder = fallback_row.querySelector(`[name^="saml_provision_groups"][name$="[sortorder]"]`).value;

				popup_params = {
					add_group: 1,
					is_fallback: <?= GROUP_MAPPING_REGULAR ?>
				};
			}

			popup_params.idp_type = <?= IDP_TYPE_SAML ?>;

			const overlay = PopUp('popup.usergroupmapping.edit', popup_params, {dialogueid: 'user_group_edit'});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				const saml_provision_group = {...e.detail,
					...{row_index: row_index, fallback_status: status, sortorder: sortorder}
				};

				if (row === null) {
					fallback_row.parentNode.insertBefore(this._renderProvisionGroupRow(saml_provision_group),
						fallback_row
					);
					fallback_row.querySelector(`[name^="saml_provision_groups"][name$="[sortorder]"]`).value = parseInt(sortorder) + 1;
				}
				else {
					row.replaceWith(this._renderProvisionGroupRow(saml_provision_group));
				}
			});
		}

		editSamlProvisionMedia(row = null) {
			let popup_params;
			let row_index = 0;

			if (row !== null) {
				row_index = row.dataset.row_index;

				popup_params = {
					name: row.querySelector(`[name="saml_provision_media[${row_index}][name]"`).value,
					attribute: row.querySelector(`[name="saml_provision_media[${row_index}][attribute]"`).value,
					mediatypeid: row.querySelector(`[name="saml_provision_media[${row_index}][mediatypeid]"`).value
				};
			}
			else {
				while (this.saml_media_type_mapping_table.querySelector(`[data-row_index="${row_index}"]`) !== null) {
					row_index++;
				}

				popup_params = {
					add_media_type_mapping: 1
				};
			}

			const overlay = PopUp('popup.mediatypemapping.edit', popup_params, {dialogueid: 'media_type_mapping_edit'});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				const saml_media_type_mapping = {...e.detail, ...{row_index: row_index}};

				if (row === null) {
					this.saml_media_type_mapping_table
						.querySelector('tbody')
						.appendChild(this._renderProvisionMediaRow(saml_media_type_mapping));
				}
				else {
					row.replaceWith(this._renderProvisionMediaRow(saml_media_type_mapping));
				}
			});
		}

		editLdapServer(row = null) {
			let popup_params;
			let row_index = 0;

			if (row !== null) {
				row_index = row.dataset.row_index;

				const provision_group_indexes = [...row.querySelectorAll(
					`[name^="ldap_servers[${row_index}][provision_groups]"][name$="[is_fallback]"]`
				)].map((element) => {
					let start = 33 + row_index.toString().length;
					let end = element.name.length - 14;

					return element.name.substring(start, end);
				});
				const provision_groups = provision_group_indexes.map((i) => {
					let user_groups = row.querySelectorAll(
						`[name="ldap_servers[${row_index}][provision_groups][${i}][user_groups][][usrgrpid]"`
					);
					let provision_group = {
						is_fallback: row.querySelector(
							`[name="ldap_servers[${row_index}][provision_groups][${i}][is_fallback]"`
						).value,
						roleid: row.querySelector(
							`[name="ldap_servers[${row_index}][provision_groups][${i}][roleid]"`
						).value,
						sortorder: row.querySelector(
							`[name="ldap_servers[${row_index}][provision_groups][${i}][sortorder]"`
						).value,
						user_groups: [...user_groups].map(usrgrp => usrgrp.value)
					}

					if (provision_group.is_fallback == <?= GROUP_MAPPING_FALLBACK ?>) {
						provision_group.fallback_status = row.querySelector(
							`[name="ldap_servers[${row_index}][provision_groups][${i}][fallback_status]"`
						).value;
					}
					else {
						provision_group.name = row.querySelector(
							`[name="ldap_servers[${row_index}][provision_groups][${i}][name]"`
						).value;
					}

					return provision_group;
				});

				const provision_media_indexes = [...row.querySelectorAll(
					`[name^="ldap_servers[${row_index}][provision_media]"][name$="[name]"]`
				)].map((element) => {
					let start = 32 + row_index.toString().length;
					let end = element.name.length - 7;

					return element.name.substring(start, end);
				});
				const provision_media = provision_media_indexes.map((i) => {
					return {
						name: row.querySelector(
							`[name="ldap_servers[${row_index}][provision_media][${i}][name]"`
						).value,
						mediatypeid: row.querySelector(
							`[name="ldap_servers[${row_index}][provision_media][${i}][mediatypeid]"`
						).value,
						attribute: row.querySelector(
							`[name="ldap_servers[${row_index}][provision_media][${i}][attribute]"`
						).value
					};
				});

				popup_params = {
					row_index,
					add_ldap_server: 0,
					name: row.querySelector(`[name="ldap_servers[${row_index}][name]"`).value,
					host: row.querySelector(`[name="ldap_servers[${row_index}][host]"`).value,
					port: row.querySelector(`[name="ldap_servers[${row_index}][port]"`).value,
					base_dn: row.querySelector(`[name="ldap_servers[${row_index}][base_dn]"`).value,
					search_attribute: row.querySelector(`[name="ldap_servers[${row_index}][search_attribute]"`).value,
					search_filter: row.querySelector(`[name="ldap_servers[${row_index}][search_filter]"`).value,
					start_tls: row.querySelector(`[name="ldap_servers[${row_index}][start_tls]"`).value,
					bind_dn: row.querySelector(`[name="ldap_servers[${row_index}][bind_dn]"`).value,
					description: row.querySelector(`[name="ldap_servers[${row_index}][description]"`).value,
					provision_status: row.querySelector(`[name="ldap_servers[${row_index}][provision_status]"`).value,
					group_basedn: row.querySelector(`[name="ldap_servers[${row_index}][group_basedn]"`).value,
					group_name: row.querySelector(`[name="ldap_servers[${row_index}][group_name]"`).value,
					group_member: row.querySelector(`[name="ldap_servers[${row_index}][group_member]"`).value,
					group_filter: row.querySelector(`[name="ldap_servers[${row_index}][group_filter]"`).value,
					group_membership: row.querySelector(`[name="ldap_servers[${row_index}][group_membership]"`).value,
					user_username: row.querySelector(`[name="ldap_servers[${row_index}][user_username]"`).value,
					user_lastname: row.querySelector(`[name="ldap_servers[${row_index}][user_lastname]"`).value,
					provision_groups,
					provision_media
				};

				const userdirectoryid_input = row.querySelector(`[name="ldap_servers[${row_index}][userdirectoryid]"`);
				const bind_password_input = row.querySelector(`[name="ldap_servers[${row_index}][bind_password]"`);

				if (userdirectoryid_input !== null) {
					popup_params['userdirectoryid'] = userdirectoryid_input.value;
				}

				if (bind_password_input !== null) {
					popup_params['bind_password'] = bind_password_input.value;
				}
			}
			else {
				while (document.querySelector(`#ldap-servers [data-row_index="${row_index}"]`) !== null) {
					row_index++;
				}

				popup_params = {
					row_index,
					add_ldap_server: 1
				};
			}

			const overlay = PopUp('popup.ldap.edit', popup_params, {dialogueid: 'ldap_edit'});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				const ldap = {...e.detail, ...{row_index: row_index}};

				if (row === null) {
					ldap.is_default = document.getElementById('ldap-servers')
							.querySelector('input[name="ldap_default_row_index"]:checked') === null
						? 'checked'
						: '';
					ldap.usrgrps = 0;

					document
						.querySelector('#ldap-servers tbody')
						.appendChild(this._prepareServerRow(ldap));
				}
				else {
					ldap.is_default = row.querySelector('input[name="ldap_default_row_index"]').checked === true
						? 'checked'
						: '';
					ldap.usrgrps = row.querySelector('.js-ldap-usergroups').textContent;

					row.parentNode.insertBefore(this._prepareServerRow(ldap), row);
					row.remove();
				}
			});
		}

		_prepareServerRow(ldap) {
			const template_ldap_server_row = new Template(this._templateLdapServerRow());
			const template = document.createElement('template');
			template.innerHTML = template_ldap_server_row.evaluate(ldap).trim();
			const row = template.content.firstChild;

			if ('provision_groups' in ldap) {
				for (const [group_index, provision_group] of Object.entries(ldap.provision_groups)) {
					provision_group.sortorder = +group_index + 1;

					for (const [name, value] of Object.entries(provision_group)) {
						if (name === 'user_groups') {
							for (const usrgrp of value) {
								const input = document.createElement('input');
								input.name = 'ldap_servers[' + ldap.row_index + '][provision_groups][' + group_index + '][user_groups][][usrgrpid]';
								input.value = usrgrp.usrgrpid;
								input.type = 'hidden';
								row.appendChild(input);
							}
						}
						else {
							const input = document.createElement('input');
							input.name = 'ldap_servers[' + ldap.row_index + '][provision_groups][' + group_index + '][' + name + ']';
							input.value = value;
							input.type = 'hidden';
							row.appendChild(input);
						}
					}
				}
			}

			if ('provision_media' in ldap) {
				for (const [group_index, media] of ldap.provision_media.entries()) {
					for (const [name, value] of Object.entries(media)) {
						if (name === 'mediatype_name') {
							continue;
						}
						const input = document.createElement('input');
						input.name = 'ldap_servers[' + ldap.row_index + '][provision_media][' + group_index + '][' + name + ']';
						input.value = value;
						input.type = 'hidden';
						row.appendChild(input);
					}
				}
			}

			const optional_fields = ['userdirectoryid', 'bind_password', 'start_tls', 'search_filter'];

			for (const field of optional_fields) {
				if (!(field in ldap)) {
					row.querySelector('input[name="ldap_servers[' + ldap.row_index + '][' + field + ']"]').remove();
				}
			}

			if (ldap.usrgrps > 0) {
				row.querySelector('.js-remove').disabled = true;
			}

			return row;
		}

		_renderProvisionGroupRow(saml_provision_group) {
			const template = document.createElement('template');
			const template_saml_group_row = saml_provision_group.is_fallback == <?= GROUP_MAPPING_FALLBACK ?>
				? new Template(this._templateProvisionFallbackGroupRow())
				: new Template(this._templateProvisionGroupRow());

			saml_provision_group.user_group_names = Object.values(saml_provision_group.user_groups)
				.map(user_group => user_group.name).join(', ');

			if (saml_provision_group.is_fallback == <?= GROUP_MAPPING_FALLBACK ?>) {
				if (saml_provision_group.fallback_status == <?= GROUP_MAPPING_FALLBACK_ON ?>) {
					saml_provision_group.action_label = '<?= _('Enabled') ?>';
					saml_provision_group.action_class = 'js-enabled <?= ZBX_STYLE_GREEN ?>';
				}
				else {
					saml_provision_group.action_label = '<?= _('Disabled') ?>';
					saml_provision_group.action_class = 'js-disabled <?= ZBX_STYLE_RED ?>';
				}
			}

			template.innerHTML = template_saml_group_row.evaluate(saml_provision_group).trim();
			const row = template.content.firstChild;

			for (const user_group of Object.values(saml_provision_group.user_groups)) {
				const input = document.createElement('input');
				input.name = 'saml_provision_groups[' + saml_provision_group.row_index + '][user_groups][][usrgrpid]';
				input.value = user_group.usrgrpid;
				input.type = 'hidden';

				row.appendChild(input);
			}

			return row;
		}

		_renderProvisionMediaRow(saml_media) {
			const template_saml_media_mapping_row = new Template(this._templateProvisionMediaRow());
			const template = document.createElement('template');

			template.innerHTML = template_saml_media_mapping_row.evaluate(saml_media).trim();

			return template.content.firstChild;
		}

		_templateLdapServerRow() {
			return `
				<tr data-row_index="#{row_index}">
					<td>
						<a href="javascript:void(0);" class="wordwrap js-edit">#{name}</a>
						<input type="hidden" name="ldap_servers[#{row_index}][userdirectoryid]" value="#{userdirectoryid}">
						<input type="hidden" name="ldap_servers[#{row_index}][name]" value="#{name}">
						<input type="hidden" name="ldap_servers[#{row_index}][host]" value="#{host}">
						<input type="hidden" name="ldap_servers[#{row_index}][port]" value="#{port}">
						<input type="hidden" name="ldap_servers[#{row_index}][base_dn]" value="#{base_dn}">
						<input type="hidden" name="ldap_servers[#{row_index}][search_attribute]" value="#{search_attribute}">
						<input type="hidden" name="ldap_servers[#{row_index}][search_filter]" value="#{search_filter}">
						<input type="hidden" name="ldap_servers[#{row_index}][start_tls]" value="#{start_tls}">
						<input type="hidden" name="ldap_servers[#{row_index}][bind_dn]" value="#{bind_dn}">
						<input type="hidden" name="ldap_servers[#{row_index}][bind_password]" value="#{bind_password}">
						<input type="hidden" name="ldap_servers[#{row_index}][description]" value="#{description}">
						<input type="hidden" name="ldap_servers[#{row_index}][provision_status]" value="#{provision_status}">
						<input type="hidden" name="ldap_servers[#{row_index}][group_basedn]" value="#{group_basedn}">
						<input type="hidden" name="ldap_servers[#{row_index}][group_name]" value="#{group_name}">
						<input type="hidden" name="ldap_servers[#{row_index}][group_member]" value="#{group_member}">
						<input type="hidden" name="ldap_servers[#{row_index}][group_filter]" value="#{group_filter}">
						<input type="hidden" name="ldap_servers[#{row_index}][group_membership]" value="#{group_membership}">
						<input type="hidden" name="ldap_servers[#{row_index}][user_username]" value="#{user_username}">
						<input type="hidden" name="ldap_servers[#{row_index}][user_lastname]" value="#{user_lastname}">
					</td>
					<td class="wordbreak">#{host}</td>
					<td class="js-ldap-usergroups">#{usrgrps}</td>
					<td>
						<input type="radio" name="ldap_default_row_index" value="#{row_index}" #{is_default}>
					</td>
					<td>
						<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove"><?= _('Remove') ?></button>
					</td>
				</tr>
			`;
		}

		_templateProvisionFallbackGroupRow() {
			return `
				<tr data-row_index="#{row_index}" data-row_fallback>
					<td></td>
					<td>
						<a href="javascript:void(0);" class="wordwrap js-edit"><?= _('Fallback group') ?></a>
						<input type="hidden" name="saml_provision_groups[#{row_index}][roleid]" value="#{roleid}">
						<input type="hidden" name="saml_provision_groups[#{row_index}][is_fallback]" value="<?= GROUP_MAPPING_FALLBACK ?>">
						<input type="hidden" name="saml_provision_groups[#{row_index}][fallback_status]" value="#{fallback_status}">
						<input type="hidden" name="saml_provision_groups[#{row_index}][sortorder]" value="#{sortorder}">
					</td>
					<td class="wordbreak">#{user_group_names}</td>
					<td class="wordbreak">#{role_name}</td>
					<td>
						<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> #{action_class}">#{action_label}</button>
					</td>
				</tr>
			`;
		}

		_templateProvisionGroupRow() {
			return `
				<tr data-row_index="#{row_index}" class="sortable">
					<td class="td-drag-icon">
						<div class="drag-icon ui-sortable-handle"></div>
					</td>
					<td>
						<a href="javascript:void(0);" class="wordwrap js-edit">#{name}</a>
						<input type="hidden" name="saml_provision_groups[#{row_index}][name]" value="#{name}">
						<input type="hidden" name="saml_provision_groups[#{row_index}][roleid]" value="#{roleid}">
						<input type="hidden" name="saml_provision_groups[#{row_index}][is_fallback]" value="<?= GROUP_MAPPING_REGULAR ?>">
						<input type="hidden" name="saml_provision_groups[#{row_index}][sortorder]" value="#{sortorder}">
					</td>
					<td class="wordbreak">#{user_group_names}</td>
					<td class="wordbreak">#{role_name}</td>
					<td>
						<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove"><?= _('Remove') ?></button>
					</td>
				</tr>
			`;
		}

		_templateProvisionMediaRow() {
			return `
				<tr data-row_index="#{row_index}">
					<td>
						<a href="javascript:void(0);" class="wordwrap js-edit">#{name}</a>
						<input type="hidden" name="saml_provision_media[#{row_index}][name]" value="#{name}">
						<input type="hidden" name="saml_provision_media[#{row_index}][mediatypeid]" value="#{mediatypeid}">
						<input type="hidden" name="saml_provision_media[#{row_index}][attribute]" value="#{attribute}">
					</td>
					<td class="wordbreak">#{mediatype_name}</td>
					<td class="wordbreak">#{attribute}</td>
					<td>
						<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove"><?= _('Remove') ?></button>
					</td>
				</tr>
			`;
		}

		toggleSamlJitProvisioning(checked) {
			for (const element of this.form.querySelectorAll('.saml-provision-status')) {
				element.classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !checked);
			}
		}

		showPasswordField(e) {
			const form_field = e.target.parentNode;
			const password_field = form_field.querySelector('[name="auth_token"][type="password"]');
			const password_var = form_field.querySelector('[name="auth_token"][type="hidden"]');

			password_field.style.display = '';
			password_field.disabled = false;

			if (password_var !== null) {
				form_field.removeChild(password_var);
			}
			form_field.removeChild(e.target);
		}
	};
</script>
