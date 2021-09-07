<?php

/**
* This file generates a CAPTCHA, a picture displaying some message which a bot should not be able
* to understand but which a human user should be able to read. Such picture is reinitialized each
* time the user displays a form to post a new message. Such mecanism is being used to prevent both
* spam sent by non-registred users and bot messages.
*
* The CAPTCHA itself consists in a calculation. The human user should then input the result of that
* calculation in the form; it is later verified thanks to a hash of the result stored in a 
* $_SESSION.
*/

session_start();

// Creates the terms of the calculation
$firstTerm = str_pad(mt_rand(0, pow(10, 4) - 1), 4, '0', STR_PAD_LEFT);
$secondTerm = str_pad(mt_rand(0, pow(10, 4) - 1), 4, '0', STR_PAD_LEFT);

// Possible operands; shuffles them
$operands = array('+', '-', '*');
shuffle($operands);

// Final calculation is written as a string; result is computed
if($operands[0] == '+')
{
   $expr = $firstTerm.' + '.$secondTerm;
   $result = intval($firstTerm) + intval($secondTerm);
}
else if($operands[0] == '*')
{
   $expr = $firstTerm.' X '.$secondTerm;
   $result = intval($firstTerm) * intval($secondTerm);
}
else
{
   $firstVal = intval($firstTerm);
   $secondVal = intval($secondTerm);
   if($firstVal > $secondVal)
   {
      $expr = $firstTerm.' - '.$secondTerm;
      $result = $firstVal - $secondVal;
   }
   else
   {
      $expr = $secondTerm.' - '.$firstTerm;
      $result = $secondVal - $firstVal;
   }
}

// Hashes the result ands puts it in a $_SESSION. No bcrypt is used here for now (takes time).
$_SESSION['captcha'] = sha1($result);

// Signals that the content is an image
header("Content-type: image/png");

// Generates the picture, with random "twists" so that a bot cannot understand the calculation
$size = 15;
$margin = 15;
$font = './style/fonts/OldTypewriter.ttf';
   
$box = imagettfbbox($size, 0, $font, $expr);
$width = $box[2] - $box[0];
$height = $box[1] - $box[7];
$widthCharacter = round($width / strlen($expr));

$img = imagecreate($width + $margin, $height + $margin);
$white = imagecolorallocate($img, 255, 255, 255); 
$black = imagecolorallocate($img, 0, 0, 0);

for($i = 0; $i < strlen($expr); ++$i)
{
   $l = $expr[$i];
   $angle = mt_rand(-20, 20);
   imagettftext($img, $size, $angle, ($i * $widthCharacter) + $margin, $height + mt_rand(0, $margin / 2), $black, $font, $l);
}

imagepng($img);
imagedestroy($img);

?>
