<?php
require 'result.php';
require 'logger.php';

$settings_loc = parse_ini_file ('settings.ini');
$LOGFILE = $settings_loc['LOGFILE'] or die('Please set up LOGFILE value in settings.ini');
$CREDENTIALS_FILE = $settings_loc['CREDENTIALS'] or die('Please set up CREDENTIALS value in settings.ini');

define("logfile", $LOGFILE);
define("DB_EXTENSION", "mysqli");
define( "charset", 'utf8');
define( "DEBUG", 1 );

ini_set('display_startup_errors', 0);
ini_set('display_errors', 0);


function require_auth() {
  Logger::info( "Remote = " . get_user_ip() );

  $has_supplied_credentials = !(empty($_SERVER['PHP_AUTH_USER']) && empty($_SERVER['PHP_AUTH_PW']));
  $is_not_authenticated = (
    !$has_supplied_credentials ||
    $_SERVER['PHP_AUTH_USER'] != auth_user ||
    $_SERVER['PHP_AUTH_PW']   != auth_pw
  );
  if (!$has_supplied_credentials && $is_not_authenticated) {
    Logger::warn("Credentials not supplied");
  }

  if ($is_not_authenticated) {
    Logger::warn( "Refusing to serve (unauthorized) = " . get_user_ip() );
    Logger::warn( "User = " . $_SERVER['PHP_AUTH_USER'] );
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
      Logger::warn("PHP auth: [" . $_SERVER['HTTP_AUTHORIZATION'] . "]");
    }
    handle_error(401, 'Unauthorized');
  }
}

function is_secure_link() {
  if(is_localhost()){
    return true;
  }
  return
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || $_SERVER['SERVER_PORT'] == 443;
}

function get_user_ip(){
  if(!empty($_SERVER['HTTP_CLIENT_IP'])){
    //ip from share internet
    $ip = $_SERVER['HTTP_CLIENT_IP'];
  }elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
    //ip pass from proxy
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
  }else{
    $ip = $_SERVER['REMOTE_ADDR'];
  }
  return $ip;
}

function is_localhost(){
  $addr = get_user_ip();
  return ($addr == 'localhost' || $addr == '127.0.0.1' || $addr == '::1') 
    && !array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER);
}

function is_allowed_ip($list) {
  $addr = get_user_ip();
  if($list == null || count($list) == 0){
    return true;
  } else if (is_localhost()) {
    return true;
  } else if (in_array($addr, explode(',', $list))) {
    return true;
  } else {
    return false;
  }
}

function refine_value($str){
  return stripslashes($str);
}

function get_request_value($name){
  if(array_key_exists($name,$_POST)){
    $value=$_POST[$name];
  } else if(array_key_exists($name,$_GET)){
    $value=$_GET[$name];
  } else {
    return null;
  }

  if(!is_array($value))
    return refine_value($value);
  $ret=array();
  foreach($value as $key=>$val)
    $ret[$key]=refine_value($val);
  return $ret;
}

function connect_database ($host, $port, $username, $password, $dbname){
  Logger::info( "Trying to connect" );
  $mysqli = new mysqli($host, $username,  $password, $dbname, ($port != "" ? $port : null));
  if (strlen (charset)!=0){
    $query = "SET NAMES " . charset . ";";
    $mysqli->query($query);
  }
  return $mysqli;
}

function disconnect_database ( $mysqli ){
  try {
    $mysqli->close();
  } catch (Exception $e) {
    Logger::error( 'Error closing connection: ', $e->getMessage() );
  }
}

function parse_query($query){
  $queries = preg_split("/;+(?=([^'|^\\\']*['|\\\'][^'|^\\\']*['|\\\'])*[^'|^\\\']*[^'|^\\\']$)/", $query);
  if(count($queries) > 1){
    Logger::warn("Multiple queries in single request not supported!");
  }
  return $queries[0];
}

/* 
  Handling of mysql & custom errors
  Return error response
*/
function handle_error($errno, $error){
  $res = new Result('array');
  $res->error_number = $errno;
  $res->error_desc = $error;
  $res->print_result();
  Logger::close();
  die;
}

