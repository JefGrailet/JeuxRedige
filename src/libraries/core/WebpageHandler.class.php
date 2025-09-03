<?php

/**
 * This file defines a static class handling a variety of variables tied to the display (HTML- and 
 * CSS-wise) or functionality of the website (w.r.t. JavaScript). Most notably, it gathers two 
 * arrays that list the CSS and JS files that should be used when writing the final HTML page, as 
 * well as miscellaneous variables to handle some URLs and wrapping <div> tags. It also maintains 
 * a large "miscParams" array gathering a variety of parameters which influence both the display 
 * (e.g., number of messages per page) and QOL (e.g., automatic activation of auto-preview).
 */

class WebpageHandler
{
   private static $overallStart;

   public static $CSSFiles;
   public static $JSFiles;
   public static $redirections; // Array of 2 bools to handle redirection upon logging in/out
   public static $URLRewriting; // Bool equal to true if the URL of this page has been rewritten
   public static $container; // Start and end <div> tag wrapping page content (array of 2 strings)
   public static $miscParams;

   /**
    * Initializes the static elements of the class, setting default values for the $miscParams 
    * array in the process. It only requires the UNIX timestamp when the script starts running to 
    * eventually compute the total runtime for generating the final HTML page.
    * 
    * @param int $startTime  The UNIX timestamp at the start of the full script (microseconds)
    */
   
   public static function init($startTime)
   {
      self::$overallStart = $startTime;

      self::$CSSFiles = array();
      self::$JSFiles = array();
      self::$redirections = array('log_in' => false, 'log_out' => true);
      self::$URLRewriting = false;
      
      // Default wrapping for page content
      self::$container = array(
      'start' => '<div id="content">'."\n".'<div class="wallOfText">'."\n", 
      'end' => '</div>'."\n".'</div>'."\n");
      
      // Default parameters
      self::$miscParams = array(
      'webdesign_variant' => 'default', // (November 2021) Logo variant linked to article type
      'posts_per_page' => 10, // Amount of messages per page in a topic (normally 20)
      'topics_per_page' => 30, // Amount of topics per page in a list of topics
      'articles_per_page' => 16, // Same for the articles
      'emoticons_per_page' => 30, // Amount of emoticons per page on the "My emoticons" page 
      'pins_per_page' => 50, // Amount of pins per page on the "My pins" page
      'message_size' => 'default', // "Size" of displayed messages (size of avatars, font, etc.)
      'video_default_display' => 'thumbnail', // Whether videos are embedded at load time or not
      'video_thumbnail_style' => 'hq', // Size of video thumbnails (HQ, i.e. 480x360, by default)
      'default_nav_mode' => 'classic', // Navigation mode (topics, pings) by default
      'auto_preview' => 'no', // Activation by default of automatic/quick preview
      'auto_refresh' => 'no', // Activation by default of auto refresh
      'consecutive_posts_delay' => 15, // Minimum delay (seconds) between two posts by same user
      'consecutive_anon_posts_delay' => 120, // Minimum delay between two same-IP anonymous posts
      'consecutive_topics_delay' => 1800, // Same but for consecutive topics by the same user
      'consecutive_pings_delay' => 180, // Same but for consecutive "pings" by the same user
      'meta_title' => '', // Meta-tag: title of the content
      'meta_author' => '', // Meta-tag: author of the content
      'meta_description' => '', // Meta-tag: description of the content
      'meta_image' => '', // Meta-tag: image/thumbnail
      'meta_url' => '', // Meta-tag: URL
      'meta_keywords' => '' // Meta-tag: keywords
      );
   }

   /**
    * Copies user's preferences into self::$misParams.
    * 
    * @param string $userPreferences[]  The user's preferences
    */

   public static function getUserPreferences($userPrefs)
   {
      self::$miscParams['posts_per_page'] = $userPrefs['pref_posts_per_page'];
      self::$miscParams['message_size'] = $userPrefs['pref_message_size'];
      self::$miscParams['video_default_display'] = $userPrefs['pref_video_default_display'];
      self::$miscParams['video_thumbnail_style'] = $userPrefs['pref_video_thumbnail_style'];
      self::$miscParams['default_nav_mode'] = $userPrefs['pref_default_nav_mode'];
      self::$miscParams['auto_preview'] = $userPrefs['pref_auto_preview'];
      self::$miscParams['auto_refresh'] = $userPrefs['pref_auto_refresh'];
   }
   
   public static function addCSS($fileName)
   {
      array_push(self::$CSSFiles, $fileName);
   }
   
   public static function addJS($fileName)
   {
      array_push(self::$JSFiles, $fileName);
   }
   
