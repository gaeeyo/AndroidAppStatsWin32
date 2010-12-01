<?php
mb_internal_encoding('UTF-8');
mb_regex_encoding('UTF-8');
mb_http_output('CP932');
ob_start('mb_output_handler');

define('DB_DSN', 'sqlite:'.dirname($_SERVER['PHP_SELF']).'/stats.sqlite');


require_once('appstats.php');

main($argv);


function main($argv) {
  if (count($argv) != 3) {
    echo('php '.basename($argv[0])." <email> <password>\n\n");
    showSummary();
    die();
  }

  $email = $argv[1];
  $pass = $argv[2];
  
  $apps = getStats($email, $pass);
  
  $db = new StatsDatabase();
  foreach ($apps as $app) {
    $db->insertApp($app);
    $db->insertComments($app);
  }
  
  showSummary();
}

function showSummary() {
  // 7日分のデータを取得
  $days = 7;
  
  $db = new StatsDatabase();
  
  // 最新の7日分の日付の配列を取得
  $dates = $db->getLastDate($days);

  // 7日分の範囲に存在するパッケージ名の配列を取得
  $packageNames = $db->getPackageNames($db->whereIn('date', $dates));
  
  
  $columns = array(
    'date', 'versionCode', '+v', 'version', 'total', '+t', 'active', '+a', '%', '**',
    'star5', '+5', 'star4', '+4', 'star3', '+3', 'star2', '+2', 'star1', '+1'
  );
  $columnNames = array(
    'Date', 'VerCode', '', 'Ver', 'Total', '', 'Active', '', '%', '*(avg)',
    '*5', '', '*4', '', '*3', '', '*2', '', '*1', ''
  );
  $diffColumns = array();
  $prevKey = null;
  foreach ($columns as $key) {
    if ($key[0] == '+') $diffColumns[$key] = $prevKey;
    $prevKey = $key;
  }  
  
  foreach ($packageNames as $packageName) {
    printf("%s\n%s\n", $packageName, str_repeat('=', strlen($packageName)));
    
    $rows = $db->getStats(
      join(' AND ', array($db->whereIn('date', $dates), 'packageName=:packageName')),
      array(':packageName'=>$packageName)
    );
    
    // 表示用のデータに整形
    foreach ($rows as $idx => &$row) {
      if ($row['total'] > 0) {
        $row['%'] = sprintf('%2.1f', $row['active'] * 100/ $row['total']);
      } else {
        $row['%'] = 1;
      }
      $starTotal = $row['star1'] + $row['star2'] + $row['star3'] + $row['star4'] + $row['star5'];
      $starAvg = 0;
      if ($starTotal != 0) {
        $starAvg = (
            $row['star5']*5 + $row['star4']*4 + $row['star3']*3
            + $row['star2']*2 + $row['star1']
          ) / $starTotal;
      }
      $row['**'] = sprintf('%1.2f', $starAvg);
    }
    unset($row);

    foreach ($rows as $idx => &$row) {
      if ($idx == count($rows)-1) {
        foreach ($diffColumns as $key=>$key2) {
          $row[$key] = '';
        }
      } else {
        foreach ($diffColumns as $key=>$key2) {
          $diff = $rows[$idx][$key2] - $rows[$idx+1][$key2];
          $row[$key] = ($diff == 0 ? '' : sprintf('(%+d)', $diff));
        }
      }
    }
    unset($row);
        
    // カラムの最大の幅を求める
    $columnWidth = array();
    foreach ($columnNames as $name) {
      $columnWidth []= strlen($name);
    }
    foreach ($rows as $rowIdx => $row) {
      foreach ($columns as $idx => $key) {
        $columnWidth[$idx] = max($columnWidth[$idx], strlen($row[$key]));
      }
    }
    
    // データを表示
    foreach ($columnNames as $idx => $name) {
      if ($name == '') {
        printf('%'.$columnWidth[$idx].'s', $name);
      } else {
        printf('  %'.$columnWidth[$idx].'s', $name);
      }
    }
    echo "\n";
    foreach ($rows as $row) {
      foreach ($columns as $idx => $key) {
        if ($key[0] == '+') {
          printf('%-'.$columnWidth[$idx].'s', $row[$key]);
        } else {
          printf('  %'.$columnWidth[$idx].'s', $row[$key]);
        }
      }
      echo "\n";
    }
    echo "\n";
  }
  
  $comments = $db->getRecentComments($days);
  foreach ($comments as $c) {
    printComment($c, $c['packageName']);
  }
}

