<?php

/*
* Script to edit a game entry in the DB. Exclusive to authorized users.
*/

require './libraries/Header.lib.php';
require './libraries/Keywords.lib.php';
require './libraries/Buffer.lib.php';
require './model/Game.class.php';
require './model/Tag.class.php';

// Errors where the user is either not logged in, either not allowed to edit games
if(!LoggedUser::isLoggedIn())
{
   header('Location:./index.php');
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/content/EditGame.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}
if(!Utils::check(LoggedUser::$fullData['advanced_features']))
{
   $tplInput = array('error' => 'forbiddenAccess');
   $tpl = TemplateEngine::parse('view/content/EditGame.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous n\'êtes pas autorisé à utiliser cette page');
}
/*
TODO (later)
if(!Utils::check(LoggedUser::$data['can_edit_games']))
{
   header('Location:./index.php');
   $tplInput = array('error' => 'forbiddenAccess');
   $tpl = TemplateEngine::parse('view/content/EditGame.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous n\'êtes pas autorisé à utiliser cette page');
}
*/

// Webpage settings
WebpageHandler::addCSS('multiple-select');
WebpageHandler::addJS('jquery.multiple.select');
WebpageHandler::addJS('game_editor');

// Thumbnail creation dialog
$dialogTpl = TemplateEngine::parse('view/dialog/CustomThumbnail.dialog.ctpl');
$dialogs = '';
if(!TemplateEngine::hasFailed($dialogTpl))
   $dialogs = $dialogTpl;

// Retrieves both lists of genres and game consoles and prepares them
$genres = null;
$genresAsString = '';
$hardware = null;
$hardwareAsString = '';
try
{
   $genres = Game::getGenres();
   $hardware = Game::getHardware();
   
   $genresAsString = implode('|', $genres);
   for($i = 0; $i < count($hardware); $i++)
   {
      if($i > 0)
         $hardwareAsString .= '|';
      $hardwareAsString .= $hardware[$i]['code'].','.$hardware[$i]['full_name'];
   }
}
catch(Exception $e)
{
   $tplInput = array('error' => 'dbError');
   $tpl = TemplateEngine::parse('view/content/EditGame.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Une erreur est survenue');
}

$hardwareShort = array();
for($i = 0; $i < count($hardware); $i++)
   array_push($hardwareShort, $hardware[$i]['code']);

// Date components
$days = range(1, 31);
$daysAsString = implode('|', $days);
$months = range(1, 12);
$monthsAsString = implode('|', $months);
$years = range(1970, intval(date('Y', Utils::SQLServerTime())) + 5);
$yearsAsString = implode('|', $years);

