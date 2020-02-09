# mca2mcfunction
Take a minecraft region .mca (Minecraft Anvil) format and a pair of coordinates and create
a mcfunction file to rebuild what we see in the world in any other world.

to be used with https://mcblockmarket.com

# TODO
* We need to be able to parse NBT
* We need to be able to read coords and entities from NBT
* Dumping to mcfunction format from json
* We need to be able to calculate world xyz -> region.mca -> nbt section for start + end
* Deduplicate functions in static classes, passing by reference

# Links
* https://minecraft.gamepedia.com/Region_file_format
* https://minecraft.gamepedia.com/NBT_format
* https://web.archive.org/web/20110723210920/http://www.minecraft.net/docs/NBT.txt
* https://minecraft.gamepedia.com/Chunk_format

# Notes
r.-1.-1.mca is packaged with this.

Our test model was in r.-1.-1.mca in chunk x=30, y=30 (saved as data.30.30.nbt)