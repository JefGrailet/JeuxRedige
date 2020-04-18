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
            <a href="<?php echo $webRootPath; ?>" class="iconLink"><i class="icon-general_home" style="font-size: 24px;"  title="Revenir à l'accueil"></i></a>
         </p>
         <p>v0.9 - Généré en <?php echo round(($overallEnd - $overallStart),5); ?> seconde</p>
         </div>
      </div>
   </body>
</html>
