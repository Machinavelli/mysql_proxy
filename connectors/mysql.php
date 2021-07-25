<?php
class MysqlConnector {
    private $params, $host, $username,  $password, $dbname, $port;
    private $out_fmt;
    private $connection;
    private $req_params = array('host', 'username', 'password', 'dbname', 'port');


    function __construct($conn_par, $out_format) {
        $this->params = $conn_par;

        foreach($this->req_params as $item){
            if (array_key_exists($item, $conn_par) == false || $conn_par[$item] == '' || $conn_par[$item] == null){
                throw new Error('Missing DB parameter: ' . $item);
            }
        }
        $this->host = $conn_par['host'];
        $this->port = !$conn_par['port'] ? 3306 : $conn_par['port'];
        $this->username = $conn_par['username'];
        $this->password = $conn_par['password'];
        $this->dbname = $conn_par['dbname'];
        $this->out_fmt = $out_format;
      }

    function connect() {
        if (!$this->params) {
            return false;        
        } else {
            Logger::info( "Trying to connect" );
            $mysqli = new mysqli($this->host, 
                                $this->username, 
                                $this->password, 
                                $this->dbname, 
                                ($this->port != "" ? $this->port : null));

            Logger::info( "Attempted" );
            if (mysqli_connect_errno()) {
                throw new Error("Could not connect DB:" . $mysqli->connect_error);
            }
            Logger::info( "Connected" );
            if (strlen (charset)!=0){
                $query = "SET NAMES " . charset . ";";
                $mysqli->query($query);
            }
            $this->connection = $mysqli;
            return $mysqli;
        }
    }

    function disconnect() {
        try {
            $this->connection->close();
            return true;
          } catch (Exception $e) {
            Logger::error( 'Error closing connection: ', $e->getMessage() );
            return false;
          }
    }

    function query($qr) {
        return $this->load_and_output($qr);
    }

    
    private function load_and_output($query) {
        $result = $this->connection->query($query, MYSQLI_STORE_RESULT);
        
        try {
            $n_rows = $this->connection->affected_rows;
        } catch (Exception $e) {
            Logger::error('Could not fetch affected rows count: ', $e->getMessage());
            $n_rows = 0;
        }
    
        try {
            $n_fields = is_bool($result) ? 0 : $this->connection->field_count;
        } catch (Exception $e) {
            Logger::error('Could not fetch fields count: ', $e->getMessage());
            $n_fields = 0;
        }
    
        if (mysqli_errno($this->connection)!=0) {
            Logger::error("DB Exception! ", $this->connection->errno . " " . $this->connection->error );
            $r = handle_error ( $this->connection->errno, $this->connection->error, $query);
            return $r;
        } else {
            Logger::info("SQL run successful");
            if ($result === FALSE && $this->connection->errno != 0) {
                $out_result = array("result" => -1, "affected_rows" => $n_rows);
                $r = $this->create_result($this->connection, $query, $out_result);
            } elseif ($result === FALSE) {
                $out_result = array("result" => 1, "affected_rows" => $n_rows);
                $r = $this->create_result($this->connection, $query, $out_result);
            } elseif (is_bool($result)) {
                $out_result = array("result" => 1, "affected_rows" => $n_rows);
                $r = $this->create_result($this->connection, $query, $out_result);
            } else {
                $out_result = array("result"=>$result, "affected_rows"=>$n_rows, "field_count" => $n_fields);
                $r = $this->create_result ($this->connection, $query, $out_result);
                $result->close();
            }
        }
        return $r;
    }
    
    private function create_result($mysql, $query, $mysql_result){
        $out_formt = $this->out_fmt;
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
        return $res;
    }
  
}
?>
