<?php

/**
* This library handles the parsing of an input text from a user in order to obtain the 
* corresponding HTML equivalent of some format code (e.g. [g]this[/g] will become 
* <strong>this</strong>). The reverse operation is available as well.
*/

class FormParsing
{
   /*
   * Replaces tags of a tag pair for which there is no nesting. Nested opening tags are removed. 
   * The tags are provided with an array made of the 4 following strings:
   *
   * -[0]: opening tag (before parsing)
   * -[1]: closing tag (before parsing)
   * -[2]: opening tag (after parsing)
   * -[3]: opening tag (after parsing)
   *
   * @param string $content  The input text where tags must be replaced
   * @param string[] $tags   The opening/closing tags and what they should be replaced with
   * @param string $regex    Regex which will replace a sub-string "placeholder" found in some 
   *                         opening tags (color, url) to only parse correct usage of the tag; 
   *                         this is purely optional and only valid for opening tags; 
   *                         additionnaly, this of course requires $tags[2] to have $1, $2, etc.
   * @return string          The same text, with replaced tags
   */

   private static function replaceTags($content, $tags, $regex = '')
   {
      $parsed = $content;
      
      $withRegex = strlen($regex) > 0;
      
      $regexOpening = str_replace('[', '\[', $tags[0]);
      $regexOpening = str_replace(']', '\]', $regexOpening);
      
      if($withRegex)
         $regexOpening = str_replace('placeholder', $regex, $regexOpening);

      $regexClosing = str_replace('[', '\[', $tags[1]);
      $regexClosing = str_replace(']', '\]', $regexClosing);
      
      // If additionnal parameter, removes "placeholder]"
      $openingTag = $tags[0];
      $posEquality = strpos($openingTag, '=');
      if($posEquality !== FALSE)
         $openingTag = substr($openingTag, 0, $posEquality);
      
      $pos = strpos($parsed, $openingTag);
      $posEnd = 0;
      while($pos !== FALSE)
      {
         if($pos < $posEnd)
         {
            $parsed = preg_replace('('.$regexOpening.')iUs', '', $parsed, 1);
         }
         else
         {
            $posEnd = strpos($parsed, $tags[1], $pos + 1);
            
            // Skips closing tags which are unmatched
            while($posEnd !== FALSE && $posEnd < $pos)
               $posEnd = strpos($parsed, $tags[1], $posEnd + 1);
            
            if($posEnd === FALSE)
            {
               break;
            }
            else
            {
               // Splitting here prevents incorrect replacements of the closing tag.
               $beforePos = substr($parsed, 0, $pos);
               $afterPos = substr($parsed, $pos);
               
               $actualReplacement = true;
               
               // Checks the opening tag matches the regex
               if($withRegex)
               {
                  $matches = array();
                  preg_match('/'.$regexOpening.'/', $afterPos, $matches, PREG_OFFSET_CAPTURE);
                  
                  if(count($matches) == 0 || $matches[0][1] > 0)
                     $actualReplacement = false;
               }
               
               if($actualReplacement)
               {
                  $afterPos = preg_replace('('.$regexOpening.')iUs', $tags[2], $afterPos, 1);
                  $afterPos = preg_replace('('.$regexClosing.')iUs', $tags[3], $afterPos, 1);
               }
               else
               {
                  $closingBracket = strpos($afterPos, ']');
                  $afterPos = substr($afterPos, $closingBracket + 1);
                  $afterPos = preg_replace('('.$regexClosing.')iUs', '', $afterPos, 1);
               }
               
               $parsed = $beforePos.$afterPos;
            }
         }
         $pos = strpos($parsed, $openingTag);
      }
      
      return $parsed;
   }

   /*
   * Replaces tags of a tag pair which are potentially nested within another pair. The tags are 
   * provided with an array made of the 4 following strings:
   *
   * -[0]: opening tag (before parsing)
   * -[1]: closing tag (before parsing)
   * -[2]: opening tag (after parsing)
   * -[3]: opening tag (after parsing)
   *
   * @param string $content  The input text where tags must be replaced
   * @param string[] $tags   The opening/closing tags and what they should be replaced with
   * @return string          The same text, with replaced tags
   */

