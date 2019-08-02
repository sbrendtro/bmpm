# Import Language Definitions

From time to time, SteveMorse.org releases new versions of language files in the PHP implementation.
We try to make it easy to upgrade to newer versions of these files without having to re-port everything.
Note that this only affects language file changes, not the engine itself.

To import a new version of the BMPM Library:

1.  Copy the zip file to this folder.
2.  Run:   ./install.sh {three digit version number}

This script then:

- Creates a new (or overwrites an existing) folder with the specified version number
- Unzips the code into the folder
- Removes all PHP ending markers from the files, to prevent stray line feeds from showing up in the output of your application
- Compiles the given language into serialized data
- Saves the serialized data to ../language/{TYPE}/{VERSION}/*.php

You can specify the version your application should use by passing it when creating Language():

```
<?php
 $language = new \sbrendtro\bmpm\Language('gen', '311');

```

