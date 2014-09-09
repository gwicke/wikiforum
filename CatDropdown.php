<?php
# category dropdown
#
#


$wgHooks['EditPage::showEditForm:checkboxhtml'][] = 'wfShowCustomEditControls';
# freely editable, using parser plugin
function wfShowCustomEditControls($editpage, $checkboxhtml) {
	global $wgCustomEditControlVars, $wgTitle, $wgForumNamespace;
	# check if talk namespace
	if(!isset($wgForumNamespace) or $wgTitle->getNamespace() != $wgForumNamespace)
		return false;
	#XXX: check if user created this or is admin
	$checkboxhtml .= wfMsgWikiHtml('CustomEditControls');
	$wgCustomEditControlVars = array('cats' => get_cats_from_text($editpage->textbox1));
	return false;
}


$wgExtensionFunctions[] = 'registerCatDropdownExtension';
$wgExtensionCredits['parserhook'][] = array(
'name' => 'CatDropdown',
'author' => 'Gabriel Wicke/wikidev.net for wikiation.nl',
'url' => 'http://www.wikiation.nl',
);

function registerCatDropdownExtension()
{
    global $wgParser;
    $wgParser->setHook('catdropdown', 'renderCatDropdown');
}


function renderCatDropdown($input, $params, &$parser) {
	global $wgTitle,$catdropdowns;
	if(!isset($catdropdowns))
		$catdropdowns = 0;
	else
		$catdropdowns++;
	$cur_cats = $wgTitle->getParentCategories();
	$cats = split("\n",wfMsg(htmlspecialchars($input)));
	$sel = '<input type="hidden" name="catdropdown-source-'.$catdropdowns.'" value="'.
		htmlspecialchars($input).'"/><select name="catdropdown-'.$catdropdowns.'">';
	if (!is_array($cur_cats)) # returns either string or array- bleh!
	    $sel .= '<option value="" selected="selected"></option>';
	foreach($cats as $cat) {
		if( isset($cur_cats[$cat]) )
			$set = ' selected="selected"';
		else
			$set = '';
		$cat = htmlspecialchars($cat);
		$sel .= "<option value='$cat'$set>$cat</option>";
	}
	$sel .= '</select>';
	return $sel;

}

# get list of cats this article is in
function get_cats_from_text($text) {
	preg_match_all('/\[\[(Category:[^\]]+)\]\]/',$text,$matches);
	return $matches[1];
}

$wgHooks['ArticleSave'][] = 'wfEditcats';
function wfEditcats($article,$user,$text,$summary,$flags,$x,$y,$flags) {
	global $wgRequest,$wgTitle;

	if(!$wgTitle->isTalkPage())
		return true;

	$old_cats = $wgTitle->getParentCategories();
	$count = 0;
	while(true) {
		$cat_source = $wgRequest->getVal('catdropdown-source-'.$count);
		$cats = $wgRequest->getArray('catdropdown-'.$count);
		if($cat_source == '' or !is_array($cats))
			break;
		$count++;
		$selectable_cats = split("\n",wfMsg($cat_source));
		foreach($selectable_cats as $scat) {
			if($scat == '')
				continue;
			$scat = str_replace('/','\/',$scat);
			$scat = str_replace('[','\[',$scat);
			$scat = str_replace(']','\]',$scat);
			$text = preg_replace('/\n?\[\['.$scat.'\]\]/','',$text);
			#if(isset($old_cats[$scat]) and !isset($cats[$scat])) {
			#}
		}
		foreach($cats as $cat) {
			if($cat == '')
				continue;
			$text .= "\n[[$cat]]";
		}
	}
	return true;
}

?>
