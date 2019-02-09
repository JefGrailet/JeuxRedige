<?php

class CommentIR
{
   /*
   * Converts the array modelizing a post into an intermediate representation, ready to be used in
   * a template. The intermediate representation is a new array containing:
   *
   * -ID of the post
   * -Pseudonym, avatar and style (can be empty) of the author when (s)he posted this post
   * -Date of the post as a text
   * -The content (HTML, some additions regarding what is stored in the DB)
   *
   * In short, it is a very simplified version of PostIR. Indeed, comments are just a simplified 
   * display of some message, and are not supposed to be interactive. They also have no display of 
   * their attachments; if there are some, a message displayed at the end of the content will 
   * advertise the reader there is more to see in the full topic.
   *
   * @param mixed $post[]  The post itself (obtained with method getAll() from Post class)
   * @return mixed[]       The intermediate representation
   */

   public static function process($post)
   {
      // Special case: if the post is automatic, $interactive is reset to false
      if($post['posted_as'] === 'author')
         $interactive = false;
      
      $output = array('ID' => $post['id_post'], 
      'authorPseudo' => $post['author'], 
      'authorAvatar' => PathHandler::HTTP_PATH.'defaultavatar-small.jpg', 
      'authorStyle' => '',
      'date' => Utils::printDate($post['date']), 
      'content' => '');
      
      // Avatar, style of the <h1> displaying the author's pseudonym and online status
      if($post['posted_as'] !== 'anonymous')
         $output['authorAvatar'] = PathHandler::getAvatarSmall($post['author']);
      
      if($post['posted_as'] === 'anonymous')
      {
         $output['authorStyle'] = ' style="background-color: #557083;" title="Anonyme"';
         $output['authorType'] = 'anon';
      }
      else if($post['posted_as'] === 'administrator')
      {
         if(array_key_exists('online', $post) && $post['online'])
            $output['authorStyle'] = ' style="background-color: #DD0017;" title="Administrateur, en ligne"';
         else
            $output['authorStyle'] = ' style="background-color: #910017;" title="Administrateur"';
         $output['authorType'] = 'admin';
      }
      else
      {
         if(array_key_exists('online', $post) && $post['online'])
            $output['authorStyle'] = ' style="background-color: #38883f;" title="En ligne"';
         $output['authorType'] = 'user';
      }
      
      // If author is not anonymous, a link to Posts.php with his/her history is added
      if($post['posted_as'] !== 'anonymous' && $post['posted_as'] !== 'administrator')
      {
         $output['authorPseudo'] = '<a href="'.PathHandler::userURL($post['author']).'" target="_blank" class="authorPseudo">'.$post['author'].'</a>';
      }
      
      // If content is ending with a div, do not end with "</p>"
      $postEnd = '</p>';
      if(substr($post['content'], -8) === "</div>\r\n")
         $postEnd = '';
      
      // Small message to signal the post has attachments which can only be seen in the full topic
      $aboutAttachment = '';
      $displayPolicy = '';
      if(strpos($post['attachment'], "_") !== FALSE)
      {
         $firstSplit = explode(':', $post['attachment']);
         $prefix = $firstSplit[0];
         $prefixSplitted = explode('_', $prefix);
         $displayPolicy = $prefixSplitted[1];
      }
      
      $hiddenPolicies = array('noshow', 'noshownsfw', 'noshowspoiler');
      if(strlen($post['attachment']) > 0 && !in_array($displayPolicy, $hiddenPolicies))
      {
         $aboutAttachment = '<p style="color: grey;">Ce message possède des pièces jointes. Vous 
         pouvez les voir soit en consultant le sujet entier, soit en cliquant 
         <a href="'.PathHandler::HTTP_PATH.'Permalink.php?id_post='.$post['id_post'].'" target="_blank">ici</a>.</p>';
      }
      
      // Message is masked if reported too many times or reported by an admin
      if($post['bad_score'] >= 10)
      {
         $output['content'] = '<p>
         <span style="color: grey;">Ce message a été signalé par plusieurs inscrits et/ou
         un modérateur comme inapproprié/offensant. Par conséquent, son contenu a été masqué à titre
         préventif.<br/>
         <br/>
         <a href="javascript:void(0)" class="link_masked_post" data-id-post="'.$post['id_post'].'">
         Cliquez ici</a> pour afficher/masquer ce contenu.</span>
         </p>
         <div id="masked'.$post['id_post'].'" style="display: none;">
         <p>
         '.$post['content'].'
         '.$postEnd.'
         '.$aboutAttachment.'
         </div>';
      }
      else
      {
         $output['content'] = '<p>
         '.$post['content'].'
         '.$postEnd.'
         '.$aboutAttachment;
      }
      
      return $output;
   }
}

?>
