<?php

/**
* Script to invite a third party to create an account on the site.
*/

require './libraries/Header.lib.php';

require_once getenv("DOCUMENT_ROOT") . '/libraries/core/Twig.config.php';

WebpageHandler::redirectionAtLoggingIn();

// Script is useless if user is already connected. A special message is displayed.
if(!LoggedUser::isLoggedIn() || !Utils::check(LoggedUser::$data['can_invite']))
{
   $errorKey = !LoggedUser::isLoggedIn() ? 'notConnected' : 'notPermitted';
   http_response_code(401);
   echo $twig->render("errors/error.html.twig", [
      "error_title" => "Connexion requise",
      "error_key" => $errorKey,
      "meta" => $twig->getGlobals()["meta"]
   ]);
   die();
}

require './libraries/Mailing.lib.php';
require './model/User.class.php';
require './model/Invitation.class.php';

$formErrorMessagesTriggered = [];
$sendEmailSuccess = false;

$errorAlreadyInvited = "Cette adresse e-mail a déjà été invitée par un autre utilisateur";
$friendEmail = "";
if(!empty($_POST))
{
   $friendEmail = Utils::secure($_POST['email']);
   $formErrorMessages = $twig->getGlobals()["error_messages"];

   // Basic errors
   if(strlen($friendEmail) === 0)
   {
      array_push($formErrorMessagesTriggered, $formErrorMessages["emptyFields"]);
   }
   // At this point, the provided e-mail is considered to be OK
   else
   {
      try
      {
         // Checks e-mail availability
         if(User::isEmailUsed($friendEmail))
         {
            array_push($formErrorMessagesTriggered, 'Cette adresse e-mail est déjà utilisée pour un compte confirmé');
         }
         else
         {
            /*
             * Checks if this is a new invitation, a re-send or if the address has been invited
             * by a third party.
             */

            $sponsor = Invitation::hasBeenInvited($friendEmail);
            print(strlen($sponsor));
            print_r( LoggedUser::$data['pseudo']);
            if(strlen($sponsor) == 0)
            {
               $invitation = Invitation::insert($friendEmail);
               $invitationKey = $invitation->get('invitation_key');
               $invitationLink = PathHandler::HTTP_PATH().'Invitation.php?key='.$invitationKey;
               $mailInput = array('pseudo' => LoggedUser::$data['pseudo'], 'invitationLink' => $invitationLink);
               $mailContent = TemplateEngine::parse('view/user/Sponsorship.mail.ctpl', $mailInput);

               if(!TemplateEngine::hasFailed($mailContent))
               {
                  if(Mailing::send($friendEmail, 'Vous êtes invité sur JeuxRédige', $mailContent))
                  {
                     $sendEmailSuccess = true;
                     $invitation->updateLastEmailDate();
                  } else {
                     array_push($formErrorMessagesTriggered, "Une erreur est survenue.");
                  }
               } else {
                  array_push($formErrorMessagesTriggered, "L'envoi de l'e-mail à {$friendEmail} a échoué. Ré-utilisez ce formulaire d'ici quelques instants pour re-tenter l'envoi, ou contactez l'administrateur.");
               }
            }
            else if($sponsor === LoggedUser::$data['pseudo'])
            {
               $invitation = Invitation::getByEmail($friendEmail);

               // Checks the last attempt at sending an invitation was more than an hour ago
               $delay = Utils::SQLServerTime() - Utils::toTimestamp($invitation->get('last_email'));

               if($delay < 3600)
               {
                  array_push($formErrorMessagesTriggered, "Vous avez déjà envoyé une invitation à l'adresse \"{$friendEmail}\" il y a moins d'une heure. Veuillez patienter ou vérifiez avec votre ami si l'email n'est pas dans ses spams");
               }
               else
               {
                  $invitationKey = $invitation->get('invitation_key');
                  $invitationLink = PathHandler::HTTP_PATH().'Invitation.php?key='.$invitationKey;
                  $mailInput = array('pseudo' => LoggedUser::$data['pseudo'], 'invitationLink' => $invitationLink);
                  $mailContent = TemplateEngine::parse('view/user/Sponsorship.mail.ctpl', $mailInput);

                  // Success message display
                  if(!TemplateEngine::hasFailed($mailContent))
                  {
                     if(Mailing::send($friendEmail, 'Vous êtes invité sur JeuxRédige', $mailContent))
                     {
                        $sendEmailSuccess = true;
                        $invitation->updateLastEmailDate();
                     }
                  }
               }
            }
            else
            {
               array_push($formErrorMessagesTriggered, $errorAlreadyInvited);
            }
         }
      }
      // Fail while creating new account : check the error provided by SQL
      catch(Exception $e)
      {
         if(strstr($e->getMessage(), 'for key \'guest_email\'') != FALSE)
            array_push($formErrorMessagesTriggered, $errorAlreadyInvited);
         else
            array_push($formErrorMessagesTriggered, $formErrorMessages['dbError']);
      }
   }
}

echo $twig->render("sponsorship.html.twig", [
   "meta" => $twig->getGlobals()["meta"],
   "friend_email" => $friendEmail,
   "is_email_sent_success" => $sendEmailSuccess,
   "form_error_messages_triggered" => $formErrorMessagesTriggered,
]);