   private static function replaceTagsNested($content, $tags)
   {
      $parsed = $content;
      
      $regexOpening = str_replace('[', '\[', $tags[0]);
      $regexOpening = str_replace(']', '\]', $regexOpening);
      
      $regexClosing = str_replace('[', '\[', $tags[1]);
      $regexClosing = str_replace(']', '\]', $regexClosing);
      
      // First opening tag
      $pos = strpos($parsed, $tags[0]);
      $posEnd = 0;
      while($pos !== FALSE)
      {
         $posEnd = strpos($parsed, $tags[1]);
         
         // Skips closing tags if before the opening tag
         while($posEnd !== FALSE && $posEnd < $pos)
            $posEnd = strpos($parsed, $tags[1], $posEnd + 1);
         
         if($posEnd === FALSE)
         {
            break;
         }
         else
         {
            // Checks for nested tag(s)
            $offset = $pos + 1; // Ignores first [ to look for next nested tag (if any)
            $posNested = strpos($parsed, $tags[0], $offset);
            while($posNested !== FALSE && $posNested < $posEnd)
            {
               $pos = $posNested;
               $offset = $pos + 1;
               $posNested = strpos($parsed, $tags[0], $offset);
            }
            
            // At this point, we have a pair free of nested blocks, so replacement is possible
            $beforePos = substr($parsed, 0, $pos);
            $afterPos = substr($parsed, $pos);
            
            $afterPos = preg_replace('('.$regexOpening.')iUs', $tags[2], $afterPos, 1);
            $afterPos = preg_replace('('.$regexClosing.')iUs', $tags[3], $afterPos, 1);
            
            $parsed = $beforePos.$afterPos;
         }
         
         // Next opening tag
         $pos = strpos($parsed, $tags[0]);
      }
      
      return $parsed;
   }

   /*
   * Parses an input text to translate format code into HTML.
   *
   * @param string $content  The input text
   * @return string          The same text, formatted in HTML
   */

