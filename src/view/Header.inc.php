<?php

/**
* Header of a page; contains all the HTML that models the design and includes basic JavaScript
* features. The PHP indentation may look odd, but it guarantees that the displayed page remains
* coherent regarding HTML when inspecting the code.
*/

if(!isset($pageTitle) || strlen($pageTitle) == 0)
{
   $pageTitle = 'JeuxRédige.be';
}
else
{
   $pageTitle .= ' - JeuxRédige.be';
}

$webRootPath = PathHandler::HTTP_PATH();

?>
<!DOCTYPE html>
<html lang="fr">
   <head>
      <meta charset="UTF-8" />
      <link rel="stylesheet" href="<?php echo $webRootPath; ?>style/default.css" />
      <link rel="stylesheet" href="<?php echo $webRootPath; ?>style/icons.css" />
      <script text="text/javascript">
      var ConfigurationValues = {};
      ConfigurationValues.HTTP_PATH = '<?php echo $webRootPath; ?>';
      </script>
      <script type="text/javascript" src="<?php echo $webRootPath; ?>javascript/jquery-3.6.1.min.js"></script>
      <script type="text/javascript" src="<?php echo $webRootPath; ?>javascript/default<?php echo PathHandler::JS_EXTENSION(); ?>"></script>
<?php

// After main CSS/JS files, the particular ones
for($i = 0; $i < count(WebpageHandler::$CSSFiles); $i++)
{
   echo '      <link rel="stylesheet" href="'.$webRootPath.'style/'.WebpageHandler::$CSSFiles[$i].'.css" />'."\n";
}
for($i = 0; $i < count(WebpageHandler::$JSFiles); $i++)
{
   echo '      <script type="text/javascript" src="'.$webRootPath.'javascript/'.WebpageHandler::$JSFiles[$i].PathHandler::JS_EXTENSION().'"></script>'."\n";
}

// Auto-activated JS features (navigation mode, auto preview and auto refresh)
$cond1 = in_array('pages', WebpageHandler::$JSFiles) && WebpageHandler::$miscParams['default_nav_mode'] !== 'classic';
$cond2 = in_array('preview', WebpageHandler::$JSFiles) || in_array('quick_preview', WebpageHandler::$JSFiles);
$cond2 = $cond2 || in_array('segment_editor', WebpageHandler::$JSFiles);
$cond2 = $cond2 || in_array('content_editor', WebpageHandler::$JSFiles);
$cond2 = $cond2 && Utils::check(WebpageHandler::$miscParams['auto_preview']);
$cond3 = in_array('refresh', WebpageHandler::$JSFiles) && Utils::check(WebpageHandler::$miscParams['auto_refresh']);
if($cond1 || $cond2 || $cond3)
{
   echo '      <script type="text/javascript">'."\n";
   echo '      $(document).ready(function() {'."\n";

   // Default navigation mode
   if($cond1)
   {
      switch(WebpageHandler::$miscParams['default_nav_mode'])
      {
         case 'dynamic':
            echo '         if (typeof PagesLib !== \'undefined\') { PagesLib.switchNavMode(2); }'."\n";
            break;
         case 'flow':
            echo '         if (typeof PagesLib !== \'undefined\') { PagesLib.switchNavMode(3); }'."\n";
            break;
         default:
            break;
      }
   }

   // Automatic (or quick) preview
   if($cond2)
   {
      echo '         if (typeof PreviewLib !== \'undefined\') { PreviewLib.previewMode(); }'."\n";
   }

   /*
   * Remark for auto preview: previewMode() is defined in both preview.js and
   * quick_preview.js, and as these files are mutually exclusive (never invoked at the same
   * time), we do not need to check here which kind of preview is activated. The existing
   * previewMode() always matches the current type of preview.
   */

   // Automatic refresh
   if($cond3)
      echo '         if (typeof RefreshLib !== \'undefined\') { RefreshLib.changeAutoRefresh(); }'."\n";

   echo '      });'."\n";
   echo '      </script>'."\n";
}

