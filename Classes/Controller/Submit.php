<?php

namespace JambageCom\TtBoard\Controller;

/***************************************************************
*  Copyright notice
*
*  (c) 2017 Kasper Skårhøj <kasperYYYY@typo3.com>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * See TSref document: boardLib.inc / FEDATA section for details on how to use this script.
 * The static template 'plugin.tt_board' provides a working example of configuration.
 *
 * @author	Kasper Skårhøj <kasperYYYY@typo3.com>
 * @author	Franz Holzinger <franz@ttproducts.de>
 */


use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Messaging\ErrorpageMessage;

use JambageCom\TslibFetce\Controller\TypoScriptFrontendDataController;
use JambageCom\Div2007\Utility\MailUtility;

class Submit implements \TYPO3\CMS\Core\SingletonInterface {

    static public function execute (TypoScriptFrontendDataController $pObj, $conf) {

        $row = $pObj->newData[TT_BOARD_EXT]['NEW'];

        $prefixId = $row['prefixid'];
        unset($row['prefixid']);
        $pid = intval($row['pid']);
        $local_cObj = \JambageCom\Div2007\Utility\FrontendUtility::getContentObjectRenderer();
        $local_cObj->setCurrentVal($pid);
        $allowCaching = $conf['allowCaching'] ? 1 : 0;

        if (is_array($row)) {
            $email = $row['email'];
        }
        $modelObj = GeneralUtility::makeInstance(\JambageCom\TtBoard\Domain\TtBoard::class);
        $modelObj->init();
        $allowed = $modelObj->isAllowed($conf['memberOfGroups']);

        if (
            $allowed &&
            (
                !$conf['emailCheck'] ||
                MailUtility::checkMXRecord($email)
            )
        ) {
            if (is_array($row) && trim($row['message'])) {
                do {
                    $internalFieldArray = array('hidden', 'parent', 'pid', 'reference', 'doublePostCheck', 'captcha');

                    if (
                        $conf['captcha'] == 'freecap' &&
                        ExtensionManagementUtility::isLoaded('sr_freecap')
                    ) {
                        require_once(ExtensionManagementUtility::extPath('sr_freecap') . 'pi2/class.tx_srfreecap_pi2.php');
                        $freeCapObj = GeneralUtility::makeInstance('tx_srfreecap_pi2');
                        if (!$freeCapObj->checkWord($row['captcha'])) {
                            $GLOBALS['TSFE']->applicationData[TT_BOARD_EXT]['error']['captcha'] = true;
                            $GLOBALS['TSFE']->applicationData[TT_BOARD_EXT]['row'] = $row;
                            $GLOBALS['TSFE']->applicationData[TT_BOARD_EXT]['word'] = $row['captcha'];
                            break;
                        }
                    }

                    $spamArray = GeneralUtility::trimExplode(',', $conf['spamWords']);
                    $bSpamFound = false;

                    foreach ($row as $field => $value) {
                        if (!in_array($field, $internalFieldArray)) {
                            foreach ($spamArray as $k => $word) {
                                if ($word && stripos($value, $word) !== false) {
                                    $bSpamFound = true;
                                    break;
                                }
                            }
                        }
                        if ($bSpamFound) {
                            break;
                        }
                        $row[$field] = $value;
                    }

                    if ($bSpamFound) {
                        $GLOBALS['TSFE']->applicationData[TT_BOARD_EXT]['error']['spam'] = true;
                        $GLOBALS['TSFE']->applicationData[TT_BOARD_EXT]['row'] = $row;
                        $GLOBALS['TSFE']->applicationData[TT_BOARD_EXT]['word'] = $word;
                        break;
                    } else {
                        $row['cr_ip'] = GeneralUtility::getIndpEnv('REMOTE_ADDR');
                        if (isset($row['captcha'])) {
                            unset($row['captcha']);
                        }

                            // Plain insert of record:
                        $pObj->execNEWinsert('tt_board', $row);
                        $newId = $GLOBALS['TYPO3_DB']->sql_insert_id();

                            // Link to this thread
                        $linkParams = array();
                        if ($GLOBALS['TSFE']->type) {
                            $linkParams['type'] = $GLOBALS['TSFE']->type;
                        }
                        $linkParams[$prefixId . '[uid]'] = $newId;
                        $url =
                            \tx_div2007_alpha5::getPageLink_fh003(
                                $local_cObj,
                                $pid,
                                '',
                                $linkParams,
                                array(
                                    'useCacheHash' => $allowCaching,
                                    'forceAbsoluteUrl' => 1
                                )
                            );

                        $pObj->clear_cacheCmd($pid);
                        $GLOBALS['TSFE']->clearPageCacheContent_pidList($pid);
                        if ($pid != $GLOBALS['TSFE']->id) {
                            $pObj->clear_cacheCmd($GLOBALS['TSFE']->id);
                            $GLOBALS['TSFE']->clearPageCacheContent_pidList(
                                $GLOBALS['TSFE']->id
                            );
                        }

                            // Clear specific cache:
                        if ($conf['clearCacheForPids']) {
                            $ccPids = GeneralUtility::intExplode(',', $conf['clearCacheForPids']);
                            foreach($ccPids as $ccPid) {
                                if ($ccPid > 0) {
                                    $pObj->clear_cacheCmd($ccPid);
                                }
                            }
                            $GLOBALS['TSFE']->clearPageCacheContent_pidList($conf['clearCacheForPids']);
                        }

                            // Send post to Mailing list ...
                        if ($conf['sendToMailingList'] && $conf['sendToMailingList.']['email']) {
                        /*
                            TypoScript for this section (was used for the TYPO3 mailing list.
                        FEData.tt_board.processScript {
                            sendToMailingList = 1
                            sendToMailingList {
                                email = typo3@netfielders.de
                                reply = submitmail@typo3.com
                                namePrefix = Typo3Forum/
                                altSubject = Post from www.typo3.com
                            }
                        }
                        */
                            $mConf = $conf['sendToMailingList.'];

                            // If there is a FE-user group defined, then send notifiers to all FE-members of this group
                            if ($mConf['sendToFEgroup']) {
                                $res =
                                    $GLOBALS['TYPO3_DB']->exec_SELECTquery(
                                        '*',
                                        'fe_users',
                                        'usergroup=' . intval($mConf['sendToFEgroup'])
                                    );
                                $c = 0;
                                while($feRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
                                    $c++;
                                    $emails .= $feRow['email'] . ',';
                                }
                                $GLOBALS['TYPO3_DB']->sql_free_result($res);
                                $maillist_recip = substr($emails, 0, -1);
                                // else, send to sendToMailingList.email
                            } else {
                                $maillist_recip = $mConf['email'];
                            }

                            $maillist_header='From: ' . $mConf['namePrefix'] . $row['author'] . ' <' . $mConf['reply'] . '>' . chr(10);
                            $maillist_header .= 'Reply-To: ' . $mConf['reply'];

                                //  Subject
                            if ($row['parent']) {	// RE:
                                $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tt_board', 'uid=' . intval($row['parent']));
                                $parentRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
                                $GLOBALS['TYPO3_DB']->sql_free_result($res);
                                $maillist_subject = 'Re: ' . $parentRow['subject'] . ' [#' . $row['parent'] . ']';
                            } else {	// New:
                                $maillist_subject =  (trim($row['subject']) ? trim($row['subject']) : $mConf['altSubject']) . ' [#' . $newId . ']';
                            }

                                // Message
                            $maillist_msg = chr(10) . chr(10) . $conf['newReply.']['subjectPrefix'] . chr(10) . $row['subject'] . chr(10) . chr(10) . $conf['newReply.']['message'] . chr(10) . $row['message'] . chr(10) . chr(10) . $conf['newReply.']['author'] . chr(10) . $row['author'] . chr(10) . chr(10) . chr(10);

                            $maillist_msg .= $conf['newReply.']['followThisLink'] . chr(10);
                            $maillist_msg .= $url;

                                // Send
                            if ($conf['debug']) {
                                debug($maillist_recip);
                                debug($maillist_subject);
                                echo nl2br($maillist_msg . chr(10));
                                debug($maillist_header);
                            } else {
                                $addresses = GeneralUtility::trimExplode(',', $maillist_recip);

                                foreach ($addresses as $email) {
                                    \tx_div2007_email::sendMail(
                                        $email,
                                        $maillist_subject,
                                        $maillist_msg,
                                        '',
                                        $mConf['reply'],
                                        $mConf['namePrefix'] . $row['author']
                                    );
                                }
                            }
                        }

                        // Notify me...
                        $notify = GeneralUtility::_POST('notify_me');

                        if (
                            $notify &&
                            $conf['notify'] &&
                            trim($row['email']) &&
                            (
                                !$conf['emailCheck'] ||
                                MailUtility::checkMXRecord($row['email'])
                            )
                        ) {
                            $markersArray = array();
                            $markersArray['###AUTHOR###'] = trim($row['author']);
                            $markersArray['###AUTHOR_EMAIL###'] = trim($row['email']);
                            $markersArray['###CR_IP###'] = $row['cr_ip'];
                            $markersArray['###HOST###'] = GeneralUtility::getIndpEnv('HTTP_HOST');
                            $markersArray['###URL###'] = $url;

                            if ($row['parent']) {		// If reply and not new thread:
                                $msg = GeneralUtility::getUrl($GLOBALS['TSFE']->tmpl->getFileName($conf['newReply.']['msg']));
                                $markersArray['###DID_WHAT###'] = $conf['newReply.']['didWhat'];
                                $markersArray['###SUBJECT_PREFIX###'] = $conf['newReply.']['subjectPrefix'];
                            } else {	// If new thread:
                                $msg = GeneralUtility::getUrl($GLOBALS['TSFE']->tmpl->getFileName($conf['newThread.']['msg']));
                                $markersArray['###DID_WHAT###'] = $conf['newThread.']['didWhat'];
                                $markersArray['###SUBJECT_PREFIX###'] = $conf['newThread.']['subjectPrefix'];
                            }
                            $markersArray['###SUBJECT###'] = strtoupper($row['subject']);
                            $markersArray['###BODY###'] = GeneralUtility::fixed_lgd_cs($row['message'], 1000);

                            foreach($markersArray as $marker => $markContent) {
                                $msg = str_replace($marker, $markContent, $msg);
                            }

                            $headers = array();
                            if ($conf['notify_from']) {
                                $headers[] = 'FROM: ' . $conf['notify_from'];
                            }

                            $msgParts = explode(chr(10), $msg, 2);
                            $emailList = GeneralUtility::rmFromList($row['email'], $notify);

                            $notifyMe =
                                GeneralUtility::uniqueList(
                                    $emailList
                                );

                            if ($conf['debug']) {
                                debug($notifyMe);
                                debug($headers);
                                debug($msgParts);
                            } else {
                                $addresses = GeneralUtility::trimExplode(',', $notifyMe);
                                $senderArray =
                                    preg_split(
                                        '/(<|>)/',
                                        $conf['notify_from'],
                                        3,
                                        PREG_SPLIT_DELIM_CAPTURE
                                    );
                                if (count($senderArray) >= 4) {
                                    $fromEmail = $senderArray[2];
                                } else {
                                    $fromEmail = $senderArray[0];
                                }
                                $fromName = $senderArray[0];
                                foreach ($addresses as $email) {
                                    \tx_div2007_email::sendMail(
                                        $email,
                                        $msgParts[0],
                                        $msgParts[1],
                                        '',
                                        $fromEmail,
                                        $fromName
                                    );
                                }
                            }
                        }
                    }
                } while (1 == 0);	// only once
            }
        } else {
            if ($allowed) {
                $message = $email . ' is not a valid email address.';
            } else {
                $message = 'You do not have the permission to post into this forum!';
            }

            $title = 'Access denied!';
            $messagePage = GeneralUtility::makeInstance(ErrorpageMessage::class, $message, $title);
            $messagePage->output();
        }

        return true;
    }
}