   public static function parse($content)
   {
      $httpPathBis = str_replace('http://', '', PathHandler::HTTP_PATH);
      $httpPathBis = str_replace('.', '\.', $httpPathBis);
      $httpPathBis = str_replace('/', '\/', $httpPathBis);
      
      $regexURL = '(https?:\/\/(?:www\.|(?!www))?[^\s\.]+\.[^\s]{2,}|www\.[^\s]+\.[^\s]{2,})';
      $regexRGB = '(\d{1,3}),\s?(\d{1,3}),\s?(\d{1,3})'; // Also matches numbers larger than 255, but will be interpreted as 255 by browser
      $regexHexa = '#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})'; // http://www.mkyong.com/regular-expressions/how-to-validate-hex-color-code-with-regular-expression/
      $regexEmoticon = '\!e[([_a-zA-Z0-9\.\-]*?)\]';
      $regexPseudo = '([a-zA-Z0-9_-]{3,20})';

      /*
      * Deals with line prefixes (i.e., ">" and "*" characters starting a line), used for quotations 
      * and bullet lists. For simplicity, this is carried out before the application of nl2br() to 
      * avoid taking account of <br /> at the end of lines while placing the [/cite] tags.
      */
      
      $explodedText = explode("\n", $content);
      array_push($explodedText, "<END>"); // Eases parsing
      
      /*
      * First takes care of the ">" line prefixes. The rules are the following:
      * -A line can start with multiple ">". Consecutive lines with the same amount of prefix ">" 
      *  will be put between by the same [cite][/cite] tags (nested if more than one ">").
      * -Each time there is a > more, a [cite] tag is appended to the current line.
      * -Each time there is a > less, a [/cite] tag is appended to the previous line.
      */
      
      $globalLevel = 0;
      for($i = 0; $i < count($explodedText); $i++)
      {
         // Ignores empty lines
         if($explodedText[$i] === "\r" || $explodedText[$i] === "")
            continue;
         
         // Counts the amount of prefix ">" (as &gt;) and also removes them
         $curLevel = 0;
         while(substr($explodedText[$i], 0, 4) === '&gt;')
         {
            $explodedText[$i] = substr($explodedText[$i], 4);
            $curLevel++;
         }
         
         if($curLevel > $globalLevel)
         {
            for($j = $globalLevel; $j < $curLevel; $j++)
               $explodedText[$i] = "[cite]".$explodedText[$i];
            $globalLevel = $curLevel;
         }
         else if($curLevel < $globalLevel)
         {
            $lastChar = substr($explodedText[$i], -1, 1);
            if($lastChar === "\r")
               $explodedText[$i] = substr($explodedText[$i], 0, -1);
            for($j = $curLevel; $j < $globalLevel; $j++)
               $explodedText[$i - 1] .= '[/cite]';
            if($lastChar === "\r")
               $explodedText[$i] .= "\r";
            $globalLevel = $curLevel;
         }
      }
      
      /*
      * Lists are handled by sequentially going through the lines to place <listBegin>, <listEnd> and 
      * <listElement> tags at the right places, with the text still being free of any HTML. The main 
      * challenge is to take account of [cite] and [spoiler] tags at the start, then end if the start
      * besides these tags is indeed "*". Finally, the code takes advantage of the fact that encoding 
      * ensures "<" and ">" are respectively &gt; and &lt; in the text to parse, ensuring no user can 
      * temper with these tags.
      */
      
      $openedList = false;
      $atLeastOneList = false;
      for($i = 0; $i < count($explodedText); $i++)
      {
         // Ignores empty lines
         if($explodedText[$i] === "\r" || $explodedText[$i] === "")
            continue;
         else if($explodedText[$i] === '<END>')
         {
            if($openedList)
            {
               $openedList = false;
               $explodedText[$i] = "<listEnd>";
               array_push($explodedText, '<END>');
            }
         }
         
         // Checks that there isn't [cite] or [spoiler] tags at start
         $prefix = '';
         $curPrefix = substr($explodedText[$i], 0, 6);
         while($curPrefix === '[cite]' || $curPrefix === '[spoil')
         {
            if($curPrefix === '[cite]')
            {
               $prefix .= '[cite]';
               $explodedText[$i] = substr($explodedText[$i], 6);
            }
            else if($curPrefix === '[spoil')
            {
               $prefix .= '[spoiler]';
               $explodedText[$i] = substr($explodedText[$i], 9);
            }
            $curPrefix = substr($explodedText[$i], 0, 6);
         }
         
         // Does the same for closing tags
         $suffix = '';
         $curSuffix = substr($explodedText[$i], -7);
         while($curSuffix === '[/cite]' || $curSuffix === 'poiler]')
         {
            if($curSuffix === '[/cite]')
            {
               $suffix = '[/cite]'.$suffix;
               $explodedText[$i] = substr($explodedText[$i], 0, -7);
            }
            else if($curSuffix === 'poiler]')
            {
               $suffix = '[/spoiler]'.$suffix;
               $explodedText[$i] = substr($explodedText[$i], 0, -10);
            }
            $curSuffix = substr($explodedText[$i], -7);
         }
         
         if(substr($explodedText[$i], 0, 1) === '*')
         {
            // Removes any other cite/spoiler tag (forbidden in lists)
            $explodedText[$i] = str_replace('[cite]', '', $explodedText[$i]);
            $explodedText[$i] = str_replace('[spoiler]', '', $explodedText[$i]);
            $explodedText[$i] = str_replace('[/cite]', '', $explodedText[$i]);
            $explodedText[$i] = str_replace('[/spoiler]', '', $explodedText[$i]);
            
            if(strlen($prefix) > 0 && $openedList)
            {
               $openedList = false;
               $prefix = '<listEnd>'.$prefix;
            }
            
            // Suppressing * and blank spaces (if any)
            $tempStr = substr($explodedText[$i], 1);
            while(substr($tempStr, 0, 1) == ' ')
               $tempStr = substr($tempStr, 1);
            
            if($openedList)
            {
               $explodedText[$i] = "<listElement>".$tempStr."</listElement>";
            }
            else
            {
               $openedList = true;
               $atLeastOneList = true;
               $explodedText[$i] = "<listBegin><listElement>".$tempStr."</listElement>";
            }
            
            if(strlen($suffix) > 0)
            {
               $openedList = false;
               $explodedText[$i] .= '<listEnd>';
            }
         }
         else
         {
            if($openedList)
            {
               $openedList = false;
               $explodedText[$i] = "<listEnd>".$explodedText[$i];
            }
         }
         
         if(strlen($prefix) > 0)
            $explodedText[$i] = $prefix.$explodedText[$i];
         if(strlen($suffix) > 0)
            $explodedText[$i] .= $suffix;
      }
      array_pop($explodedText);
      $parsed = implode("\n", $explodedText);
      $parsed = nl2br($parsed);
      
      if($atLeastOneList)
      {
         // Replaces <listBegin> and <listElement> tags
         $parsed = str_replace('<listBegin>', "</p>\n<ul>\n", $parsed);
         $parsed = str_replace('<listEnd>', "</ul>\n<p>", $parsed);
         $parsed = str_replace('<listElement>', "<li>", $parsed);
         $parsed = str_replace('</listElement>', "</li>\n", $parsed);
         
         // Removes needless <br /> between <li> elements
         $parsed = str_replace("</li>\n<br />", "</li>\n", $parsed);
         $parsed = str_replace("</li>\r\n<br />", "</li>\r\n", $parsed);
      }
      
      // Replacing [url]*[/url] with preg_replace since the regex is very precise
      $parsed = preg_replace('(\[url\]'.$regexURL.'\[/url\])iUs', '<a href="$1" target="blank">$1</a>', $parsed);
      
      // Parsing with replaceTags() (for tags which can encompass other, distinct tags)
      $parsed = self::replaceTags($parsed, array('[g]', '[/g]', '<strong>', '</strong>'));
      $parsed = self::replaceTags($parsed, array('[i]', '[/i]', '<em>', '</em>'));
      $parsed = self::replaceTags($parsed, array('[s]', '[/s]', '<u>', '</u>'));
      $parsed = self::replaceTags($parsed, array('[b]', '[/b]', '<s>', '</s>'));
      $parsed = self::replaceTags($parsed, array('[t]', '[/t]', "\n</p>\n<h2>", "</h2>\n<p>"));
      $parsed = self::replaceTags($parsed, array('[c]', '[/c]', '<span class="hiddenText">', '</span>'));
      $parsed = self::replaceTags($parsed, array('[url=placeholder]', '[/url]', '<a href="$1" target="blank">', '</a>'), $regexURL);
      $parsed = self::replaceTags($parsed, array('[rgb=placeholder]', '[/rgb]', '<span style="color: rgb($1,$2,$3);">', '</span>'), $regexRGB);
      $parsed = self::replaceTags($parsed, array('[hexa=placeholder]', '[/hexa]', '<font color="#$1">', '</font>'), $regexHexa);
      $parsed = self::replaceTags($parsed, array('!emoticon[', ']', '<img class="emoticon" alt="Emoticon" src="./upload/emoticons/', '"/>'), $regexEmoticon);
      $parsed = self::replaceTags($parsed, array('[centre]', '[/centre]', "\n</p>\n<p style=\"text-align: center;\">\n", "\n</p>\n<p>"));
      $parsed = self::replaceTags($parsed, array('[droite]', '[/droite]', "\n</p>\n<p style=\"text-align: right;\">\n", "\n</p> \n<p>"));
      
      // Simple quotation
      $quotationTags = array('[cite]',
                             '[/cite]',
                             "</p>\n<div class=\"quotation\"><p>\n",
                             "\n</p>\n</div>\n<p>");
      $parsed = self::replaceTagsNested($parsed, $quotationTags);
      
      // Spoiler tag
      $fullOpeningTagSpoiler = "</p>\n<div class=\"spoiler\">\n";
      $fullOpeningTagSpoiler .= "<p><a data-id-spoiler=\"placeholderSpoiler\">Cliquez pour afficher</a></p>\n";
      $fullOpeningTagSpoiler .= "<div id=\"placeholderSpoiler\" style=\"display: none;\"><p>\n";
      $spoilerTags = array('[spoiler]',
                           '[/spoiler]',
                           $fullOpeningTagSpoiler,
                           "\n</p>\n</div>\n</div>\n<p>");
      $parsed = self::replaceTagsNested($parsed, $spoilerTags);
      
      // Some post-processing to finish spoiler tags
      $nbToReplace = substr_count($parsed, 'id="placeholderSpoiler"');
      $toHash = LoggedUser::$data['pseudo'].Utils::SQLServerTime().'-';
      for($i = 1; $i <= $nbToReplace; $i++)
      {
         $hash = substr(sha1($toHash.$i), 0, 10);
         $parsed = preg_replace('(javascript:showSpoiler\(\'placeholderSpoiler\'\))iUs', 'javascript:showSpoiler(\''.$hash.'\')', $parsed, 1);
         $parsed = preg_replace('(id="placeholderSpoiler")iUs', 'id="'.$hash.'"', $parsed, 1);
         $parsed = preg_replace('(data-id-spoiler="placeholderSpoiler")iUs', 'data-id-spoiler="'.$hash.'"', $parsed, 1);
      }
      
      return $parsed;
   }

