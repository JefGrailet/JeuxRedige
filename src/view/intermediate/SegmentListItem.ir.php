<?php

class SegmentListItemIR
{
   /*
   * Converts the array modelizing a segment into an intermediate representation, ready to be used 
   * in a template. The intermediate representation is a new array containing:
   *
   * -ID of the segment
   * -Position of the segment within the structure of the parent article
   * -Title of the segment (specifically formatted for template)
   * -3 inputs for template blocks to show or not buttons to interact with the segments
   *
   * @param mixed $data[]      The segment itself
   * @param number $position   Position (order) of the segment in its parent article
   * @param bool $published    True if the parent article is published
   * @return mixed[]           The intermediate representation
   */

   public static function process($data, $published)
   {
      $output = array('ID' => $data['id_segment'], 
      'position' => $data['position'], 
      'title' => '', 
      'moveUp' => '', 
      'moveDown' => '', 
      'delete' => '');
      
      if($data['position'] == 1 && $data['title'] == NULL)
         $output['title'] = 'head||'.$data['id_segment'];
      else
         $output['title'] = 'other||'.$data['id_segment'].'|'.$data['title'];
      
      if(!$published)
      {
         if($data['position'] > 1)
            $output['moveUp'] = $data['id_segment'];
         $output['moveDown'] = $data['id_segment'];
         $output['delete'] = $data['id_segment'];
      }
      
      return $output;
   }
}

?>
