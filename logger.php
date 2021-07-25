<?php
class Logger {
  private static $file;
  private static $is_set_up = false, $failed = null;
  
  public static function info($msg) {
    self::write($msg, 'INFO');
  }

  public static function warn($msg) {
    self::write($msg, 'WARN');
  }

  public static function error($msg, $exception = null) {
    if ($exception && get_class($exception) === 'Error'){
      $ex = $exception->getMessage();
    } else {
      $ex = $exception;
    }
    self::write($msg . ' ' . ($ex ? ": " . $ex : ''), 'ERROR');
  }

  static function setup(){
    global $LOGFILE;

    if ((self::$file = fopen ( $LOGFILE, "a")) == true){
      self::$is_set_up = true;
      self::$failed = false;
    } else {
      self::$failed = false;
    };
  }

  public static function close(){
    if (is_resource(self::$file)){
      fclose(self::$file);
    }
  }

  static function write ( $msg, $lvl = 'INFO' ){
    try {
      if ( !self::$is_set_up && self::$failed == null){
        self::setup();
        self::info( "-------------------" );
      }
      if (self::$is_set_up && !self::$failed){
        fwrite (self::$file, date("Y-m-d H:i:s")." - $lvl - $msg<br>\r\n");
      }  
    } catch (Exception $e) {
        // what else is there to do
    }
  }
}
