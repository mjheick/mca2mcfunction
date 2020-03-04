# mca2mcfunction
Take a minecraft region .mca (Minecraft Anvil) format and a pair of coordinates and create
a mcfunction file to rebuild what we see in the world in any other world.

to be used with https://mcblockmarket.com

# TODO
* We need to be able to calculate world xyz -> region.mca -> nbt section for start + end
* Be able to give arbitrary x1y1z1-x2y2yz2 and derive blocks from that to .mcfunction
* Debug our overall block extraction algorithm

# Links
* https://minecraft.gamepedia.com/Region_file_format
* https://minecraft.gamepedia.com/NBT_format
* https://web.archive.org/web/20110723210920/http://www.minecraft.net/docs/NBT.txt
* https://minecraft.gamepedia.com/Chunk_format
* https://github.com/jaquadro/NBTExplorer/releases/tag/v2.8.0-win

# Notes
r.-1.-1.mca is packaged with this.

Our test model was in r.-1.-1.mca in chunk -2, 0, -2
