<?php

session_start();

include 'conf.php';
  
include 'HTTP/OAuth/Consumer.php';
$consumer = new HTTP_OAuth_Consumer($consumer_key, $consumer_secret);
 
$http_request = new HTTP_Request2();
$http_request->setConfig('ssl_verify_peer', false);
$consumer_request = new HTTP_OAuth_Consumer_Request;
$consumer_request->accept($http_request);
$consumer->accept($consumer_request);

if( isset($_GET['oauth_verifier'])){
	$verifier = $_GET['oauth_verifier'];
	$consumer->setToken($_SESSION['request_token']);
	$consumer->setTokenSecret($_SESSION['request_token_secret']);
	$consumer->getAccessToken('https://twitter.com/oauth/access_token', $verifier);

	$_SESSION['access_token'] = $consumer->getToken();
	$_SESSION['access_token_secret'] = $consumer->getTokenSecret();
}

if( isset($_GET['api_type'] ) ){
	$consumer->setToken($_SESSION['access_token']);
	$consumer->setTokenSecret($_SESSION['access_token_secret']);

	$url = 'http://api.twitter.com/1/';
	$type = $_GET['api_type'];
	unset( $_GET['api_type'] );
	
	//何故かcallbackというプロパティがsendRequestの段階で無視される為、別途処理する。
	$callback = $_GET['callback'];
	unset( $_GET['callback'] );

	switch( $type ){
		case 'statuses_followers':
			$url .= 'statuses/followers.json';
			$data = $_GET;
			$method = 'GET';
			break;
		case 'friendships_create':
			$url .= 'friendships/create.json';
			$data = $_GET;
			$method = 'POST';
			break;
		case 'friendships_destroy':
			$url .= 'friendships/destroy.json';
			$data = $_GET;
			$method = 'POST';
			break;
		case 'statuses_user_timeline':
			$url .= 'statuses/user_timeline.json';
			$_GET['count'] = 100;
			$data = $_GET;
			$method = 'GET';
			break;
		case 'user_show':
			$url .= 'users/show.json';
			$data = $_GET;
			$method = 'GET';
			break;
		default:
			header("HTTP/1.0 404 Not Found");
			header("Content-type: application/json; charset=utf-8 ");
			print '{"request":'.$_GET['api_type'].',"error":"This api requires no support."}';
			exit;
			break;
	}
/*
print $url;
print_r($data);
exit();
//*/
	$response = $consumer->sendRequest( $url , $data , $method );
	
	header("HTTP/".$response->getVersion()." ".$response->getStatus()." ".$response->getReasonPhrase());
	header("Content-type: application/json; charset=utf-8 ");
	
	print $callback.'('.$response->getBody().');';

	exit;
}
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<title>re follow</title>
<?php
if( !isset($_SESSION['access_token']) ){
	$callback = 'http://'.$_SERVER['HTTP_HOST'].'/deleter/';
	$consumer->getRequestToken('https://twitter.com/oauth/request_token', $callback);

	$_SESSION['request_token'] = $consumer->getToken();
	$_SESSION['request_token_secret'] = $consumer->getTokenSecret();

	$auth_url = $consumer->getAuthorizeUrl('https://twitter.com/oauth/authorize');

?>
</head>
<body>
API利用の為にTwitterでoAuthの承認を行なってください。<br />
<a href="https://twitter.com/oauth/authorize?oauth_token=<?php echo $_SESSION['request_token']; ?>">認証ページへ</a>

<?php
}else{
?>
<link rel="stylesheet" type"text/css" href="./style.css" />
<script type="text/javascript" src="./js/jquery.js"></script>
<script type="text/javascript" src="./js/jquery.selectboxes.js"></script>
<script type="text/javascript" src="./js/jquery.quicksearch.js"></script>
<script type="text/javascript" >

var my_url = 'http://<?php echo $_SERVER['HTTP_HOST']; ?>/deleter/';
var error_func = function(r,t,e){ alert( 'error:' + t );};
var log_str="";
var all_tweet= new Array();
var all_row= new Array();

$( function(){
	$('#get_list').click(function(){
		var user = $('#nick').val();
		var page = 0;
		$('#progress').css('display','block');
		$('#progress_msg').text('1-100件を読み込み中');
		if( user.length ){
			$('#get_list').attr( 'disabled', 'disabled' );
			$.ajax({
				//url: 'http://twitter.com/statuses/followers.json',
				//data: { id : user },
				url: my_url,
				data: { id : user, api_type : 'statuses_user_timeline', cursor:-1 },
				dataType: 'jsonp',
				success:function( json_data ){
					$('#progress_msg').text((page*100+1)+'-'+((page+1)*100)+'件を処理中');
					var cnt = 0;
					var list_id = "";
					$.each( json_data, function(i,val){
						list_id = 'following';

						all_tweet[ val.id_str ] = val;
						all_row.push( '<tr id="'+val.id_str+'"><td>'+val.text+'</td><td>'+val.source+'</td><td>'+val.created_at+'</td></tr>' );
                        cnt++;
                	});
                	if( cnt == 100 && !( $("#force_check").attr('checked') && $("#range").val()-1 <= page  ) ){
						page++;
						$.ajax({ url:my_url, data:{ id:user,api_type:'statuses_followers',cursor:json_data.next_cursor_str},
							dataType:'jsonp',success:arguments.callee,error:error_func});
						$('#progress_msg').text((page*100+1)+'-'+((page+1)*100)+'件を読み込み中');
					}else if( cnt ){
						$('#progress').css('display','none');
						//$('#search_tweet').html( all_row.join(""));
						$('#search_tweet tbody').html( all_row.join('') );//'<tr><td>hoge</td><td>huga</td><td>piyo</td></tr>');

						send_log('check complete!',true);
						$('#delete_to').attr( 'disabled', '' );
						$("input#search").quicksearch('table#search_tweet tbody tr',{
							noResults: '#noresults',
							stripeRows: ['c1','c2'],
						});
					}
                	else{
						$('#progress').css('display','none');
						$('#progress_msg').text('');
						alert( 'not tweet...' );
					}
             	},
				error:error_func
			});
		}else{ alert('nick name not found...'); }
	});

	$('#delete_to').click( function(){
		var list = $('#search_tweet tbody tr:visible');
		console.log(list);
		if( list.length ){
			$("#delete_list").addOption( list.map(function(){
				return this.id + ',' + $('td',this).eq(0).text();
			}));
			$('#execute').attr( 'disabled', '' );
		}
	});
	
});

function send_log(str,flash){
	log_str =  str + "\n" + log_str;
	if( flash ){
		$("#log").text( log_str );
	}
}

</script>
</head>
<body>
id:<input type="edit" id="nick" />&nbsp;
<input type="button" id="get_list" value="tweet get" ><br />
<input type="edit" id="range" size="2" value="1" />page (Pages 1 are 100 user. )<br />
<div id="progress" style="display:none;">
	<img src="./img/loading.gif">
	<span id="progress_msg" ></span>
</div>
<hr/>
<h2>delete list</h2>
<select multiple=true id="delete_list" size="7" ></select>
<button id="execute" disabled="disabled">delete</button><br/>
<hr/>
<h2>tweet</h2>
<button id="delete_to" disabled="disabled">delete to</button><br/>
filter:<input type="text" id="search" />
<table id="search_tweet">
	<thead>
	<tr>
		<th>tweet</th>
		<th>via</th>
		<th>time</th>
	</tr>
	<tr id="noresults">
		<td colspan="3">No Results</td>
	</tr>
	</thead>
	<tbody>
	</tbody>
</table>
<hr/>
log<br />
<textarea rows="4" cols="120" id="log" readonly></textarea>
<?php } ?>
</body>
</html>
