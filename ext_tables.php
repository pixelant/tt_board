<?php

if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

$emClass = '\\TYPO3\\CMS\\Core\\Utility\\ExtensionManagementUtility';

if (
	class_exists($emClass) &&
	method_exists($emClass, 'extPath')
) {
	// nothing
} else {
	$emClass = 't3lib_extMgm';
}

call_user_func($emClass . '::addStaticFile', $_EXTKEY, 'Configuration/TypoScript/DefaultCSS/', 'default CSS-styles');
call_user_func($emClass . '::addStaticFile', $_EXTKEY, 'Configuration/TypoScript/Default/', 'Board Setup');

$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['4'] = 'layout,select_key';
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['4'] = 'pi_flexform';
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['2'] = 'layout,select_key';
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['2'] = 'pi_flexform';

call_user_func($emClass . '::addPiFlexFormValue', '4', 'FILE:EXT:' . $_EXTKEY . '/flexform_ds_pi_list.xml');
call_user_func($emClass . '::addPiFlexFormValue', '2', 'FILE:EXT:' . $_EXTKEY . '/flexform_ds_pi_tree.xml');

call_user_func($emClass . '::addPlugin', array('LLL:EXT:' . $_EXTKEY . '/locallang_tca.php:pi_list', '4'), 'list_type');
call_user_func($emClass . '::addPlugin', array('LLL:EXT:' . $_EXTKEY . '/locallang_tca.php:pi_tree', '2'), 'list_type');

call_user_func($emClass . '::allowTableOnStandardPages', 'tt_board');
call_user_func($emClass . '::addToInsertRecords', 'tt_board');

call_user_func($emClass . '::addLLrefForTCAdescr', 'tt_board', 'EXT:' . $_EXTKEY . '/locallang_csh_ttboard.php');

if (TYPO3_MODE == 'BE') {
	$GLOBALS['TBE_MODULES_EXT']['xMOD_db_new_content_el']['addElClasses']['tx_ttboard_wizicon'] = PATH_BE_TTBOARD . 'class.tx_ttboard_wizicon.php';
}

