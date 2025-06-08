The files in this directory use the same format as the WXSIM plaintext-parser.php language files.

The only entries in the files that are used by the AW-forecast.php script are the

lang|<english words>|<equivalent language words>|

and

charset|ISO-8859-n|

Please note that the files by default (and those without a charset entry) are all
in ISO-8859-1 (Latin/ANSI) format and must remain so.  Files with a charset entry
at the start of the file must remain in the specified ISO-8859-n format also, so
be careful with your editor (Notepad++ is recommended) as you add new entries to 
ensure the proper character set is specified.  If an ISO charset is inadvertently
changed to UTF-8, the translations will fail to function for that language.