// Obtains game title and retrieves the corresponding entry
if(!empty($_GET['game']))
{
   $getTitle = Utils::secure(urldecode($_GET['game']));
   try
   {
      $game = new Game($getTitle);
      $curAliases = $game->getAliases();
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'nonexistingGame';
      $tpl = TemplateEngine::parse('view/content/EditGame.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Jeu introuvable');
   }
   $timestampDate = Utils::toTimestamp($game->get('publication_date'));
   $curD = date('d', $timestampDate);
   $curM = date('m', $timestampDate);
   $curY = date('Y', $timestampDate);
   $thumbnailLocation = './upload/games/'.PathHandler::formatForURL($game->get('tag')).'/thumbnail1.jpg';

   // Form components (with current values)
   $formComp = array('gameURL' => PathHandler::gameURL($game->getAll()), 
   'title' => $game->get('tag'), 
   'success' => '', 
   'errors' => '', 
   'target' => urlencode($game->get('tag')), 
   'thumbnail' => $thumbnailLocation, 
   'genre' => $game->get('genre').'||'.$genresAsString, 
   'publisher' => $game->get('publisher'), 
   'developer' => $game->get('developer'), 
   'publication_day' => $curD.'||'.$daysAsString, 
   'publication_month' => $curM.'||'.$monthsAsString, 
   'publication_year' => $curY.'||'.$yearsAsString, 
   'hardware' => $game->get('hardware').'||'.$hardwareAsString, 
   'aliases' => implode('|', $curAliases), 
   'aliasesList' => Keywords::displayAliases($curAliases));

   // New input only
   $formInput = array('thumbnail' => '',
   'genre' => '',
   'publisher' => '',
   'developer' => '',
   'publication_day' => 1,
   'publication_month' => 1,
   'publication_year' => 1970,
   'hardware' => '',
   'aliases' => '');
   
   // Form treatment is similar to that of NewGame.php
   if(!empty($_POST['sent']))
   {
      $inputList = array_keys($formInput);
      $fullyCompleted = true;
      for($i = 0; $i < count($inputList); $i++)
      {
         if(strpos($inputList[$i], 'publication_') === 0)
            $formInput[$inputList[$i]] = intval($_POST[$inputList[$i]]);
         else if($inputList[$i] === 'hardware')
            $formInput['hardware'] = isset($_POST['hardware']) ? $_POST['hardware'] : null;
         else
         {
            $formInput[$inputList[$i]] = Utils::secure($_POST[$inputList[$i]]);
            if($formInput[$inputList[$i]] === '')
               $fullyCompleted = false;
         }
      }
      
      // Hardware field is a special case (multiselect input)
      $consoles = '';
      for($i = 0; $i < count($formInput['hardware']); $i++)
      {
         if(in_array($formInput['hardware'][$i], $hardwareShort))
            $consoles .= $formInput['hardware'][$i].'|';
      }
      if(strlen($consoles) > 0)
         $consoles = substr($consoles, 0, -1);
      
      // Checks date format
      $dateOK = false;
      $d = $formInput['publication_day'];
      $m = $formInput['publication_month'];
      $y = $formInput['publication_year'];
      if(in_array($d, $days) && in_array($m, $months) && in_array($y, $years))
      {
         $isLeapYear = (($y % 4) == 0) && ((($y % 100) != 0) || (($y % 400) == 0));
         if((in_array($m, array(4,6,9,11)) && $d <= 30) || 
            ($m == 2 && (($isLeapYear && $d <= 29) || $d <= 28)) || 
            (!in_array($m, array(2,4,6,9,11)) && $d <= 31))
            $dateOK = true;
      }
      
      // Aliases
      $newAliases = explode('|', $formInput['aliases']);
      
      // Various errors (title already used for alias, wrong genre, etc.)
      if(!$fullyCompleted)
         $formComp['errors'] .= 'emptyFields|';
      if(strlen($formInput['publisher']) > 50 || strlen($formInput['developer']) > 50)
         $formComp['errors'] .= 'tooLongData|';
      if(!file_exists(PathHandler::WWW_PATH().substr($formInput['thumbnail'], 2)))
         $formComp['errors'] .= 'invalidThumbnail|';
      if(!in_array($formInput['genre'], $genres))
         $formComp['errors'] .= 'invalidGenre|';
      if($consoles === '')
         $formComp['errors'] .= 'invalidHardware|';
      if(!$dateOK)
         $formComp['errors'] .= 'invalidDate|';
      
      if(strlen($formComp['errors']) == 0)
      {
         // Finally updates the game
         try
         {
            $formattedDate = (string) $y.'-'.($m < 10 ? '0'.(string) $m : (string) $m);
            $formattedDate .= '-'.($d < 10 ? '0'.(string) $d : (string) $d).' 00:00:00';
            
            $newGameData = array('genre' => $formInput['genre'],
            'publisher' => $formInput['publisher'],
            'developer' => $formInput['developer'],
            'publicationDate' => $formattedDate,
            'hardware' => $consoles);
            
            $game->update($newGameData);
         }
         catch(Exception $e)
         {
            $formComp['errors'] = 'dbError';
            $formComp['thumbnail'] = $formInput['thumbnail'];
            $formComp['genre'] = $formInput['genre'].'||'.$genresAsString;
            $formComp['publisher'] = $formInput['publisher'];
            $formComp['developer'] = $formInput['developer'];
            $formComp['publication_day'] = (string) $d.'||'.$daysAsString;
            $formComp['publication_month'] = (string) $m.'||'.$monthsAsString;
            $formComp['publication_year'] = (string) $y.'||'.$yearsAsString;
            $formComp['hardware'] = $consoles.'||'.$hardwareAsString;
            $formComp['aliases'] = $formInput['aliases'];
            $formComp['aliasesList'] = Keywords::displayAliases($newAliases);
         
            $formTpl = TemplateEngine::parse('view/content/EditGame.form.ctpl', $formComp);
            WebpageHandler::wrap($formTpl, 'Editer un jeu', $dialogs);
         }
         
         // Updates the thumbnail if edited
         if($formInput['thumbnail'] !== $thumbnailLocation)
         {
            $fileName = substr(strrchr($formInput['thumbnail'], '/'), 1);
            Buffer::save('upload/games/'.PathHandler::formatForURL($game->get('tag')), $fileName, 'thumbnail1');
         }
         
         // Updates the aliases
         $nbCommonAliases = sizeof(Keywords::common($curAliases, $newAliases));
         $aliasesToDelete = Keywords::distinct($curAliases, $newAliases);
         $aliasesToAdd = Keywords::distinct($newAliases, $curAliases);
         
         // Deletes the keywords absent from the new string
         try
         {
            Tag::unmapAliases($game->get('tag'), $aliasesToDelete);
         }
         catch(Exception $e) { } // No dedicated error printed for now
         
         // Adds the new aliases (maximum 10 - $nbCommonAliases)
         for($j = 0; $j < count($aliasesToAdd) && $j < (10 - $nbCommonAliases); $j++)
         {
            try
            {
               $tag = new Tag($aliasesToAdd[$j]);
               if($tag->canBeAnAlias())
                  $tag->createAlias($game->get('tag'));
               else
                  continue;
            }
            catch(Exception $e)
            {
               continue;
            }
         }
         
         // Cleans the DB from tags that are no longer mapped to anything
         Tag::cleanOrphanTags();
         
         // Success page
         $formComp['success'] = 'yes';
         $formComp['thumbnail'] = $formInput['thumbnail'];
         $formComp['genre'] = $formInput['genre'].'||'.$genresAsString;
         $formComp['publisher'] = $formInput['publisher'];
         $formComp['developer'] = $formInput['developer'];
         $formComp['publication_day'] = (string) $d.'||'.$daysAsString;
         $formComp['publication_month'] = (string) $m.'||'.$monthsAsString;
         $formComp['publication_year'] = (string) $y.'||'.$yearsAsString;
         if($consoles !== '')
            $formComp['hardware'] = $consoles.'||'.$hardwareAsString;
         else
            $formComp['hardware'] = $hardwareAsString;
         $formComp['aliases'] = $formInput['aliases'];
         $formComp['aliasesList'] = Keywords::displayAliases($newAliases);
         
         $formTpl = TemplateEngine::parse('view/content/EditGame.form.ctpl', $formComp);
         WebpageHandler::wrap($formTpl, 'Editer un jeu', $dialogs);
      }
      else
      {
         $formComp['errors'] = substr($formComp['errors'], 0, -1);
         $formComp['thumbnail'] = $formInput['thumbnail'];
         $formComp['genre'] = $formInput['genre'].'||'.$genresAsString;
         $formComp['publisher'] = $formInput['publisher'];
         $formComp['developer'] = $formInput['developer'];
         $formComp['publication_day'] = (string) $d.'||'.$daysAsString;
         $formComp['publication_month'] = (string) $m.'||'.$monthsAsString;
         $formComp['publication_year'] = (string) $y.'||'.$yearsAsString;
         if($consoles !== '')
            $formComp['hardware'] = $consoles.'||'.$hardwareAsString;
         else
            $formComp['hardware'] = $hardwareAsString;
         $formComp['aliases'] = $formInput['aliases'];
         $formComp['aliasesList'] = Keywords::displayAliases($newAliases);
         
         $formTpl = TemplateEngine::parse('view/content/EditGame.form.ctpl', $formComp);
         WebpageHandler::wrap($formTpl, 'Editer un jeu', $dialogs);
      }
   }
   else
   {
      $formTpl = TemplateEngine::parse('view/content/EditGame.form.ctpl', $formComp);
      WebpageHandler::wrap($formTpl, 'Editer un jeu', $dialogs);
   }
}
else
{
   $tplInput = array('error' => 'missingGame');
   $tpl = TemplateEngine::parse('view/content/EditGame.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Une erreur est survenue');
}

?>
