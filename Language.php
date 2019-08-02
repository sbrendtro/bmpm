<?php

namespace sbrendtro\bmpm;

class Language
{

    public $type = 'gen';
    public $version = 311;

    public $debug = false;

    public $approxCommon;
    public $approx;
    public $languageRules;
    public $languages;
    public $rules;
    public $all;

    public function __construct($type='',$version='')
    {
        $this->type = $type ? $type : $this->type;
        $this->version = $version ? $version : $this->version;
        $this->init();
    }

    protected function init()
    {
        $folder = __DIR__ . '/' . implode('/', [ 'language', $this->version, $this->type ]);
        $sources = ['approxCommon', 'approx', 'languageRules', 'languages', 'rules', 'all'];

        foreach ( $sources as $source )
        {
            $raw = file_get_contents($folder . '/' . $source . '.php');
            $data = unserialize($raw);
            if ( $data )
            {
                $this->{$source} = $data;
            }
        }
    }

    public function getIndex($langName)
    {
      for ($i = 0; $i < count($this->languages); $i++) {
        if ($this->languages[$i] == $langName) {
          return $i;
        }
      }
      return 0; // name not found
    }
  
    public function getName($index)
    {
      if ($index < 0 || $index > count($this->languages)) {
        return "any"; // index out of range
      }
      return $this->languages[$index];
    }
  
    public function getCode($langName)
    {
      return pow(2, $this->getIndex($langName));
    }
  
    public function getIndexFromCode($code)
    {
      if ($code < 0 || $code > pow(2, count($this->languages) - 1)) { // code out of range
        return 0;
      }
      $log = log($code, 2);
      $result = floor($log);
      if ($result != $log) { // choice was more than one language, so use "any"
        $result = $this->getIndex("any", $this->languages);
      }
      return $result;
    }
  
    public function detect($name)
    {
      // convert $name to utf8
      $name = utf8_encode($name); // takes care of things in the upper half of the ascii chart, e.g., u-umlaut
      if (strpos($name, "&") !== false) { // takes care of ampersand-notation encoding of unicode (&#...;)
        $name = html_entity_decode($name, ENT_NOQUOTES, "UTF-8");
      }
      return $this->UTF8($name);
    }
  
    public function UTF8($name)
    {
      //    $name = mb_strtolower($name, mb_detect_encoding($name));
      $name = mb_strtolower($name, "UTF-8");
      $choicesRemaining = $this->all;
      for ($i = 0; $i < count($this->languageRules); $i++) {
        list($letters, $languages, $accept) = $this->languageRules[$i];
        //echo "testing letters=$letters languages=$this->languages accept=$accept<br>";
        if (preg_match($letters, $name)) {
          if ($accept) {
            $choicesRemaining &= $languages;
          } else { // reject
            $choicesRemaining &= (~$languages) % ($this->all + 1);
          }
        }
      }
      if ($choicesRemaining == 0) {
        $choicesRemaining = 1;
      }
      return $choicesRemaining;
    }
  

}

include_once("Engine.php");
include_once("DMSoundex.php");

$lang = new \sbrendtro\bmpm\Language();
$name = "Steven Lincoln Brendtro";
echo PHP_EOL.PHP_EOL."------------------------" . PHP_EOL;
echo $name . PHP_EOL;
$langCode = $lang->detect($name);

$engine = new \sbrendtro\bmpm\Engine( $lang );
$result = $engine->phonetic($name, $langCode);
$result = $engine->phoneticNumbers($result);

var_dump($result);

$dmSoundex = DMSoundex::name($name);
var_dump($dmSoundex);
//echo "DM Soundex:\t" . json_encode($dmSoundex) . PHP_EOL;

$name = "VonNotHaus";
echo PHP_EOL.PHP_EOL."------------------------" . PHP_EOL;
echo $name . PHP_EOL;

$langCode = $lang->detect($name);

$engine = new \sbrendtro\bmpm\Engine( $lang );
$result = $engine->phonetic($name, $langCode);
$result = $engine->phoneticNumbers($result);

var_dump($result);

$dmSoundex = DMSoundex::name($name);
var_dump($dmSoundex);
//echo "DM Soundex:\t" . json_encode($dmSoundex) . PHP_EOL;

$name = "Lincoln";
echo PHP_EOL.PHP_EOL."------------------------" . PHP_EOL;
echo $name . PHP_EOL;

$langCode = $lang->detect($name);

$engine = new \sbrendtro\bmpm\Engine( $lang );
$result = $engine->phonetic($name, $langCode);
$result = $engine->phoneticNumbers($result);

var_dump($result);

$dmSoundex = DMSoundex::name($name);
var_dump($dmSoundex);
//echo "DM Soundex:\t" . json_encode($dmSoundex) . PHP_EOL;