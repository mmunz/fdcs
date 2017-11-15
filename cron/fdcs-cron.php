<?php
require_once("/var/www/froxlor/lib/userdata.inc.php");
require_once("/var/www/froxlor/lib/functions/filedir/function.safe_exec.php");

$changed = false;
$pdo = new PDO('mysql:host=' . $sql['host'] . ';dbname=' . $sql['db'], $sql['user'], $sql['password']);

// get all users that have set fdcs as login shell
$fdcs_users = [];
$sql = "SELECT username FROM ftp_users WHERE shell='/usr/local/fdcs/bin/fdcs' AND login_enabled='Y';";
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
