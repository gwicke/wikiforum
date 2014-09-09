<?php

$credprefix = 'wikiation_wikiforum_';
$wgExtensionCredits['wikiation'][] = array(
	'namemsg' => $credprefix . 'name',
	'authormsg' => $credprefix . 'author',
	'urlmsg' => $credprefix . 'url',
	'descriptionmsg' => $credprefix . 'description',
	'version' => '10062',

);

$dir = dirname(__FILE__) . '/';
$wgExtensionMessagesFiles['wikiforum'] = $dir . 'Wikiation_wikiforum.i18n.php';
$wgExtensionMessagesFiles['wikiforum-private'] = $dir . 'Wikiation_wikiforum.private.i18n.php';

$wgHooks['ParserBeforeStrip'][] = 'wfInsertHashes';
$wgGroupPermissions['sysop']['w_wikiforumedit'] = true;
$fe_header_reg = '\n={1,6}[^=]+={1,6}[ \t]*\n';
#$fe_sig_reg = '-*\[\[.*\]\][^\n]{15,30}\(\w+\)\n';
$fe_sig_reg = '--\[\[.*\]\][^\n]{15,200}\(\w+\)\n';
function fe_splitText($text) {
	global $fe_header_reg, $fe_sig_reg;
	return preg_split('/('.$fe_header_reg.'|'.$fe_sig_reg.')/', 
		"\n".$text."\n", -1, PREG_SPLIT_DELIM_CAPTURE);
}
# generate hashes for sections
function wfInsertHashes($parser, $text, $x) {
	global $fe_header_reg, $fe_sig_reg;
	global $wgThreadEditNamespaces, $wgRequest;
        global $wgContLang;
	$ns  = strval($parser->mTitle->getNamespace());
	$action = $wgRequest->getVal('action');
	$save = $wgRequest->getVal('wpSave') . $wgRequest->getVal('wpNTSave');
	wfDebug('SAVE:'.serialize($save));
	$preview = $wgRequest->getVal('wpPreview');
        $lcat =  $wgContLang->getNsText( NS_CATEGORY );
        if(($save == '' && !isInCatOpen($parser->mTitle))
               || ! preg_match('/\[\[(Category|'.$lcat.'):Wikiforum open\]\]/i', $text)
               || $action == 'edit'
               || $preview != ''
               || ($ns !== '-1' && !$wgThreadEditNamespaces[$ns]))

		#echo "ouch.";
		return true;
	$t = array();
	$blocks = fe_splitText($text);
	#wfDebug(serialize($blocks));
	$headerhashes = array();
	$headerhash = sha1('').'0';
	foreach($blocks as $pos => $match) {
		if(preg_match('/'.$fe_header_reg.'/', $match)) {
			$headerhash = sha1($match);
			if(isset($headerhashes[$headerhash]))
				$headerhashes[$headerhash] += 1;
			else
				$headerhashes[$headerhash] = 0;
			#append number
			$headerhash .= $headerhashes[$headerhash];
			$blockhashes = array();
			$t[] = $lastmatch . $match;
			$lastmatch = '';
		} else if(preg_match('/'.$fe_sig_reg.'/', $match)) {
			$blockhash = sha1($lastmatch . $match);
			if(isset($blockhashes[$blockhash]))
				$blockhashes[$blockhash] += 1;
			else
				$blockhashes[$blockhash] = 0;
			$blockhash .= $blockhashes[$blockhash];
			$t[] = $lastmatch . str_replace("\n",'',$match) . 
				'····hash' . $headerhash.'-'.$blockhash."hsah····\n";
			$lastmatch = '';
		} else {
			$lastmatch = $match;
		}
	}
	$t[] = $lastmatch;
	$text = substr(join('',$t),1,-1);
	#echo $text;
	return true;
    
}

$wgHooks['Parser::extractSections'][] = 'wfAppendSection';


function wikiforum_edittabrights($skintemplate) {
	global $wgThreadEditNamespaces;
	
	return !(is_array($wgThreadEditNamespaces) 
	       && array_key_exists(strval($skintemplate->mTitle->getNamespace()),
		       $wgThreadEditNamespaces)
	       && $wgThreadEditNamespaces[strval($skintemplate->mTitle->getNamespace())] 
	       && !$skintemplate->mUser->isAllowed('w_wikiforumedit')); 
}
$wgHooks['SkinTemplate:edittab'][] = 'wikiforum_edittabrights';

