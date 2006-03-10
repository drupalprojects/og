<?php
// $Id$

include_once "includes/bootstrap.inc";
include_once 'includes/common.inc';

// backport of changes in HEAD that landed on 2005-10-19
// db_queryd("ALTER TABLE `og_uid` ADD `og_role` int(1) NOT NULL default '0'");
// db_queryd("ALTER TABLE `og_uid` ADD `is_active` int(1) default '0'");
// db_queryd("ALTER TABLE `og_uid` ADD `is_admin` int(1) default '0'");

// migrate subscriptions to og_uid table
$result = db_query("SELECT * FROM {node_access} WHERE realm = 'og_uid'");
while ($object = db_fetch_object($result)) {
  $sql = "REPLACE INTO {og_uid} (nid, uid, og_role, is_active, is_admin) VALUES (%d, %d, %d, %d, %d)";
  db_queryd($sql, $object->nid, $object->gid, ($object->grant_view + $object->grant_update), $object->grant_view, $object->grant_update);
}
$sql = "DELETE FROM {node_access} WHERE realm = 'og_uid'";
db_queryd($sql);

// feb 11, 2006
$sql = "SELECT DISTINCT(n.nid) FROM {node} n INNER JOIN {node_access} na ON n.nid = na.nid WHERE type != 'og' AND na.realm = 'og_group'";
$result = db_queryd($sql);
while ($row = db_fetch_object($result)) {
  $sql = "UPDATE {node_access} SET grant_view=1, grant_update=1, grant_delete=1 WHERE realm = 'og_group' AND nid = %d AND gid != 0";
  db_queryd($sql, $row->nid);
}

$sql = "SELECT nid FROM {node} WHERE type = 'og'";
$result = db_queryd($sql);
while ($row = db_fetch_object($result)) {
  $sql = "REPLACE INTO {node_access} (nid, gid, realm, grant_view, grant_update, grant_delete) VALUES (%d, %d, 'og_group', 1, 1, 0)";
  db_queryd($sql, $row->nid, $row->nid);
}

// end feb 11, 2006

// mar 10, 2006. need to change this index in order for query below to succeed and for proper saving of nodes
$sql = "ALTER TABLE `node_access` DROP PRIMARY KEY, ADD INDEX `nid_gid_realm` ( `nid` , `gid` , `realm`)";
db_query($sql);

// feb 19, 2006 - show public posts on group home page
// add a row for each combination of public node and group. needed to make public nodes show up in group homepage for non subscribers
$sql = "SELECT DISTINCT(nid) as nid FROM {node_access} WHERE realm = 'og_group' AND gid = 0" ;
$result = db_queryd($sql);
while ($row = db_fetch_object($result)) {
  $sql = "SELECT gid FROM {node_access} WHERE nid = %d AND realm = 'og_group' AND gid != 0" ;
  $result2 = db_queryd($sql, $row->nid);
  while ($row2 = db_fetch_object($result2)) {  
    $sql = "REPLACE INTO {node_access} (nid, realm, gid, grant_view) VALUES (%d, 'og_public', 0, %d)";
    db_queryd($sql, $row->nid, $row2->gid); 
  }
}

// change all former public node grants to 'og_all' realm
$sql = "UPDATE {node_access} SET realm = 'og_all' WHERE realm = 'og_group' AND gid = 0 AND grant_view = 1";
db_queryd($sql);

// change all nodes in groups  to new 'og_subscriber' realm
$sql = "UPDATE {node_access} SET realm = 'og_subscriber' WHERE realm = 'og_group' AND gid != 0";
db_queryd($sql);

// these records are no longer used. we've migrated them to new grant scheme
$sql = "DELETE FROM {node_access} WHERE realm = 'og_group'";
db_queryd($sql);

// end feb 19

?>