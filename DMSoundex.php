<?php

namespace sbrendtro\bmpm;

class DMSoundex
{

  public static function name($MyStrArg) {

    $GROUPSEPARATOR = " ";

    // replace certain text in strings with a slash
    $re = "/ v | v\. | vel | aka | f | f. | r | r. | false | recte | on zhe /i";
    $MyStr = preg_replace($re, "/", $MyStrArg);

    // append soundex of each individual word
    $result = [];
    $MyStrArray = preg_split("/[ |,]+/", $MyStr); // use space or comma as token delimiter
    for ($i=0; $i<count($MyStrArray); $i++) {
      if (strlen($MyStrArray[$i]) > 0) { // ignore null at ends of array (due to leading or trailing space)
        $result[] = self::dmsoundex2($MyStrArray[$i]);
      }
    }
    return $result;
  }

  public static function place($MyStrArg) {

    $GROUPSEPARATOR = " ";

    // append soundex of each individual word
    $MyStr = preg_replace("[,]", "/", $MyStrArg);
    $result = [];
    $MyStrArray = preg_split("/[,]+/", $MyStr); // use comma as token delimiter
    for ($i=0; $i<count($MyStrArray); $i++) {
      if (strlen($MyStrArray[$i]) > 0) { // ignore null at ends of array (due to leading or trailing space)
        $result[] = self::dmsoundex2($MyStrArray[$i]);
      }
    }
    return $result;
  }