// Meta-tags (it's assumed all meta_ fields are filled if the title is not empty)
if(strlen(WebpageHandler::$miscParams['meta_title']) > 0)
{
   echo '      <meta property="og:title" content="'.WebpageHandler::$miscParams['meta_title'].'">'."\n";
   echo '      <meta property="og:description" content="'.WebpageHandler::$miscParams['meta_description'].'">'."\n";
   echo '      <meta property="og:image" content="'.WebpageHandler::$miscParams['meta_image'].'">'."\n";
   echo '      <meta property="og:url" content="'.WebpageHandler::$miscParams['meta_url'].'">'."\n";

   echo '      <meta property="og:site_name" content="JeuxRédige.be">'."\n";
   echo '      <meta name="twitter:image:alt" content="Vignette">'."\n";
}

// Page title
echo '      <title>'.$pageTitle.'</title>'."\n";
?>
   </head>

   <body>
      <div id="blackScreen"></div>
      <div id="bubble"></div>
<?php

// Other dialog boxes, if provided by the calling code.
if(isset($dialogs) && !empty($dialogs))
{
   echo $dialogs;
}

// Logo (varies for articles)
$selectedLogo = 'default';
$logoVariants = array('chronicle', 'opinion', 'preview', 'review'); // N.B.: could be moved in $miscParams
if(in_array(WebpageHandler::$miscParams['webdesign_variant'], $logoVariants))
   $selectedLogo = WebpageHandler::$miscParams['webdesign_variant'];

// Finally, lightbox for pictures.
?>
      <div id="lightbox" style="display: none;" data-cur-file="none">
         <div class="lightboxContent"></div>
         <div class="lightboxBottom">
            <div class="LBLeft"></div>
            <div class="LBCenter"></div>
            <div class="LBRight"></div>
         </div>
      </div>
      <div id="topBar">
         <div id="mainMenu">
            <div class="mainMenuItem"><a href="<?php echo $webRootPath; ?>" class="mainMenuLogo <?php echo $selectedLogo; ?>"></a></div>
            <div class="mainMenuItem"><a href="<?php echo $webRootPath; ?>Articles.php">Articles</a></div>
            <div class="mainMenuItem"><a href="<?php echo $webRootPath; ?>Forum.php">Forum</a></div>
         </div>
         <div id="userCorner">
