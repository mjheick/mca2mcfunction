<?php

/**
 * Lets take a minecraft world and some parameters, and "carve" out
 * something in that world so we can recreate it as a mcfunction file.
 *
 * We'll need to be able to read an MCA file (minecraft anvil)
 * - https://minecraft.gamepedia.com/Region_file_format
 * We'll need to be able to parse the nbt format 
 * - https://minecraft.gamepedia.com/NBT_format
 * - https://web.archive.org/web/20110723210920/http://www.minecraft.net/docs/NBT.txt
 * We'll need to be able to interpret the world in the nbt format
 * - https://minecraft.gamepedia.com/Chunk_format
 * and then we'll need to spit out the relevant commands
 */

require_once('MCA.php');
require_once('NBT.php');

# Region filename to load up
$mca_file = "./r.-1.-1.mca";

MCA::readMCA($mca_file);
file_put_contents("data.30.30.nbt", MCA::readChunk(30, 30));
$chunk_nbt = MCA::readChunk(30, 30);

$json = NBT::getJSON($chunk_nbt);
$data = json_decode($json, true);

var_export($data);