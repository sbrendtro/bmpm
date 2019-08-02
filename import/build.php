<?php

    function export($file,$data)
    {
        echo 'Writing to ' . $file . PHP_EOL;
        $string = serialize($data);
        file_put_contents($file,$string);
    }

    function getVersion()
    {
        global $argv;
        if ( ! isset( $argv[2] ) )
        {
            echo 'You must specify a version (310, 311, etc.)'. PHP_EOL;
            exit;
        }
        return $argv[2];
    }

    function getLanguageType()
    {
        global $argv;
        if ( ! isset( $argv[1] ) )
        {
            echo 'You must specify a language type (gen, ash, sep)'. PHP_EOL;
            exit;
        }
        return $argv[1];
    }

    $type = getLanguageType();
    $_GET['type'] = $type;

    include_once(implode('/',[getVersion(),'phoneticutils.php']));
    include_once(implode('/',[getVersion(),$type,'approxcommon.php']));
    include_once(implode('/',[getVersion(),$type,'lang.php']));

    foreach ( $languages as $lang )
    {
        include_once(implode('/',[getVersion(),$type,'rules'.$lang.'.php']));
        include_once(implode('/',[getVersion(),$type,'approx'.$lang.'.php']));
    }

    $rootPath = __DIR__ . '/..';
    $path = implode('/',[$rootPath, 'language', getVersion(), $type]);;

    export( implode('/',[$path,'approxCommon.php']), $approxCommon);
    export( implode('/',[$path,'rules.php']), $rules);
    export( implode('/',[$path,'languages.php']), $languages);
    export( implode('/',[$path,'approx.php']), $approx);
    export( implode('/',[$path,'languageRules.php']), $languageRules);
    export( implode('/',[$path,'all.php']), $all);