function wikiforum_get_reply_section($editpage, $wgOut) {
	global $IP, $wgParser, $wgRequest;
	wfLoadExtensionMessages('wikiforum');
	$res = '<ul>';
	$ct = $wgRequest->getVal('closethread');
	if($ct != '') {
		$res .= '<li><b>'.wfMsg('youareclosingthisthreadas',htmlspecialchars($ct)) . '</b></li>';
	}
	$article = $editpage->mArticle;
	if ( !$article )
		$article = new Article($editpage->mTitle);
	if(wikiforum_is_reply($editpage->section))
		$res .= '<li>' . wfMsg('youarereplyingto') . '<br/>' . $wgOut->parse(
			$wgParser->getSection(
				$article->getContent(), $editpage->section
			)) . '</li>' ;

	return $res . '</ul>';
}
$wgHooks['EditPage::ShowPreview'][] = 'wikiforum_get_reply_section';

function wfAppendSection($parser, $text, $section, $mode, $newtext, $rv) {
	global $fe_header_reg, $fe_sig_reg, $wgRequest;
        global $wgContLang;
	# Check if we have a hash as section to work on
	if( !wikiforum_is_reply($section))
		return true;
	wfLoadExtensionMessages('wikiforum');
	#wfDebug("$mode $section");
	$hashes = wikiforum_parse_reply($section);
	$blocks = fe_splitText($text);
	#print_r($blocks);
	$headerhashes = array();
	$foundheader = false;
	$prefix = false;
	$done = false;
	$headerhash = sha1('').'0';
	$lastmatch = '';
	$t = array();
	foreach($blocks as $pos => $match) {
		if($done) {
			$t[] = $match;
		} else {
			if($foundheader && $prefix) {
				$indented = preg_match('/(\n|^)(:+)[^\n]+(\n|$)/', $match, $prefmatches);
				if(($indented && strlen($prefmatches[2]) < strlen($prefix)) ||
				!($indented || preg_match('/'.$fe_sig_reg.'|^[\n \t]*$/', $match)))
				{
					# append now
					#wfDebug("appending at $match with len ".
						#strlen($prefmatches[1])." and prefix $prefix..\n");
					# prepend prefix to new text
					$newtext = preg_replace('/(\n|^)(?!\n)/', '\1'.$prefix,
						$newtext).' --~~~~';
					$t[] = "\n" . $newtext . "\n";
					$done = true;
				} else {
					#wfDebug("Not appending at $match");
				}
				$t[] = $match;
			} else {
				if(preg_match('/'.$fe_header_reg.'/', $match)) {
					$headerhash = sha1($match);
					if(isset($headerhashes[$headerhash]))
						$headerhashes[$headerhash] += 1;
					else
						$headerhashes[$headerhash] = 0;
					#append number
					$headerhash .= $headerhashes[$headerhash];
					if($headerhash == $hashes[1]) {
						# found correct header
						$foundheader = true;
					}

					$blockhashes = array();
					$t[] = $lastmatch . $match;
					$lastmatch = '';
				} else if(preg_match('/'.$fe_sig_reg.'/', $match)) {
					$blockhash = sha1($lastmatch . $match);
					if(isset($blockhashes[$blockhash]))
						$blockhashes[$blockhash] += 1;
					else
						$blockhashes[$blockhash] = 0;
					$blockhash .= $blockhashes[$blockhash];
                                       if(!$foundheader)
                                               $foundheader = ($headerhash == $hashes[1]);
                                       if($foundheader &&
					     $blockhash == $hashes[2]) {
						# found the block we want to reply to
						if($mode == 'get') {
							$rv = @end($t) . $lastmatch . $match;
							global $wgRequest;
							#wfDebug("GET mode: returning $rv");
							return false;
						} else {
							# get indentation of block
							if(preg_match('/(?:\n|^)(:+)[^\n]+\n?$/', $lastmatch, $prefmatches))
								$prefix = $prefmatches[1] . ':';
							else
								$prefix = ':';
							#wfDebug("continuing..");
						}
					}
					$t[] = $lastmatch . $match;
					$lastmatch = '';
				} else {
					$lastmatch = $match;
				}
			}
		}
	}
	#wfDebug(serialize($t));
	if(!$done) {
		# page ended before we found a header or less
		# indented post. Append the post to the end of the page.
		if($newtext != '') {
			if (!$prefix)
				$pref = ':';
			else
				$pref = $prefix;
			$newtext = preg_replace(
				'/(\n|^)(?!\n)/', '\1'.$pref,
				$newtext).' --~~~~';
			$t[] = "\n" . $newtext . "\n";
		}
		# categories etc
		$t[] = "\n".$lastmatch;
	}
	$rv = substr(join('',$t),1,-1);
	#wfDebug($rv);
	global $wgRequest;
	$newcat = $wgRequest->getVal('closethread');
        if($newcat != '') {
                $lcat =  $wgContLang->getNsText( NS_CATEGORY );
                $rv = preg_replace('/\[\[(Category|'.$lcat.
                    '):Wikiforum open\]\]/i',"[[$lcat:$newcat]]",$rv);

		
		global $wgTitle,$wgUser;
		if($mode == 'replace') {
			$wgUser->mDataLoaded = true;
			$wgUser->mRights[] = 'protect';
			$article = new Article($wgTitle);
			$article->updateRestrictions(array('edit' => 'sysop'));
		}
	}
	#wfDebug("RETURNING $rv");
	return false;
}

