<?php

/*
* Script to edit a review. As long as the current user is the author of the review, (s)he can edit 
* it.
*/

require './libraries/Header.lib.php';
require './libraries/Keywords.lib.php';
require './libraries/FormParsing.lib.php';
require './model/Article.class.php';
require './model/Game.class.php';
require './model/Trope.class.php';
require './model/Review.class.php';

WebpageHandler::redirectionAtLoggingIn();

// Error if the user is not logged in
if(!LoggedUser::isLoggedIn())
{
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}

// Obtains review ID and retrieves the corresponding entry
if(!empty($_GET['id_review']) && preg_match('#^([0-9]+)$#', $_GET['id_review']))
{
   $reviewID = intval(Utils::secure($_GET['id_review']));
   $review = NULL;
   $game = NULL;
   $tropes = NULL;
   try
   {
      $review = new Review($reviewID);
      $game = new Game($review->get('game'));
      $review->getTropes();
      $tropes = $review->getTropesSimple();
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'missingContent';
      $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Evaluation introuvable');
   }
   
   // Forbidden access if the user's neither the author, neither an admin
   if(!$review->isMine() && !Utils::check(LoggedUser::$data['can_edit_all_posts']))
   {
      $tplInput = array('error' => 'notYours');
      $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Cette évaluation n\'est pas la vôtre');
   }
   
   // Webpage settings
   WebpageHandler::addCSS('content_edition');
   WebpageHandler::addJS('formatting');
   WebpageHandler::addJS('content_editor');
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
   
   // Edition form components (with current values)
   $formData = array('URL' => PathHandler::reviewURL($review->getAll()),
   'gameURL' => PathHandler::gameURL($game->getAll()), 
   'game' => $review->get('game'), 
   'success' => '', 
   'errors' => '', 
   'ID' => $review->get('id_commentable'), 
   'rating' => $ratings[$review->get('rating') - 1].'||'.implode('|', $ratings), 
   'title' => $review->get('title'),
   'comment' => FormParsing::unparse($review->get('comment')), 
   'tropesList' => '', 
   'related' => '', 
   'tropes' => '');
   
   // Content of the "related" form, which depends whether the user has articles or not
   $relatedData = array('related_pick' => '', 
   'related_id' => '', 
   'related_url' => '', 
   'related_title' => '');
   
   // Puts the tropes back into a single string
   $parsedTropes = implode('|', $tropes);
   $formData['tropes'] = $parsedTropes;
   $formData['tropesList'] = Keywords::displayTropes($tropes);
   
   /****** PRE-PROCESSING FORM ******/
   
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
      
      if($review->get('id_article') != NULL && $review->get('id_article') != 0)
      {
         $relatedData['related_id'] = $review->get('id_article').'||'.$articlesStr;
         $relatedData['related_pick'] = 'article';
      }
      else if($review->get('external_link') != NULL)
      {
         $relatedSplit = explode('|', $review->get('external_link'));
         $relatedData['related_url'] = $relatedSplit[0];
         $relatedData['related_title'] = $relatedSplit[1];
         $relatedData['related_pick'] = 'external';
      }
      else
      {
         $relatedData['related_id'] = $articles[0]['id_article'].'||'.$articlesStr;
         $relatedData['related_pick'] = 'nothing';
      }
   }
   else
   {
      $externalOnly = true;
      if($review->get('external_link') != NULL)
      {
         $relatedSplit = explode('|', $review->get('external_link'));
         $relatedData['related_url'] = $relatedSplit[0];
         $relatedData['related_title'] = $relatedSplit[1];
      }
   }

   /****** END OF PRE-PROCESSING ******/
   
   // Form treatment is similar to that of NewReview.php
   if(!empty($_POST['sent']))
   {
      $formData['rating'] = Utils::secure($_POST['rating']);
      $formData['title'] = Utils::secure($_POST['title']);
      $formData['comment'] = Utils::secure($_POST['message']);
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
      
      $newTropes = explode('|', $formData['tropes']); // Here because of error case (cf. below)
      
      // Various errors (title already used for alias, wrong genre, etc.)
      if(strlen($formData['title']) == 0 || strlen($formData['comment']) == 0)
         $formData['errors'] .= 'emptyFields|';
      if(strlen($formData['title']) > 60)
         $formData['errors'] .= 'titleTooLong|';
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
         // Finally updates the review
         try
         {
            if($relatedData['related_pick'] === 'article' || $relatedData['related_pick'] == 'external')
            {
               $newRelated = 0;
               if($relatedData['related_pick'] === 'article')
                  $newRelated = $relatedID;
               else if(strlen($relatedData['related_url']) > 0 && strlen($relatedData['related_title']) > 0)
                  $newRelated = $relatedData['related_url'].'|'.$relatedData['related_title'];
               $review->edit($ratingsToInt[$formData['rating']], $formData['title'], FormParsing::parse($formData['comment']), $newRelated);
            }
            else
            {
               $review->edit($ratingsToInt[$formData['rating']], $formData['title'], FormParsing::parse($formData['comment']));
            }
         }
         catch(Exception $e)
         {
            $formData['tropesList'] = Keywords::displayTropes($newTropes);
            if($externalOnly)
               $formData['related'] = TemplateEngine::parse('view/content/RelatedURL.subform.ctpl', $relatedData);
            else
               $formData['related'] = TemplateEngine::parse('view/content/RelatedContent.subform.ctpl', $relatedData);
            $formData['errors'] = 'dbError';
            $formTpl = TemplateEngine::parse('view/content/EditReview.form.ctpl', $formData);
            WebpageHandler::wrap($formTpl, 'Modifier mon évaluation de '.$review->get('game'), $dialogs);
         }
         
         // Updates the tropes
         $nbCommonTropes = sizeof(Keywords::common($tropes, $newTropes));
         $tropesToDelete = Keywords::distinct($tropes, $newTropes);
         $tropesToAdd = Keywords::distinct($newTropes, $tropes);
         
         // Deletes the tropes absent from the new string
         try
         {
            Trope::unmap($review->get('id_commentable'), $tropesToDelete);
         }
         catch(Exception $e) { } // No dedicated error printed for now
         
         // Adds the new tropes (maximum 10 - $nbCommonTropes)
         for($j = 0; $j < count($tropesToAdd) && $j < (10 - $nbCommonTropes); $j++)
         {
            try
            {
               $trope = new Trope($tropesToAdd[$j]);
               $trope->mapTo($review->get('id_commentable'));
            }
            catch(Exception $e)
            {
               continue;
            }
         }
         
         // Registers tropes within the review entry (trope denoted by name, separated with |)
         try
         {
            $review->registerTropes();
         }
         catch(Exception $e) { }
         
         // Reloads page and notifies the user everything was updated
         $formData['tropesList'] = Keywords::displayTropes($newTropes);
         if($externalOnly)
            $formData['related'] = TemplateEngine::parse('view/content/RelatedURL.subform.ctpl', $relatedData);
         else
            $formData['related'] = TemplateEngine::parse('view/content/RelatedContent.subform.ctpl', $relatedData);
         $formData['success'] = 'yes';
         $formTpl = TemplateEngine::parse('view/content/EditReview.form.ctpl', $formData);
         WebpageHandler::wrap($formTpl, 'Modifier mon évaluation de '.$review->get('game'), $dialogs);
      }
      else
      {
         $formData['tropesList'] = Keywords::displayTropes($newTropes);
         if($externalOnly)
            $formData['related'] = TemplateEngine::parse('view/content/RelatedURL.subform.ctpl', $relatedData);
         else
            $formData['related'] = TemplateEngine::parse('view/content/RelatedContent.subform.ctpl', $relatedData);
         $formData['errors'] = substr($formData['errors'], 0, -1);
         $formTpl = TemplateEngine::parse('view/content/EditReview.form.ctpl', $formData);
         WebpageHandler::wrap($formTpl, 'Modifier mon évaluation de '.$review->get('game'), $dialogs);
      }
   }
   else
   {
      if($externalOnly)
         $formData['related'] = TemplateEngine::parse('view/content/RelatedURL.subform.ctpl', $relatedData);
      else
         $formData['related'] = TemplateEngine::parse('view/content/RelatedContent.subform.ctpl', $relatedData);
      $formTpl = TemplateEngine::parse('view/content/EditReview.form.ctpl', $formData);
      WebpageHandler::wrap($formTpl, 'Modifier mon évaluation de '.$review->get('game'), $dialogs);
   }
}
else
{
   $tplInput = array('error' => 'missingID');
   $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Une erreur est survenue');
}

?>
