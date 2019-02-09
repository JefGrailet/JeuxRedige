<?php

/*
* Script to create a review, i.e., a short comment on a game entry coming along a text rating and 
* some "tropes" the author identified in it. Unlike an article (segment), a review does not come 
* along with uploads. It can however be linked to an existing article (either on the site, either 
* from a remote resource).
*/

require './libraries/Header.lib.php';
require './libraries/Keywords.lib.php';
require './libraries/FormParsing.lib.php';
require './model/Article.class.php';
require './model/Game.class.php';
require './model/Trope.class.php';
require './model/Review.class.php';

WebpageHandler::redirectionAtLoggingIn();

// Errors where the user is either not logged in either not allowed to create content
if(!LoggedUser::isLoggedIn())
{
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}
if(!Utils::check(LoggedUser::$fullData['advanced_features']))
{
   $tplInput = array('error' => 'forbiddenAccess');
   $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous n\'êtes pas autorisé à utiliser cette page');
}

// Webpage settings
WebpageHandler::addCSS('content_edition');
WebpageHandler::addJS('formatting');
WebpageHandler::addJS('content_editor');
WebpageHandler::addJS('games'); // For game selection
WebpageHandler::addJS('tropes'); // For trope selection

$dialogs = '';
$formattingDialogsTpl = TemplateEngine::parse('view/dialog/Formatting.multiple.ctpl');
if(!TemplateEngine::hasFailed($formattingDialogsTpl))
   $dialogs .= $formattingDialogsTpl;

// Arrays to handle the ratings
$ratings = array('À éviter absolument', 
'Médiocre', 
'Passable', 
'Moyen', 
'Pour les fans du genre', 
'Honnête', 
'Bon', 
'Très bon',
'Excellent', 
'À essayer absolument');

$ratingsToInt = array('À éviter absolument' => 1, 
'Médiocre' => 2, 
'Passable' => 3, 
'Moyen' => 4, 
'Pour les fans du genre' => 5, 
'Honnête' => 6, 
'Bon' => 7, 
'Très bon' => 8,
'Excellent' => 9, 
'À essayer absolument' => 10);

// Content of the main form
$formData = array('errors' => '', 
'displayedGame' => '', 
'rating' => $ratings[0].'||'.implode('|', $ratings), 
'title' => '',
'comment' => '',
'tropesList' => '', 
'related' => '', 
'game' => '', 
'tropes' => '');

// Content of the "related" form, which depends whether the user has articles or not
$relatedData = array('related_pick' => '', 
'related_id' => '', 
'related_url' => '',
'related_title' => '');

/****** PRE-PROCESSING FORM ******/

// Game name might be in the URL, otherwise the form will ask the name of the evaluated game
if(!empty($_GET['game']))
{
   $gameTitle = Utils::secure(urldecode($_GET['game']));
   try
   {
      $preselectedGame = new Game($gameTitle);
      $existingID = Review::hasReviewed($preselectedGame->get('tag'));
      if($existingID != 0)
      {
         $existingReview = new Review($existingID);
         $tplInput = array('error' => 'oncePerGame||'.PathHandler::reviewURL($existingReview->getAll()));
         $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
         WebpageHandler::wrap($tpl, 'Vous avez déjà évalué ce jeu');
      }
      $formData['displayedGame'] = 'chosen||'.$gameTitle.'|'.PathHandler::gameURL($preselectedGame->getAll());
      $formData['game'] = $gameTitle;
   }
   catch(Exception $e)
   {
      $formData['displayedGame'] = 'pick';
   }
}
else
{
   $formData['displayedGame'] = 'pick';
}

// Lists articles of current user to prepare the "related" part
$articles = NULL;
try
{
   $articles = Article::listAllMyArticles();
}
catch(Exception $e) { }

