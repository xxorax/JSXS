<?php
$html = false;

if (!$html) header('Content-Type: text/plain');

require('./Jsxs.php');
require ('./PregFile.php');



$src = "
function test (arg1) {
  var func = function (arg1) {
    return arg1+2;
  }
  return 'your value' + ' : ' + arg1 + func(arg1);
}
";



$jsxs = new Jsxs();

$jsxs->setRegexDirectory(dirname(__FILE__).'/preg');
$jsxs->setCompatibility(true);
$jsxs->setReduce(true);
$jsxs->setShrink(true);
$jsxs->setConcatString(true);

$startTime = microtime(true);

$srcxs = $jsxs->exec($src);

$endTime = microtime(true);

$result = array(
  'time' => ($endTime - $startTime),
  'original lenght' => strlen($src),
  'compact lenght' => strlen($srcxs),
  'diff' => strlen($srcxs) - strlen($src),
  'ratio' => strlen($srcxs) / strlen($src),
);

if ($html) {
  echo '<pre>';
  $srcxs = htmlspecialchars($srcxs);
}

echo "--------------------";
foreach($result as $k => $v){
  echo "\n\t".$k.' : '.$v;
}
echo "\n--------------------\n";

echo $srcxs;
  
if ($html) {
  echo '</pre>';
}

