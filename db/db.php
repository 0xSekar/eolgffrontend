<?php 
require_once dirname(dirname(__FILE__)).'/config.php';
class Database 
{
    private $db_host = DB_FRONTEND_HOST;
    private $db_user = DB_FRONTEND_USER;
    private $db_pass = DB_FRONTEND_PASSWORD;
    private $db_name = DB_FRONTEND_DATABASE;

    private $_db;
    static $_instance;

    private function __construct() {
        $this->_db = new PDO("mysql:host=$this->db_host;dbname=$this->db_name;charset=utf8", $this->db_user, $this->db_pass);
        $this->_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function __clone(){}

    /**
     * @return PDO
     */
    public static function getInstance() {
        if (!(self::$_instance instanceof self)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __call ( $method, $args ) {
        if ( is_callable(array($this->_db, $method)) ) {
            return call_user_func_array(array($this->_db, $method), $args);
        }
        else {
            throw new BadMethodCallException('Undefined method Database::' . $method);
        }
    }
}
