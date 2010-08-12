<?php
/**
 * Copyright (C) 2005, 2006, 2007, 2008  Brice Burgess <bhb@iceburg.net>
 * 
 * This file is part of poMMo (http://www.pommo.org)
 * 
 * poMMo is free software; you can redistribute it and/or modify 
 * it under the terms of the GNU General Public License as published 
 * by the Free Software Foundation; either version 2, or any later version.
 * 
 * poMMo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See
 * the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with program; see the file docs/LICENSE. If not, write to the
 * Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA.
 */

/**********************************
	INITIALIZATION METHODS
 *********************************/
require ('../bootstrap.php');
require_once(Pommo::$_baseDir.'classes/Pommo_Mailing.php');
require_once(Pommo::$_baseDir.'classes/Pommo_Groups.php');

Pommo::init();
$logger = & Pommo::$_logger;
$dbo = & Pommo::$_dbo;

/**********************************
	SETUP TEMPLATE, PAGE
 *********************************/
require_once(Pommo::$_baseDir.'classes/Pommo_Template.php');
$smarty = new Pommo_Template();
$smarty->prepareForForm();

if (Pommo_Mailing::isCurrent())
	Pommo::kill(sprintf(Pommo::_T('A Mailing is currently processing. Visit the %sStatus%s page to check its progress.'),'<a href="mailing_status.php">','</a>'));

$dbvalues = Pommo_Api::configGet(array(
	'list_fromname',
	'list_fromemail',
	'list_frombounce',
	'list_charset',
	'list_wysiwyg'
));

// Initialize page state with default values overriden by those held in $_REQUEST
$state =& Pommo_Api::stateInit('mailing',array(
	'fromname' => $dbvalues['list_fromname'],
	'fromemail' => $dbvalues['list_fromemail'],
	'frombounce' => $dbvalues['list_frombounce'],
	'list_charset' => $dbvalues['list_charset'],
	'wysiwyg' => $dbvalues['list_wysiwyg'],
	'mailgroup' => 'all',
	'subject' => '',
	'body' => '',
	'altbody' => ''
),
$_POST);

// SmartyValidate Custom Validation Function
function check_charset($value, $empty, & $params, & $formvars) {
	$validCharsets = array (
		'UTF-8',
		'ISO-8859-1',
		'ISO-8859-2',
		'ISO-8859-7',
		'ISO-8859-15',
		'cp1251',
		'KOI8-R',
		'GB2312',
		'EUC-JP',
		'ISO-2022-JP'
	);
	return in_array($value, $validCharsets);
}

if (!SmartyValidate :: is_registered_form() || empty ($_POST)) {
	// ___ USER HAS NOT SENT FORM ___

	SmartyValidate :: connect($smarty, true);

	// register custom criteria
	SmartyValidate :: register_criteria('isCharSet', 'check_charset');

	SmartyValidate :: register_validator('fromname', 'fromname', 'notEmpty', false, false, 'trim');
	SmartyValidate :: register_validator('subject', 'subject', 'notEmpty', false, false, 'trim');
	SmartyValidate :: register_validator('fromemail', 'fromemail', 'isEmail', false, false, 'trim');
	SmartyValidate :: register_validator('frombounce', 'frombounce', 'isEmail', false, false, 'trim');
	SmartyValidate :: register_validator('mailgroup', 'mailgroup:/(all|\d+)/i', 'isRegExp', false, false, 'trim');

	SmartyValidate :: register_validator('list_charset', 'list_charset', 'isCharSet', false, false, 'trim');
	
	$vMsg = array ();
	$vMsg['fromname'] = $vMsg['subject'] = Pommo::_T('Cannot be empty.');
	$vMsg['charset'] = Pommo::_T('Invalid Character Set');
	$vMsg['fromemail'] = $vMsg['frombounce'] = Pommo::_T('Invalid email address');
	$vMsg['ishtml'] = $vMsg['mailgroup'] = Pommo::_T('Invalid Input');
	$smarty->assign('vMsg', $vMsg);
	
} else {
	// ___ USER HAS SENT FORM ___

	/**********************************
		JSON OUTPUT INITIALIZATION
	 *********************************/
	require_once(Pommo::$_baseDir.'classes/Pommo_Json.php');
	$json = new Pommo_Json();

	SmartyValidate :: connect($smarty);

	if (SmartyValidate :: is_valid($_POST)) {
		// __ FORM IS VALID

		SmartyValidate :: disconnect();
		$json->success();

	} else {
		// __ FORM NOT VALID
		
		$json->add('fieldErrors',$smarty->getInvalidFields());
		$json->fail(Pommo::_T('Please review and correct errors with your submission.'));
	}
}

$mailgroups = explode( ',', $state['mailgroup'] );
$smarty->assign( 'mailgroups', $mailgroups );
$smarty->assign('groups', Pommo_Groups::get());
$smarty->assign($state);
$smarty->display('admin/mailings/mailing/setup.tpl');