   public static function redirectionAtLoggingIn()
   {
      self::$redirections['log_in'] = true;
   }
   
   public static function noRedirectionAtLoggingOut()
   {
      self::$redirections['log_out'] = false;
   }
   
   public static function changeContainer($divName)
   {
      self::$container['start'] = '<div id="'.$divName.'">'."\n";
      self::$container['end'] = '</div>'."\n";
   }
   
   public static function noContainer()
   {
      self::$container['start'] = '';
      self::$container['end'] = '';
   }
   
   public static function usingURLRewriting()
   {
      self::$URLRewriting = true;
   }
   
   /**
    * Resets the display for some specific scripts (e.g., success pages).
    */
   
   public static function resetDisplay()
   {
      self::$CSSFiles = array();
      self::$JSFiles = array();
      self::$container = array(
      'start' => '<div id="content">'."\n".'<div class="wallOfText">'."\n", 
      'end' => '</div>'."\n".'</div>'."\n");
   }
   
   /**
    * Receives a HTML code and wraps it in a single div (default name: "singleBlock").
    *
    * @param string $html  The HTML code to encapsulated
    * @param string $name  The name of the wrapping block; "singleBlock" by default
    * @return string       The wrapped HTML code
    */
   
   public static function wrapInBlock($html, $blockName = 'singleBlock')
   {
      $newHtml = '<div class="'.$blockName.'">'."\n";
      $newHtml .= $html."\n";
      $newHtml .= '</div>'."\n";
      return $newHtml;
   }

   /**
    * Receives a HTML code, a page title and a optional set of "boxes" (HTML code that can be used 
    * to embed JavaScript-based dialog boxes), then displays the whole and exits.
    *
    * @param string $html       The HTML code to display (page itself; not the whole design)
    * @param string $pageTitle  The title of the HTML page (displayed in the browser's tab)
    * @param string $dialogs    Concatenated dialog boxes or single dialog box, written in HTML 
    *                           and made interactible through JavaScript (optional)
    */
   
