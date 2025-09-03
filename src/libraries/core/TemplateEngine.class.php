<?php

/**
 * This file defines a static class acting as the custom template engine for the website. The 
 * templates tailored for it should use the .ctpl extension (for Custom TemPLate). Each template 
 * is a HTML bit of the website where interchangeable parts, included between curly braces, will 
 * be replaced with inputs provided by the calling code, using the template engine. Various types 
 * of interchangeable parts have been defined to implement various page components.
 */

class TemplateEngine
{
   /**
    * Gets a template as a string on the basis of its location compared to the root directory.
    *
    * @param string $path  The path to the template (relative to the root directory)
    * @return string       The template as a string or an empty string in case of error
    */
   
   private static function get($path)
   {
      $finalPath = PathHandler::WWW_PATH().$path;
      
      if(strtolower(substr($finalPath, -5)) !== '.ctpl' || !file_exists($finalPath))
         return '';
      
      $content = file_get_contents($finalPath);
      if($content != FALSE)
         return $content;
      return '';
   }
   
   /**
    * Processes a list block to insert a HTML list whose items can chosen by calling code.
    * 
    * Syntax: {list:label[start1|start2|end]||lab1 & content1|...|labX & contentX}
    *         * start1 = opening part of the HTML list when only one item is displayed
    *         * start2 = opening part of the HTML list when multiple items are displayed
    *         * end = closing part of the HTML list
    *         * labX = label of item X
    *         * contentX = HTML of item X (to display)
    * Input: label => lab1|lab2|...|labX or empty string
    *        * labX = same label as in the template syntax, to select an item to display
    *        * Only uses labX you want to display, separated with "|"
    *        * If empty string, this part of the template is ignored
    *
    * @param string $block      The block itself (minus its type)
    * @param mixed $data[][]    The data fed to the overarching call to process()
    * @param string &$outs[][]  The outputs of the overarching call to process()
    * @throws Exception         If syntax or inputs are mismanaged
    */
   
   private static function listBlock($block, $data, &$outs)
   {
      $delimiter = strpos($block, '[');
      if($delimiter === FALSE)
         throw new Exception('Bad syntax in list block {list:'.$block.'}');

      $label = substr($block, 0, $delimiter);
      $firstSplit = explode('||', substr($block, $delimiter + 1));
      if(count($firstSplit) != 2)
         throw new Exception('Bad syntax in list '.$label.', missing "||"');

      $itemsToParse = explode('|', $firstSplit[1]);
      $items = array();
      for($i = 0; $i < count($itemsToParse); $i++)
      {
         $split = explode(' & ', $itemsToParse[$i]);
         if (count($split) != 2)
            throw new Exception('Bad syntax in list '.$label.', missing " & " for items');
         $items[$split[0]] = $split[1];
      }
      $keys = array_keys($items);
      
      $wrappings = explode('|', substr($firstSplit[0], 0, -1));
      if(count($wrappings) != 3)
         throw new Exception('Bad syntax in list '.$label.', bad format for list wrappings');
      
      for($i = 0; $i < count($data); $i++)
      {
         if(!isset($data[$i][$label]))
            continue;

         $selected = $data[$i][$label];
         if($selected != '')
         {
            if(strpos($selected, '|') !== FALSE)
            {
               $outs[$i] .= $wrappings[1]."\n";
               $selectedItems = explode('|', $selected);
               for($j = 0; $j < count($selectedItems); $j++)
               {
                  if(!in_array($selectedItems[$j], $keys))
                  {
                     $eMsg = 'Item '.$selectedItems[$j].' (parsing n°'.($i + 1).') ';
                     $eMsg .= 'does not exist in list '.$label;
                     throw new Exception($eMsg);
                  }
                  $outs[$i] .= '-'.$items[$selectedItems[$j]]."<br/>\n";
               }
            }
            else
            {
               if(!in_array($selected, $keys))
               {
                  $eMsg = 'Item '.$selected.' (parsing n°'.($i + 1).') ';
                  $eMsg .= 'does not exist in list '.$label;
                  throw new Exception($eMsg);
               }
               $outs[$i] .= $wrappings[0]."\n";
               $outs[$i] .= $items[$selected]."<br/>\n";
            }
            $outs[$i] .= $wrappings[2]."\n";
         }
      }
   }
   
