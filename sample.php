<?php
/*
  Sample client
*/
require_once 'Lime.php';

use \Lime;

Lime\Client::set_endpoint('http://lime.local:8081/DataService/?wsdl');

$sql =
  "SELECT DISTINCT\n" .
  "       idsostype, descriptive, soscategory, soscategory.sosbusinessarea,\n" .
  "       webcompany, webperson, web, department, name\n"                      .
  "FROM   sostype\n"                                                           .
  "WHERE  active='1':numeric AND\n"                                            .
  "       soscategory.sosbusinessarea != 2701 AND\n"                           .
  "       web=1 AND (webperson=1 OR webcompany=1)\n"                           .
  "ORDER BY descriptive, soscategory DESC\n"                                   .
  "LIMIT  0, 5";

$res = Lime\query($sql);

foreach ($res as $row) {
  echo "* {$row['name']} ({$row['soscategory.descriptive']})\n";
}

?>