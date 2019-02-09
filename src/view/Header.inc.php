<?php

/**
* Header of a page; contains all the HTML that models the design and includes basic JavaScript
* features. The PHP indentation may look odd, but it guarantees that the displayed page remains
* coherent regarding HTML when inspecting the code.
*/

if(!isset($pageTitle) || strlen($pageTitle) == 0)
{
   $pageTitle = 'Project AG';
}
else
{
   $pageTitle .= ' - Project AG';
}
?>
<!DOCTYPE html>
<html lang="fr">
   <head>
      <meta charset="UTF-8" />
      <link rel="stylesheet" href="<?php echo PathHandler::HTTP_PATH; ?>style/default.css" />
<?php
for($i = 0; $i < count(WebpageHandler::$CSSFiles); $i++)
{
   echo '      <link rel="stylesheet" href="'.PathHandler::HTTP_PATH.'style/'.WebpageHandler::$CSSFiles[$i].'.css" />'."\n";
}
?>
      <script type="text/javascript" src="<?php echo PathHandler::HTTP_PATH; ?>javascript/jquery-3.2.1.min.js"></script>
      <script type="text/javascript" src="<?php echo PathHandler::HTTP_PATH; ?>javascript/default.js"></script>
<?php
for($i = 0; $i < count(WebpageHandler::$JSFiles); $i++)
{
   echo '      <script type="text/javascript" src="'.PathHandler::HTTP_PATH.'javascript/'.WebpageHandler::$JSFiles[$i].'.js"></script>'."\n";
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
      echo '         else if (typeof QuickPreviewLib !== \'undefined\') { QuickPreviewLib.enable(); }'."\n";
      echo '         else if (typeof SegmentEditorLib !== \'undefined\') { SegmentEditorLib.previewMode(); }'."\n";
      echo '         else if (typeof ContentEditorLib !== \'undefined\') { ContentEditorLib.previewMode(); }'."\n";
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
   
   echo '      <meta property="og:site_name" content="Project AG">'."\n";
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
if(!LoggedUser::isLoggedIn())
{
?>
      <div id="connection" class="window" style="display:none;"> 
         <div class="windowTop">
            <span class="windowTitle"><strong>Connexion</strong></span> 
            <span class="closeDialog">Fermer</span>
         </div>
         <div class="windowContent">
            <form method="post" action="<?php echo PathHandler::HTTP_PATH; ?>LogIn.php">
            <table class="windowFields">
               <tr>
                  <td class="connectionColumn1">Pseudo:</td>
                  <td class="connectionColumn2"><input type="text" name="pseudo"/></td>
                  <td class="connectionColumn3"><a href="<?php echo PathHandler::HTTP_PATH; ?>Registration.php">M'inscrire</a></td>
               </tr>
               <tr>
                  <td class="connectionColumn1">Mot de passe:</td>
                  <td class="connectionColumn2"><input type="password" name="pwd"/></td>
                  <td class="connectionColumn3"><a href="<?php echo PathHandler::HTTP_PATH; ?>PasswordReset.php">Mot de passe perdu ?</a></td>
               </tr>
            </table>
            <p>
<?php
   if(WebpageHandler::$redirections['log_in'])
   {
?>
               <input type="hidden" name="redirection" value="<?php echo "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']; ?>"/>
<?php
   }
   else
   {
?>
               <input type="hidden" name="redirection" value=""/>
<?php
   }
?>
               <input type="checkbox" name="rememberMe"/> Se souvenir de moi 
               <input type="submit" name="sent" value="Connexion"/>
            </p>
            </form>
         </div>
      </div>
<?php
}

// Other dialog boxes, if provided by the calling code.
if(isset($dialogs) && !empty($dialogs))
{
   echo $dialogs;
}

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
      <div id="topMenu">
         <div class="websiteMainMenu">
            <p>
               <a class="websiteTitle" href="<?php echo PathHandler::HTTP_PATH; ?>">Project AG</a><sup>Beta</sup> &nbsp;
               <a href="<?php echo PathHandler::HTTP_PATH; ?>Articles.php">Articles</a> | 
               <a href="<?php echo PathHandler::HTTP_PATH; ?>Forum.php">Forum</a>
            </p>
         </div>
         <?php
if(LoggedUser::isLoggedIn())
{
   $padding = '            ';
   echo '<ul id="showUserMenu">'."\n";
   echo $padding.'<li>';
   
   /*
    * Handles function account of a logged user (i.e., the link to switch between accounts) and 
    * "My account" page.
    */
   
   $alternateAccount = '';
   $pseudoPart = '<img src="'.PathHandler::getAvatarSmall(LoggedUser::$data['used_pseudo']).'" class="avatarMini" alt="Avatar mini"/> ';
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
            $pseudoPart .= LoggedUser::$data['pseudo'];
         }
         $r = str_replace('&', 'amp;', "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
         $alternateAccount = '<a href="'.PathHandler::HTTP_PATH.'SwitchAccount.php?pos='.$r.'">Changer pour '.LoggedUser::$data['pseudo'].'</a>';
         $adminTools = true; // Always, for now
      }
      else
      {
         $pseudoPart .= LoggedUser::$data['pseudo'];
         if(LoggedUser::$data['function_name'] === 'administrator')
         {
            $r = str_replace('&', 'amp;', "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
            $alternateAccount = '<a href="'.PathHandler::HTTP_PATH.'SwitchAccount.php?pos='.$r.'">Changer pour <span style="color: rgb(255,63,63);">'.LoggedUser::$data['function_pseudo'].'</span></a>';
         }
      }
   }
   else
   {
      $pseudoPart .= LoggedUser::$data['pseudo'];
   }
   
   // Display with dropdown menu
   echo $padding.$pseudoPart.''."\n";
   echo $padding.'<ul id="userMenu">'."\n";
   if(strlen($alternateAccount) > 0)
   {
      echo $padding.'   '.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/switch.png" alt="Changer de compte"/> '.$alternateAccount.'</li>'."\n";
   }
   echo $padding.'   '.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/my_account.png" alt="Mon compte"/> <a href="'.PathHandler::HTTP_PATH.'MyAccount.php">Mon compte</a></li>'."\n";
   echo $padding.'   '.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/emoticons.png" alt="Mes émoticônes"/> <a href="'.PathHandler::HTTP_PATH.'MyEmoticons.php">Mes émoticônes</a></li>'."\n";
   echo $padding.'   '.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/pins.png" alt="Mes messages favoris"/> <a href="'.PathHandler::HTTP_PATH.'MyPins.php">Mes messages favoris</a></li>'."\n";
   echo $padding.'   '.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/articles.png" alt="Mes articles"/> <a href="'.PathHandler::HTTP_PATH.'MyArticles.php">Mes articles</a></li>'."\n";
   echo $padding.'   '.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/my_lists.png" alt="Mes listess"/> <a href="'.PathHandler::HTTP_PATH.'MyLists.php">Mes listes</a><sup>Beta</sup></li>'."\n";
   echo $padding.'   '.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/games.png" alt="Jeux"/> <a href="'.PathHandler::HTTP_PATH.'Games.php">Jeux</a><sup>Beta</sup></li>'."\n";
   echo $padding.'   '.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/tropes.png" alt="Codes ludiques"/> <a href="'.PathHandler::HTTP_PATH.'Tropes.php">Codes ludiques</a><sup>Beta</sup></li>'."\n";
   echo $padding.'   '.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/didyouknow.png" alt="Le saviez-vous ?"/> <a href="'.PathHandler::HTTP_PATH.'RandomTrivia.php">Le saviez-vous ?</a><sup>Beta</sup></li>'."\n";
   echo $padding.'   '.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/invite.png" alt="Inviter un ami"/> <a href="'.PathHandler::HTTP_PATH.'Sponsorship.php">Inviter un ami</a></li>'."\n";
   if($adminTools)
   {
      echo $padding.'   '.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/users.png" alt="Utilisateurs"/> <a href="'.PathHandler::HTTP_PATH.'Users.php">Utilisateurs</a></li>'."\n";
      echo $padding.'   '.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/alerts.png" alt="Alertes"/> <a href="'.PathHandler::HTTP_PATH.'Alerts.php">Alertes</a></li>'."\n";
   }
   // Log out link
   if(WebpageHandler::$redirections['log_out'])
   {
      $r = str_replace('&', 'amp;', "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
      echo $padding.'   '.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/logout.png" alt="Déconnexion"/> <a href="'.PathHandler::HTTP_PATH.'LogOut.php?redirection='.$r.'">Déconnexion</a></li>'."\n";
   }
   else
   {
      echo $padding.'   '.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/logout.png" alt="Déconnexion"/> <a href="'.PathHandler::HTTP_PATH.'LogOut.php">Déconnexion</a></li>'."\n";
   }
   echo $padding.'</ul></li>'."\n";
   echo '         </ul>'."\n";
   
   // Private messages
   echo '         <ul id="showPings">'."\n";
   if(LoggedUser::$data['new_pings'] > 0)
   {
      echo $padding.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/messages_new.png" alt="Messages"/>'."\n";
      echo $padding.'<ul id="pings">'."\n";
      for($i = 0; $i < LoggedUser::$data['new_pings'] && $i < 5; $i++)
      {
         echo $padding.'<li>';
         switch(LoggedUser::$messages[$i]['ping_type'])
         {
            case 'notification':
               echo '<img src="'.PathHandler::HTTP_PATH.'res_icons/ping_alert.png" alt="Notification"/> ';
               echo LoggedUser::$messages[$i]['title'];
               break;
            
            case 'ping pong':
               $otherParty = LoggedUser::$messages[$i]['emitter'];
               if($otherParty === LoggedUser::$data['pseudo'])
                  $otherParty = LoggedUser::$messages[$i]['receiver'];
               echo '<img src="'.PathHandler::HTTP_PATH.'res_icons/ping_discussion.png" alt="Discussion privée"/> ';
               echo '<a href="'.PathHandler::HTTP_PATH.'PrivateDiscussion.php?id_ping='.LoggedUser::$messages[$i]['id_ping'].'"><strong>'.$otherParty.' -</strong> '.LoggedUser::$messages[$i]['title'].'</a>';
               break;
            
            default:
               echo '<img src="'.PathHandler::HTTP_PATH.'res_icons/ping_alert.png" alt="Inconnu"/> ';
               echo LoggedUser::$messages[$i]['title'];
               break;
         }
         echo '</li>'."\n";
      }
      if(LoggedUser::$data['new_pings'] > 5)
         echo $padding.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/ping_list.png" alt="Mes pings"/> <a href="'.PathHandler::HTTP_PATH.'Pings.php">Liste de mes pings ('.LoggedUser::$data['new_pings'].' nouveaux)</a></li>'."\n";
      else
         echo $padding.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/ping_list.png" alt="Mes pings"/> <a href="'.PathHandler::HTTP_PATH.'Pings.php">Liste de mes pings</a></li>'."\n";
      echo $padding.'</ul></li>'."\n";
   }
   else if(LoggedUser::$data['new_pings'] == 0)
   {
      echo $padding.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/messages.png" alt="Messages"/>'."\n";
      echo $padding.'<ul id="pings">'."\n";
      echo $padding.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/ping_list.png" alt="Mes pings"/> <a href="'.PathHandler::HTTP_PATH.'Pings.php">Liste de mes pings</a></li>'."\n";
      echo $padding.'</ul></li>'."\n";
   }
   else
   {
      echo $padding.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/messages_buggy.png" alt="Messages"/>'."\n";
      echo $padding.'<ul id="pings">'."\n";
      echo $padding.'<li>Une erreur est survenue...</li>'."\n";
      echo $padding.'<li><img src="'.PathHandler::HTTP_PATH.'res_icons/ping_list.png" alt="Mes pings"/> <a href="'.PathHandler::HTTP_PATH.'Pings.php">Liste de mes pings</a></li>'."\n";
      echo $padding.'</ul></li>'."\n";
   }
   echo '         </ul>'."\n";
}
else
{
?>
         <ul>
            <li><img src="<?php echo PathHandler::HTTP_PATH; ?>defaultavatar-small.jpg" class="avatarMini" alt="Avatar mini"/> <a class="connectionLink">Se connecter</a></li>
         </ul>
<?php
}
?>
         <div class="mirroredTitle"></div>
      </div>
      <div id="main">
<?php 
echo WebpageHandler::$container['start'];
?>