   /**
    * Processes a switch block, a list of HTML parts where only one item can be selected at once 
    * but which may contain replacable substrings in the form of [X] (X = 0, 1, 2...).
    * 
    * Syntax: {switch:label||lab1 & content1|...|labX & contentX}
    *         * labX = label of item X
    *         * contentX = HTML of item X (to display)
    *           * May contain [0], [1], ... [X] as X replacable substrings
    * Input: label => labX or labX||sub1|sub2|...|subX or empty string
    *        * labX = same label as in the template syntax, to select an item to display
    *        * subX = substring to replace in contentX mapped to labX; ordering must be respected
    *        * If empty string, this part of the template is ignored
    *
    * @param string $block      The block itself (minus its type)
    * @param mixed $data[][]    The data fed to the overarching call to process()
    * @param string &$outs[][]  The outputs of the overarching call to process()
    * @throws Exception         If syntax or inputs are mismanaged
    */
   
   private static function switchBlock($block, $data, &$outs)
   {
      $delimiter = strpos($block, '||');
      if($delimiter === FALSE)
         throw new Exception('Erroneous syntax in switch block {switch:'.$block.'}');
      
      $label = substr($block, 0, $delimiter);
      $itemsToParse = explode('|', substr($block, $delimiter + 2));
      $items = array();
      for($i = 0; $i < count($itemsToParse); $i++)
      {
         $split = explode(' & ', $itemsToParse[$i]);
         if (count($split) != 2)
            throw new Exception('Erroneous syntax in switch '.$label.', missing " & " for items');
         $items[$split[0]] = $split[1];
      }
      $keys = array_keys($items);
      
      for($i = 0; $i < count($data); $i++)
      {
         if(!isset($data[$i][$label]))
            continue;

         $selectedLabel = $data[$i][$label];
         if($selectedLabel !== '')
         {
            $values = NULL;
            if(strpos($selectedLabel, '||') != FALSE)
            {
               $exploded = explode('||', $selectedLabel);
               $selectedLabel = $exploded[0];
               $values = explode('|', $exploded[1]);
            }
            if(!in_array($selectedLabel, $keys))
            {
               $eMsg = 'Item '.$selectedLabel.' (parsing n°'.($i + 1).') ';
               $eMsg .= 'does not exist in switch block '.$label;
               throw new Exception($eMsg);
            }
            $item = $items[$selectedLabel];
            if($values != NULL)
            {
               for($j = 0; $j < count($values); $j++)
               {
                  if($values[$j] === 'NULL')
                     $item = str_replace('['.$j.']', '', $item);
                  else
                     $item = str_replace('['.$j.']', $values[$j], $item);
               }
            }
            $outs[$i] .= $item."\n";
         }
      }
   }
   
   /**
    * Processes a (multi) select block to insert a selection input.
    *
    * Syntax: {select:label} OR {multiselect:label}
    * Input: label => selection||val1|val2|...|valX
    *        * selection = current selection; if none, then "selection||" can be omitted
    *        * valX = an item to select among X available
    *          * If the form value must differ from the form display, may be "value,label" where 
    *            "value" is the actual value while "label" is the display between <option> tags
    *
    * @param string $block      The block itself (minus its type)
    * @param mixed $data[][]    The data fed to the overarching call to process()
    * @param string &$outs[][]  The outputs of the overarching call to process()
    * @param boolean $multi     Optional boolean to set to true to enable multiselection (via JS)
    */
   
