<?php

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');


// 設定の読み込み
$conf = parse_ini_file('/srv/api/conf/conf.ini');
$dbconf = parse_ini_file('/srv/api/conf/dbconf.ini');


//設定値から値取得
$mergin = $conf['mergin'];
//mysql接続用設定値取得
$hosts = $dbconf['hosts'];
$dbUser = $dbconf['user'];
$dbPassword = $dbconf['password'];
$dbName = $dbconf['name'];

$banCharArray = parse_ini_file('/srv/api/conf/settings_charaset.ini');

//DB接続
$mysql = new PDO("mysql:dbname=" .$dbName .";host=".$hosts, $dbUser, $dbPassword,array(
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_EMULATE_PREPARES => false,
));

//----------------------------------------------------------
// テーブル定義
//----------------------------------------------------------
// hash_tag_tweetsテーブル
// Culume
// ---------------------------------------------------------
// |hash_id|tweet_id|user_name|user_id|tweet_text|tweet_date|
// ---------------------------------------------------------
// hash_id    :hash_tag_tweetsテーブルの一意の値
// tweet_id   :ツイートに対するid
// user_name  :ユーザー名@ユーザーID
// user_id    :@より後ろのユーザーが設定しているID
// tweet_text :ツイート本文
// tweet_date :ツイートされた日時(YYYY-mm-dd HH:ii:ss)
//----------------------------------------------------------
// max_idテーブル(view)
// Culume
// -------------------
// |hash_id|tweet_date|
// -------------------
// hash_id    :has_tag_tweetsテーブルのhas_idの最大値
// tweet_date :hash_idに紐づく日時(YYYY-mm-dd HH:ii:ss)
//----------------------------------------------------------

//現状のhash_id取得
$maxHashIdSelect = "SELECT MAX(hash_id) FROM hash_tag_tweets";
$maxHashIdExe = $mysql->prepare($maxHashIdSelect);
$maxHashIdExe->execute();
$max =  $maxHashIdExe->fetch();
$maxHashId = $max["0"];
$minSelect = "SELECT MIN(hash_id) FROM hash_tag_tweets";
$minExe = $mysql->prepare($minSelect);
$minExe->execute();
$min =  $minExe->fetch();
$minX = $min["0"];

//設定する最小値取得
$minHashId = $maxHashId - $mergin;
if($minHashId <= $minX) $minHashId = $minX;
if($minHashId < 0) $minHashId = $minX;

//text文生成
$textSelect = "SELECT tweet_text FROM hash_tag_tweets where hash_id BETWEEN :minI AND :maxI";
$textListExe = $mysql->query("SET NAMES UTF8;");
$textListExe = $mysql->prepare($textSelect);
$textListExe->bindParam(':minI',$minHashId,PDO::PARAM_INT);
$textListExe->bindParam(':maxI',$maxHashId,PDO::PARAM_INT);
$textListExe->execute();
$textListFetch = $textListExe->fetchAll();
//print_r($textListFetch);
$count = 0;
foreach($textListFetch as $key => $text){
	$textList[$key] = $text["tweet_text"];
	$count += 1;
}
// DB接続を閉じる
$mysql = null;

// Content-TypeをJSONに指定する
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

echo json_encode($textList);