function load_and_output($mysqli, $query) {
  $result = $mysqli->query($query, MYSQLI_STORE_RESULT);
  
  try {
    $n_rows = $mysqli->affected_rows;
  } catch (Exception $e) {
    Logger::error('Could not fetch affected rows count: ', $e->getMessage());
    $n_rows = 0;
  }

  try {
    $n_fields = is_bool($result) ? 0 : $mysqli->field_count;
  } catch (Exception $e) {
    Logger::error('Could not fetch fields count: ', $e->getMessage());
    $n_fields = 0;
  }

  if (mysqli_errno($mysqli)!=0) {
    Logger::error("DB Exception! ", $mysqli->errno . " " . $mysqli->error );
    handle_error ( $mysqli->errno, $mysqli->error );
  }else {
    Logger::info("SQL run successful");


    if ($result === FALSE && $mysqli->errno != 0) {
      $out_result = array("result" => -1, "affected_rows" => $n_rows);
      create_result($mysqli, $query, $out_result);
    } elseif ($result === FALSE) {
      $out_result = array("result" => 1, "affected_rows" => $n_rows);
      create_result($mysqli, $query, $out_result);
    } else if (is_bool($result)) {
      $out_result = array("result" => 1, "affected_rows" => $n_rows);
      create_result($mysqli, $query, $out_result);
    } else {
      $out_result = array("result"=>$result, "affected_rows"=>$n_rows, "field_count" => $n_fields);
      create_result ($mysqli, $query, $out_result);
      $result->close();
    }
  }
}

function create_result($mysql, $query, $mysql_result){
  global $out_formt;
  $result = $mysql_result['result'];

  $res = new Result($out_formt);
  $res->query = $query;
  $res->affected_rows = $mysql_result['affected_rows'];
  $res->insert_id = $mysql->insert_id;


  if (!is_int($result)) {
    $field_names = Array();

    foreach ($result->fetch_fields() as $val) {
      $field_names[] = $val->name;
    }

    $res->set_field_names($field_names);
    $res->field_count = $mysql_result['field_count'];
    while ($row = $result->fetch_assoc()) {
      $res->add_row($row);
    }
  }
  $res->print_result();
}


function process_request (){
  global $host, $port, $dbname, $username, $password, $query;

  $mysqli = connect_database ($host, $port, $username, $password, $dbname );
  if ( !$mysqli ){
    Logger::error("Error Connecting DB! " . $mysqli->connect_error );
    handle_error ( $mysqli->errno, $mysqli->error );
  } else {
    Logger::info( "Connected" );
    $query = parse_query($query);
    load_and_output ( $mysqli, $query );
    disconnect_database ( $mysqli );
  }
}


// DO IT!

Logger::info( "-------------------" );

header('Cache-Control: no-cache, must-revalidate, max-age=0');
header('Content-type:application/json;charset=utf-8');

$credentials = parse_ini_file ( $CREDENTIALS_FILE );
$allow_ips = array_key_exists ( "allowed_ips" , $credentials ) ? $credentials["allowed_ips"] : null;

$host       = $credentials["hostname"];
$port       = $credentials["port"];
$dbname     = $credentials["dbname"];
$username   = $credentials["username"];
$password   = $credentials["password"];
$secret     = array_key_exists ( "key" , $credentials ) ? $credentials["key"] : null;
$query      = get_request_value("query") ?: null;
$out_formt  = get_request_value('format')?: null;
define("auth_pw", $credentials["proxy_pass"]);
define("auth_user", $credentials["proxy_user"]);

Logger::info( "Query =" . $query );

if(!is_allowed_ip($allow_ips)){
  Logger::info( "Refusing to serve (forbidden ip)= " . get_user_ip() );
  handle_error(403, '');
}

if (!is_secure_link()) {
  Logger::info( "Request was sent over unsecured link (" . $_SERVER['HTTP_HOST'] . ", " . get_user_ip() . ")" );
  handle_error(400, 'Insecure link.');
}

if ( strlen ( $query ) == 0 ) {
  Logger::info( "Query was blank" );
  handle_error(400, '');
} else {
  Logger::info( "Query = $query");
}


try {
  require_auth();
  process_request();
} catch (Exception $e) {
  handle_error(500, 'Internal error');
}

Logger::close();