   private static function selectBlock($block, $data, &$outs, $multi=false)
   {
      $label = $block;
      for($i = 0; $i < count($data); $i++)
      {
         $items = $data[$i][$label];
         $values = '';
         if(strpos($items, '||') != FALSE)
         {
            $exploded = explode('||', $items);
            $values = $exploded[0];
            $items = $exploded[1];
         }
         $itemsArr = explode('|', $items);
         $valuesArr = explode('|', $values);
         
         if($multi)
            $outs[$i] .= "<select id=\"".$label."\" name=\"".$label."[]\" multiple>\n";
         else
            $outs[$i] .= "<select name=\"".$label."\">\n";
         for($j = 0; $j < count($itemsArr); $j++)
         {
            if(strpos($itemsArr[$j], ',') != FALSE)
            {
               $pair = explode(',', $itemsArr[$j], 2);
               $outs[$i] .= '<option value="'.$pair[0].'"';
               if(in_array($pair[0], $valuesArr))
                  $outs[$i] .= ' selected="selected"';
               $outs[$i] .= '>'.$pair[1].'</option>'."\n";
            }
            else
            {
               $outs[$i] .= '<option value="'.$itemsArr[$j].'"';
               if(in_array($itemsArr[$j], $valuesArr))
                  $outs[$i] .= ' selected="selected"';
               $outs[$i] .= '>'.$itemsArr[$j].'</option>'."\n";
            }
         }
         $outs[$i] .= "</select>\n";
      }
   }
   
   /**
    * Processes a pages block.
    *
    * Syntax: {pages:label||start|end|current|default|hidden}
    *         * start = HTML starting this pages block
    *         * end = HTML ending this pages block; "none" if no HTML necessary
    *         * current = template HTML ([] = link) for current page; "none" if no unique HTML
    *         * default = template HTML ([] = link) for regular page; "none" if no unique HTML
    *         * hidden = HTML to write in place of hidden pages (when list gets too long)
    * Input: label => perPage|nbItems|curPage|linkTemplate
    *        * perPage = number of items per page
    *        * nbItems = total number of items
    *        * curPage = page currently displayed
    *        * linkTemplate = template string containing [] to replace by any page number
    *
    * @param string $block      The block itself (minus its type)
    * @param mixed $data[][]    The data fed to the overarching call to process()
    * @param string &$outs[][]  The outputs of the overarching call to process()
    * @throws Exception         If inputs are mismanaged
    */
    
   private static function pagesBlock($block, $data, &$outs)
   {
      $delimiter = strpos($block, '||');
      if($delimiter === FALSE)
         throw new Exception('Erroneous syntax in pages block {pages:'.$block.'}');
      $label = substr($block, 0, $delimiter);
      $pagesParts = explode('|', substr($block, $delimiter + 2));
      if(count($pagesParts) != 5)
         throw new Exception('Erroneous syntax in pages block '.$label.': needs 5 parameters');
      
      $currentLinkHTML = NULL;
      $defaultLinkHTML = NULL;
      if(substr_count($pagesParts[2], '[]') != 1 && $pagesParts[2] !== 'none')
      {
         $eMsg = 'Erroneous syntax in pages block '.$label.': [2] must contain ';
         $eMsg .= 'placeholder [] only once or "none"';
         throw new Exception($eMsg);
      }
      else if($pagesParts[2] !== 'none')
         $currentLinkHTML = explode('[]', $pagesParts[2]);
      
      if(substr_count($pagesParts[3], '[]') != 1 && $pagesParts[3] !== 'none')
      {
         $eMsg = 'Erroneous syntax in pages block '.$label.': [3] must contain ';
         $eMsg .= 'placeholder [] only once or "none"';
         throw new Exception($eMsg);
      }
      else if($pagesParts[3] !== 'none')
         $defaultLinkHTML = explode('[]', $pagesParts[3]);
      
      for($i = 0; $i < count($data); $i++)
      {
         if(!isset($data[$i][$label]) || $data[$i][$label] === '')
            continue;
         
         $pagesInput = explode('|', $data[$i][$label]);
         if(count($pagesInput) != 4)
            throw new Exception('Wrong use of pages block '.$label.', please check arguments');
         $perPage = intval($pagesInput[0]);
         $nbItems = intval($pagesInput[1]);
         $curPage = intval($pagesInput[2]);
         $linkTemplate = explode('[]', $pagesInput[3]);
         if(count($linkTemplate) != 2)
         {
            $eMsg = 'Wrong use of pages block '.$label.', ';
            $eMsg .= 'link template has a bad format';
            throw new Exception($eMsg);
         }
         
         if($nbItems <= $perPage)
            continue;
         
         $outs[$i] .= $pagesParts[0];
         $nbPages = ceil($nbItems / $perPage);
         $hiddenPrinted = false;
         for($j = 1; $j <= $nbPages; $j++)
         {
            $dispStart = $curPage - 5;
            $dispEnd = $curPage + 5;
            if($j <= 5 || $j >= ($nbPages - 5) || ($j >= $dispStart && $j <= $dispEnd))
            {
               if($j == $curPage)
               {
                  if($currentLinkHTML != NULL)
                     $outs[$i] .= $currentLinkHTML[0].$j.$currentLinkHTML[1].' ';
                  else
                     $outs[$i] .= $j.' ';
               }
               else
               {
                  $link = '<a href="'.$linkTemplate[0].$j.$linkTemplate[1].'">'.$j.'</a>';
                  if($defaultLinkHTML != NULL)
                     $outs[$i] .= $defaultLinkHTML[0].$link.$defaultLinkHTML[1].' ';
                  else
                     $outs[$i] .= $link.' ';
               }
               $hiddenPrinted = false;
            }
            else if(!$hiddenPrinted)
            {
               $outs[$i] .= $pagesParts[5].' ';
               $hiddenPrinted = true;
            }
         }
         if($pagesParts[1] !== 'none')
            $outs[$i] .= $pagesParts[1];
      }
   }
   
