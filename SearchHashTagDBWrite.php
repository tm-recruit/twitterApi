<?php

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// twistoauth の読み込み
require 'TwistOAuth.phar';

// CSVファイルの読み込み
$file = '/srv/batch/result/SearchResult.csv';
$data = file_get_contents($file);
$data = mb_convert_encoding($data,"UTF-8","SJIS");

// 設定の読み込み
$conf = parse_ini_file('/srv/api/conf/conf.ini');
$dbconf = parse_ini_file('/srv/api/conf/dbconf.ini');

// 設定から色々取得
$consumer_key = $conf['consumer_key'];
$consumer_secret = $conf['consumer_secret'];
$access_token = $conf['access_token'];
$access_token_secret = $conf['access_token_secret'];
$hash_tag = $conf['hash_tag'];
$count = $conf['get_count'];
$lang = $conf['lang'];
$mode = $conf['mode'];
$maxCount = $conf['maxCount'];

//mysql接続用設定値取得
$hosts = $dbconf['hosts'];
$dbUser = $dbconf['user'];
$dbPassword = $dbconf['password'];
$dbName = $dbconf['name'];

// よくわからないけど認証してる
$connection = new TwistOAuth($consumer_key, $consumer_secret, $access_token, $access_token_secret);
//ツイートの件数をカウントする変数用意
$tweetCount = 0;
//何日前のツイートを取得するか指定
$beforeDate = 0;

//DB接続
$mysql = new PDO("mysql:dbname=" .$dbName .";host=".$hosts, $dbUser, $dbPassword);
if ($mysql->connect_error) {
    echo $mysql->connect_error;
    exit();
} else {
    $mysql->set_charset("utf8");
}

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

//Insert文生成
$updateQuery = 'INSERT INTO hash_tag_tweets VAULES(?, ?, ?, ?, ?, ?)';

//select文実行
$selectHasTagTabelLatestTweetDate = $mysql->query('SELECT tweet_date FROM max_id');
//日時計算
$nowDate = strtotime("now");
$diffDate = ($nowDate - $selectHasTagTabelLatestTweetDate) / 60;
$diffDate *= -1;

// ハッシュタグによるツイート検索
$query = $hash_tag . " exclude:retweets since:" . date("Y-m-d H:i:s" , strtotime($diffDate." min"));
$hash_params = array('q' => $query ,'count' => $count, 'lang'=> $lang, 'tweet_mode' => $mode);
$tweets = $connection->get('search/tweets', $hash_params)->statuses;



//
if(count($tweets) > 0){
	// 検索結果を1行ごとに整形 
	foreach ($tweets as $tweet) {
		
		$timestamp = $tweet->created_at;

		$user = $tweet->user;
		$name = $user->name . '@' . $user->screen_name;
		// "を""に変換
		$text = str_replace('"', '""', $tweet->full_text);
		//文字をコマンドラインに出力（必要になったらコメントアウトを外せば出力される）
		//$text = mb_convert_encoding($text,"UTF-8","auto");
		//print_r($text);
		//---
		$rt = $tweet->retweet_count;
		$like = $tweet->favorite_count;
		$sep = '","';
		$record =  '"' . $timestamp . $sep . $name  . $sep . $text  . $sep . $rt  . $sep . $like . '"';

		$data .= "\n" . $record;
		$tweetCount++;
	}
}

//ツイートの件数取得
//echo count($tweets)
//---
$data = mb_convert_encoding($data,"SJIS","UTF-8");

// CSVファイルへ書き込み
file_put_contents ($file , $data);

// DB接続を閉じる
$mysqli->close();