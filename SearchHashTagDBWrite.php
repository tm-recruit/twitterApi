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
$conf = parse_ini_file('/srv/batch/conf/conf.ini');

// 設定から色々取得
$consumer_key = $conf['consumer_key'];
$consumer_secret = $conf['consumer_secret'];
$access_token = $conf['access_token'];
$access_token_secret = $conf['access_token_secret'];
$hash_tag = $conf['hash_tag'];
$count = $conf['get_count'];
$lang = $conf['lang'];
$mode = $conf['mode'];

// よくわからないけど認証してる
$connection = new TwistOAuth($consumer_key, $consumer_secret, $access_token, $access_token_secret);

// ハッシュタグによるツイート検索
$query = $hash_tag . " exclude:retweets since:" . date("Y-m-d" , strtotime("-1 day"));
$hash_params = array('q' => $query ,'count' => $count, 'lang'=> $lang, 'tweet_mode' => $mode);
$tweets = $connection->get('search/tweets', $hash_params)->statuses;

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
}

//ツイートの件数取得
//echo count($tweets)
//---
$data = mb_convert_encoding($data,"SJIS","UTF-8");

// CSVファイルへ書き込み
file_put_contents ($file , $data);
