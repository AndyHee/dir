<?php

// MySQL database class
//
// For debugging, insert 'dbg(x);' anywhere in the program flow.
// x = 1: display db success/failure following content
// x = 2: display full queries following content
// x = 3: display full queries using echo; which will mess up display
//        really bad but will return output in stubborn cases.

class dba
{
	private $debug = 0;
	public $db;

	public function __construct($server, $user, $pass, $db, $install = false)
	{
		$this->db = @new mysqli($server, $user, $pass, $db);

		if (mysqli_connect_errno() && ! $install) {
			system_unavailable();
		}
	}

	public function getdb()
	{
		return $this->db;
	}

	public function q($sql)
	{
		global $debug_text;

		if (! $this->db) {
			return false;
		}

		$result = @$this->db->query($sql);

		if ($this->debug) {
			$mesg = '';

			if ($this->db->mysqli->errno) {
				$debug_text .= $this->db->mysqli->error . EOL;
			}

			if ($result === false) {
				$mesg = 'false';
			} elseif ($result === true) {
				$mesg = 'true';
			} else {
				$mesg = $result->num_rows . ' results' . EOL;
			}

			$str = 'SQL = ' . printable($sql) . EOL . 'SQL returned ' . $mesg . EOL;

			switch ($this->debug) {
				case 3:
					echo $str;
					break;
				default:
					$debug_text .= $str;
					break;
			}
		}

		if (($result === true) || ($result === false)) {
			return $result;
		}

		$r = array();
		if ($result->num_rows) {
			while ($x = $result->fetch_array(MYSQLI_ASSOC)) {
				$r[] = $x;
			}
			$result->free_result();
		}

		if ($this->debug == 2) {
			$debug_text .= printable(print_r($r, true) . EOL);
		} elseif ($this->debug == 3) {
			echo printable(print_r($r, true) . EOL);
		}

		return $r;
	}

	public function dbg($dbg)
	{
		$this->debug = $dbg;
	}

	public function escape($str)
	{
		return @$this->db->real_escape_string($str);
	}

	public function __destruct()
	{
		@$this->db->close();
	}
}

function printable($s)
{
	$s = preg_replace("~([\x01-\x08\x0E-\x0F\x10-\x1F\x7F-\xFF])~", ".", $s);
	$s = str_replace("\x00", '.', $s);
	if (x($_SERVER, 'SERVER_NAME')) {
		$s = escape_tags($s);
	}
	return $s;
}

// Procedural functions
function dbg($state)
{
	global $db;
	$db->dbg($state);
}

function dbesc($str)
{
	global $db;
	if ($db) {
		return($db->escape($str));
	}
}

// Function: q($sql,$args);
// Description: execute SQL query with printf style args.
// Example: $r = q("SELECT * FROM `%s` WHERE `uid` = %d",
//                   'user', 1);

function q($sql)
{
	global $db;
	$args = func_get_args();
	unset($args[0]);

	$ret = null;

	if ($db) {
		$final_sql = vsprintf($sql, $args);

		$ret = $db->q($final_sql);

		if ($db->db->errno) {
			logger('dba: ' . $db->db->error . ' sql: ' . $final_sql);
		}
	} else {
		error_log(__FILE__ . ':' . __LINE__ . ' $db has gone');
	}

	return $ret;
}

// Caller is responsible for ensuring that any integer arguments to
// dbesc_array are actually integers and not malformed strings containing
// SQL injection vectors. All integer array elements should be specifically
// cast to int to avoid trouble.
function dbesc_array_cb(&$item, $key)
{
	if (is_string($item)) {
		$item = dbesc($item);
	}
}

function dbesc_array(&$arr)
{
	if (is_array($arr) && count($arr)) {
		array_walk($arr, 'dbesc_array_cb');
	}
}
