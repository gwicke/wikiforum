<?php

/**
 * Localisation file for Wikiation WikiForum, private messages.
 */

$messages = array();

/** English */
$messages['en'] = array(
	'closethread-solved-category'     => 'Wikiforum_closed_and_solved',
	'closethread-solved-text'         => 'Close this thread as solved',
	'closethread-notsolved-category'  => 'Wikiforum_closed_but_not_solved',
	'closethread-notsolved-text'      => 'Close thread as \'\'\'not\'\'\' solved',
	'closethread-justclosed-category' => 'Wikiforum_just_closed',
	'closethread-justclosed-text'     => 'Just close this thread',
	'replylink'                       => 'Reply',
);

/** Message documentation (Message documentation)
 * @author Hulsman
 * @author Robert
 */
$messages['documentation'] = array(
	'closethread-solved-category' => "The category name which is given to the thread when the thread is closed and solved.

Allways use in the category name the prefix : '''Wikiforum'''",
	'closethread-solved-text' => 'These words appear in the thread header when the person who started the thread does reply.',
	'closethread-notsolved-category' => "The category name which is given to the thread when the thread is : closed and NOT solved.

Allways use in the category name the prefix : '''Wikiforum'''",
	'closethread-notsolved-text' => 'These words appear in the thread header when the person who started the thread does reply.',
	'closethread-justclosed-category' => "The category name which is given to the thread when the thread is just closed. So indifferent to solved or not solved.

Allways use in the category name the prefix : '''Wikiforum'''",
	'closethread-justclosed-text' => 'These words appear in the thread header when the person who started the thread does reply.',
);

/** Dutch (Nederlands)
 * @author Hulsman
 */
$messages['nl'] = array(
	'closethread-solved-category' => 'Wikiforum_gesloten_en_opgelost',
	'closethread-solved-text' => 'Sluit dit gesprek af als opgelost',
	'closethread-notsolved-category' => 'Wikiforum_gesloten_maar_niet_opgelost',
	'closethread-notsolved-text' => "Sluit dit gesprek af als '''niet''' opgelost",
	'closethread-justclosed-category' => 'Wikiforum_gewoon_gesloten',
	'closethread-justclosed-text' => 'Sluit gewoon dit gesprek af',
);
