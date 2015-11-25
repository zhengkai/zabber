<?PHP
/*
 * a lazy way to connect MySQL
 */
class MySQLite extends MySQLi {
	public function __construct() {
		parent::__construct("localhost", "user", "password", "zabber", 3306); //, "/var/run/mysqld/mysqld.sock"
		$this->set_charset("utf8");
	}
}
?>