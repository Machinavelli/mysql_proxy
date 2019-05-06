<?php
class Logger {
  public static function info($msg) {
    self::write($msg, 'INFO');
  }

  public static function warn($msg) {
    self::write($msg, 'WARN');
  }

  public static function error($msg, $exception = null) {
    self::write($msg . $exception ? "Exception: $exception" : '', 'ERROR');
  }

  static function write ( $msg, $lvl = 'INFO' ){
    global $MAXLOGSIZE, $LOGFILE;

    if ( ($log_file = fopen ( $LOGFILE, "a" )) == FALSE )
      return;

    fwrite ($log_file, date("Y-m-d H:i:s")." - $lvl - $msg<br>\r\n");
    $lstat=fstat($log_file);
    if ($lstat["size"]>$MAXLOGSIZE) self::rotate_log();
    fclose($log_file);
  }

  static function rotate_log() {
    global $MAXLOGSIZE, $LOGFILE;
    self::write("Logfile reached maximum size ($MAXLOGSIZE)- rotating.");
    rename ($LOGFILE,$LOGFILE."_".date("Y-m-d"));
  }
}
