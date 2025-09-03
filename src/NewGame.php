<?php

/*
* Script to create a new game entry in the DB. Exclusive to authorized users.
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

// Form components
$formComp = array('errors' => '',
'tag' => '',
'thumbnail' => './defaultthumbnail.jpg',
'genre' => $genresAsString,
'publisher' => '',
'developer' => '',
'publication_day' => $daysAsString,
'publication_month' => $monthsAsString,
'publication_year' => '2014||'.$yearsAsString,
'hardware' => $hardwareAsString,
'aliases' => '',
'aliasesList' => '');

// Input only (distinct from above, as items in select fields are not present)
$formInput = array('tag' => '',
'thumbnail' => './defaultthumbnail.jpg',
'genre' => '',
'publisher' => '',
'developer' => '',
'publication_day' => 1,
'publication_month' => 1,
'publication_year' => 1970,
'hardware' => '',
'aliases' => '');

// Form treatment starts here
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
         if($formInput[$inputList[$i]] === '' && $inputList[$i] !== 'aliases')
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
   
   // Checks the title of the game is not used for aliases
   $titleOK = false;
   if(strlen($formInput['tag']) > 0)
   {
      try
      {
         $titleTag = new Tag($formInput['tag']);
         if($titleTag->countAliases() == 0 && $titleTag->canBeAnAlias())
            $titleOK = true;
      }
      catch(Exception $e)
      {
         $formComp['errors'] .= 'dbError|';
      }
   }
   
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
   $aliasesArr = explode('|', $formInput['aliases']);
   
   // Various errors (title already used for alias, wrong genre, etc.)
   if(!$fullyCompleted)
      $formComp['errors'] .= 'emptyFields|';
   if(strlen($formInput['tag']) > 100 || strlen($formInput['publisher']) > 50 || strlen($formInput['developer']) > 50)
      $formComp['errors'] .= 'tooLongData|';
   if($formInput['thumbnail'] === './defaultthumbnail.jpg' || !file_exists(PathHandler::WWW_PATH().substr($formInput['thumbnail'], 2)))
      $formComp['errors'] .= 'invalidThumbnail|';
   if(!$titleOK)
      $formComp['errors'] .= 'invalidTitle|';
   if(!in_array($formInput['genre'], $genres))
      $formComp['errors'] .= 'invalidGenre|';
   if($consoles === '')
      $formComp['errors'] .= 'invalidHardware|';
   if(!$dateOK)
      $formComp['errors'] .= 'invalidDate|';
      
   if(strlen($formComp['errors']) == 0)
   {
      // Finally inserts the game (new error display in case of DB problem)
      try
      {
         $formattedDate = (string) $y.'-'.($m < 10 ? '0'.(string) $m : (string) $m);
         $formattedDate .= '-'.($d < 10 ? '0'.(string) $d : (string) $d).' 00:00:00';
         
         $gameData = array('tag' => $formInput['tag'],
         'genre' => $formInput['genre'],
         'publisher' => $formInput['publisher'],
         'developer' => $formInput['developer'],
         'publicationDate' => $formattedDate,
         'hardware' => $consoles);
         
         $newGame = Game::insert($gameData);
      }
      catch(Exception $e)
      {
         $formComp['errors'] = 'dbError';
         $formComp['tag'] = $formInput['tag'];
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
         $formComp['aliasesList'] = Keywords::displayAliases($aliasesArr);
      
         $formTpl = TemplateEngine::parse('view/content/NewGame.form.ctpl', $formComp);
         WebpageHandler::wrap($formTpl, 'Ajouter un jeu dans la base de données', $dialogs);
      }
   
      $fileName = substr(strrchr($formInput['thumbnail'], '/'), 1);
      Buffer::save('upload/games/'.PathHandler::formatForURL($formInput['tag']), $fileName, 'thumbnail1');
   
      // Creates aliases; moves to next iteration if alias could not be created
      for($i = 0; $i < count($aliasesArr) && $i < 10; $i++)
      {
         if(strlen($aliasesArr[$i]) == 0)
            continue;
      
         try
         {
            $tag = new Tag($aliasesArr[$i]);
            if($tag->canBeAnAlias())
               $tag->createAlias($formInput['tag']);
            else
               continue;
         }
         catch(Exception $e)
         {
            continue;
         }
      }
      
      // Success page
      $newGameURL = PathHandler::gameURL($newGame->getAll());
      $tplInput = array('title' => $newGame->get('tag'), 'target' => $newGameURL);
      $successPage = TemplateEngine::parse('view/content/NewGame.success.ctpl', $tplInput);
      WebpageHandler::resetDisplay();
      WebpageHandler::wrap($successPage, 'Ajouter un jeu dans la base de données');
   }
   else
   {
      $formComp['errors'] = substr($formComp['errors'], 0, -1);
      $formComp['tag'] = $formInput['tag'];
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
      $formComp['aliasesList'] = Keywords::displayAliases($aliasesArr);
      
      $formTpl = TemplateEngine::parse('view/content/NewGame.form.ctpl', $formComp);
      WebpageHandler::wrap($formTpl, 'Ajouter un jeu dans la base de données', $dialogs);
   }
}
else
{
   $formTpl = TemplateEngine::parse('view/content/NewGame.form.ctpl', $formComp);
   WebpageHandler::wrap($formTpl, 'Ajouter un jeu dans la base de données', $dialogs);
}

?>