   /**
    * Processes a navigation block, a variant of the pages block suitable for dynamic paging.
    *
    * Syntax: {navigation:label}
    * Input: label => perPage|nbItems|curPage|linkTemplate|getterScript|refreshScript|seenScript
    *        * perPage = number of items per page
    *        * nbItems = total number of items
    *        * curPage = page currently displayed
    *        * linkTemplate = template string containing [] to replace by any page number
    *        * getterScript = PHP script (+ argument(s) if any) to retrieve new items
    *        * refreshScript = PHP script (+ argument(s) if any) to refresh the items (optional)
    *        * seenScript = PHP (+ argument(s) if any) to record having seen new items (optional)
    * 
    * @param string $block      The block itself (minus its type)
    * @param mixed $data[][]    The data fed to the overarching call to process()
    * @param string &$outs[][]  The outputs of the overarching call to process()
    * @throws Exception         If inputs are mismanaged
    */
   
   private static function navigationBlock($block, $data, &$outs)
   {
      $label = $block;
      for($i = 0; $i < count($data); $i++)
      {
         if(!isset($data[$i][$label]))
            throw new Exception('No data provided for navigation block '.$label);
       
         $pagesInput = explode('|', $data[$i][$label]);
         if(count($pagesInput) < 5 || count($pagesInput) > 7)
            throw new Exception('Bad use of navigation block '.$label.', please check arguments');
       
         $perPage = intval($pagesInput[0]);
         $nbItems = intval($pagesInput[1]);
         $curPage = intval($pagesInput[2]);
         $linkTemplate = explode('[]', $pagesInput[3]);
         $getterScript = $pagesInput[4];
         $refreshScript = '';
         $allSeenScript = '';
         if(count($pagesInput) >= 6)
            $refreshScript = $pagesInput[5];
         if(count($pagesInput) == 7)
            $allSeenScript = $pagesInput[6];
       
         if(count($linkTemplate) != 2)
         {
            $eMsg = 'Bad use of navigation block '.$label.', ';
            $eMsg .= 'link template has a bad format';
            throw new Exception($eMsg);
         }
         
         $outs[$i] .= '<p class="pagesNav" ';
         $outs[$i] .= 'data-per-page="'.$perPage.'" ';
         $outs[$i] .= 'data-getter="'.$getterScript.'" ';
         if(strlen($refreshScript) > 0)
            $outs[$i] .= 'data-refresh="'.$refreshScript.'" ';
         if(strlen($allSeenScript) > 0)
            $outs[$i] .= 'data-all-seen="'.$allSeenScript.'" ';
         $outs[$i] .= 'data-static-link="'.$pagesInput[3].'"';
         if($nbItems <= $perPage)
            $outs[$i] .= ' style="display: none;"';
         $outs[$i] .= '><span class="backBlack">Pages :</span> ';
         if($nbItems <= $perPage)
         { 
            $outs[$i] .= '</p>';
            return;
         }
         
         $nbPages = ceil($nbItems / $perPage);
         $hideMiddle = false;
         if($nbPages > 15)
            $hideMiddle = true;
         for($j = 1; $j <= $nbPages; $j++)
         {
            if($hideMiddle)
            {
               if($j == 6)
                  $outs[$i] .= '<span class="hiddenPages">';
               else if($j == $nbPages - 4)
                  $outs[$i] .= '</span><span class="unhidePages">...</span> ';
            }
         
            $outs[$i] .= '<span data-page="'.$j.'" class="pageLink';
            if($j == $curPage)
               $outs[$i] .= 'Selected">'.$j;
            else
               $outs[$i] .= '"><a href="'.$linkTemplate[0].$j.$linkTemplate[1].'">'.$j.'</a>';
            $outs[$i] .= '</span> ';
         }
         $outs[$i] .= '</p>';
      }
   }
   
