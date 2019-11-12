<?php

include_once('somefile.php');
if (!$GLOBALS['db']) {
    try {
        $db = new PDO("mysql:host=$db_host;dbname=$db_database", $db_user, $db_pass);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec("set names utf8");
        $GLOBALS['db'] = $db;
    } catch (PDOException $pe) {
        die('Ошибка связи с БД');
    }
  
SQLexecute('
CREATE TABLE IF NOT EXISTS `tree` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `text` varchar(256) NOT NULL,
  `IDparent` int(11) NOT NULL,
  `path` varchar(256) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8');

}

class Path {

    private $parr = array();

    function __construct($path) {
        $this->parr = explode('.', $path);
    }

    function it($path2) {
        $arr1 = $this->getArrData();
        $arr2 = $path2->getArrData();

        for ($i = 0; $i < count($arr1) || $i < count($arr2); $i++) {
            if ($arr1[$i] < $arr2[$i])
                return true;
            if ($arr1[$i] > $arr2[$i])
                return false;
        }

        return false;
    }

    function getArrData() {
        return $this->parr;
    }

    function add($num) {
        $a = $this->parr;
        $a[count($a) - 1] += $num;
        $this->parr = $a;
    }

    function goString() {
        return implode('.', $this->parr);
    }

}

require_once( '../../smarty-v.e.r/libs/Smarty.class.php');

$smarty = new Smarty();

$smarty->template_dir = '../../project_smarty_files/templates/';
$smarty->compile_dir = '../../project_smarty_files/templates_c/';
$smarty->config_dir = '../../project_smarty_files/configs/';
$smarty->cache_dir = '../../project_smarty_files/cache/';

function SQLexecute($sql) {
    $stmt = $GLOBALS['db']->prepare($sql);
    $stmt->execute();
    return $stmt;
}

function SQLget($sql) {
    $stmt = SQLexecute($sql);
    if ($stmt->rowCount() < 1)
        return null;
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function err($msg) {
    echo('<center><form method="get" href=".">');
    echo($msg);
    echo('<br><input type="submit" value="назад">');
    echo('</form>');
    exit();
}

function findDepth($path) {
    return count(explode('.', $path)) - 1;
}

$isPost = $_SERVER['REQUEST_METHOD'] == 'POST';
if ($isPost) {
    $act = $_POST['act'];

    if ($act == 'new') {
        $text = $_POST['text'];
        $IDparent = $_POST['IDparent'];
        if (!isset($IDparent))
            $IDparent = 0;

        $path = 0;
        if ($IDparent != 0) {
            $arr = SQLget("SELECT path FROM tree WHERE ID=$IDparent");

            $path = $arr['path'];

            $sqlr = "SELECT * FROM tree WHERE path LIKE '$path.%'";
            $stmt = SQLexecute($sqlr);
            $count = $stmt->rowCount();

            $path = $path . '.' . ($count + 1);
        } else {
            $sqlr = 'SELECT * FROM tree WHERE IDparent=0';
            $stmt = SQLexecute($sqlr);
            $count = $stmt->rowCount();
            $path = $count + 1;
        }


        $arr = SQLget("SELECT ID FROM tree WHERE path='$path'");
        while (count($arr) > 0) {
            $path = new Path($path);
            $path->add(1);
            $path = $path->goString();
            $arr = SQLget("SELECT ID FROM tree WHERE path='$path'");
        }

        $sqlr = "INSERT INTO `tree`(`text`,`IDparent`,`path`) VALUES ('$text','$IDparent','$path')";
        $stmt = SQLexecute($sqlr);
    }

    if ($act == 'del') {
        $ID = $_POST['ID'];
        $arr = SQLget("SELECT path FROM tree WHERE ID=$ID");
        $path = $arr['path'];

        $sqlr = "DELETE FROM `tree` WHERE path LIKE '$path.%'";
        $stmt = SQLexecute($sqlr);

        $sqlr = "DELETE FROM `tree` WHERE ID=$ID";
        $stmt = SQLexecute($sqlr);
    }

    echo('<script>window.location="."</script>');
    echo('<form method="get" href=".">');
    echo('<input type="submit" value="назад">');
    echo('</form>');
}


$sqlr = "SELECT * FROM tree";
$stmt = SQLexecute($sqlr);
$count = $stmt->rowCount();

$tree = $stmt->fetchAll(PDO::FETCH_ASSOC);
for ($i = 0; $i < $count; $i++)
    $tree[$i]['depth'] = findDepth($tree[$i]['path']);


for ($k = 0; $k < $count - 1; $k++) {

    $curpath = new Path($tree[$k]['path']);
    $curel = $k;

    for ($i = $k + 1; $i < $count; $i++) {
        $element = $tree[$i];

        $p = new Path($element['path']);
        if ($p->it($curpath)) {
            $curpath = $p;
            $curel = $i;
        }
    }
    if ($curel != $k) {
        $t = $tree[$k];
        $tree[$k] = $tree[$curel];
        $tree[$curel] = $t;
    }
}

$smarty->assign('tree', $tree);

//header('Content-Type: text/html; charset=utf8');
$smarty->display('smarty.html');