$wgHooks['ParserBeforeTidy'][] = 'wfLinkSections';

function wikiforum_add_closethread($editpage, $wgOut) {
	global $wgRequest;
	$ct = $wgRequest->getVal('closethread');
	if($ct != '')
		$wgOut->addHTML( '<input type="hidden" name="closethread" value="'.$ct.'" />' );
	return true;
}
$wgHooks['EditPage::showEditForm:fields'][] = 'wikiforum_add_closethread';

function wikiforum_preload($text, $section, $def_text, $editpage) {
	global $wgParser;
	if(wikiforum_is_reply($section)) {
		$text = ''; #$wgParser->getSection( $text, $section, $def_text );
		return false;
	}
	return true;
}
$wgHooks['EditPagePreloadSection'][] = 'wikiforum_preload';


function wikiforum_permissions($permErrors, $editpage) {
	# forum namespace
	global $wgThreadEditNamespaces, $wgUser;
	if(is_array($wgThreadEditNamespaces) 
		&& $wgThreadEditNamespaces[$editpage->mTitle->getNamespace()]
			&& !$wgUser->isAllowed('w_wikiforumedit')
				&& $editpage->section == ""
			)
			$permErrors += array('no forum namespace');
	return true;
}
$wgHooks['EditFormGetEditPermissionErrors'][] = 'wikiforum_permissions';

function wikiforum_sectionanchor($anchor, $editpage) {
	if(wikiforum_is_reply($editpage->section))
		$anchor = '#' . $editpage->section;
	return false;
}
$wgHooks['EditPage::SectionAnchor'][] = 'wikiforum_sectionanchor';


function wikiforum_uitweaks($editpage) {	
	global $wgOut;
	if( wikiforum_is_reply($editpage->section) ) {
		$wgOut->addHTML( wikiforum_get_reply_section(&$editpage, &$wgOut) );
		#$editsummary = '<div>';
	}
	return true;
}
$wgHooks['EditPage::showEditForm:initial'][] = 'wikiforum_uitweaks';

function wikiforum_savebutton($editpage, $buttons, $tabindex) {
	if(wikiforum_is_reply($editpage->section) ) {
		wfLoadExtensionMessages('wikiforum');
		$temp = array(
			'id'        => 'wpSave',
			'name'      => 'wpSave',
			'type'      => 'submit',
			'tabindex'  => '5',
			'value'     => wfMsg('savenewthread'),
			'accesskey' => wfMsg('accesskey-save'),
			'title'     => wfMsg('tooltip-save'),
		);
		$buttons['save'] = XML::element('input', $temp, '');
	}
	return true;
}
$wgHooks['EditPageBeforeEditButtons'][] = 'wikiforum_savebutton';


