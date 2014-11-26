<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


$discoveryWidget = new CWidget();

// create new discovery rule button
$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addItem(new CSubmit('form', _('Create discovery rule')));
$discoveryWidget->addPageHeader(_('CONFIGURATION OF DISCOVERY RULES'), $createForm);
$discoveryWidget->addHeader(_('Discovery rules'));
$discoveryWidget->addHeaderRowNumber();

// create form
$discoveryForm = new CForm();
$discoveryForm->setName('druleForm');

// create table
$discoveryTable = new CTableInfo(_('No discovery rules found.'));
$discoveryTable->setHeader(array(
	new CCheckBox('all_drules', null, "checkAll('".$discoveryForm->getName()."', 'all_drules', 'g_druleid');"),
	make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
	_('IP range'),
	_('Delay'),
	_('Checks'),
	_('Status')
));
foreach ($data['drules'] as $drule) {
	array_push($drule['description'], new CLink($drule['name'], '?form=update&druleid='.$drule['druleid']));

	$status = new CCol(new CLink(
		discovery_status2str($drule['status']),
		'?g_druleid[]='.$drule['druleid'].'&action='.($drule['status'] == DRULE_STATUS_ACTIVE ? 'drule.massdisable' : 'drule.massenable'),
		discovery_status2style($drule['status'])
	));

	$discoveryTable->addRow(array(
		new CCheckBox('g_druleid['.$drule['druleid'].']', null, null, $drule['druleid']),
		$drule['description'],
		$drule['iprange'],
		$drule['delay'],
		!empty($drule['checks']) ? implode(', ', $drule['checks']) : '',
		$status
	));
}

// append table to form
$discoveryForm->addItem(array(
	$this->data['paging'],
	$discoveryTable,
	$this->data['paging'],
	get_table_header(new CActionGoButtonGroup(
		'g_druleid',
		array(
			'drule.massenable' => array(_('Enable'), _('Enable selected discovery rules?')),
			'drule.massdisable' => array(_('Disable'), _('Disable selected discovery rules?')),
			'drule.massdelete' => array(_('Delete'), _('Delete selected discovery rules?'))
		)
	))
));

// append form to widget
$discoveryWidget->addItem($discoveryForm);

return $discoveryWidget;
