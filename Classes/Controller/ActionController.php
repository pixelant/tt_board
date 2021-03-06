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
 * tx_ttboard_pibase
 *
 * Function library for a forum/board in tree or list style
 *
 * TypoScript config:
 * - See static_template 'plugin.tt_board_tree' and plugin.tt_board_list
 * - See TS_ref.pdf
 *
 * @author	Kasper Skårhøj  <kasperYYYY@typo3.com>
 * @author	Franz Holzinger <franz@ttproducts.de>
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;


class ActionController implements \TYPO3\CMS\Core\SingletonInterface {

    public function processCode ($theCode, &$content, $storeObject) {

        $conf = $storeObject->getConf();
        $ref = (isset($conf['ref']) ? $conf['ref'] : '');
        $linkParams = (isset($conf['linkParams.']) ? $conf['linkParams.'] : array());

        switch($theCode) {
            case 'LIST_CATEGORIES':
            case 'LIST_FORUMS':
                $content .=
                    $this->forum_list(
                        $theCode,
                        $storeObject,
                        $linkParams
                    );
            break;
            case 'POSTFORM':
            case 'POSTFORM_REPLY':
            case 'POSTFORM_THREAD':
                $pidArray =
                    GeneralUtility::trimExplode(
                        ',',
                        $storeObject->getPidList()
                    );
                $pid = $pidArray[0];
                $content .=
                    $this->forum_postform(
                        $theCode,
                        $pid,
                        $ref,
                        $linkParams,
                        $storeObject
                    );
            break;
            case 'FORUM':
            case 'THREAD_TREE':
                $forumViewObj = GeneralUtility::makeInstance(\JambageCom\TtBoard\View\Forum::class);
                if ($forumViewObj->needsInit()) {

                    $pid = ($conf['PIDforum'] ? $conf['PIDforum'] : $GLOBALS['TSFE']->id);
                    $forumViewObj->init(
                        $conf,
                        $storeObject->getAllowCaching(),
                        $storeObject->getTypolinkConf(),
                        $pid,
                        $storeObject->getPrefixId()
                    );
                }
                $content .= $forumViewObj->printView(
                    $storeObject->getLanguageObj(),
                    $storeObject->getMarkerObj(),
                    $storeObject->getModelObj(),
                    $storeObject->getTtBoardUid(),
                    $ref,
                    $storeObject->getPidList(),
                    $theCode,
                    $storeObject->getOrigTemplateCode(),
                    $storeObject->getAlternativeLayouts(),
                    $linkParams
                );
            break;
            default:
                $contentTmp = 'error';
            break;
        }	// Switch

        if ($contentTmp == 'error') {
            $fileName = 'EXT:' . TT_BOARD_EXT . '/template/board_help.tmpl';
            $helpTemplate = $storeObject->getCObj()->fileResource($fileName);

            $content .= \tx_div2007_alpha5::displayHelpPage_fh003(
                $storeObject->getLanguageObj(),
                $storeObject->getCObj(),
                $helpTemplate,
                TT_BOARD_EXT,
                $storeObject->getErrorMessage(),
                $theCode
            );
            $storeObject->setErrorMessage('');
        }
    }


    /**
    * Returns the content record
    */
    public function getContentRecord ($pid) {
        $where = 'pid=' . intval($pid) . ' AND list_type IN (\'2\',\'4\') AND sys_language_uid=' . intval($GLOBALS['TSFE']->config['config']['sys_language_uid']) . $GLOBALS['TSFE']->sys_page->deleteClause('tt_content');
        $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tt_content', $where);
        $rc = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
        $GLOBALS['TYPO3_DB']->sql_free_result($res);

        return $rc;
    } //getRecord