$externalOnly = false;
$articlesIDs = array();
if($articles != NULL)
{
   $articlesStr = '';
   for($i = 0; $i < count($articles); ++$i)
   {
      if($i > 0)
         $articlesStr .= '|';
      $artTitle = $articles[$i]['title'].' - '.$articles[$i]['subtitle'];
      $artTitle = str_replace('|', '', $artTitle); // str_replace by security
      $articlesStr .= $articles[$i]['id_article'].','.$artTitle;
      array_push($articlesIDs, $articles[$i]['id_article']);
   }
   $relatedData['related_id'] = $articles[0]['id_article'].'||'.$articlesStr;
   $relatedData['related_pick'] = 'nothing';
}
else
{
   $externalOnly = true;
}

/****** END OF PRE-PROCESSING ******/

// Form treatment directly starts here, as there are no pre-requisite
if(!empty($_POST['sent']))
{
   $formData['rating'] = Utils::secure($_POST['rating']);
   $formData['title'] = Utils::secure($_POST['title']);
   $formData['comment'] = Utils::secure($_POST['message']);
   $formData['game'] = Utils::secure($_POST['game']);
   $formData['tropes'] = Utils::secure($_POST['tropes']);
   
   $relatedID = 0;
   if(!$externalOnly)
   {
      $relatedData['related_pick'] = Utils::secure($_POST['related_pick']);
      $relatedID = intval(Utils::secure($_POST['related_id']));
      $relatedData['related_id'] = $relatedID.'||'.$articlesStr;
   }
   else
      $relatedData['related_pick'] = 'external';
   $relatedData['related_url'] = str_replace('|', '', Utils::secure($_POST['related_url']));
   $relatedData['related_title'] = str_replace('|', '', Utils::secure($_POST['related_title']));
   
   $validGame = false;
   $userReviewed = false;
   $selectedGame = null;
   try
   {
      $selectedGame = new Game($formData['game']);
      $validGame = true;
      $userReviewed = Review::hasReviewed($selectedGame->get('tag'));
   }
   catch(Exception $e) { }
   
   $tropesArr = explode('|', $formData['tropes']); // Here because of error case (cf. below)
   
   // Various possible errors
   if(strlen($formData['game']) == 0 || strlen($formData['title']) == 0 || strlen($formData['comment']) == 0)
      $formData['errors'] .= 'emptyFields|';
   if(strlen($formData['title']) > 60)
      $formData['errors'] .= 'titleTooLong|';
   if(strlen($formData['game']) > 0 && !$validGame)
      $formData['errors'] .= 'invalidGame|';
   if(strlen($formData['game']) > 0 && $validGame && $userReviewed != 0)
      $formData['errors'] .= 'duplicateReview|';
   if(!in_array($formData['rating'], $ratings))
      $formData['errors'] .= 'invalidRating|';
   if($relatedData['related_pick'] === 'article' && $relatedID != 0 && !in_array($relatedID, $articlesIDs))
      $formData['errors'] .= 'invalidID|';
   if($relatedData['related_pick'] === 'external' && strlen($relatedData['related_url']) > 0 && !preg_match('/^(https?:\/\/(?:www\.|(?!www))?[^\s\.]+\.[^\s]{2,}|www\.[^\s]+\.[^\s]{2,})$/', $relatedData['related_url']))
      $formData['errors'] .= 'invalidURL|';
   if($relatedData['related_pick'] === 'external' && strlen($relatedData['related_url']) > 0 && strlen($relatedData['related_title']) == 0)
      $formData['errors'] .= 'missingURLTitle|';
   
   if(strlen($formData['errors']) == 0)
   {
      $newReview = null;
      try
      {
         if($relatedData['related_pick'] === 'article' || $relatedData['related_pick'] == 'external')
         {
            $relatedFinal =  $relatedData['related_url'].'|'.$relatedData['related_title'];
            if($relatedData['related_pick'] === 'article')
               $relatedFinal = $relatedID;
            
            $newReview = Review::insert($selectedGame->get('tag'), 
                                        $ratingsToInt[$formData['rating']],
                                        $formData['title'], 
                                        FormParsing::parse($formData['comment']),
                                        $relatedFinal);
         }
         else
         {
            $newReview = Review::insert($selectedGame->get('tag'), 
                                        $ratingsToInt[$formData['rating']],
                                        $formData['title'], 
                                        FormParsing::parse($formData['comment']));
         }
      }
      catch(Exception $e)
      {
         // Completing some fields
         if(strlen($formData['game']) > 0 && $validGame)
            $formData['displayedGame'] = 'chosen||'.$formData['game'].'|'.PathHandler::gameURL($selectedGame->getAll());
         else
            $formData['displayedGame'] = 'pick';
         $formData['tropesList'] = Keywords::displayTropes($tropesArr);
         
         if($externalOnly)
            $formData['related'] = TemplateEngine::parse('view/content/RelatedURL.subform.ctpl', $relatedData);
         else
            $formData['related'] = TemplateEngine::parse('view/content/RelatedContent.subform.ctpl', $relatedData);
         $formData['errors'] = 'dbError';
         $formTpl = TemplateEngine::parse('view/content/NewReview.form.ctpl', $formData);
         WebpageHandler::wrap($formTpl, 'Rédiger une nouvelle évaluation', $dialogs);
      }
      
      // Inserts tropes; we move to the next if an exception occurs while mapping the keywords
      for($i = 0; $i < count($tropesArr) && $i < 10; $i++)
      {
         if(strlen($tropesArr[$i]) == 0)
            continue;
      
         try
         {
            $trope = new Trope($tropesArr[$i]);
            $trope->mapTo($newReview->get('id_commentable'));
         }
         catch(Exception $e)
         {
            continue;
         }
      }
      
      // Registers tropes within the review entry (trope denoted by name, separated with |)
      try
      {
         $newReview->registerTropes();
      }
      catch(Exception $e) { }
      
      // Redirection
      $newReviewURL = PathHandler::reviewURL($newReview->getAll());
      header('Location:'.$newReviewURL);
      
      // Success page
      $tplInput = array('target' => $newReviewURL);
      $successPage = TemplateEngine::parse('view/content/NewContent.success.ctpl', $tplInput);
      WebpageHandler::resetDisplay();
      WebpageHandler::wrap($successPage, 'Nouvelle évaluation pour le jeu "'.$selectedGame->get('tag').'"');
   }
   else
   {
      // Completing some fields
      if(strlen($formData['game']) > 0 && $validGame)
         $formData['displayedGame'] = 'chosen||'.$formData['game'].'|'.PathHandler::gameURL($selectedGame->getAll());
      else
         $formData['displayedGame'] = 'pick';
      $formData['tropesList'] = Keywords::displayTropes($tropesArr);
      
      if($externalOnly)
         $formData['related'] = TemplateEngine::parse('view/content/RelatedURL.subform.ctpl', $relatedData);
      else
         $formData['related'] = TemplateEngine::parse('view/content/RelatedContent.subform.ctpl', $relatedData);
      $formData['errors'] = substr($formData['errors'], 0, -1);
      $formTpl = TemplateEngine::parse('view/content/NewReview.form.ctpl', $formData);
      WebpageHandler::wrap($formTpl, 'Rédiger une nouvelle évaluation', $dialogs);
   }
}
else
{
   if($externalOnly)
      $formData['related'] = TemplateEngine::parse('view/content/RelatedURL.subform.ctpl', $relatedData);
   else
      $formData['related'] = TemplateEngine::parse('view/content/RelatedContent.subform.ctpl', $relatedData);
   $formTpl = TemplateEngine::parse('view/content/NewReview.form.ctpl', $formData);
   WebpageHandler::wrap($formTpl, 'Rédiger une nouvelle évaluation', $dialogs);
}

?>