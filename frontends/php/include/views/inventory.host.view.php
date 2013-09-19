<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


$hostInventoryWidget = new CWidget();

$hostInventoryWidget->addPageHeader(_('HOST INVENTORY'), SPACE);

$hostInventoriesForm = new CForm();

/*
 * Overview tab
 */
$overviewFormList = new CFormList(null, 'host-inventories-overview');

$hostSpan = new CSpan($this->data['host']['host'], 'link_menu menu-host');

$hostSpan->setMenuPopup(getMenuPopupHost(
	$this->data['host'],
	$this->data['hostScripts'][$this->data['host']['hostid']],
	false
));

$hostName = $this->data['host']['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON
	? array($hostSpan, SPACE, new CDiv(null, 'icon-maintenance-inline'))
	: $hostSpan;

$overviewFormList->addRow(_('Host name'), $hostName);

if ($this->data['host']['host'] != $this->data['host']['name']) {
	$overviewFormList->addRow(_('Visible name'), new CSpan($this->data['host']['name'], 'text-field'));
}

$snmpInterfaceRow = array();
$ipmiInterfaceRow = array();
$jmxInterfaceRow = array();

foreach ($this->data['host']['interfaces'] as $interface) {
	$spanClass = $interface['main'] ? ' default_interface' : null;

	switch ($interface['type']) {
		case INTERFACE_TYPE_AGENT:
			$agentInterfaceRow[] = new CRow(array(
				new CDiv($interface['ip'], 'ip'.$spanClass),
				new CDiv($interface['dns'], 'dns'.$spanClass),
				new CDiv($interface['useip'] == 1 ? _('IP') : _('DNS'), 'useip'.$spanClass),
				new CDiv($interface['port'], 'port'.$spanClass),
			));
			break;

		case INTERFACE_TYPE_SNMP:
			$snmpInterfaceRow[] = new CRow(array(
				new CDiv($interface['ip'], 'ip'.$spanClass),
				new CDiv($interface['dns'], 'dns'.$spanClass),
				new CDiv($interface['useip'] == 1 ? _('IP') : _('DNS'), 'useip'.$spanClass),
				new CDiv($interface['port'], 'port'.$spanClass),
			));
			break;

		case INTERFACE_TYPE_IPMI:
			$ipmiInterfaceRow[] = new CRow(array(
				new CDiv($interface['ip'], 'ip'.$spanClass),
				new CDiv($interface['dns'], 'dns'.$spanClass),
				new CDiv($interface['useip'] == 1 ? _('IP') : _('DNS'), 'useip'.$spanClass),
				new CDiv($interface['port'], 'port'.$spanClass),
			));
			break;

		case INTERFACE_TYPE_JMX:
			$jmxInterfaceRow[] = new CRow(array(
				new CDiv($interface['ip'], 'ip'.$spanClass),
				new CDiv($interface['dns'], 'dns'.$spanClass),
				new CDiv($interface['useip'] == 1 ? _('IP') : _('DNS'), 'useip'.$spanClass),
				new CDiv($interface['port'], 'port'.$spanClass),
			));
			break;
	}
}

$agentInterfacesTable = new CTable(null, 'formElementTable border_dotted objectgroup element-row-first interfaces');
$agentInterfacesTable->setHeader(array(_('IP address'), _('DNS name'), _('Connect to'), _('Port')));

// Agent interface
foreach ($agentInterfaceRow as $interface) {
	$agentInterfacesTable->addRow($interface);
}

$overviewFormList->addRow(
	_('Agent interfaces'),
	new CDiv($agentInterfacesTable)
);

// SNMP interface
if ($snmpInterfaceRow) {
	$snmpInterfacesTable = new CTable(null, 'formElementTable border_dotted objectgroup element-row interfaces');

	foreach ($snmpInterfaceRow as $interface) {
		$snmpInterfacesTable->addRow($interface);
	}

	$overviewFormList->addRow(
		_('SNMP interfaces'),
		new CDiv($snmpInterfacesTable)
	);
}

// IPMI interface
if ($ipmiInterfaceRow) {
	$ipmiInterfacesTable = new CTable(null, 'formElementTable border_dotted objectgroup element-row interfaces');

	foreach ($ipmiInterfaceRow as $interface) {
		$ipmiInterfacesTable->addRow($interface);
	}

	$overviewFormList->addRow(
		_('IPMI interfaces'),
		new CDiv($ipmiInterfacesTable)
	);
}

// JMX interface
if ($jmxInterfaceRow) {
	$jmxInterfacesTable = new CTable(null, 'formElementTable border_dotted objectgroup element-row interfaces');

	foreach ($jmxInterfaceRow as $interface) {
		$jmxInterfacesTable->addRow($interface);
	}

	$overviewFormList->addRow(
		_('JMX interfaces'),
		new CDiv($jmxInterfacesTable)
	);
}

// inventory (OS, Hardware, Software)
unset($this->data['host']['inventory']['hostid'], $this->data['host']['inventory']['inventory_mode']);
if ($this->data['host']['inventory']) {
	foreach ($this->data['host']['inventory'] as $key => $value) {
		if (($this->data['tableTitles'][$key]['db_field'] == 'os' || $this->data['tableTitles'][$key]['db_field'] == 'hardware'
				|| $this->data['tableTitles'][$key]['db_field'] == 'software') && !zbx_empty($value)) {
			$overviewFormList->addRow($this->data['tableTitles'][$key]['title'], new CSpan($value, 'text-field'));
		}
	}
}

// latest data
$latestArray = array(
	new CLink(_('Web'), 'httpmon.php?hostid='.$this->data['host']['hostid'].url_param('groupid')),
	new CSpan (
		new CLink(_('Latest data'), 'latest.php?form=1&select=&show_details=1&filter_set=Filter&hostid='.
		$this->data['host']['hostid'].url_param('groupid')),
		'overview-link'),
	new CSpan (
		new CLink(_('Triggers status'),
		'tr_status.php?show_triggers=2&ack_status=1&show_events=1&show_events=0&show_details=1'.
		'&txt_select=&hostid='.$this->data['host']['hostid'].url_param('groupid')),
		'overview-link'),
	new CSpan (
		new CLink(_('Events'), 'events.php?hostid='.$this->data['host']['hostid'].url_param('groupid')),
		'overview-link'),
	new CSpan (
		new CLink(_('Graphs'), 'charts.php?hostid='.$this->data['host']['hostid'].url_param('groupid')),
		'overview-link'),
	new CSpan (
		new CLink(_('Screens'), 'host_screen.php?hostid='.$this->data['host']['hostid'].url_param('groupid')),
		'overview-link')
);

$overviewFormList->addRow(_('Latest data'), $latestArray);

// configuration
if ($this->data['rwHost']) {
	$hostLink = new CLink(_('Host'),
		'hosts.php?form=update&hostid='.$this->data['host']['hostid'].url_param('groupid'));
	$applicationsLink = new CLink(_('Applications'),
		'applications.php?hostid='.$this->data['host']['hostid'].url_param('groupid'));
	$itemsLink = new CLink(_('Items'), 'items.php?hostid='.$this->data['host']['hostid'].url_param('groupid'));
	$triggersLink = new CLink(_('Triggers'), 'triggers.php?hostid='.$this->data['host']['hostid'].url_param('groupid'));
	$graphsLink = new CLink(_('Graphs'), 'graphs.php?hostid='.$this->data['host']['hostid'].url_param('groupid'));
	$discoveryLink = new CLink(_('Discovery'),
		'host_discovery.php?hostid='.$this->data['host']['hostid'].url_param('groupid'));
	$webLink = new CLink(_('Web'), 'httpconf.php?hostid='.$this->data['host']['hostid'].url_param('groupid'));
}
else {
	$hostLink = _('Host');
	$applicationsLink = _('Application');
	$itemsLink = _('Items');
	$triggersLink = _('Triggers');
	$graphsLink = _('Graphs');
	$discoveryLink = _('Discovery');
	$webLink = _('Web');
}

$configurationArray = array(
	$hostLink,
	new CSpan (array($applicationsLink, SPACE, '('.$this->data['host']['applications'].')'), 'overview-link'),
	new CSpan (array($itemsLink, SPACE, '('.$this->data['host']['items'].')'), 'overview-link'),
	new CSpan (array($triggersLink, SPACE, '('.$this->data['host']['triggers'].')'), 'overview-link'),
	new CSpan (array($graphsLink, SPACE, '('.$this->data['host']['graphs'].')'), 'overview-link'),
	new CSpan (array($discoveryLink, SPACE, '('.$this->data['host']['discoveries'].')'), 'overview-link'),
	new CSpan (array($webLink, SPACE, '('.$this->data['host']['httpTests'].')'), 'overview-link')
);

$overviewFormList->addRow(_('Configuration'), $configurationArray);

$hostInventoriesTab = new CTabView(array('remember' => true));
$hostInventoriesTab->setSelected(0);

$hostInventoriesTab->addTab('overviewTab', _('Overview'), $overviewFormList);

/*
 * Details tab
 */
$detailsFormList = new CFormList('hostinventoriesDetailsFormList');

$inventoryValues = false;
if ($this->data['host']['inventory']) {
	foreach ($this->data['host']['inventory'] as $key => $value) {
		if (!zbx_empty($value)) {
			$detailsFormList->addRow($this->data['tableTitles'][$key]['title'], new CSpan($value, 'text-field'));
			$inventoryValues = true;
		}
	}
}

if (!$inventoryValues) {
	$hostInventoriesTab->setDisabled([1]);
}

$hostInventoriesTab->addTab('detailsTab', _('Details'), $detailsFormList);

// append tabs and form
$hostInventoriesForm->addItem($hostInventoriesTab);
$hostInventoriesForm->addItem(makeFormFooter(
	null,
	new CButtonCancel(url_param('groupid'))
));
$hostInventoryWidget->addItem($hostInventoriesForm);

return $hostInventoryWidget;
