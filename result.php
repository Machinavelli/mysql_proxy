<?php

class Result {
  public $query;
  public $error_number;
  public $error_desc;
  public $affected_rows;
  public $insert_id;
  public $field_count;
  public $field_names;
  private $rows;
  private $print_method;

// Constructor, setting up name, file and parameters
  function __construct($output_method = null){
    $this->query = "";
    $this->affected_rows = 0;
    $this->field_count = 0;
    $this->insert_id = "";
    $this->error_number = 0;
    $this->error_desc = "";
    $this->field_names = Array();
    if ($output_method == 'array' OR $output_method == 'json') {
      $this->print_method = $output_method;
    } else {
      Logger::warn('Output must be one of [json, array]; defaulting to json.');
      $this->print_method = 'json';
    }
  }

  public function set_field_names($names_arr) {
    $this->field_names = $names_arr;
  }

// Print JSON Result
  public function print_result(){

    $res = array();
    $res["query"] = $this->query;
    $res["error_number"] = $this->error_number;
    $res["error_desc"] = $this->error_desc;
    $res["affected_rows"] = $this->affected_rows;
    $res["insert_id"] = $this->insert_id;
    $res["field_count"] = $this->field_count;
    $res["field_names"] = $this->field_names;
    $res["rows"] = $this->rows;

    echo self::to_json($res);
  }

  public function add_row($row){
    $currow = Array();
    if ($this->print_method == 'json'){
      foreach ($row as $key => $value) {
        if ($value == null) {
          $currow[$key] = null;
        } else {
          $currow[$key] = $value;
        }
      }
    } else {
      $currow = array_values($row);
    }
    
    $this->rows[] = $currow;
  }

  function to_json($val){
    if (is_string($val)) return '"'.addslashes($val).'"';
    if (is_numeric($val)) return $val;
    if ($val === null) return 'null';
    if ($val === true) return 'true';
    if ($val === false) return 'false';

    $assoc = false;
    $i = 0;
    foreach ($val as $k=>$v){
      if ($k !== $i++){
        $assoc = true;
        break;
      }
    }
    $res = array();
    foreach ($val as $k=>$v){
      $v = self::to_json($v);
      if ($assoc){
        $k = '"'.addslashes($k).'"';
        $v = $k.':'.$v;
      }
      $res[] = $v;
    }
    $res = implode(',', $res);
    return ($assoc)? '{'.$res.'}' : '['.$res.']';
  }
}
