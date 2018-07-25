<?php

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// twistoauth の読み込み
require 'TwistOAuth.phar';

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

//mysql接続用設定値取得
$hosts = $dbconf['hosts'];
$dbUser = $dbconf['user'];
$dbPassword = $dbconf['password'];
$dbName = $dbconf['name'];

$banCharArray = parse_ini_file('/srv/api/conf/settings_charaset.ini');



// よくわからないけど認証してる
$connection = new TwistOAuth($consumer_key, $consumer_secret, $access_token, $access_token_secret);
//ツイートの件数をカウントする変数用意
$tweetCount = 0;
//何日前のツイートを取得するか指定
$beforeDate = 0;

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

//Insert文生成
$updateQuery = 'INSERT INTO hash_tag_tweets (tweet_id,user_name,user_id,tweet_text,tweet_date,ins_date) VALUES(:tweet_id, :user_name, :user_id, :tweet_text, :tweet_date, :ins_date)';

//前回実行日を取得
$selectSerch = $mysql->query('SELECT ins_date FROM hash_tag_tweets where hash_id = (SELECT MAX(hash_id) FROM hash_tag_tweets)');
$lastExecutionDateList = $selectSerch->fetch();
$lastExecutionDate = date("Y-m-d H:i:s" ,strtotime($lastExecutionDateList["ins_date"]));

//日時計算
$nowDate = strtotime("now");
$lastExecutionDate = strtotime($lastExecutionDate);
$diffDate = ($nowDate - $lastExecutionDate) / 60;
$diffDate = floor($diffDate);
$diffDate *= -1;
echo $diffDate . "分前のツイートを取得します。\n";
// ハッシュタグによるツイート検索
$query = $hash_tag . " exclude:retweets since:" . date("Y-m-d_H:i:s" , strtotime($diffDate." min"))."_JST" ." until:" . date("Y-m-d_H:i:s" , strtotime("now"))."_JST";
$hash_params = array('q' => $query , 'lang'=> $lang, 'tweet_mode' => $mode);
$tweets = $connection->get('search/tweets', $hash_params)->statuses;

if(count($tweets) > 0){
	// 検索結果を1行ごとに整形 
	//sort
	$tweets = (array)$tweets;
	foreach($tweets as $key => $value){
		$sort[$key] = $value->created_at;
	}
	array_multisort($sort, SORT_ASC, $tweets);

	foreach ($tweets as $tweet) {
		$timestamp = $tweet->created_at;
		$tweetId = $tweet->id;
		$user = $tweet->user;
		$userName = $user->name . '@' . $user->screen_name;
		$userId = $user->screen_name;
		$text =  $tweet->full_text;
		//禁止文字チェック
		$breakPoint = false;
		foreach($banCharArray as $array){
			$pieces = explode(",", $array);
			foreach($pieces as $banChar){
				if(strpos($text, $banChar) !== false){
					$breakPoint = true;
				}
			}
		}
		//重複チェック
		$serchTweetId = "SELECT tweet_id FROM hash_tag_tweets WHERE tweet_id = :tweet_id";
		$selectID = $mysql->prepare($serchTweetId);
		$selectID->bindParam(':tweet_id',$tweetId,PDO::PARAM_STR);
		$selectID->execute();
		$idDate = $selectID->fetch();
		$IDOverlap = false;
		if($idDate["tweet_id"] != 0){
			$IDOverlap = true;
		}
		//禁止文字が含まれてためデータとしては加えない
		if($breakPoint == true) {
			echo "禁止文字が入ってます。 \n";
		} else if($IDOverlap == true) {
			//selectIDがヒットしていれば重複
			echo "同じツイートが含まれてます！ \n";
		} else {
			//取得した日付を変更
			$timestamp = date("Y-m-d H:i:s" , strtotime($timestamp));
			$nowtimestamp = date("Y-m-d H:i:s" , strtotime("now"));
			//Innsert準備
			$stmt = $mysql->query("SET NAMES UTF8;");
			$innsertData = $mysql->prepare($updateQuery);
			$innsertData->bindParam(':tweet_id',$tweetId,PDO::PARAM_STR);
			$innsertData->bindParam(':user_name',$userName,PDO::PARAM_STR);
			$innsertData->bindParam(':user_id',$userId,PDO::PARAM_STR);
			$innsertData->bindParam(':tweet_text',$text,PDO::PARAM_STR);
			$innsertData->bindParam(':tweet_date',$timestamp,PDO::PARAM_STR);
			$innsertData->bindParam(':ins_date',$nowtimestamp,PDO::PARAM_STR);
			$innsertData->execute();
		}
	}
}

// DB接続を閉じる
$mysql = null;