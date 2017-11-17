<?php

function isDeclaration($line) {
    return strpos($line, '#') !== 0 && strpos($line, "=");
}

function loadConfig($dir) {
	$conf = [];
	$confFiles = [
		sprintf('%s/config/default.config.sh', $dir),
		sprintf('%s/config/local.config.sh', $dir),
		'/etc/fdcs/config'
	];

	foreach($confFiles as $confFile) {
		if (file_exists($confFile)) {
			$handle = fopen($confFile, "r");
			$lines = [];
			while (($line = fgets($handle)) !== false) {
			        $lines[] = $line;
			}
			//$contents = fread($handle, filesize($confFile));
			//$lines = explode("\n", $contents);
			$keyValuePairs = array_filter($lines, "isDeclaration");
			// Now you can iterator over $decls exploding on "=" to see param/value
			foreach($keyValuePairs as $line) {
				list($key, $value) = explode("=", $line);
				$value = trim($value);
				$value = trim($value, "'");
				$conf[$key] = trim($value, '"');
			}
			fclose($handle);
		}
	}
	return $conf;
}

$dir = realpath(__DIR__ . '/../' );
$config = loadConfig($dir);

require_once(sprintf("%s/lib/userdata.inc.php", $config['FROXLOR_DIR']));
require_once(sprintf("%s/lib/functions/filedir/function.safe_exec.php", $config['FROXLOR_DIR']));

$changed = false;
$pdo = new PDO('mysql:host=' . $sql['host'] . ';dbname=' . $sql['db'], $sql['user'], $sql['password']);

// get all users that have set fdcs as login shell
$fdcs_users = [];
$sql = sprintf("SELECT username FROM ftp_users WHERE shell='%s/bin/fdcs' AND login_enabled='Y';", $dir);
foreach ($pdo->query($sql) as $row) {
  $fdcs_users[] = $row['username'];
}
$fdcs_users_string = implode($fdcs_users,',');

// fetch the fdcs group from ftp_groups
$sth = $pdo->prepare("SELECT * FROM ftp_groups WHERE groupname='fdcs'");
$sth->execute();
$sql_fdcs_group = $sth->fetch(PDO::FETCH_ASSOC);

if (is_array($sql_fdcs_group)) {
	// fdcs group already exists
	if ($sql_fdcs_group['members'] !== $fdcs_users_string) {
		// group membership changed, update row
	        $stmt = $pdo->prepare("UPDATE ftp_groups SET members = :members WHERE groupname = 'fdcs';");
	        $stmt->bindParam(':members', $members);
	        $members = $fdcs_users_string;
	        $stmt->execute();
		$changed = true;
	}
} else {
	// no fdcs group exists yet, create it
	$stmt = $pdo->prepare("INSERT INTO ftp_groups (groupname,gid,members,customerid) VALUES(:groupname, :gid, :members, :customerid);");
	$stmt->bindParam(':groupname', $groupname);
	$stmt->bindParam(':gid', $gid);
	$stmt->bindParam(':members', $members);
	$stmt->bindParam(':customerid', $customerid);
	$groupname = 'fdcs';
	$gid = '9998';
	$members = $fdcs_users_string;
	$customerid = -1;
	$stmt->execute();
	$changed = true;
}

if ($changed) {
	// fdcs group membership changed, invalidating nscd caches
	$false_val = false;
	safe_exec('nscd -i group 1> /dev/null', $false_val, array('>'));
	safe_exec('nscd -i passwd 1> /dev/null', $false_val, array('>'));
}

?>