   /**
    * Processes an optional block. Input can be missing (like feeding an empty string).
    *
    * Syntax: {optional:label||HTML string with replacable [] (can appear multiple times)}
    * Input: label => string to replace the [] substring or empty string (nothing displayed)
    *
    * @param string $block      The block itself (minus its type)
    * @param mixed $data[][]    The data fed to the overarching call to process()
    * @param string &$outs[][]  The outputs of the overarching call to process()
    * @throws Exception         If the syntax or inputs are mismanaged
    */
    
   private static function optionalBlock($block, $data, &$outs)
   {
      $delimiter = strpos($block, '||');
      if($delimiter === FALSE)
         throw new Exception('Erroneous syntax in optional block {optional:'.$block.'}');
      $label = substr($block, 0, $delimiter);
      $item = substr($block, $delimiter + 2);
            
      for($i = 0; $i < count($data); $i++)
      {
         if(!isset($data[$i][$label]))
            continue;

         $selectedLabel = $data[$i][$label];
         if($selectedLabel != '')
         {
            $itemEdited = str_replace('[]', $selectedLabel, $item);
            $outs[$i] .= $itemEdited."\n";
         }
      }
   }
   
   /**
    * Processes an enumeration block. Input can be missing (like feeding an empty string).
    * 
    * Syntax: {enum:label||start|end}
    *         * start = HTML at the beginning of an item HTML display; "none" if no HTML necessary
    *         * end = HTML at the end of an item HTML display; "none" if no HTML necessary
    * Input: label => item1|item2|...|itemX or empty string
    *        * itemX = substring (may contain HTML) to place between start and end
    *        * Items are inserted in their order of appearance
    *        * If empty string, this part of the template is ignored
    *
    * @param string $block      The block itself (minus its type)
    * @param mixed $data[][]    The data fed to the overarching call to process()
    * @param string &$outs[][]  The outputs of the overarching call to process()
    * @throws Exception         If syntax or inputs are mismanaged
    */

   private static function enumBlock($block, $data, &$outs)
   {
      $delimiter = strpos($block, '||');
      if($delimiter === FALSE)
         throw new Exception('Erroneous syntax in enum block {'.$block.'}');
      $label = substr($block, 0, $delimiter);
      $HTMLparts = explode('|', substr($block, $delimiter + 2));

      if(count($HTMLparts) != 2)
         throw new Exception('Erroneous syntax in enum block '.$label.': needs 2 parameters');

      for($i = 0; $i < count($data); $i++)
      {
         if(!isset($data[$i][$label]))
            continue;

         $itemsArr = explode('|', $data[$i][$label]);
         for($j = 0; $j < count($itemsArr); $j++)
            $outs[$i] .= $HTMLparts[0].$itemsArr[$j].$HTMLparts[1]."\n";
      }
   }

