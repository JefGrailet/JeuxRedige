<?php

/**
 * This script is used to create a new account via the sponsorship process. It is a sort of mix
 * between the registration and confirmation, as here, the confirmation is not required afterwards
 * (it is already done through the invitation key provided in the invitation e-mail).
 */

require './libraries/Header.lib.php';

require_once getenv("DOCUMENT_ROOT") . '/libraries/core/Twig.config.php';

// Script is useless if user is already connected. A special message is displayed.
if (LoggedUser::isLoggedIn()) {
   $tplInput = array('pseudo' => LoggedUser::$data['pseudo'], 'operation' => 'registration');
   $tplRes = TemplateEngine::parse('view/user/UnnecessaryOperation.ctpl', $tplInput);
   WebpageHandler::wrap($tplRes, 'Cette opération n\'est plus nécessaire');
}

require './model/User.class.php';
require './model/Invitation.class.php';
require './libraries/Mailing.lib.php';

$errorKey = null;
$formErrorMessagesTriggered = [];

do {
   if (!empty($_GET['key'])) {
      $invitationKey = Utils::secure($_GET['key']);

      // Getting the invitation and checking there is no error.
      $invitation = null;

      try {
         $invitation = new Invitation($invitationKey);
      } catch (Exception $e) {
         if (strstr($e->getMessage(), 'does not exist') !== FALSE)
            $errorKey = 'noInvitation';
         else
            $errorKey = 'dbError';
         break;
      }

      // Now checking an account for that e-mail address does not exist already
      try {
         $accountExists = User::isEmailUsed($invitation->get('guest_email'));
         if ($accountExists) {
            $errorKey = "alreadyRegistered";
         }
      } catch (Exception $e) {
         $errorKey = "dbError";

         break;
      }

      if (!empty($_POST)) {
         $formErrorMessages = $twig->getGlobals()["error_messages"]["user_account"];

         $data = array(
            'pseudo' => Utils::secure($_POST['pseudo']),
            'newPwd' => Utils::secure($_POST['newPwd'])
         );

         // Testing several possible errors (labels are self-explanatory)
         $errors = '';
         if (strlen($data['pseudo']) == 0 || strlen($data['newPwd']) == 0)
            array_push($formErrorMessagesTriggered, $formErrorMessages['emptyFields']);
         if (strlen($data['newPwd']) < 6 || strlen($data['newPwd']) > 20)
            array_push($formErrorMessagesTriggered, "Votre mot de passe n'est pas compris entre 6 et 20 caractères");
         if (!preg_match('!^[a-zA-Z0-9_-]{3,20}$!', $data['pseudo']))
            array_push($formErrorMessagesTriggered, "Le pseudo \"{$data['pseudo']}\" n'est pas compris entre 3 et 20 caractères ou possède des caractères interdits");

         // Also checking that the pseudo is available
         try {
            $pseudoExists = User::isPseudoUsed($data['pseudo']);
            if ($pseudoExists)
               array_push($formErrorMessagesTriggered, "Ce pseudonyme est déjà utilisé par un autre internaute inscrit.");
         } catch (Exception $e) {
            array_push($formErrorMessagesTriggered, $formErrorMessages['dbError']);

            break;
         }

         if (count($formErrorMessagesTriggered) === 0) {
            // At this point, the provided data is considered to be valid
            Database::beginTransaction();
            try {
               $user = User::insert($data['pseudo'], $invitation->get('guest_email'), $data['newPwd']);
               $user->confirm();
               $user->updateAdvancedFeatures();
               Database::commit();

               // Small e-mail to inform the new user his/her account has been created
               $inputEmail = array('pseudo' => $data['pseudo']);
               $emailContent = TemplateEngine::parse('view/user/Invitation.mail.ctpl', $inputEmail);
               if (!TemplateEngine::hasFailed($emailContent))
                  Mailing::send($user->get('email'), 'Bienvenue sur JeuxRédige', $emailContent);

               $display = TemplateEngine::parse('view/user/Invitation.success.ctpl');
               WebpageHandler::wrap($display, 'Bienvenue sur JeuxRédige !');

               break;
            }
            // Fail while creating new account : check the error provided by SQL
            catch (Exception $e) {
               Database::rollback();
               if (strstr($e->getMessage(), 'for key \'PRIMARY\'') != FALSE)
                  array_push($formErrorMessagesTriggered, "Ce pseudonyme est déjà utilisé par un autre internaute inscrit.");
               else
                  array_push($formErrorMessagesTriggered, $formErrorMessages['dbError']);

               break;
            }
         }
      }
   }
} while (0);

if ($errorKey) {
   echo $twig->render("errors/error.html.twig", [
      "page_title" => "Article vide",
      "error_key" => $errorKey,
      "meta" => [
         ...$twig->getGlobals()["meta"],
         "title" => "Erreur - Article vide",
         "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
         "full_title" => "",
      ]
   ]);
} else {
   echo $twig->render("registration-invitation.html.twig", [
      "page_title" => "Inscription sur invitation",
      "is_invitation" => true,
      "form_payload" => [
         "presentation" => "",
         "pseudo" => "",
         "newPwd" => "",
      ],
      "form_error_messages_triggered" => $formErrorMessagesTriggered,
      "meta" => [
         ...$twig->getGlobals()["meta"],
         "title" => "Inscription sur invitation",
      ]
   ]);
}