function printComment($c, $package) {
  printf("%s (%s) %s [%s]\n %s\n\n",
    $c['name'], $c['date'], 
    preg_replace('/☆/u', '★', '☆☆☆☆☆', $c['star']),
    $package,
    $c['body']);
}

class StatsDatabase
{
  private static $db = null;

  public function whereIn($column, $values) {
    $db = $this->getInstance();
    
    $a = array();
    foreach ($values as $value) {
      $a []= $db->quote($value);
    }
    return sprintf(' %s IN (%s) ', $column, join(',', $a));
  }
  
  public static function getInstance() {
    if (self::$db == null) {
      $db = new PDO(DB_DSN);
      self::$db = $db;
      
      $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      
      $sql = <<<END_OF_SQL
        CREATE TABLE IF NOT EXISTS stats (
         _id INTEGER PRIMARY KEY AUTOINCREMENT,
         date DATE NOT NULL,
         packageName TEXT NOT NULL,
         versionCode INTEGER NOT NULL,
         version TEXT NOT NULL,
         active INTEGER NOT NULL,
         total INTEGER NOT NULL,
         star1 INTEGER NOT NULL,
         star2 INTEGER NOT NULL,
         star3 INTEGER NOT NULL,
         star4 INTEGER NOT NULL,
         star5 INTEGER NOT NULL,
         UNIQUE (date, packageName)
         );
END_OF_SQL;
      $db->query($sql);
      
      $sql = <<<END_OF_SQL
        CREATE TABLE IF NOT EXISTS comments (
          _id INTEGER PRIMARY KEY AUTOINCREMENT,
          date TEXT NOT NULL,
          packageName TEXT NOT NULL,
          name TEXT NOT NULL,
          body TEXT NOT NULL,
          star INTEGER NOT NULL,
          UNIQUE (date, packageName, name, body)
          );
END_OF_SQL;
      $db->query($sql);
      
    }
    
    return self::$db;
  }
  
  public function insertApp($app) {
    $db = $this->getInstance();
    $stmt = $db->prepare('INSERT OR REPLACE INTO stats('
      .'date,packageName,versionCode,version,'
      .'active,total,'
      .'star1,star2,star3,star4,star5)'
      .' VALUES('
      ."date('now','localtime'),:packageName,:versionCode,:version,"
      .':active,:total,'
      .':star1,:star2,:star3,:star4,:star5)');
    $stars = $app['stars'];
    $stmt->execute(array(
      ':packageName' => $app['packageName'],
      ':versionCode' => $app['versionCode'],
      ':version' => $app['version'],
      ':active' => $app['active'],
      ':total' => $app['total'],
      ':star1' => $stars[0],
      ':star2' => $stars[1],
      ':star3' => $stars[2],
      ':star4' => $stars[3],
      ':star5' => $stars[4]
    ));
  }
  
  public function getRecentComments($days) {
    $db = $this->getInstance();
    $stmt = $db->prepare(
      'SELECT * FROM comments WHERE date>=:date '
      .' ORDER BY date DESC, _id DESC');
    $stmt->execute(array(
      ':date' => date('Y-m-d', time()-$days*24*60*60)
    ));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
  
  public function insertComments($app) {
    $db = $this->getInstance();
    $stmt = $db->prepare('INSERT OR IGNORE INTO comments('
      .'date, packageName, name, body, star)'
      .' VALUES('
      .':date,:packageName,:name,:body,:star)');
    
    foreach ($app['comments'] as $c) {
      $result = $stmt->execute(array(
        ':date' => $c['date'],
        ':packageName' => $app['packageName'],
        ':name' => $c['name'],
        ':body' => $c['body'],
        ':star' => $c['star']
      ));
      if ($stmt->rowCount() > 0) {
        printComment($c, $app['packageName']);
      }
      //var_dump($result);
    }
  }
  
  public function getLastDate($count) {
    $db = $this->getInstance();
    $sql = sprintf('SELECT DISTINCT date FROM stats ORDER BY date DESC LIMIT %d', $count);
    $stmt = $db->query($sql);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
  }
  
  public function getPackageNames($where) {
    $db = $this->getInstance();
    $sql = 'SELECT DISTINCT packageName FROM stats WHERE '.$where.' ORDER BY packageName';
    $stmt = $db->query($sql);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
  }
  
  public function getStats($where, $whereArgs = null) {
    $db = $this->getInstance();
    $sql = 'SELECT date,versionCode,version,total,active,star1,star2,star3,star4,star5'
      .' FROM stats '
      .' WHERE '.$where
      .' ORDER BY date DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($whereArgs);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}

