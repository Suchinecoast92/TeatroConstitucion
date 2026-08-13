<?php
include "../conexion.php";
$res = $conn->query("SHOW CREATE TABLE trt_25.boletos");
$row = $res->fetch_assoc();
echo $row['Create Table'];
?>
