<?php
exec("git checkout . 2>&1",$out);
var_export($out);
exec("git pull 2>&1",$out);
var_export($out);


