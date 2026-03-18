<?php

/*
* Script to publish an article. Along making the article visible for all users, this script also
* creates a topic for the reactions
*/

require './libraries/Header.lib.php';
require './libraries/FormParsing.lib.php';
require './libraries/Upload.lib.php';
require './model/Article.class.php';
require './model/Tag.class.php';
require './model/Topic.class.php';
require './model/Post.class.php';
require './view/intermediate/ArticleFirstReaction.ir.php';

require_once './libraries/core/Twig.config.php';

WebpageHandler::redirectionAtLoggingIn();

// Errors where the user is either not logged in
if(!LoggedUser::isLoggedIn())
{
   echo $twig->render("errors/error.html.twig", [
      "page_title" => "Erreur",
      "error_key" => "notConnected",
      "meta" => [
         ...$twig->getGlobals()["meta"],
         "title" => "Erreur",
         "description" => "Erreur",
         "full_title" => "",
      ]
   ]);

   die();
}

// Obtains article ID and retrieves the corresponding entry
if(!empty($_GET['id_article']) && preg_match('#^([0-9]+)$#', $_GET['id_article']))
{
   $articleID = intval(Utils::secure($_GET['id_article']));
   $article = null;
   $keywords = null;
   $segments = null;
   $formErrorMessagesTriggered = [];
   $formErrorMessages = $twig->getGlobals()["error_messages"];

   try
   {
      $article = new Article($articleID);
      $article->loadRelatedData();
      $keywords = $article->getKeywordsSimple();
      $segments = $article->getBufferedSegments();
   }
   catch(Exception $e)
   {
      $errorKey = 'dbError';
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $errorKey = 'nonexistingArticle';

      echo $twig->render("errors/error.html.twig", [
         "page_title" => "Erreur",
         "error_key" => $errorKey,
         "meta" => [
            ...$twig->getGlobals()["meta"],
            "title" => "Erreur",
            "description" => "Erreur",
            "full_title" => "",
         ]
      ]);
      die();
   }

   $errorKey = null;
   $publishedArticleURL = "";

   // Errors are displayed if article is already published, not written by this user or empty
   if(!$article->isMine())
   {
      $errorKey = 'notYours';
   }
   else if(count($segments) == 0)
   {
      $errorKey = 'noSegment';
   }

   if(!is_null($errorKey))
   {
      echo $twig->render("errors/error.html.twig", [
         "page_title" => "Erreur",
         "article" => [
            ...(is_null($article) ? [] : $article->getAll()),
            "public_link" => $publishedArticleURL,
         ],
         "error_key" => $errorKey,
         "meta" => [
            ...$twig->getGlobals()["meta"],
            "title" => "Erreur",
            "description" => "Erreur",
            "full_title" => "",
         ]
      ]);
      die();
   }

   $formTplInput = array('errors' => '',
      'articleID' => $article->get('id_article'),
      'fullArticleTitle' => $article->get('title').' - '.$article->get('subtitle'),
      'anonChecked' => '',
      'uploadsChecked' => ''
   );

   if (!empty($_POST['publish']))
   {
      // Checkboxes for the general configuration of the topic
      $anonPosting = false;
      if(isset($_POST['anon_posting']))
      {
         $anonPosting = true;
         $formTplInput['anonChecked'] = 'checked';
      }

      $enableUploads = false;
      if (isset($_POST['enable_uploads']))
      {
         $enableUploads = true;
         $formTplInput['uploadsChecked'] = 'checked';
      }
      else
         $formTplInput['uploadsChecked'] = '';

      // Gets the delay between current time and the latest topic created by this user
      $delay = 3600;
      try
      {
         $delay = Topic::getUserDelay();
      }
      catch(Exception $e)
      {
         // Nothing, so far
      }

      if($delay < WebpageHandler::$miscParams['consecutive_topics_delay'])
         array_push($formErrorMessagesTriggered, $formErrorMessages["tooManyTopics"]);

      if(count($formErrorMessagesTriggered) === 0)
      {
         // Title of the topic (should normally fit: title of topics is VARCHAR(255), title and subtitle are VARCHAR(100))
         $topicTitle = 'Article: '.$article->get('title').' - '.$article->get('subtitle');

         $thumbnail = 'none'; // Value that will be input in Topic::insert()
         $articleThumbnail = $article->getThumbnail(true);
         if(strlen($articleThumbnail) > 0)
            $thumbnail = 'CUSTOM';

         Database::beginTransaction();
         try
         {
            $autoMsg = FormParsing::parse(ArticleFirstReactionIR::process($article->getAll(), $segments[0]));

            $newTopic = Topic::autoInsert($topicTitle,
                                          $thumbnail,
                                          1,
                                          $anonPosting,
                                          $enableUploads);

            $newPost = Post::autoInsert($newTopic->get('id_topic'), $autoMsg);
            $newTopic->update($newPost->getAll());
            $article->publish($newTopic->get('id_topic'));

            Database::commit();
         }
         catch(Exception $e)
         {
            Database::rollback();

            array_push($formErrorMessagesTriggered, $formErrorMessages["dbError"]);
            // $tpl = TemplateEngine::parse('view/user/PublishArticle.form.ctpl', $formTplInput);
            // WebpageHandler::wrap($tpl, 'Publier un article');
         }

         /*
         * To create the topic thumbnail, we re-use the article thumbnail and resize it. To do so,
         * we have to "cheat" with storeResizedPicture() as the 1st argument is supposed to be a
         * $_FILES array. We artificially create it, providing only the indexes relevant for this
         * specific function (i.e., "name" and "tmp_name").
         */

         if(count($formErrorMessagesTriggered) === 0) {
            if(strlen($articleThumbnail) > 0)
            {
               $toResize = array('name' => 'thumbnail.jpg',
               'tmp_name' => $articleThumbnail);
               Upload::storeResizedPicture($toResize, 'upload/topics/'.$newTopic->get('id_topic'), 260, 162);
            }

            // Inserts topic keywords; it is just a copy/paste of the keywords of the related article
            for($i = 0; $i < count($keywords) && $i < 10; $i++)
            {
               if(strlen($keywords[$i]) == 0)
                  continue;

               try
               {
                  $tag = new Tag($keywords[$i]);
                  $tag->mapToTopic($newTopic->get('id_topic'));
               }
               catch(Exception $e)
               {
                  continue;
               }
            }
         }

         $publishedArticleURL = PathHandler::articleURL($article->getAll());

         echo $twig->render("add_edit_article/article_publishing.html.twig", [
            "page_title" => "\"{$article->get("title")} - {$article->get("subtitle")}\" publié avec succès",
            "article" => [
               ...$article->getAll(),
               "public_link" => $publishedArticleURL,
                "is_published" => $article->isPublished(),
            ],
            "form_error_messages_triggered" => $formErrorMessagesTriggered,
            "is_submitting" => true,
            "meta" => $twig->getGlobals()["meta"],
         ]);
      }
   }
   else
   {
      echo $twig->render("add_edit_article/article_publishing.html.twig", [
            "page_title" => "Publier l'article \"{$article->get("title")} - {$article->get("subtitle")}\" ?",
            "article" => [
               ...$article->getAll(),
               "is_published" => $article->isPublished(),
            ],
            "form_error_messages_triggered" => $formErrorMessagesTriggered,
            "meta" => $twig->getGlobals()["meta"],
         ]);
   }
}
else
{
   echo $twig->render("errors/error.html.twig", [
      "page_title" => "Erreur",
      "error_key" => "",
      "meta" => [
         ...$twig->getGlobals()["meta"],
         "title" => "Erreur",
         "description" => "Erreur",
         "full_title" => "",
      ]
   ]);
}