  public static function dmsoundex2($MyStrArg) {

    include "dmlat.php";
    $SEPARATOR = " ";
    $DMDEBUG = FALSE;
    $MyStr = strtolower($MyStrArg);
    $MyStr3 = $MyStr;

    // MyStr = original, MyStr2 = the current string being split off, MyStr3 = what's left to process
    $dm3 = "";
    while (strlen($MyStr3) > 0) {
      $MyStr2 = "";
      $LenMyStr3 = strlen($MyStr3);

      for ($i=0; $i < strlen($MyStr3); $i++) {
        if (($MyStr3[$i] >= self::$firstLetter && $MyStr3[$i] <= self::$lastLetter) || $MyStr3[$i] == '/' || $MyStr3[$i] == ' ') {
        // if (($MyStr3[$i] >= self::$firstLetter && $MyStr3[$i] <= self::$lastLetter) || $MyStr3[$i] == '/') {
          if ($MyStr3[$i] == '/') {
            $MyStr3 = substr($MyStr3, $i + 1);
            break;
          } else {
            if ($MyStr3[$i] != ' ') {
              $MyStr2 .= $MyStr3[$i];
            }
          }
        } else {
          // if ($MyStr[$i] == "(" || $MyStr[$i] == $SEPARATOR) {
          // if ($MyStr3[$i] == "(" || $MyStr3[$i] == $SEPARATOR) {
          // Ostend, Belgium (Morristown, Pa., U.S.)
          if ($MyStr3[$i] == "(") {
            $MyStr3 = substr($MyStr3, $i + 1); // Gary added
            break;
          }
        }
      }

      if ($i == $LenMyStr3) {
        $MyStr3 = ""; // finished
      }
      $MyStr = $MyStr2;
      $dm = "";
      $allblank = true;
      for ($k=0; $k<strlen($MyStr); $k++) {
        if ($MyStr[$k] != ' ') {
          $allblank = false;
          break;
        }
      }
      if (!$allblank) {

        $dim_dm2 = 1;
        $dm2 = array();
        $dm2[0] = "";

        $first = 1;
        $lastdm = array();
        $lastdm[0] = "";

        while (strlen($MyStr) > 0) {

          for ($rule=0; $rule<count($newrules); $rule++) { // loop through the rules
            if (substr($MyStr, 0, strlen($newrules[$rule][0])) == $newrules[$rule][0]) { // match found
              //check for $xnewrules branch
              $xr = "!" . $newrules[$rule][0] . "!";
              if (strpos($xnewruleslist, $xr) !== false) {
                $xr = strpos($xnewruleslist, $xr) / 3;
                for ($dmm = $dim_dm2; $dmm < 2 * $dim_dm2; $dmm++) {
                  $dm2[$dmm] = $dm2[$dmm - $dim_dm2];
                  $lastdm[$dmm] = $lastdm[$dmm - $dim_dm2];
                }
                $dim_dm2 = 2 * $dim_dm2;
              } else {
                $xr = -1;
              }
   
              $dm = $dm . "_" . $newrules[$rule][0];
              if (strlen($MyStr) > strlen($newrules[$rule][0])) {
                $MyStr = substr($MyStr, strlen($newrules[$rule][0]));
              } else {
                $MyStr = "";
              }

              // If first rule hit
              if ($first == 1) {
                $dm2[0] = $newrules[$rule][1];
                $first = 0;
                $lastdm[0] = $newrules[$rule][1];

                if ($xr >= 0) {
                  $dm2[1] = $xnewrules[$xr][1];
                  $lastdm[1] = $xnewrules[$xr][1];
                }
              // If after first rule hit
              } else {
                $dmnumber = 1;
                if ($dim_dm2 > 1) {
                  $dmnumber = $dim_dm2 / 2;
                }
                // if 1st position is a vowel
                if (strlen($MyStr) > 0 && strpos(self::$vowels, $MyStr[0]) !== false) {

                  for ($ii=0; $ii<$dmnumber; $ii++) {
                    if ($newrules[$rule][2] != "999" && $newrules[$rule][2] != $lastdm[$ii]) {
                      $lastdm[$ii] = $newrules[$rule][2];
                      $dm2[$ii] .= $newrules[$rule][2];
                    } else if ($newrules[$rule][3] == 999) {
                      // reset $lastdm if vowel is encountered but not if adjacent consonants
                      $lastdm[$ii] = "";
                    }
                  }

                  if ($dim_dm2 > 1) {
                    for ($ii=$dmnumber; $ii<$dim_dm2; $ii++) {
                      if ($xr >= 0 && $xnewrules[$xr][2] != "999" && $xnewrules[$xr][2] != $lastdm[$ii]) {
                        $lastdm[$ii] = $xnewrules[$xr][2];
                        $dm2[$ii] .= $xnewrules[$xr][2];
                      } else {
                        if ($xr < 0 && $newrules[$rule][2] != "999" && $newrules[$rule][2] != $lastdm[$ii]) {
                          $lastdm[$ii] = $newrules[$rule][2];
                          $dm2[$ii] .= $newrules[$rule][2];
                        } else if ($newrules[$rule][3] == 999) {
                          // reset $lastdm if vowel is encountered but not if adjacent consonants
                          $lastdm[$ii] = "";
                        }
                      }
                    }
                  }
      
                // 1st position not a vowel
                } else {
                  for ($ii=0; $ii<$dmnumber; $ii++) {
                    if ($newrules[$rule][3] != "999" && $newrules[$rule][3] != $lastdm[$ii]) {
                      $lastdm[$ii] = $newrules[$rule][3];
                      $dm2[$ii] .= $newrules[$rule][3];
                    } else if ($newrules[$rule][3] == 999) {
                      // reset $lastdm if vowel is encountered but not if adjacent consonants
                      $lastdm[$ii] = "";
                    }
                  }
                  if ($dim_dm2 > 1) {
                    for ($ii=$dmnumber; $ii<$dim_dm2; $ii++) {
                      if ($xr >= 0 && $xnewrules[$xr][3] != "999" && $xnewrules[$xr][3] != $lastdm[$ii]) {
                        $lastdm[$ii] = $xnewrules[$xr][3];
                        $dm2[$ii] .= $xnewrules[$xr][3];
                      } else {
                        if ($xr < 0 && $newrules[$rule][3] != "999" && $newrules[$rule][3] != $lastdm[$ii]) {
                          $lastdm[$ii] = $newrules[$rule][3];
                          $dm2[$ii] .= $newrules[$rule][3];
                        } else if ($newrules[$rule][3] == 999) {
                          // reset $lastdm if vowel is encountered but not if adjacent consonants
                          $lastdm[$ii] = "";
                        }
                      }
                    }
                  } // end of dim_dm2 > 1
                } // end of not a vowel
              }

              break; // stop looping through rules
            } // end of match found

          } // end of looping through the rules
        } // end of while (strlen($MyStr)) > 0)

        $dm = "";
        for ($ii=0; $ii<$dim_dm2; $ii++) {
          $dm2[$ii] = substr($dm2[$ii] . "000000",0, 6);
          if ($ii == 0 && strpos($dm, $dm2[$ii]) === false && strpos($dm3,$dm2[$ii]) === false) {
            $dm = $dm2[$ii];
          } else {
            if (strpos($dm, $dm2[$ii]) === false && strpos($dm3, $dm2[$ii]) === false) {
              if (strlen($dm) > 0) {
                $dm = $dm . $SEPARATOR . $dm2[$ii];
              } else {
                $dm = $dm2[$ii];

              }
            }
          }
        }

        if (strlen($dm3) > 0 && strlen($dm) > 0 && strpos($dm3, $dm) === false) {
          $dm3 = $dm3 . $SEPARATOR . $dm;
        } else {
          if (strlen($dm) > 0) {
            $dm3 = $dm;
          }
        }

      }

    } // end of while

    $dm = $dm3;
    return $dm;
  }

  // DM SoundEx Rules
  static $firstLetter = 'a';
  static $lastLetter = 'z';
  static $vowels = "aeioujy";

