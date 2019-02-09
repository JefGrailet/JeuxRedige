<?php
echo WebpageHandler::$container['end'];
?>
         <div id="footer">
         <p>
            <a href="#main" class="iconLink"><img src="<?php echo PathHandler::HTTP_PATH; ?>res_icons/back_to_top.png" alt="Back to top" title="Revenir en haut de page"/></a> 
            <a href="<?php echo PathHandler::HTTP_PATH; ?>" class="iconLink"><img src="<?php echo PathHandler::HTTP_PATH; ?>res_icons/index.png" alt="Index" title="Revenir à l'accueil"/></a>
         </p>
         <p>v0.9 - Généré en <?php echo round(($overallEnd - $overallStart),5); ?> seconde</p>
         </div>
      </div>
   </body>
</html>