<?php
if(LoggedUser::isLoggedIn())
{
   // Account details (avatar, alternate account, etc.)
   $alternateAccount = '';
   $pseudoPart = '<img src="'.PathHandler::getAvatarMedium(LoggedUser::$data['used_pseudo']).'" class="avatarMini" alt="Avatar mini"/> ';
   $adminTools = false;
   if(strlen(LoggedUser::$data['function_pseudo']) > 0 && LoggedUser::$data['function_name'] !== 'alumnus')
   {
      if(LoggedUser::$data['function_pseudo'] === LoggedUser::$data['used_pseudo'])
      {
         if(LoggedUser::$data['function_name'] === 'administrator')
         {
            $pseudoPart .= '<span style="color: rgb(255,63,63);">'.LoggedUser::$data['function_pseudo'].'</span>';
         }
         else
         {
            $pseudoPart .= '<a href="'.$webRootPath.'User.php?user='.LoggedUser::$data['pseudo'].'">'.LoggedUser::$data['pseudo'].'</a>';
         }
         $r = str_replace('&', 'amp;', "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
         $alternateAccount = '<a href="'.$webRootPath.'SwitchAccount.php?pos='.$r.'">Changer pour '.LoggedUser::$data['pseudo'].'</a>';
         $adminTools = true; // Always, for now
      }
      else
      {
         $pseudoPart .= '<a href="'.$webRootPath.'User.php?user='.LoggedUser::$data['pseudo'].'">'.LoggedUser::$data['pseudo'].'</a>';
         if(LoggedUser::$data['function_name'] === 'administrator')
         {
            $r = str_replace('&', 'amp;', "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
            $alternateAccount = '<a href="'.$webRootPath.'SwitchAccount.php?pos='.$r.'">Changer pour <span style="color: rgb(255,63,63);">'.LoggedUser::$data['function_pseudo'].'</span></a>';
         }
      }
   }
   else
   {
      $pseudoPart .= '<a href="'.$webRootPath.'User.php?user='.LoggedUser::$data['pseudo'].'">'.LoggedUser::$data['pseudo'].'</a>';
   }
?>
            <h6><?php echo $pseudoPart; ?></h6>
            <input class="userMenuToggle" title="Ouvrir le menu" type="checkbox">
            <input class="pingsToggle" title="Mes pings" type="checkbox">
            <i class="icon-general_menu"></i>
<?php
   // Padding to be used to align HTML tags
   $padding1 = '               ';
   $padding2 = '                  ';

    // Private messages (or pings)
   if(LoggedUser::$data['new_pings'] > 0)
   {
      echo '            <i class="icon-general_messages" style="color: #4bd568;" title="Mes pings"></i>'."\n";
      echo '            <div class="pingsSlider">'."\n";
      echo $padding1.'<ul id="pings">'."\n";
      for($i = 0; $i < LoggedUser::$data['new_pings'] && $i < 5; $i++)
      {
         echo $padding2.' <li>';
         switch(LoggedUser::$messages[$i]['ping_type'])
         {
            case 'notification':
               echo '<i class="icon-general_alert" style="color: #e04f5f;"></i> ';
               echo LoggedUser::$messages[$i]['title'];
               break;

            case 'ping pong':
               $otherParty = LoggedUser::$messages[$i]['emitter'];
               if($otherParty === LoggedUser::$data['pseudo'])
                  $otherParty = LoggedUser::$messages[$i]['receiver'];
               echo '<i class="icon-general_messages" style="color: #25b6d2;"></i> ';
               echo '<a href="'.$webRootPath.'PrivateDiscussion.php?id_ping='.LoggedUser::$messages[$i]['id_ping'].'"><strong>'.$otherParty.' -</strong> '.LoggedUser::$messages[$i]['title'].'</a>';
               break;

            // Unknown
            default:
               echo '<i class="icon-general_alert" style="color: #f2b851;"></i> ';
               echo LoggedUser::$messages[$i]['title'];
               break;
         }
         echo '</li>'."\n";
      }
      if(LoggedUser::$data['new_pings'] > 5)
         echo $padding2.'<li><i class="icon-menu_lists" style="color: #25b6d2;"></i> <a href="'.$webRootPath.'Pings.php">Liste de mes pings ('.LoggedUser::$data['new_pings'].' nouveaux)</a></li>'."\n";
      else
         echo $padding2.'<li><i class="icon-menu_lists" style="color: #25b6d2;"></i> <a href="'.$webRootPath.'Pings.php">Liste de mes pings</a></li>'."\n";
      echo $padding1.'</ul>'."\n";
      echo '            </div>'."\n";
   }
   else if(LoggedUser::$data['new_pings'] == 0)
   {
      echo '            <i class="icon-general_messages" style="color: #25b6d2;" title="Mes pings"></i>'."\n";
      echo '            <div class="pingsSlider">'."\n";
      echo $padding1.'<ul id="pings">'."\n";
      echo $padding2.'<li><i class="icon-menu_lists" style="color: #25b6d2;"></i> <a href="'.$webRootPath.'Pings.php">Liste de mes pings</a></li>'."\n";
      echo $padding1.'</ul>'."\n";
      echo '            </div>'."\n";
   }
   else
   {
      echo '            <i class="icon-general_messages" style="color: #f2b851;" title="Mes pings"></i>'."\n";
      echo '            <div class="pingsSlider">'."\n";
      echo $padding1.'<ul id="pings">'."\n";
      echo $padding2.'<li>Une erreur est survenue...</li>'."\n";
      echo $padding2.'<li><i class="icon-menu_lists" style="color: #25b6d2;"></i> <a href="'.$webRootPath.'Pings.php">Liste de mes pings</a></li>'."\n";
      echo $padding1.'</ul>'."\n";
      echo '            </div>'."\n";
   }

   // User hamburger menu
   echo '            <div class="userMenu">'."\n";
   echo $padding1.'<ul>'."\n";
   if(strlen($alternateAccount) > 0)
   {
      echo $padding2.'<li><i class="icon-menu_switch"></i> '.$alternateAccount.'</li>'."\n";
   }
   echo $padding2.'<li><i class="icon-general_edit"></i> <a href="'.$webRootPath.'MyAccount.php">Mon compte</a></li>'."\n";
   echo $padding2.'<li><i class="icon-menu_smilies"></i> <a href="'.$webRootPath.'MyEmoticons.php">Mes émoticônes</a></li>'."\n";
   echo $padding2.'<li><i class="icon-general_pin"></i> <a href="'.$webRootPath.'MyPins.php">Mes messages favoris</a></li>'."\n";
   echo $padding2.'<li><i class="icon-menu_articles"></i> <a href="'.$webRootPath.'MyArticles.php">Mes articles</a></li>'."\n";
   echo $padding2.'<li><i class="icon-menu_lists"></i> <a href="'.$webRootPath.'MyLists.php">Mes listes</a><sup>Beta</sup></li>'."\n";
   echo $padding2.'<li><i class="icon-menu_games"></i> <a href="'.$webRootPath.'Games.php">Jeux</a><sup>Beta</sup></li>'."\n";
   echo $padding2.'<li><i class="icon-menu_didyouknow"></i> <a href="'.$webRootPath.'RandomTrivia.php">Le saviez-vous ?</a><sup>Beta</sup></li>'."\n";
   echo $padding2.'<li><i class="icon-menu_invite"></i> <a href="'.$webRootPath.'Sponsorship.php">Inviter un ami</a></li>'."\n";
   if($adminTools)
   {
      echo $padding2.'<li><i class="icon-menu_users"></i> <a href="'.$webRootPath.'Users.php">Utilisateurs</a></li>'."\n";
      echo $padding2.'<li><i class="icon-general_alert"></i> <a href="'.$webRootPath.'Alerts.php">Alertes</a></li>'."\n";
   }
   echo $padding2.'<li></li>'; // Spacing out the log out link
   echo $padding2.'<li></li>'; // Ditto
   // Log out link
   if(WebpageHandler::$redirections['log_out'])
   {
      $r = str_replace('&', 'amp;', "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
      echo $padding2.'<li><i class="icon-menu_logout"></i> <a href="'.$webRootPath.'LogOut.php?redirection='.$r.'">Déconnexion</a></li>'."\n";
   }
   else
   {
      echo $padding2.'<li><i class="icon-menu_logout"></i> <a href="'.$webRootPath.'LogOut.php">Déconnexion</a></li>'."\n";
   }
   echo $padding1.'</ul>'."\n";
   echo '            </div>'."\n";
}
else
{
?>
            <input class="loginToggle" title="Ouvrir le formulaire de connexion" type="checkbox">
            <h6 class="loginToggleUnderlay"><img src="<?php echo $webRootPath; ?>defaultavatar-small.jpg" class="avatarMini" alt="Avatar mini"/> Se connecter</h6>
            <div class="loginSlider">
               <form method="post" action="<?php echo $webRootPath; ?>LogIn.php">
               <p class="connectionForm">
                  <input type="text" name="pseudo" placeholder="Pseudo" required><br/>
                  <input type="password" name="pwd" placeholder="Mot de passe" required><br/>
<?php
   if(WebpageHandler::$redirections['log_in'])
   {
      echo '                  <input type="hidden" name="redirection" value="'.$webRootPath.$_SERVER['REQUEST_URI'].'"/>';
   }
   else
   {
       echo '                  <input type="hidden" name="redirection" value=""/>';
   }
?>
                  <input type="checkbox" name="rememberMe"/><label for="rememberMe">Se souvenir de moi</label><br/>
                  <input type="submit" name="sent" value="Connexion"/><br/>
               </p>
               <p>
                  <a href="<?php echo $webRootPath; ?>Registration.php">Créer un compte</a><br/>
                  <a href="<?php echo $webRootPath; ?>PasswordReset.php">Mot de passe perdu ?</a>
               </p>
               </form>
            </div>
<?php
}
?>
         </div>
      </div>
      <div id="main">
<?php
echo WebpageHandler::$container['start'];
?>