  public static function rules()
  {
    return [
      ["schtsch", "2", "4", "4"],
      ["schtsh", "2", "4", "4"],
      ["schtch", "2", "4", "4"],
      ["shtch", "2", "4", "4"],
      ["shtsh", "2", "4", "4"],
      ["stsch", "2", "4", "4"],
      ["ttsch", "4", "4", "4"],
      ["zhdzh", "2", "4", "4"],
      ["shch", "2", "4", "4"],
      ["scht", "2", "43", "43"],
      ["schd", "2", "43", "43"],
      ["stch", "2", "4", "4"],
      ["strz", "2", "4", "4"],
      ["strs", "2", "4", "4"],
      ["stsh", "2", "4", "4"],
      ["szcz", "2", "4", "4"],
      ["szcs", "2", "4", "4"],
      ["ttch", "4", "4", "4"],
      ["tsch", "4", "4", "4"],
      ["ttsz", "4", "4", "4"],
      ["zdzh", "2", "4", "4"],
      ["zsch", "4", "4", "4"],
      ["chs", "5", "54", "54"],
      ["csz", "4", "4", "4"],
      ["czs", "4", "4", "4"],
      ["drz", "4", "4", "4"],
      ["drs", "4", "4", "4"],
      ["dsh", "4", "4", "4"],
      ["dsz", "4", "4", "4"],
      ["dzh", "4", "4", "4"],
      ["dzs", "4", "4", "4"],
      ["sch", "4", "4", "4"],
      ["sht", "2", "43", "43"],
      ["szt", "2", "43", "43"],
      ["shd", "2", "43", "43"],
      ["szd", "2", "43", "43"],
      ["tch", "4", "4", "4"],
      ["trz", "4", "4", "4"],
      ["trs", "4", "4", "4"],
      ["tsh", "4", "4", "4"],
      ["tts", "4", "4", "4"],
      ["ttz", "4", "4", "4"],
      ["tzs", "4", "4", "4"],
      ["tsz", "4", "4", "4"],
      ["zdz", "2", "4", "4"],
      ["zhd", "2", "43", "43"],
      ["zsh", "4", "4", "4"],
      ["ai", "0", "1", "999"],
      ["aj", "0", "1", "999"],
      ["ay", "0", "1", "999"],
      ["au", "0", "7", "999"],
      ["cz", "4", "4", "4"],
      ["cs", "4", "4", "4"],
      ["ds", "4", "4", "4"],
      ["dz", "4", "4", "4"],
      ["dt", "3", "3", "3"],
      ["ei", "0", "1", "999"],
      ["ej", "0", "1", "999"],
      ["ey", "0", "1", "999"],
      ["eu", "1", "1", "999"],
      ["ia", "1", "999", "999"],
      ["ie", "1", "999", "999"],
      ["io", "1", "999", "999"],
      ["iu", "1", "999", "999"],
      ["ks", "5", "54", "54"],
      ["kh", "5", "5", "5"],
      ["mn", "66", "66", "66"],
      ["nm", "66", "66", "66"],
      ["oi", "0", "1", "999"],
      ["oj", "0", "1", "999"],
      ["oy", "0", "1", "999"],
      ["pf", "7", "7", "7"],
      ["ph", "7", "7", "7"],
      ["sh", "4", "4", "4"],
      ["sc", "2", "4", "4"],
      ["st", "2", "43", "43"],
      ["sd", "2", "43", "43"],
      ["sz", "4", "4", "4"],
      ["th", "3", "3", "3"],
      ["ts", "4", "4", "4"],
      ["tc", "4", "4", "4"],
      ["tz", "4", "4", "4"],
      ["ui", "0", "1", "999"],
      ["uj", "0", "1", "999"],
      ["uy", "0", "1", "999"],
      ["ue", "0", "1", "999"],
      ["zd", "2", "43", "43"],
      ["zh", "4", "4", "4"],
      ["zs", "4", "4", "4"],
      ["rz", "4", "4", "4"],
      ["ch", "5", "5", "5"],
      ["ck", "5", "5", "5"],
      //["rs", "4", "4", "4"],
      ["fb", "7", "7", "7"],
      ["a", "0", "999", "999"],
      ["b", "7", "7", "7"],
      ["d", "3", "3", "3"],
      ["e", "0", "999", "999"],
      ["f", "7", "7", "7"],
      ["g", "5", "5", "5"],
      ["h", "5", "5", "999"],
      ["i", "0", "999", "999"],
      ["k", "5", "5", "5"],
      ["l", "8", "8", "8"],
      ["m", "6", "6", "6"],
      ["n", "6", "6", "6"],
      ["o", "0", "999", "999"],
      ["p", "7", "7", "7"],
      ["q", "5", "5", "5"],
      ["r", "9", "9", "9"],
      ["s", "4", "4", "4"],
      ["t", "3", "3", "3"],
      ["u", "0", "999", "999"],
      ["v", "7", "7", "7"],
      ["w", "7", "7", "7"],
      ["x", "5", "54", "54"],
      ["y", "1", "999", "999"],
      ["z", "4", "4", "4"],
      ["c", "5", "5", "5"],
      ["j", "1", "999", "999"],
    ];

  }

  public static function xnewrules()
  {
    // Now branching cases
    return [
      ["rz", "94", "94", "94"],
      ["ch", "4", "4", "4"],
      ["ck", "45", "45", "45"],
      //["rs", "94", "94", "94"],
      ["c", "4", "4", "4"],
      ["j", "4", "4", "4"],
    ];
  }
      
  public static function xnewruleslist()
  {
    return "!rz!ch!ck!c!!j!"; // temporarily remove rs
    // return "!rz!ch!ck!rs!c!!j!";
  }
  

}