    /**
    * Creates a list of forums or categories depending on theCode
    */
    public function forum_list ($theCode, $storeObject, array $linkParams) {
        $conf = $storeObject->getConf();
        $modelObj = $storeObject->getModelObj();
        $markerObj = $storeObject->getMarkerObj();
        $languageObj = $storeObject->getLanguageObj();
        $alternativeLayouts = $storeObject->getAlternativeLayouts();
        $allowCaching = $storeObject->getAllowCaching();

        $local_cObj = \JambageCom\Div2007\Utility\FrontendUtility::getContentObjectRenderer();
        $local_cObj->setCurrentVal($GLOBALS['TSFE']->id);
        $forum_cObj = \JambageCom\Div2007\Utility\FrontendUtility::getContentObjectRenderer(array(), $modelObj->getTablename());

        if (!$storeObject->getTtBoardUid()) {
            $forumlist = 0;		// set to true if this is a list of forums and not categories + forums
            if ($theCode == 'LIST_CATEGORIES') {
                    // Config if categories are listed.
                $lConf = $conf['list_categories.'];
            } else {
                $forumlist = 1;
                    // Config if forums are listed.
                $lConf = $conf['list_forums.'];
                $lConf['noForums'] = 0;
            }

            $GLOBALS['TSFE']->set_cache_timeout_default($lConf['cache_timeout'] ? intval($lConf['cache_timeout']) : 300);
            $templateCode =
                $local_cObj->getSubpart(
                    $storeObject->getOrigTemplateCode(),
                    '###TEMPLATE_OVERVIEW###'
                );

            if ($templateCode) {
                    // Clear
                $subpartMarkerArray = array();
                $wrappedSubpartContentArray = array();

                    // Getting the specific parts of the template
                $markerObj->getColumnMarkers(
                    $markerArray,
                    $languageObj
                );
                $templateCode =
                    $local_cObj->substituteMarkerArrayCached(
                        $templateCode,
                        $markerArray,
                        $subpartMarkerArray,
                        $wrappedSubpartContentArray
                    );

                    // Getting the specific parts of the template
                $categoryHeader =
                    $markerObj->getLayouts(
                        $templateCode,
                        $alternativeLayouts,
                        'CATEGORY'
                    );
                $forumHeader =
                    $markerObj->getLayouts(
                        $templateCode,
                        $alternativeLayouts,
                        'FORUM'
                    );
                $postHeader =
                    $markerObj->getLayouts(
                        $templateCode,
                        $alternativeLayouts,
                        'POST'
                    );
                $subpartContent = '';

                    // Getting categories
                $categories = $modelObj->getPagesInPage($storeObject->getPidList());
                $c_cat = 0;

                foreach ($categories as $k => $catData) {
                        // Getting forums in category
                    if ($forumlist) {
                        $forums = $categories;
                    } else {
                        $forums = $modelObj->getPagesInPage($catData['uid']);
                    }

                    if (!$forumlist && count($categoryHeader)) {
                            // Rendering category
                        $out = $categoryHeader[$c_cat % count($categoryHeader)];
                        $c_cat++;
                        $local_cObj->start($catData);

                            // Clear
                        $markerArray = array();
                        $wrappedSubpartContentArray = array();

                            // Markers
                        $markerArray['###CATEGORY_TITLE###'] =
                            $local_cObj->stdWrap(
                                $markerObj->formatStr(
                                    $catData['title']
                                ),
                                $lConf['title_stdWrap.']
                            );
                        $markerArray['###CATEGORY_DESCRIPTION###'] =
                            $local_cObj->stdWrap(
                                $markerObj->formatStr(
                                    $catData['subtitle']
                                ),
                                $lConf['subtitle_stdWrap.']
                            );
                        $markerArray['###CATEGORY_FORUMNUMBER###'] =
                            $local_cObj->stdWrap(
                                count($forums),
                                $lConf['count_stdWrap.']
                            );

                        $pageLink =
                            \tx_div2007_alpha5::getPageLink_fh003(
                                $storeObject->getCObj(),
                                $catData['uid'],
                                '',
                                $linkParams,
                                array('useCacheHash' => $allowCaching)
                            );

                        $wrappedSubpartContentArray['###LINK###'] =
                            array('<a href="' . htmlspecialchars($pageLink) . '">',
                            '</a>'
                        );

                            // Substitute
                        $subpartContent .=
                            $local_cObj->substituteMarkerArrayCached(
                                $out,
                                $markerArray,
                                array(),
                                $wrappedSubpartContentArray
                            );
                    }

                    if (count($forumHeader) && !$lConf['noForums']) {
                            // Rendering forums
                        $c_forum = 0;
                        foreach($forums as $forumData) {
                            $contentRow = $this->getContentRecord($forumData['uid']);
                            $out = $forumHeader[$c_forum % count($forumHeader)];
                            $c_forum++;
                            $forum_cObj->start($forumData);

                                // Clear
                            $markerArray = array();
                            $wrappedSubpartContentArray = array();

                                // Markers
                            $markerArray['###FORUM_TITLE###'] =
                                $forum_cObj->stdWrap(
                                    $markerObj->formatStr(
                                        $forumData['title']
                                    ),
                                    $lConf['forum_title_stdWrap.']
                                );

                            $markerArray['###FORUM_DESCRIPTION###'] =
                                $forum_cObj->stdWrap(
                                    $markerObj->formatStr(
                                        $forumData['subtitle']
                                    ),
                                    $lConf['forum_description_stdWrap.']
                                );

                            $pid = (
                                isset($contentRow) &&
                                is_array($contentRow) &&
                                $contentRow['pages'] ?
                                    $contentRow['pages'] :
                                    $forumData['uid']
                            );
                            $markerArray['###FORUM_POSTS###'] =
                                $forum_cObj->stdWrap(
                                    $modelObj->getNumPosts($pid),
                                    $lConf['forum_posts_stdWrap.']
                                );
                            $markerArray['###FORUM_THREADS###'] =
                                $forum_cObj->stdWrap(
                                    $modelObj->getNumThreads($pid),
                                    $lConf['forum_threads_stdWrap.']
                                );

                                // Link to the forum (wrap)
                            $pageLink =
                                \tx_div2007_alpha5::getPageLink_fh003(
                                    $storeObject->getCObj(),
                                    $forumData['uid'],
                                    '',
                                    $linkParams,
                                    array('useCacheHash' => $allowCaching)
                                );
                            $wrappedSubpartContentArray['###LINK###'] =
                                array(
                                    '<a href="' . htmlspecialchars($pageLink) . '">',
                                    '</a>'
                                );

                                // LAST POST:
                            $lastPostInfo = $modelObj->getLastPost($pid);
                            $forum_cObj->start($lastPostInfo);

                            if ($lastPostInfo) {
                                $markerArray['###LAST_POST_AUTHOR###'] =
                                    $forum_cObj->stdWrap($markerObj->formatStr($lastPostInfo['author']), $lConf['last_post_author_stdWrap.']);
                                $markerArray['###LAST_POST_DATE###'] =
                                    $forum_cObj->stdWrap(
                                        $modelObj->recentDate(
                                            $lastPostInfo
                                        ),
                                        $conf['date_stdWrap.']
                                    );
                                $markerArray['###LAST_POST_TIME###'] =
                                    $forum_cObj->stdWrap(
                                        $modelObj->recentDate(
                                            $lastPostInfo
                                        ),
                                        $conf['time_stdWrap.']
                                    );
                                $markerArray['###LAST_POST_AGE###'] =
                                    $forum_cObj->stdWrap(
                                        $modelObj->recentDate($lastPostInfo),
                                        $conf['age_stdWrap.']
                                    );
                            } else {
                                $markerArray['###LAST_POST_AUTHOR###'] = '';
                                $markerArray['###LAST_POST_DATE###'] = '';
                                $markerArray['###LAST_POST_TIME###'] = '';
                                $markerArray['###LAST_POST_AGE###'] = '';
                            }

                                // Link to the last post
                            $overrulePIvars =
                                array_merge(
                                    $linkParams,
                                    array('uid' => $lastPostInfo['uid'])
                                );
                            $pageLink =
                                \tx_div2007_alpha5::getPageLink_fh003(
                                    $storeObject->getCObj(),
                                    $contentRow['pid'],
                                    '',
                                    $overrulePIvars,
                                    array('useCacheHash' => $allowCaching)
                                );

                            $wrappedSubpartContentArray['###LINK_LAST_POST###'] =
                                array(
                                    '<a href="' . htmlspecialchars($pageLink) . '">',
                                    '</a>'
                                );

                                // Add result
                            $subpartContent .=
                                $forum_cObj->substituteMarkerArrayCached(
                                    $out,
                                    $markerArray,
                                    array(),
                                    $wrappedSubpartContentArray
                                );

                                // Rendering the most recent posts
                            if (count($postHeader) && $lConf['numberOfRecentPosts']) {
                                $recentPosts =
                                    $modelObj->getMostRecentPosts(
                                        $forumData['uid'],
                                        intval($lConf['numberOfRecentPosts']),
                                        intval($lConf['numberOfRecentDays'])
                                    );

                                $c_post = 0;
                                foreach($recentPosts as $recentPost) {
                                    $out = $postHeader[$c_post % count($postHeader)];
                                    $c_post++;
                                    $forum_cObj->start($recentPost);

                                        // Clear:
                                    $markerArray = array();
                                    $wrappedSubpartContentArray = array();

                                        // markers:
                                    $markerArray['###POST_TITLE###'] =
                                        $forum_cObj->stdWrap(
                                            $markerObj->formatStr(
                                                $recentPost['subject']
                                            ),
                                            $lConf['post_title_stdWrap.']
                                        );
                                    $markerArray['###POST_CONTENT###'] =
                                        $markerObj->substituteEmoticons(
                                            $forum_cObj->stdWrap(
                                                $markerObj->formatStr(
                                                    $recentPost['message']
                                                ),
                                                $lConf['post_content_stdWrap.']
                                            )
                                        );
                                    $markerArray['###POST_REPLIES###'] =
                                        $forum_cObj->stdWrap(
                                            $modelObj->getNumReplies(
                                                $recentPost['pid'],
                                                $recentPost['uid']
                                            ),
                                            $lConf['post_replies_stdWrap.']
                                        );
                                    $markerArray['###POST_AUTHOR###'] =
                                        $forum_cObj->stdWrap(
                                            $markerObj->formatStr(
                                                $recentPost['author']
                                            ),
                                            $lConf['post_author_stdWrap.']
                                        );
                                    $markerArray['###POST_DATE###'] =
                                        $forum_cObj->stdWrap(
                                            $modelObj->recentDate(
                                                $recentPost
                                            ),
                                            $conf['date_stdWrap.']
                                        );
                                    $markerArray['###POST_TIME###'] =
                                        $forum_cObj->stdWrap(
                                            $modelObj->recentDate(
                                                $recentPost
                                            ),
                                            $conf['time_stdWrap.']
                                        );
                                    $markerArray['###POST_AGE###'] =
                                        $forum_cObj->stdWrap(
                                            $modelObj->recentDate(
                                                $recentPost
                                            ),
                                            $conf['age_stdWrap.']
                                        );

                                        // Link to the post:
                                    $forum_cObj->setCurrentVal($recentPost['pid']);
                                    $temp_conf = $storeObject->getTypolinkConf();
                                    $temp_conf['additionalParams'] .= '&tt_board_uid=' . $recentPost['uid'];
                                    $temp_conf['useCacheHash'] = $allowCaching;
                                    $temp_conf['no_cache'] = !$allowCaching;
                                    $wrappedSubpartContentArray['###LINK###'] =
                                        $forum_cObj->typolinkWrap($temp_conf);

                                    $overrulePIvars =
                                        array_merge(
                                            $linkParams,
                                            array('uid' => $recentPost['uid'])
                                        );
                                    $pageLink =
                                        \tx_div2007_alpha5::getPageLink_fh003(
                                            $storeObject->getCObj(),
                                            $recentPost['pid'],
                                            '',
                                            $overrulePIvars,
                                            array('useCacheHash' => $allowCaching)
                                        );

                                    $wrappedSubpartContentArray['###LINK###'] =
                                        array(
                                            '<a href="' . htmlspecialchars($pageLink) . '">',
                                            '</a>'
                                        );

                                    $subpartContent .=
                                        $forum_cObj->substituteMarkerArrayCached(
                                            $out,
                                            $markerArray,
                                            array(),
                                            $wrappedSubpartContentArray
                                        );
                                }
                            }
                        }
                    }
                    if ($forumlist) {
                        break;
                    }
                }

                    // Substitution:
                $content .=
                    $local_cObj->substituteSubpart(
                        $templateCode,
                        '###CONTENT###',
                        $subpartContent
                    ) ;
            } else {
                $content = $this->outMessage('No template code for ###TEMPLATE_OVERVIEW###');
            }
        }

        return $content;
    }


