<?php
echo "[Memory: " . memory_get_usage() . ", real:" . memory_get_usage(true) . "]\n";
require_once('MCWorld.php');
echo "[Memory: " . memory_get_usage() . ", real:" . memory_get_usage(true) . "]\n";
$loaded = MCWorld::loadFiles(['./r.-1.-1.mca']);
echo "Loaded $loaded files\n";
echo "[Memory: " . memory_get_usage() . ", real:" . memory_get_usage(true) . "]\n";
$chunk_data = MCWorld::getChunk(-2, 0, -2);
echo "[Memory: " . memory_get_usage() . ", real:" . memory_get_usage(true) . "]\n";
foreach ($chunk_data as $xyz => $block_data)
{
	list($x, $y, $z) = explode(',', $xyz);
	$block_name = $block_data['Name'];
	if (strlen($block_name) > 0)
	{
		$block_properties = '';
		if (array_key_exists('Properties', $block_data))
		{
			$block_states = [];
			/* This is a list of block states */
			foreach ($block_data['Properties'] as $state => $value)
			{
				$block_states[] = "$state=$value";
			}
			$block_properties = "[" . implode(',', $block_states) . "]";
		}
		echo "setblock ~$x ~$y ~$z " . $block_name . $block_properties . "\n";
	}
}