# check if the section format looks like two hashes
function wikiforum_is_reply($section) {
	return preg_match('/.{40}\d+-.{40}\d+/', $section);
}


# split section string into two hashes
function wikiforum_parse_reply($section) {
	preg_match('/(.{40}\d+)-(.{40}\d+)/', $section, $matches);
	return $matches;
}

function isInCatOpen($title = false) {
	global $wgTitle;
	if($title === false)
		$title =& $wgTitle;
	$dbr =& wfGetDB( DB_SLAVE );
	#wfDebug('CATS: '.serialize($title->mCategories));
	#return in_array('Open',$title->mCategories);
	$openrow = $dbr->selectRow(array('categorylinks'),
		'cl_sortkey',
		array('cl_from' => $title->getArticleId(),
		'cl_to' => array('WikiForum_open', 'Wikiforum_open')),
		__METHOD__
	);
	return $openrow;
}

function isThreadOwner() {
	global $wgTitle, $wgUser;
	$dbr =& wfGetDB( DB_SLAVE );
	$tsrow = $dbr->selectRow(array('revision'),
		'rev_user',
		array('rev_page' => $wgTitle->getArticleId()),
		__METHOD__,
		array('ORDER BY' => 'rev_timestamp ASC',
		'LIMIT' => 1)
	);

	$user = $tsrow->rev_user;

	if($user != 0) {
		return $wgUser->getID() == $user; 
	} else
		return false;
}

function shouldShowcloseThreadButton() {
	global $wgRequest;
	$action = $wgRequest->getVal('action');
	return isThreadOwner() && isInCatOpen() && ($action == 'view' || $action == '');
}

function wfShowCloseThreadLink($out, $skin) {
	global $wgThreadEditNamespaces, $wgTitle, $wgLang;
	global $wgAllowClosethread_custom;
	wfLoadExtensionMessages('wikiforum-private');
	if(@$wgThreadEditNamespaces[strval($wgTitle->getNamespace())] && shouldShowcloseThreadButton()) {
		$closemsg = wfMsgForContent('Wikiation_wikiforum_closethread-custom');
		if(isset($wgAllowClosethread_custom) 
			&& $wgAllowClosethread_custom
			&& $closemsg != '&lt;Wikiation_wikiforum_closethread-custom&gt;') {
			$out->prependHTML ( '<div class="usermessage">'. $closemsg
				. '</div>');
		} else {
			# if user is thread owner: show 'close thread' button
			$link = '?action=edit&section=00000000000000000000000000000000000000000'.
				'-00000000000000000000000000000000000000000&closethread=';
			$out->prependHTML  ('<div class="usermessage">'.
				'<a href="'. 
				$link . 
				wfMsgForContent('closethread-solved-category') . '">' .
				$out->parse(wfMsg('closethread-solved-text'), false) .
				'</a> | ' .
				'<a href="'. 
				$link . 
				wfMsgForContent('closethread-notsolved-category') . '">' .
				$out->parse(wfMsg('closethread-notsolved-text'), false) . 
				'</a> | ' .
				'<a href="'. 
				$link . 
				wfMsgForContent('closethread-justclosed-category') . '">' .
				$out->parse(wfMsg('closethread-justclosed-text'), false) . 
				'</a>' .
				'</div>');
		}
	}
        return true;
}

$wgHooks['BeforePageDisplay'][] = 'wfShowCloseThreadLink';

function wfLinkSections($parser, &$text) {
	wfLoadExtensionMessages('wikiforum');
	# \1 is backref for regexp below
	$msg = '<a id="\1"></a> [<a href="?action=edit&section=\1">'.
		wfMsg('replylink') .
		'</a>]';
	$text = preg_replace('/····hash([\da-f-]+)hsah····/',
		$msg, $text);
	return true;
}

# add signature to preview text fed to the parser
function wikiforum_toparse($editpage, $toparse) {	
	if ( wikiforum_is_reply( $editpage->section ) ) $toparse .= " --~~~~";
	return true;
}
$wgHooks['EditPage::getPrefixedText:toparse'][] = 'wikiforum_toparse';
?>
