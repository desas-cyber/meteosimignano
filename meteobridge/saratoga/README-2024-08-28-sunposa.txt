This 28-Aug-2024 release of the sun-moon position graphic requires you to upload multiple new
directories/contents to your website.

./cache/ has two files (jpsun.png and jpmoon.png that need to be uploaded)
./jpgraph-4.4.2-src-only/ needs to be uploaded with the subdirectory src/*.php
./moonimg/ directory has *.gif files needed .. upload the entire directory with files.

sunposa.php - program to generate the sun-moon graph. You don't need to configure normally.
sunposa-lang.php - lookup tables for the legends in all Saratoga template supported languages.

wxastronomy.php - replacement page which displays the sun-moon graph under the Solar Cycle images.

The sunposa.php script is Saratoga template aware and will use your Settings.php entries to automatically configure.

Additional optional utilities are available at https://github.com/ktrue/SunMoon-graph in case you'd like to
tweak the legends for a particular language.  The langtrans/ directory on GitHub contains the utilities.

NOTE: Do NOT edit the sunposa-lang.php file directly .. it is created by a langtrans/gen-sunposa-lang.php 
utility and the instructions on how to do updates to the translations are included in that directory.

K. True - 28-Aug-2024