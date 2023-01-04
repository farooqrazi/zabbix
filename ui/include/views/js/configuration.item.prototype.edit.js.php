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

include dirname(__FILE__).'/common.item.edit.js.php';
include dirname(__FILE__).'/item.preprocessing.js.php';
include dirname(__FILE__).'/editabletable.js.php';
include dirname(__FILE__).'/itemtest.js.php';
?>
<script>
	const view = {
		form_name: null,
		csrf_tokens: null,

		init({form_name, trends_default, csrf_tokens}) {
			this.form_name = form_name;
			this.csrf_tokens = csrf_tokens;

			// Field switchers.
			new CViewSwitcher('value_type', 'change', item_form.field_switches.for_value_type);

			$('#type')
				.change(this.typeChangeHandler)
				.trigger('change');

			// Whenever non-numeric type is changed back to numeric type, set the default value in "trends" field.
			$('#value_type')
				.change(function() {
					const new_value = $(this).val();
					const old_value = $(this).data('old-value');
					const trends = $('#trends');

					if ((old_value == <?= ITEM_VALUE_TYPE_STR ?> || old_value == <?= ITEM_VALUE_TYPE_LOG ?>
							|| old_value == <?= ITEM_VALUE_TYPE_TEXT ?>)
							&& (new_value == <?= ITEM_VALUE_TYPE_FLOAT ?>
							|| new_value == <?= ITEM_VALUE_TYPE_UINT64 ?>)) {
						if (trends.val() == 0) {
							trends.val(trends_default);
						}

						$('#trends_mode_1').prop('checked', true);
					}

					$('#trends_mode').trigger('change');
					$(this).data('old-value', new_value);
				})
				.data('old-value', $('#value_type').val());

			$('#history_mode')
				.change(function() {
					if ($('[name="history_mode"][value=' + <?= ITEM_STORAGE_OFF ?> + ']').is(':checked')) {
						$('#history').prop('disabled', true).hide();
					}
					else {
						$('#history').prop('disabled', false).show();
					}
				})
				.trigger('change');

			$('#trends_mode')
				.change(function() {
					if ($('[name="trends_mode"][value=' + <?= ITEM_STORAGE_OFF ?> + ']').is(':checked')) {
						$('#trends').prop('disabled', true).hide();
					}
					else {
						$('#trends').prop('disabled', false).show();
					}
				})
				.trigger('change');

			let csrf_token = document.itemForm.querySelector('input[name=<?= CController::CSRF_TOKEN_NAME ?>]');

			document.addEventListener('click', (e) => {
				if (e.target.id === 'clone') {
					csrf_token.value = this.csrf_tokens['disc_prototypes.php clone'];
				}
				else if (e.target.id === 'delete') {
					if (!window.confirm('<?= _('Delete item prototype?') ?>')) {
						e.preventDefault();
						return;
					}
					csrf_token.value = this.csrf_tokens['disc_prototypes.php delete'];
				}
			})
		},

		typeChangeHandler() {
			// Selected item type.
			const type = parseInt(jQuery('#type').val(), 10);
			const has_key_button = [ <?= ITEM_TYPE_ZABBIX ?>, <?= ITEM_TYPE_ZABBIX_ACTIVE ?>, <?= ITEM_TYPE_SIMPLE ?>,
				<?= ITEM_TYPE_INTERNAL ?>, <?= ITEM_TYPE_DB_MONITOR ?>, <?= ITEM_TYPE_SNMPTRAP ?>, <?= ITEM_TYPE_JMX ?>,
				<?= ITEM_TYPE_IPMI ?>
			];

			jQuery('#keyButton').prop('disabled', !has_key_button.includes(type));

			if (type == <?= ITEM_TYPE_SSH ?> || type == <?= ITEM_TYPE_TELNET ?>) {
				jQuery('label[for=username]').addClass('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>');
				jQuery('input[name=username]').attr('aria-required', 'true');
			}
			else {
				jQuery('label[for=username]').removeClass('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>');
				jQuery('input[name=username]').removeAttr('aria-required');
			}
		},

		editHost(e, hostid) {
			e.preventDefault();
			const host_data = {hostid};

			this.openHostPopup(host_data);
		},

		openHostPopup(host_data) {
			const original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.create', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.update', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.hostDelete, {once: true});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		},

		refresh() {
			const url = new Curl('');
			const form = document.getElementsByName(this.form_name)[0];
			const fields = getFormFields(form);

			post(url.getUrl(), fields);
		},

		events: {
			hostSuccess(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				view.refresh();
			},

			hostDelete(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				const curl = new Curl('zabbix.php');
				curl.setArgument('action', 'host.list');

				location.href = curl.getUrl();
			}
		}
	};
</script>