   /*
   * Takes a text already formatted in HTML and turns it back into format code. Also takes care of 
   * HTML <br/> and new lines/carriage returns.
   *
   * @param string $content  The text (in HTML)
   * @return string          The same text in format code
   */

   public static function unparse($content)
   {
      $unparsed = $content;
      
      $unparsed = str_replace('<br/>', '', $unparsed);
      $unparsed = str_replace('<br />', '', $unparsed);
      $unparsed = str_replace('\n', '
      ', $unparsed);
      $unparsed = str_replace('\r', '', $unparsed);
      
      // Unparsing of tags
      $unparsed = str_replace('<strong>', '[g]', $unparsed);
      $unparsed = str_replace('</strong>', '[/g]', $unparsed);
      $unparsed = str_replace('<em>', '[i]', $unparsed);
      $unparsed = str_replace('</em>', '[/i]', $unparsed);
      $unparsed = str_replace('<u>', '[s]', $unparsed);
      $unparsed = str_replace('</u>', '[/s]', $unparsed);
      $unparsed = str_replace('<s>', '[b]', $unparsed);
      $unparsed = str_replace('</s>', '[/b]', $unparsed);
      $unparsed = str_replace("\n</p>\n<h2>", '[t]', $unparsed);
      $unparsed = str_replace("</h2>\n<p>", '[/t]', $unparsed);
      $unparsed = str_replace('<span class="hiddenText">', '[c]', $unparsed);
      $unparsed = str_replace('</ span>', '[/c]', $unparsed);
      $unparsed = str_replace("\n</p>\n<p style=\"text-align: center;\">\n", '[centre]', $unparsed);
      $unparsed = str_replace("\n</p>\n<p>", '[/centre]', $unparsed);
      $unparsed = str_replace("\n</p>\n<p style=\"text-align: right;\">\n", '[droite]', $unparsed);
      $unparsed = str_replace("\n</p> \n<p>", '[/droite]', $unparsed);
      $unparsed = str_replace('<img class="emoticon" alt="Emoticon" src="./upload/emoticons/', '!emoticon[', $unparsed);
      $unparsed = str_replace('"/>', ']', $unparsed);
      
      // Maybe for later ?
      // $unparsed = str_replace("</p>\n<div class=\"quotation\"><p>!user[", '[cite=', $unparsed);
      // $unparsed = str_replace("]<br/>\n<br/>\n", ']', $unparsed);
      
      // Unparsing a quote
      $unparsed = str_replace("</p>\n<div class=\"quotation\"><p>\n", '[cite]', $unparsed);
      $unparsed = str_replace("\n</p>\n</div>\n<p>", '[/cite]', $unparsed);
      
      // Unparsing for lists
      $unparsed = str_replace("</p>\n<ul>\n", '', $unparsed);
      $unparsed = str_replace("</ul>\n<p>", '', $unparsed);
      $unparsed = str_replace("<li>", '* ', $unparsed);
      $unparsed = str_replace("</li>\n", '', $unparsed);
      
      // Unparsing spoilers (needs a regex for what stands in place of the opening tag)
      $regexSpoiler = "</p>\\n<div class=\"spoiler\">\\n";
      $regexSpoiler .= "<p><a data-id-spoiler=\"([a-zA-Z0-9]{10})\">Cliquez pour afficher</a></p>\\n";
      $regexSpoiler .= "<div id=\"([a-zA-Z0-9]{10})\" style=\"display: none;\"><p>\\n";
      $unparsed = preg_replace('('.$regexSpoiler.')iUs', '[spoiler]', $unparsed);
      $unparsed = str_replace("\n</p>\n</div>\n</div>\n<p>", '[/spoiler]', $unparsed);
      
      // Unparsing URLs (easy due to the strict syntax of URLs)
      $regexURL = '(https?:\/\/(?:www\.|(?!www))?[^\s\.]+\.[^\s]{2,}|www\.[^\s]+\.[^\s]{2,})';
      $unparsed = preg_replace('(<a href="'.$regexURL.'" target="blank">'.$regexURL.'<\/a>)iUs', '[url]$2[/url]', $unparsed);
      $unparsed = preg_replace('(<a href="'.$regexURL.'" target="blank">)iUs', '[url=$1]', $unparsed);
      $unparsed = str_replace('</a>', '[/url]', $unparsed);
      
      // Unparsing colors
      $regexRGB = '(\d{1,3}),\s?(\d{1,3}),\s?(\d{1,3})'; // Also matches numbers larger than 255, but will be interpreted as 255 by browser
      $regexHexa = '#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})'; // http://www.mkyong.com/regular-expressions/how-to-validate-hex-color-code-with-regular-expression/
      $unparsed = preg_replace('(<span style="color: rgb\('.$regexRGB.'\);">)iUs', '[rgb=$1,$2,$3]', $unparsed);
      $unparsed = str_replace('</span>', '[/rgb]', $unparsed);
      $unparsed = preg_replace('(<font color="'.$regexHexa.'">)iUs', '[hexa=#$1]', $unparsed);
      $unparsed = str_replace('</font>', '[/hexa]', $unparsed);
      
      // Additionnal steps for formatting involving line breaks (\r\n and not just \n under Windows)
      $unparsed = str_replace("\r\n</p>\r\n<h2>", '[t]', $unparsed);
      $unparsed = str_replace("</h2>\r\n<p>", '[/t]', $unparsed);
      $unparsed = str_replace("\r\n</p>\r\n<p style=\"text-align: center;\">\r\n", '[centre]', $unparsed);
      $unparsed = str_replace("\r\n</p>\r\n<p>", '[/centre]', $unparsed);
      $unparsed = str_replace("\r\n</p>\r\n<p style=\"text-align: right;\">\r\n", '[droite]', $unparsed);
      $unparsed = str_replace("\r\n</p> \r\n<p>", '[/droite]', $unparsed);
      $unparsed = str_replace("</p>\r\n<div class=\"quotation\"><p>\r\n", '[cite]', $unparsed);
      $unparsed = str_replace("\r\n</p>\r\n</div>\n<p>", '[/cite]', $unparsed);
      $unparsed = str_replace("</p>\r\n<ul>\r\n", '', $unparsed);
      $unparsed = str_replace("</ul>\r\n<p>", '', $unparsed);
      $unparsed = str_replace("</li>\r\n", '', $unparsed);
      
      $regexSpoilerBis = "</p>\\r\\n<div class=\"spoiler\">\\r\\n";
      $regexSpoilerBis .= "<p><a data-id-spoiler=\"([a-zA-Z0-9]{10})\">Cliquez pour afficher</a></p>\\r\\n";
      $regexSpoilerBis .= "<div id=\"([a-zA-Z0-9]{10})\" style=\"display: none;\"><p>\\r\\n";
      $unparsed = preg_replace('('.$regexSpoilerBis.')iUs', '[spoiler]', $unparsed);
      $unparsed = str_replace("\r\n</p>\n</div>\r\n</div>\r\n<p>", '[/spoiler]', $unparsed);
      
      // Last step: sequentially goes through the unparsed text to turn [cite] at line start back to >
      $explodedText = explode("\n", $unparsed);
      $globalDepth = 0;
      for($i = 0; $i < count($explodedText); $i++)
      {
         // Ignores empty lines
         if($explodedText[$i] === "\r" || $explodedText[$i] === "")
            continue;
         
         // Counts the amount of prefix ">" (as &gt;) and also removes them
         $addedDepth = 0;
         while(substr($explodedText[$i], 0, 6) === '[cite]')
         {
            $explodedText[$i] = substr($explodedText[$i], 6);
            $addedDepth++;
         }
         $curDepth = $globalDepth + $addedDepth;
         
         // Counts the amount of suffix [/cite] (automatic [/cite] are always at end of lines)
         $lastChar = substr($explodedText[$i], -1, 1);
         if($lastChar === "\r")
            $explodedText[$i] = substr($explodedText[$i], 0, -1);
         $removedDepth = 0;
         while(substr($explodedText[$i], -7, 7) === '[/cite]')
         {
            $explodedText[$i] = substr($explodedText[$i], 0, -7);
            $removedDepth++;
         }
         if($lastChar === "\r")
            $explodedText[$i] .= "\r";
         
         for($j = 0; $j < $curDepth; $j++)
            $explodedText[$i] = ">".$explodedText[$i];
         $globalDepth = $globalDepth + $addedDepth - $removedDepth;
      }
      $unparsed = implode("\n", $explodedText);
      
      return $unparsed;
   }

   /*
   * Replaces prefixes of all picture paths which initially were in the user's buffer with the 
   * expected prefixes after saving the buffer and the new message. The ID of both the topic and 
   * the message must be provided.
   *
   * @param string $content   The text where the picture path prefixes should be replaced
   * @param integer $topicID  The ID of the topic where the new message is being posted
   * @param integer $postID   The ID of the message where prefixes are being replaced
   * @param string            The updated content
   */

   public static function relocate($content, $topicID, $postID)
   {
      $modified = $content;
      
      $expectedPrefixRelative = 'upload/topics/'.$topicID.'/'.$postID.'_'.LoggedUser::$data['used_pseudo'].'_';
      $expectedPrefixAbsolute = PathHandler::HTTP_PATH.$expectedPrefixRelative;
      
      $prefixToReplaceRelative = 'upload/tmp/'.LoggedUser::$data['pseudo'].'/';
      $prefixToReplaceAbsolute = PathHandler::HTTP_PATH.$prefixToReplaceRelative;

      $modified = str_replace($prefixToReplaceRelative, $expectedPrefixRelative, $modified);
      $modified = str_replace($prefixToReplaceAbsolute, $expectedPrefixAbsolute, $modified);
      
      return $modified;
   }

   /*
   * Does the same operation as above but for article segments.
   *
   * @param string $content     The text where the picture path prefixes should be replaced.
   * @param integer $articleID  The ID of the article where the segment is
   * @param integer $segmentID  The ID of the segment where prefixes are being replaced
   * @param string              The updated content
   */

   public static function relocateInSegment($content, $articleID, $segmentID)
   {
      $modified = $content;
      
      $expectedPrefixRelative = 'upload/articles/'.$articleID.'/'.$segmentID.'/';
      $expectedPrefixAbsolute = PathHandler::HTTP_PATH.$expectedPrefixRelative;
      
      $prefixToReplaceRelative = 'upload/tmp/'.LoggedUser::$data['pseudo'].'/';
      $prefixToReplaceAbsolute = PathHandler::HTTP_PATH.$prefixToReplaceRelative;

      $modified = str_replace($prefixToReplaceRelative, $expectedPrefixRelative, $modified);
      $modified = str_replace($prefixToReplaceAbsolute, $expectedPrefixAbsolute, $modified);
      
      return $modified;
   }
}

?>
