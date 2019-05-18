<?php
if($_SERVER["REQUEST_METHOD"] == "POST"){
	echo $source_code = $_POST["source_code"];
	exit;
}
?>