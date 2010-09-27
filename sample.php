<?php
mb_internal_encoding('UTF-8');
mb_regex_encoding('UTF-8');
mb_http_output('CP932');
ob_start('mb_output_handler');

require_once('appstats.php');

main($argv);

function main($argv) {
  if (count($argv) != 3) {
    die('php '.basename($argv[0])." <email> <password>\n");
  }

  $email = $argv[1];
  $pass = $argv[2];
  
  $apps = getStats($email, $pass);
  
  printApps($apps);
}

function printApps($apps) {

  if (count($apps) > 0) {
    $lines = array();
    array_push($lines, array(
      'packageName', 'versionCode', 'version', 'active',
      'total', '*5', '*4', '*3', '*2', '*1'));
    
    foreach ($apps as $app) {
      $stars = $app['stars'];
    
      $lines []= array(
          $app['packageName'], 
          $app['versionCode'],
          $app['version'],
          $app['active'],
          $app['total'],
          $stars[4],
          $stars[3],
          $stars[2],
          $stars[1],
          $stars[0]);
    }
  
    $columns = count($lines[0]);
    $columnWidth = array_fill(0, $columns, 1);
    foreach ($lines as $line) {
      for ($j=0; $j<$columns; $j++) {
        $columnWidth[$j] = max($columnWidth[$j], strlen($line[$j]));
      }
    }
    
    foreach ($lines as $line) {
      for ($j=0; $j<$columns; $j++) {
        if ($j != 0) echo '  ';
        printf('%'.$columnWidth[$j].'s', $line[$j]);
      }
      echo "\n";
    }
  } else {
    die("NO RESULT\n");
  }
}
