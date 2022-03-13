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
            <a href="https://github.com/JefGrailet/JeuxRedigeBE" target="blank" class="iconLink"><i class="icon-general_github" style="font-size: 24px;" title="Code source sur GitHub"></i></a>
         </p>
         <p>v1.0 - Généré en <?php echo round(($overallEnd - $overallStart),5); ?> seconde - <a href="<?php echo $webRootPath; ?>About.php">À propos de JeuxRédige.be</a></p>
         </div>
      </div>
   </body>
</html>