    /**
    * Creates a post form for a forum
    */
    public function forum_postform (
        $theCode,
        $pid,
        $ref,
        array $linkParams,
        $storeObject
    ) {
        $content = '';
        $conf = $storeObject->getConf();
        $modelObj = $storeObject->getModelObj();
        $languageObj = $storeObject->getLanguageObj();
        $local_cObj = \JambageCom\Div2007\Utility\FrontendUtility::getContentObjectRenderer();

        if (
            $modelObj->isAllowed($conf['memberOfGroups'])
        ) {
            $parent = 0;		// This is the parent item for the form. If this ends up being is set, then the form is a reply and not a new post.
            $nofity = array();

                // Find parent, if any
            if (
                $storeObject->getTtBoardUid() ||
                $ref != ''
            ) {
                if ($conf['tree']) {
                    $parent = $storeObject->getTtBoardUid();
                }

                $parentR =
                    $modelObj->getRootParent(
                        $storeObject->getTtBoardUid(),
                        $ref#
                    );
                if (!$conf['tree']) {
                    $parent = $parentR['uid'];
                }

/*				$rootParent = $modelObj->getRootParent($parent, $ref);
*/
                $wholeThread =
                    $modelObj->getSingleThread(
                        $parentR['uid'],
                        $ref,
                        1
                    );
                $notify = array();

                foreach($wholeThread as $recordP) {	// the last notification checkbox will be superseed the previous settings

                    if ($recordP['email']) {

                        $index = md5(trim(strtolower($recordP['email'])));

                        if ($recordP['notify_me']) {
                            $notify[$index] = trim($recordP['email']);
                        } else if (!$recordP['notify_me']) {
                            if (isset($notify[$index])) {
                                unset($notify[$index]);
                            }
                        }
                    }
                }
            }

                // Get the render-code
            $lConf = $conf['postform.'];

//   postform.dataArray {
//     10.label = Subject:
//     10.type = *data[tt_board][NEW][subject]=input,60
//     20.label = Message:
//     20.type =  *data[tt_board][NEW][message]=textarea,60
//     30.label = Name:
//     30.type = *data[tt_board][NEW][author]=input,40
//     40.label = Email:
//     40.type = *data[tt_board][NEW][email]=input,40
//     50.label = Notify me<BR>by reply:
//     50.type = data[tt_board][NEW][notify_me]=check
//     60.type = formtype_db=submit
//     60.value = Post Reply
//   }

            $setupArray =
                array(
                    '10' => 'subject',
                    '20' => 'message',
                    '30' => 'author',
                    '40' => 'email',
                    '50' => 'notify_me',
                    '60' => 'post_reply'
                );

            $modEmail = $conf['moderatorEmail'];
            if (!$parent && isset($conf['postform_newThread.'])) {
                $lConf = $conf['postform_newThread.'] ? $conf['postform_newThread.'] : $lConf;	// Special form for newThread posts...
                $modEmail = $conf['moderatorEmail_newThread'] ? $conf['moderatorEmail_newThread'] : $modEmail;
                $setupArray['60'] = 'post_new_reply';
            }

            if ($modEmail) {
                $modEmail = explode(',', $modEmail);
                foreach($modEmail as $modEmail_s) {
                    $notify[md5(trim(strtolower($modEmail_s)))] = trim($modEmail_s);
                }
            }

            if (
                $theCode == 'POSTFORM' ||
                ($theCode == 'POSTFORM_REPLY' && $parent) ||
                ($theCode == 'POSTFORM_THREAD' && !$parent)
            ) {
                $origRow = array();
                $bWrongCaptcha = false;
                if (
                    isset($GLOBALS['TSFE']->applicationData) &&
                    is_array($GLOBALS['TSFE']->applicationData) &&
                    isset($GLOBALS['TSFE']->applicationData[TT_BOARD_EXT]) &&
                    is_array($GLOBALS['TSFE']->applicationData[TT_BOARD_EXT]) &&
                    isset($GLOBALS['TSFE']->applicationData[TT_BOARD_EXT]['error']) &&
                    is_array($GLOBALS['TSFE']->applicationData[TT_BOARD_EXT]['error'])
                ) {
                    if ($GLOBALS['TSFE']->applicationData[TT_BOARD_EXT]['error']['captcha'] == true) {
                        $origRow = $GLOBALS['TSFE']->applicationData[TT_BOARD_EXT]['row'];
                        unset ($origRow['doublePostCheck']);
                        $bWrongCaptcha = true;
                        $word = $GLOBALS['TSFE']->applicationData[TT_BOARD_EXT]['word'];
                    }
                    if ($GLOBALS['TSFE']->applicationData[TT_BOARD_EXT]['error']['spam'] == true) {
                        $spamWord = $GLOBALS['TSFE']->applicationData[TT_BOARD_EXT]['word'];
                        $origRow = $GLOBALS['TSFE']->applicationData[TT_BOARD_EXT]['row'];
                    }
                }

                if ($spamWord != '') {
                    $out =
                        sprintf(
                            \tx_div2007_alpha5::getLL_fh003(
                                $languageObj,
                                'spam_detected'
                            ),
                            $spamWord
                        );
                    $lConf['dataArray.']['1.'] = array(
                        'label' => 'ERROR !',
                        'type' => 'label',
                        'value' => $out,
                    );
                }
                $lConf['dataArray.']['9995.'] = array(
                    'type' => '*data[tt_board][NEW][prefixid]=hidden',
                    'value' => $storeObject->getPrefixId()
                );
                $lConf['dataArray.']['9996.'] = array(
                    'type' => '*data[tt_board][NEW][reference]=hidden',
                    'value' => $ref
                );
                $lConf['dataArray.']['9998.'] = array(
                    'type' => '*data[tt_board][NEW][pid]=hidden',
                    'value' => $pid
                );
                $lConf['dataArray.']['9999.'] = array(
                    'type' => '*data[tt_board][NEW][parent]=hidden',
                    'value' => $parent
                );

                if (is_object($storeObject->getFreeCap())) {
                    $freecapMarker = $storeObject->getFreeCap()->makeCaptcha();
                    $textLabel = '';
                    if ($bWrongCaptcha) {
                        $textLabel = '<b>' .
                            sprintf(
                                \tx_div2007_alpha5::getLL_fh003(
                                    $languageObj,
                                    'wrong_captcha'
                                ),
                                $word
                            ) .
                            '</b><br/>';
                    }

                    $lConf['dataArray.']['55.'] = array(
                        'label' => $textLabel . $freecapMarker['###SR_FREECAP_IMAGE###'] . '<br/>' . $freecapMarker['###SR_FREECAP_NOTICE###'] . '<br/>' . $freecapMarker['###SR_FREECAP_CANT_READ###'],
                        'type' => '*data[tt_board][NEW][captcha]=input,60'
                    );
                }

                if (count($notify)) {
                    $lConf['dataArray.']['9997.'] = array(
                        'type' => 'notify_me=hidden',
                        'value' => htmlspecialchars(implode($notify, ','))
                    );
                }

                if (is_array($GLOBALS['TSFE']->fe_user->user)) {
                    foreach ($lConf['dataArray.'] as $k => $dataRow) {
                        if (strpos($dataRow['type'], '[author]') !== false) {
                            $lConf['dataArray.'][$k]['value'] = $GLOBALS['TSFE']->fe_user->user['name'];
                        } else if (strpos($dataRow['type'],'[email]') !== false) {
                            $lConf['dataArray.'][$k]['value'] = $GLOBALS['TSFE']->fe_user->user['email'];
                        }
                    }
                }

                foreach ($setupArray as $k => $theField) {
                    if ($k == '60') {
                        $type = 'value';
                    } else {
                        $type = 'label';
                    }
                    if (is_array($lConf['dataArray.'][$k . '.'])) {
                        if (
                            (
                                !$languageObj->getLLkey() ||
                                $languageObj->getLLkey() == 'en'
                            ) &&
                            !$lConf['dataArray.'][$k . '.'][$type] ||

                            (
                                $languageObj->getLLkey() != 'en' &&
                                (
                                    !is_array($lConf['dataArray.'][$k . '.'][$type . '.']) ||
                                    !is_array($lConf['dataArray.'][$k . '.'][$type . '.']['lang.']) ||
                                    !is_array($lConf['dataArray.'][$k . '.'][$type . '.']['lang.'][$languageObj->getLLkey() . '.'])
                                )
                            )
                        ) {
                            $lConf['dataArray.'][$k . '.'][$type] =
                                \tx_div2007_alpha5::getLL_fh003(
                                    $languageObj,
                                    $theField
                                );

                            if (
                                ($type == 'label') &&
                                isset($origRow[$theField])
                            ) {
                                $lConf['dataArray.'][$k . '.']['value'] = $origRow[$theField];
                            }
                        }
                    }
                }

                if ($storeObject->getTtBoardUid()) {
                    $linkParams[$storeObject->getPrefixId() . '[uid]'] = $storeObject->getTtBoardUid();
                }

                if (isset($linkParams) && is_array($linkParams)) {
                    $url =
                        \tx_div2007_alpha5::getPageLink_fh003(
                            $local_cObj,
                            $GLOBALS['TSFE']->id,
                            '',
                            $linkParams,
                            array('useCacheHash' => false)
                        );
                    $lConf['type'] = $url;
                }
                ksort($lConf['dataArray.']);
                $content .=  $local_cObj->cObjGetSingle('FORM', $lConf);
            }
        }

        return $content;
    }
}

