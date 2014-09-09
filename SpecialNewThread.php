<?php
$dir = dirname(__FILE__) . '/';
$wgExtensionMessagesFiles['wikiforum'] = $dir . 'Wikiation_wikiforum.i18n.php';
# show form
$wgExtensionFunctions[] = 'wfSpecialNewThread';
function wfSpecialNewThread ( ) {
	wfUsePHP( 5.1 );
	wfUseMW( '1.6alpha' );
	class SpecialNewThread extends SpecialPage {
		var $mHiddenFormHTML = '';
		public function __construct( $name ) {
			global $wgUser;
			# user needs to be logged in, thread owner does not work otherwise
			if($wgUser->isLoggedIn())
				SpecialPage::SpecialPage( $name, 'edit' );
			else
				return;
			$this->includable( true );
		}

		function getDescription() {
			$name = strtolower($this->mName);
			return wfMsg('title-special-'.$name);
		}

		private function getCat($par='') {
			global $wgRequest;
			$cat = $wgRequest->getVal('cat');
			if($cat == '') {
                                if($par == '') {
                                    $snips = preg_split('/:(NewThread|NieuwWikiForumGesprek|NieuweMededeling)\//',$_SERVER['SCRIPT_URL'], 2);
                                    if(count($snips) > 1)
                                        $par = end($snips);
                                }
                                if($par != '') {
					$args = explode('/', $par);
					if(count($args) == 1 
						and ($this->mName == 'NieuwWikiForumGesprek' 
						or $this->mName == 'NieuweMededeling')
					) {
					    $cat = $args[0];
                                        } else if(count($args) > 1) {
                                            $cat = $args[1];
                                        }
				}
			}
			return htmlspecialchars($cat);
		}
		private function getNS($par='') {
			global $wgRequest;
			if($this->mName == 'NieuwWikiForumGesprek')
			    return 1000;
			else if($this->mName == 'NieuweMededeling')
			    return 1002;
			$ns = $wgRequest->getVal('ns');
			if($ns == '') {
                                if($par == '') {
				    $snips = explode('NewThread/',$_SERVER['SCRIPT_URL']);
                                    if(count($snips) > 1)
                                        $par = end($snips);
                                }
				if($par != '') {
					$args = explode('/', $par);
					$ns = $args[0];
				}
			}
			return htmlspecialchars($ns);
		}
		private function validate_title($summary, $ns) {
			global $wgOut, $wgRequest;
			$summary = trim($summary, "_ ");
			$title = Title::newFromText($summary, $ns);
			if($title === NULL) {
				# Don't save a new article if it's blank.
				if ( ($summary == '') ) {
					$wgOut->addHtml('<p>'.wfMsg('pleaseprovidesubject').'</p>');
					# re-display edit form
					return NULL;
				}

				if($wgRequest->getBool('replacechars')) {
					$summary = preg_replace('/[][{}|#<>]+/', '_', $summary);
					$title = Title::newFromText($summary, $ns);
				} else {
					$wgOut->setHTMLTitle( wfMsg('invalidtitlechars'));

					# highlight invalid chars in the
					# provided subject
					$hlsummary = preg_replace('/(([][{}|#]|&lt;|&gt;)*)/', 
						'<span style="color: red">$1</span>', 
						htmlspecialchars($summary));
					$this->mHiddenFormHTML = '<input type="checkbox" name="replacechars" value="1" />' .
						'<span id="replacecharslabel"><label for="replacechars">'.
						wfMsg('invalidtitlechars-replacelabel').
						'</label></span><br/>';



					$wgOut->addHtml(
						'<p>' .
						wfMsg('invalidtitlechars-explanation-part1') . 
						'<code>[]{}|#&lt;&gt;</code><br/>' .
						wfMsg('invalidtitlechars-explanation-part2') . 
						'<br/><strong>' . $hlsummary . '</strong></p>');
					return NULL;
				}
			}
			return $title;
		}


		public function execute($par = null) {
			global $wgTitle, $wgOut, $wgRequest, $wgUser;
                        global $wgContLang;

			wfLoadExtensionMessages('wikiforum');
			# some desciptive titles
			$wgOut->setPageTitle( $this->getDescription() );
			$wgOut->setHTMLTitle( $this->getDescription() );
			#$wgOut->setSubtitle( $str );
			$preview = $wgRequest->getVal('wpNTPreview');

			if($wgRequest->wasPosted() && $preview == '' && $wgRequest->getVal('wpNTSave')) {
				$cat = $this->getCat($par);
				$ns = $this->getNS($par);
				# process data:
				# second arg: namespace
				global $wgForumNamespace;
				if ( $ns == '' ) {
					if(isset($wgForumNamespace))
						$ns = $wgForumNamespace;
					else
						$ns = 1;
				}
				$summary = $wgRequest->getVal('wpSummary');
				$textbox1 = $wgRequest->getVal('wpTextbox1');

				$title = $this->validate_title($summary, $ns);
				if($title === NULL) {
					$this->showForm($summary, $textbox1, $par);
					return;
				}
				$summary = $title->getDBKey();

				$article = new Article( $title );
				# check if page exists
				if($title->exists()) {
					$wgOut->setHTMLTitle( wfMsg('pagealreadyexists'));

					# show existing page for reference
					
					$wgOut->addHtml('<p>'.
						wfMsg('pagealreadyexists-explanation-part1') . 
						$wgOut->parse($article->getContent()).
						'<hr>' .
						wfMsg('pagealreadyexists-explanation-part2') . 

						'</p>');
					# re-display edit form
					$this->showForm($summary, $textbox1, $par);
				} else {
					# save page
					if ( !$wgUser->isAllowed('edit') ) {
						if ( $wgUser->isAnon() ) {
							EditPage::userNotLoggedInPage();
							return false;
						}
						else {
                                                        EditPage::noCreatePermission();
							return false;
						}
					}
					$aid = $title->getArticleID( GAID_FOR_UPDATE );
					if ( 0 == $aid ) {
						// Late check for create permission, just in case *PARANOIA*
						if ( !$title->userCan('create') ) {
							EditPage::noCreatePermission();
							return;
						}

						# Don't save a new article if it's blank.
						if ( ( '' == $textbox1 ) ) {
							$wgOut->addHtml('<p>'.wfMsg('pleaseprovidebody').'</p>');
							# re-display edit form
							$this->showForm($summary, $textbox1, $par);
							return false;
						}

						# If no edit comment was given when creating a new page, and what's being
						# created is a redirect, be smart and fill in a neat auto-comment
						if( $summary == '' ) {
							$rt = Title::newFromRedirect( $textbox1 );
							if( is_object( $rt ) )
								$this->summary = wfMsgForContent( 'autoredircomment', $rt->getPrefixedText() );
						}

						$isComment=($this->section=='new');
                                                $catns =  $wgContLang->getNsText( NS_CATEGORY );
						$textbox1 .= " --~~~~\n\n[[$catns:Wikiforum open]]";
						if($cat != '')
						    $textbox1 .= "[[$cat]]";
						$article->insertNewArticle($textbox1, $summary,
							false, true, false, $isComment);

						wfProfileOut( $fname );
					}
					$wgOut->redirect( $title->getFullURL() );
                                        $wgOut->output();
				}


					# create new page or display content plus form
			} else {
                               if ( !$wgUser->isAllowed('edit') ) {
                                       $wgOut->setPageTitle( wfMsg( 'nocreatetitle' ) );
                                       $wgOut->addWikiText( wfMsg( 'youneedtologintocreateanewthread' ) );
                                       return false;
                               } else

				       $this->showForm($wgRequest->getVal('wpSummary'), 
                                       $wgRequest->getVal('wpTextbox1'),
                                       $par);

			}
		}

		private function showForm($summary = "", $text = "", $par=null) {
			global $wgOut, $wgContLang,$wgRequest, $wgParser;
			$cat = $this->getCat($par);
			$ns = $this->getNS($par);
			$summary = htmlspecialchars( $wgContLang->recodeForEdit( $summary ) );
			$text = htmlspecialchars( $wgContLang->recodeForEdit( $text ) );
			$title = NULL;
			if($wgRequest->getVal('wpNTPreview') != ''
				&& $title = $this->validate_title($summary, $ns))
			{
				$summary = $title->getDBKey();
				$previewhead = '<h2>' . htmlspecialchars( wfMsg( 'preview' ) ) . "</h2>\n" .
					"<div class='previewnote'>" . 
					$wgOut->parse( wfMsg('previewnote') ) . "</div>\n";

				$preview = '<h1>' . $title->getPrefixedText() . '</h1>' . 
					$wgParser->parse(
						$text,
						$title,
						$wgOut->parserOptions()
					)->getText() . '<p><br /></p>';
			} else {
				$previewhead = '';
				$preview = '';
			}	
			$wgOut->addHtml($previewhead . '<div id="wikiPreview">'.$preview.'</div>
				<form id="editform" name="editform" method="post" action="" enctype="multipart/form-data">
				<input type="hidden" name="cat" value="'.$cat.'" />
				<input type="hidden" name="ns" value="'.$ns.'" />
				<span id="wpSummaryLabel"><label for="wpSummary">'.wfMsg('subject').'</label></span>
				<div class="editOptions">
				<input tabindex="1" type="text" value="'. $summary . '" name="wpSummary" id="wpSummary" maxlength="200" size="60" /><br /><input type="hidden" name="page" value="new">
				'.$this->mHiddenFormHTML.'
				<textarea tabindex="1" accesskey="," name="wpTextbox1" id="wpTextbox1" rows="40"
				cols="80" >' . $text . '</textarea>
				<div class="editButtons">
				<input id="wpNTSave" name="wpNTSave" type="submit" tabindex="5" value="'.wfMsg('savenewthread').'" accesskey="s" />
				<input id="wpNTPreview" name="wpNTPreview" type="submit" tabindex="6" value="'.wfMsg('showpreview').'" accesskey="' . wfMsg('accesskey-preview') . '" title="'.wfMsg('preview').'" />
				<span class="editHelp"><a target="helpwindow" href="/'.wfMsg('edithelppage').'">'.wfMsg('edithelp').'</a> '.wfMsg('newwindow').'</span>
				</div><!-- editButtons -->
				</div><!-- editOptions --><div class="mw-editTools">' .
				wfMsgExt('edittools', array( 'content', 'parse' ), array() ) .
				'</div></form>');
		}
	}
	$sp = new SpecialNewThread('NewThread');
	$sp->mListed = true;
	SpecialPage::setGroup($sp, 'wikiation');
	SpecialPage::addPage( $sp );
	$sp = new SpecialNewThread('NieuwWikiForumGesprek');
	$sp->mListed = false;
	SpecialPage::setGroup($sp, 'wikiation');
	SpecialPage::addPage( $sp );
	#$sp = new SpecialNewThread('NieuweMededeling');
	#$sp->mListed = true;
	#SpecialPage::addPage( $sp );
}

