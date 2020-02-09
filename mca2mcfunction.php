<?php
require_once('MCA.php');

# Region filename to load up
$mca_file = "./r.-1.-1.mca";

MCA::readMCA($mca_file);
file_put_contents("data.30.30.nbt", MCA::readChunk(30, 30));
MCA::readChunk(30, 30);