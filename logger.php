<?php
class Logger {
  private static $file;
  
  public static function info($msg) {
    self::write($msg, 'INFO');
  }

  public static function warn($msg) {
    self::write($msg, 'WARN');
  }

  public static function error($msg, $exception = null) {
    self::write($msg . $exception ? "Exception: " . $exception : '', 'ERROR');
  }

  static function write ( $msg, $lvl = 'INFO' ){
    global $LOGFILE;

    try {
      if ( ($log_file = fopen ( $LOGFILE, "a" )) == FALSE )
        return;

      fwrite ($log_file, date("Y-m-d H:i:s")." - $lvl - $msg<br>\r\n");
      fclose($log_file);
    } catch (Exception $e) {
        // what else is there to do
    }
  }
}