   public static function wrap($html, $pageTitle, $dialogs = '')
   {
      $finalHTML = $html;
      $webRootPath = PathHandler::HTTP_PATH();

      if(self::$URLRewriting)
         $finalHTML = str_replace('="./', '="'.$webRootPath, $finalHTML);
      
      $mainTplInput = array(
      'webRoot' => $webRootPath, 
      'configJS' => '', 
      'extJS' => PathHandler::JS_EXTENSION(), 
      'CSSFiles' => '', 
      'JSFiles' => '', 
      'autoJS' => '', 
      'metaAuthor' => '',
      'metaFullTitle' => '', 
      'metaKeywords' => self::$miscParams['meta_keywords'], 
      'metaTitle' => self::$miscParams['meta_title'], 
      'metaDescription' => self::$miscParams['meta_description'], 
      'metaImage' => self::$miscParams['meta_image'], 
      'metaURL' => self::$miscParams['meta_url'], 
      'pageTitle' => '', 
      'dialogs' => $dialogs, 
      'selectedLogo' => 'default', 
      'userSide' => '', 
      'innerMainDivStart' => self::$container['start'], 
      'content' => $finalHTML, 
      'innerMainDivEnd' => self::$container['end'], 
      'renderTime' => 0.
      );

      // Extra, short JS code written here because {} braces would mess with the template
      $extraJS = 'var ConfigurationValues = {}; ';
      $extraJS .= 'ConfigurationValues.HTTP_PATH = \''.$webRootPath.'\';'."\n";
      $mainTplInput['configJS'] = $extraJS;

      for($i = 0; $i < count(self::$CSSFiles); $i++)
      {
         if($i > 0)
            $mainTplInput['CSSFiles'] .= '|';
         $mainTplInput['CSSFiles'] .= $webRootPath.'style/'.self::$CSSFiles[$i].'.css';
      }

      for($i = 0; $i < count(self::$JSFiles); $i++)
      {
         if($i > 0)
            $mainTplInput['JSFiles'] .= '|';
         $mainTplInput['JSFiles'] .= $webRootPath.'javascript/'.self::$JSFiles[$i];
         $mainTplInput['JSFiles'] .= $mainTplInput['extJS'];
      }

      // Auto-activated JS features
      $autoNavMode = in_array('pages', self::$JSFiles);
      $autoNavMode = $autoNavMode && self::$miscParams['default_nav_mode'] !== 'classic';

      $autoPreview = in_array('preview', self::$JSFiles);
      $autoPreview = $autoPreview || in_array('quick_preview', self::$JSFiles);
      $autoPreview = $autoPreview || in_array('segment_editor', self::$JSFiles);
      $autoPreview = $autoPreview || in_array('content_editor', self::$JSFiles);
      $autoPreview = $autoPreview && Utils::check(self::$miscParams['auto_preview']);

      $autoRefresh = in_array('refresh', self::$JSFiles);
      $autoRefresh = $autoRefresh && Utils::check(self::$miscParams['auto_refresh']);

      // (small) JavaScript code is written here, because {} braces would mess with the template
      if($autoNavMode || $autoPreview || $autoRefresh)
      {
         $mainTplInput['autoJS'] .= "\n";
         $mainTplInput['autoJS'] .= 'window.addEventListener(\'DOMContentLoaded\', function()';
         $mainTplInput['autoJS'] .= "\n".'{'."\n";

         if($autoNavMode)
         {
            switch(self::$miscParams['default_nav_mode'])
            {
               case 'dynamic':
                  $mainTplInput['autoJS'] .= '   if (typeof PagesLib !== \'undefined\') ';
                  $mainTplInput['autoJS'] .= '{ PagesLib.switchNavMode(2); }'."\n";
                  break;
               case 'flow':
                  $mainTplInput['autoJS'] .= '   if (typeof PagesLib !== \'undefined\') ';
                  $mainTplInput['autoJS'] .= '{ PagesLib.switchNavMode(3); }'."\n";
                  break;
               default:
                  break;
            }
         }

         /*
         * Remark for auto preview: previewMode() is defined in both preview.js and 
         * quick_preview.js, and as these files are mutually exclusive (never invoked at the same 
         * time), we do not need to check here which kind of preview is activated. The existing 
         * previewMode() always matches the current type of preview.
         */

         if($autoPreview)
         {
            $mainTplInput['autoJS'] .= '   if (typeof PreviewLib !== \'undefined\') ';
            $mainTplInput['autoJS'] .= '{ PreviewLib.previewMode(); }'."\n";
         }

         if($autoRefresh)
         {
            $mainTplInput['autoJS'] .= '   if (typeof RefreshLib !== \'undefined\') ';
            $mainTplInput['autoJS'] .= '{ RefreshLib.changeAutoRefresh(); }'."\n";
         }

         $mainTplInput['autoJS'] .= '});'."\n";
         $mainTplInput['autoJS'] .= '</script>'."\n";
      }

      if(strlen(self::$miscParams['meta_description']) > 0)
      {
         $mainTplInput['metaAuthor'] = self::$miscParams['meta_author'];
         $mainTplInput['metaFullTitle'] = self::$miscParams['meta_title'];
         $mainTplInput['metaFullTitle'] .= ' - '.self::$miscParams['meta_description'];
      }

      if(!isset($pageTitle) || strlen($pageTitle) == 0)
         $mainTplInput['pageTitle'] = 'JeuxRédige';
      else
         $mainTplInput['pageTitle'] = $pageTitle.' - JeuxRédige';

      $logoVariants = array_keys(Utils::ARTICLES_CATEGORIES);
      if(in_array(self::$miscParams['webdesign_variant'], $logoVariants))
         $mainTplInput['selectedLogo'] = self::$miscParams['webdesign_variant'];

      // User corner, with separate templates (one for logged in user, another for log in form)
      if(LoggedUser::isLoggedIn())
      {
         $userMenuTplInput = array(
            'webRoot' => $webRootPath, 
            'userAvatar' => PathHandler::getAvatarMedium(LoggedUser::$data['used_pseudo']), 
            'pseudoDisplay' => '', 
            'logOutLink' => $webRootPath.'LogOut.php', 
            'switchAccount' => '', 
            'adminTools' => '', 
            'pingsColor' => '#25b6d2', # Default color (nothing new)
            'pingsContent' => '', 
            'morePings' => ''
         );

         // Current page to redirect to when logging out or switching account
         $r = str_replace(
            '&', 
            'amp;', 
            'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']
         );

         // Default pseudo display, with link to profile (will be changed with function account)
         $profileLink = '<a href="'.$webRootPath.'User.php?user='.LoggedUser::$data['pseudo'].'">';
         $profileLink .= LoggedUser::$data['pseudo'].'</a>';
         $userMenuTplInput['pseudoDisplay'] = $profileLink;

         // Redirection to current page upon logging out (if needed)
         if(self::$redirections['log_out'])
            $userMenuTplInput['logOutLink'] .= '?redirection='.$r;

         // Checks this user has a function account
         $hasAdminAccount = LoggedUser::$data['function_pseudo'] !== NULL;
         $hasAdminAccount = $hasAdminAccount && strlen(LoggedUser::$data['function_pseudo']) > 0;
         $hasAdminAccount = $hasAdminAccount && LoggedUser::$data['function_name'] !== 'alumnus';
         
         // Deals witch switch account links, changes pseudo display if needed
         if($hasAdminAccount)
         {
            if(LoggedUser::$data['function_pseudo'] === LoggedUser::$data['used_pseudo'])
            {
               if(LoggedUser::$data['function_name'] === 'administrator')
               {
                  $altPseudo = '<span style="color: rgb(255,63,63);">';
                  $altPseudo .= LoggedUser::$data['function_pseudo'].'</span>';
                  $userMenuTplInput['pseudoDisplay'] = $altPseudo;
               }
               $switchLink = '<a href="'.$webRootPath.'SwitchAccount.php?pos='.$r.'">';
               $switchLink .= 'Changer pour '.LoggedUser::$data['pseudo'].'</a>';
               $userMenuTplInput['switchAccount'] = $switchLink;
               $userMenuTplInput['adminTools'] = 'yes||'.$webRootPath;
            }
            else
            {
               if(LoggedUser::$data['function_name'] === 'administrator')
               {
                  $switchLink = '<a href="'.$webRootPath.'SwitchAccount.php?pos='.$r.'">';
                  $switchLink .= 'Changer pour <span style="color: rgb(255,63,63);">';
                  $switchLink .= LoggedUser::$data['function_pseudo'].'</span></a>';
                  $userMenuTplInput['switchAccount'] = $switchLink;
               }
            }
         }

         // Pings
         if(LoggedUser::$data['new_pings'] > 0)
         {
            $userMenuTplInput['pingsColor'] = '#4bd568';

            for($i = 0; $i < LoggedUser::$data['new_pings'] && $i < 5; $i++)
            {
               $item = '<li>'."\n";
               switch(LoggedUser::$messages[$i]['ping_type'])
               {
                  case 'notification':
                     $item .= '<i class="icon-general_alert" style="color: #e04f5f;"></i> ';
                     $item .="\n";
                     $item .= LoggedUser::$messages[$i]['title'];
                     break;

                  case 'ping pong':
                     $otherParty = LoggedUser::$messages[$i]['emitter'];
                     if($otherParty === LoggedUser::$data['pseudo'])
                        $otherParty = LoggedUser::$messages[$i]['receiver'];
                     $item .= '<i class="icon-general_messages" style="color: #25b6d2;"></i> ';
                     $item .= "\n";
                     $item .= '<a href="'.$webRootPath.'PrivateDiscussion.php?id_ping=';
                     $item .= LoggedUser::$messages[$i]['id_ping'].'"><strong>'.$otherParty;
                     $item .= ' -</strong> '.LoggedUser::$messages[$i]['title'].'</a>';
                     break;

                  // Unknown
                  default:
                     $item .= '<i class="icon-general_alert" style="color: #f2b851;"></i> ';
                     $item .= "\n";
                     $item .= LoggedUser::$messages[$i]['title'];
                     break;
               }
               $item .= "\n".'</li>'."\n";

               $userMenuTplInput['pingsContent'] .= $item;
            }

            if(LoggedUser::$data['new_pings'] > 5)
               $userMenuTplInput['morePings'] = LoggedUser::$data['new_pings'];
         }
         else if(LoggedUser::$data['new_pings'] < 0)
         {
            $userMenuTplInput['pingsColor'] = '#f2b851';
            $userMenuTplInput['pingsContent'] = '<li>Une erreur est survenue...</li>';
         }

         $mainTplInput['userCorner'] = TemplateEngine::parse(
            './view/UserMenu.ctpl', 
            $userMenuTplInput
         );
      }
      else
      {
         $logInTplInput = array(
            'webRoot' => $webRootPath, 
            'loginRedirection' => ''
         );

         if(self::$redirections['log_in'])
            $logInTplInput['logInRedirection'] = $webRootPath.$_SERVER['REQUEST_URI'];

         $mainTplInput['userCorner'] = TemplateEngine::parse(
            './view/LogInMenu.ctpl', 
            $logInTplInput
         );
      }

      $overallEnd = microtime(true);
      $mainTplInput['renderTime'] = round(($overallEnd - self::$overallStart), 5);

      $renderedPage = TemplateEngine::parse('./view/Main.ctpl', $mainTplInput);
      $renderedPage = preg_replace('/^[ \t]*[\r\n]+/m', '', $renderedPage);
      echo $renderedPage;
      exit();
   }
}
