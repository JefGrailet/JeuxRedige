<?php

/*
* N.B.: $overallEnd, $overallStart are from WebPageHandler::wrap() (see Header.lib.php) and 
* $webRootPath is already defined in Header.inc.php.
*/

echo WebpageHandler::$container['end'];
?>
         <div id="footer">
         <p>
            <a href="#main" class="iconLink"><i class="icon-general_up" style="font-size: 24px;" title="Revenir en haut de page"></i></a> 
            <a href="<?php echo $webRootPath; ?>" class="iconLink"><i class="icon-general_home" style="font-size: 24px;" title="Revenir à l'accueil"></i></a> 
            <a href="https://github.com/JefGrailet/JeuxRedige" target="blank" class="iconLink"><i class="icon-ext_github" style="font-size: 24px;" title="Code source sur GitHub"></i></a> 
            <a href="https://www.youtube.com/@jeuxredige" target="blank" class="iconLink"><i class="icon-ext_youtube_logo" style="font-size: 24px;" title="Notre chaîne YouTube"></i></a>
            <a href="https://twitter.com/JeuxRedigeBE" target="blank" class="iconLink"><i class="icon-social_x" style="font-size: 24px;" title="Suivez-nous sur X (Twitter)"></i></a>
         </p>
         <p>v1.1 - Généré en <?php echo round(($overallEnd - $overallStart),5); ?> seconde - <a href="<?php echo $webRootPath; ?>About.php">À propos de JeuxRédige</a></p>
         </div>
      </div>
   </body>
</html>