   /**
    * Processes a template to replace the parts contained inside curly braces ({}) by their values 
    * inside the array $data (either a single variable, either an index in a list of choices) or 
    * by an empty string if no data is provided. Such data is given in a 2D array to process a 
    * same template for multiple outputs, to prevent re-interpreting the template multiple times 
    * if such template (e.g., an article thumbnail) should be used multiple times. If a template 
    * should be processed only once, then the input data will consist of a single line of inputs.
    *
    * @param string $template  The template as a string
    * @param mixed $data[][]   A 2D array where each line contains the values that should be used 
    *                          to parse the {} blocks in the template; if not provided, all blocks 
    *                          are replaced by an empty string
    * @return mixed            Processed template(s) as a 1D array, or a 2D array [[line, misused 
    *                          block]] if something is wrong with template syntax or inputs
    */
   
   private static function process($template, $data = NULL)
   {
      if($data != NULL && (!is_array($data) || !is_array($data[0])))
         return array(array(0, 'The data to parse must be provided as a 2D array'));
      
      $labels = NULL;
      $nbOutputs = 1;
         
      if($data != NULL)
      {
         $nbOutputs = count($data);
         $labels = array_keys($data[0]);
         if($labels === range(0, count($data[0]) - 1))
            return array(array(0, 'The input arrays must be associative'));
      }
      
      $remaining = $template;
      $parsed = array();
      for($i = 0; $i < $nbOutputs; $i++)
         array_push($parsed, '');
      $length = strlen($remaining);
      
      $posNextBlock = strpos($remaining, '{');
      $curBlock = '';
      while($posNextBlock !== FALSE)
      {
         $posClosing = strpos($remaining, '}', $posNextBlock);
         if($posClosing === FALSE)
            return array(0, 'Parsing error: mismatching accolades');
         $curBlock = substr($remaining, $posNextBlock + 1, $posClosing - $posNextBlock - 1);
         $inBetween = substr($remaining, 0, $posNextBlock);
         for($i = 0; $i < $nbOutputs; $i++)
            $parsed[$i] .= $inBetween;
         $remaining = substr($remaining, $posClosing + 1);
         
         $curBlock = str_replace("|\r\n", '|', $curBlock);
         $curBlock = str_replace("|\n", '|', $curBlock);
         
         if($data == NULL) // No data at all (can be intentional): skip to next block
         {
            $curBlock = '';
            $posNextBlock = strpos($remaining, '{');
            continue;
         }
         
         if(substr($curBlock, 0, 1) == '$') // Simple replacement block {$label}
         {
            $label = substr($curBlock, 1);
            if(in_array($label, $labels))
            {
               for($i = 0; $i < $nbOutputs; $i++)
                  $parsed[$i] .= $data[$i][$label];
            }
            else
            {
               $eMsg = 'Failed to parse block {'.$curBlock.'}';
               return array(array(substr_count($parsed[0], "\n"), $eMsg));
            }
         }
         else
         {
            $typeDelimiter = strpos($curBlock, ':');
            if($typeDelimiter === FALSE)
               return array(array(substr_count($parsed[0], "\n"), 'Fatal error: no label given'));
            
            $typeName = substr($curBlock, 0, $typeDelimiter);
            $blockDef = substr($curBlock, $typeDelimiter + 1);
            try
            {
               switch($typeName)
               {
                  case 'enum':
                     self::enumBlock($blockDef, $data, $parsed);
                     break;
                  case 'list':
                     self::listBlock($blockDef, $data, $parsed);
                     break;
                  case 'switch':
                     self::switchBlock($blockDef, $data, $parsed);
                     break;
                  case 'select':
                     self::selectBlock($blockDef, $data, $parsed);
                     break;
                  case 'multiselect':
                     self::selectBlock($blockDef, $data, $parsed, $multi=true);
                     break;
                  case 'pages':
                     self::pagesBlock($blockDef, $data, $parsed);
                     break;
                  case 'navigation':
                     self::navigationBlock($blockDef, $data, $parsed);
                     break;
                  case 'optional':
                     self::optionalBlock($blockDef, $data, $parsed);
                     break;
                  default:
                     throw new Exception('Fatal error: unknown block type '.$typeName);
                }
            }
            catch(Exception $tplError)
            {
               return array(array(substr_count($parsed[0], "\n"), $tplError->getMessage()));
            }
         }
         
         $curBlock = '';
         $posNextBlock = strpos($remaining, '{');
      }
      for($i = 0; $i < $nbOutputs; $i++)
         $parsed[$i] .= $remaining;
      
      return $parsed;
   }
   
   /**
    * Checks the return value of process() to ensure the template was successfully processed. 
    * Otherwise, a HTML string detailing the error(s) is returned.
    *
    * @param string $tpl  Path to the template where the issue arose
    * @param string $arg  The return value of process()
    * @return string      $arg if there is no error, otherwise a HTML string detailling the error
    */
   
   private static function error($tpl, $arg)
   {
      if(strlen($tpl) === 0)
      {
         $message = '<p><strong>Unexpected error :</strong> missing template. ';
         $message .= 'Reports this error to the webmaster as soon as possible.</p>'."\n";
         return $message;
      }
      else if(is_array($arg) && is_array($arg[0]))
      {
         $message = '<p><strong>Unexpected error :</strong> misused template '.$tpl.'. ';
         $message .= 'Reports the following error to the webmaster as soon as possible:<br/>'."\n";
         $message .= '<br/>'."\n";
         $message .= 'Line '.$arg[0][0].': '.$arg[0][1].'</p>'."\n";
         return $message;
      }
      return $arg;
   }
   
   /*
    * Gets and parses a template, and checks if an error occurred. It essentially combines 
    * together the previous private static methods into a single public method that can be called 
    * to use a template once.
    *
    * @param string $path   The path to the template (relative to the root directory)
    * @param mixed $data[]  An array of values to replace the {} blocks in the template; if not 
    *                       provided, all {} blocks are replaced by an empty string
    * @return string        The produced content or some HTML string explaining what went wrong
    */
   
   public static function parse($path, $data = NULL)
   {
      $tpl = self::get($path);
      if($tpl === '')
         return self::error('', '');
      
      $as2DArray = NULL;
      if($data != NULL)
      {
         $as2DArray = array();
         array_push($as2DArray, $data);
      }
      
      $output = self::process($tpl, $as2DArray);
      if(is_array($output[0]))
         return self::error($path, $output);
      
      return self::error($path, $output[0]);
   }
   
   /*
   * Fulfills the same purpose as the previous method but for multiple outputs. This approach 
   * avoids reading and interpreting a template multiple times, an operation which would add 
   * processing overhead for pages where multiple items of a same kind are being displayed.
   *
   * @param string $path     The path to the template (relative to the root directory)
   * @param mixed $data[][]  A 2D array of values to replace the {} blocks in the template, each 
   *                         line corresponding to the input for one use of the template
   * @return mixed           The final pieces of content as a linear array (each item matches one 
   *                         use of the template) or some HTML string explaining what went wrong
   */

   public static function parseMultiple($path, $data = NULL)
   {
      $tpl = self::get($path);
      if($tpl === '')
         return self::error('', '');
      return self::error($path, self::process($tpl, $data));
   }
   
   /*
   * Parses one output string of the template engine to tell whether it's an error message or a 
   * healthy output.
   *
   * @param string $arg  An output string produced by the template engine
   * @return boolean     True if it's an error message, false otherwise
   */
   
   public static function hasFailed($arg)
   {
      if(!is_array($arg) && substr($arg, 11, 16) === 'Unexpected error')
         return true;
      return false;
   }
}
