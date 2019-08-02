<?php

namespace sbrendtro\bmpm;

class Engine
{

    public $type = 'gen';
    public $debug = false;

    public $language;

    public function __construct(Language $language)
    {
        $this->language = $language;
    }

    function postProcess($parts)
    {
        $parts = explode('-', $result);
        $values = [];
        foreach ($parts as $term) {
            $term = substr($term, 1, -1);
            $words = explode('|', $term);
            $values[] = $words;
        }
        return $values;
    }

    // 1 is language code for any
    public function phonetic($input, $langCode)
    {
        // convert $input to utf8
        $input = utf8_encode($input); // takes care of things in the upper half of the ascii chart, e.g., u-umlaut
        if (strpos($input, "&") !== false) { // takes care of ampersand-notation encoding of unicode (&#...;)
            $input = html_entity_decode($input, ENT_NOQUOTES, "UTF-8");
        }
        return $this->phoneticUTF8($input, $langCode);
    }

    public function redoLanguage($input, $rules, $finalRules1, $finalRules2, $concat)
    {
        // we can do a better job of determining the language now that multiple names have been split
        global $languageRules;
        $languageArg = $this->language->detect($input, $languageRules);
        return $this->phoneticUTF8Internal($input, $rules, $finalRules1, $finalRules2, $languageArg, $concat);
    }

    public function phoneticUTF8($input, $langCode)
    {
        $index = $this->language->getIndexFromCode($langCode, $this->language->languages);
        $rules = $this->language->rules[$index];
        $approx = $this->language->approx[$index];

        return $this->phoneticUTF8Internal($input, $rules, $this->language->approxCommon, $approx, $langCode);
    }

    public function phoneticUTF8Internal($input, $rules, $finalRules1, $finalRules2, $languageArg = "", $concat = false)
    {

        // algorithm used here is as follows:
        //
        //   Before doing anything else:
        //    (1) replace leading:
        //         de<space>la<space> with dela<space>
        //         van<space>der<space> with vander<space>
        //         van<space>den<space> with vanden<space>
        //    (2) gen and ash: remove all apostrophes (i.e., X'Y ==> XY)
        //    (3) remove all spaces, apostrophes, and dashes except for the first one (i.e. X Y Z ==> X YZ)
        //    (4) convert remaining dashes and apostrophes (if any) to space (i.e. X'Y ==> X Y)

        //   if Exact:
        //     if <space> is present (i.e. X Y/
        //       X Y => XY
        //   if Approx or Hebrew:
        //     if <space> is present (i.e. X Y)
        //       if X in list (different lists for ash, sep, gen, see below)
        //         X Y => Y and XY
        //       else if X is not in list 
        //         X Y => X, Y, and XY

        if ($this->debug) echo "<hr>";
        $input = trim($input); // remove any leading or trailing white space
        $input = mb_strtolower($input, mb_detect_encoding($input));

        // determine type (ashkenazic, sephardic, generic

        $ash = false; // ashkenazic
        $sep = false; // sephardic
        $type = $this->type;
        if ($type == "ash") {
            $ash = true; // ashkenazic
        } else if ($type == "sep") {
            $sep = true; // sephardic
        }

        // remove spaces from within certain leading words

        $list = array("de la", "van der", "van den");
        for ($i = 0; $i < count($list); $i++) {
            $target = $list[$i] . " ";
            if (substr($input, 0, strlen($target)) == $target) {
                $target = $list[$i];
                $input = str_replace(" ", "", $target) . substr($input, strlen($target));
            }
        }

        // for ash and gen -- remove all apostrophes

        if (!$sep) {
            $input = str_replace("'", "", $input);
        }

        // remove all apostrophoes, dashes, and spaces except for the first one, replace first one with space

        $list = array("'", "-", " ");
        for ($i = 0; $i < count($list); $i++) {
            $target = $list[$i];
            if (($firstOne = strpos($input, $target)) !== false) {
                $input = str_replace($target, "", $input); // remove all occurences
                $input = substr($input, 0, $firstOne) . " " . substr($input, $firstOne); // replace first occurence with space
            }
        }

        if ($sep) {

            $list = array( // sephardi
                "abe", "aben", "abi", "abou", "abu", "al", "bar", "ben", "bou", "bu",
                "d", "da", "dal", "de", "del", "dela", "della", "des", "di",
                "el", "la", "le", "ibn", "ha"
            );
        } else if ($ash) { // ashkenazi

            $list = array(
                "ben", "bar", "ha"
            );
        } else { // generic

            $list = array(
                "abe", "aben", "abi", "abou", "abu", "al", "bar", "ben", "bou", "bu",
                "d", "da", "dal", "de", "del", "dela", "della", "des", "di", "dos", "du",
                "el", "la", "le", "ibn", "van", "von", "ha", "vanden", "vander"
            );
        }

        // process a multiword name of form X Y

        if (($space = strpos($input, " ")) !== false) { // number of words is exactly two

            if ($concat) { // exact matches
                // X Y => XY 
                $input = str_replace(" ", "", $input); // concatenate the separate words of a name
            } else { // number of words is exactly two
                $word1 = substr($input, 0, $space);
                $word2 = substr($input, $space + 1);
                if (in_array($word1, $list)) {
                    // X Y => Y and XY
                    $results = $this->redoLanguage($word2, $rules, $finalRules1, $finalRules2, $concat);
                    $results .= "-" . $this->redoLanguage("$word1$word2", $rules, $finalRules1, $finalRules2, $concat);
                } else { // first word is not in list
                    // X Y => X, Y, and XY
                    $results = $this->redoLanguage($word1, $rules, $finalRules1, $finalRules2, $concat);
                    $results .= "-" . $this->redoLanguage($word2, $rules, $finalRules1, $finalRules2, $concat);
                    $results .= "-" . $this->redoLanguage("$word1$word2", $rules, $finalRules1, $finalRules2, $concat);
                }
                return $results;
            }
        }

        // at this point, $input is only a single word

        $inputLength = strlen($input);

        // format of $rules array

        $patternPos = 0;
        $lcontextPos = 1;
        $rcontextPos = 2;
        $phoneticPos = 3;
        $languagePos = 4;
        $logicalPos = 5;

        // apply language rules to map to phonetic alphabet

        $fileName = ""; // will be used when debugging
        if (count($rules[count($rules) - 1]) == 1) { // last item is name of the file
            $fileName = array_pop($rules);
            $fileName = $fileName[0];
        }

        if ($this->debug) {
            echo "applying language rules from ($fileName) to <b>$input</b> using languages $languageArg<br><br>";
            echo "char codes =";
            for ($i = 0; $i < strlen($input); $i++) {
                if (ord($input[$i]) < 128 + 64) { // 1-byte character
                    echo " [#" .
                        dechex(ord($input[$i])) .
                        "]" . $input[$i];
                } else if (ord($input[$i]) < 128 + 64 + 32) { // 2-byte character
                    echo " [#" .
                        dechex(ord($input[$i])) . dechex(ord($input[$i + 1])) .
                        "]" . substr($input, $i, 2);
                    $i++;
                } else if (ord($input[$i]) < 128 + 64 + 32 + 16) { // 3-byte character
                    echo " [#" .
                        dechex(ord($input[$i])) . dechex(ord($input[$i + 1])) . dechex(ord($input[$i + 2])) .
                        "]" . substr($input, $i, 3);
                    $i += 2;
                } else if (ord($input[$i]) < 128 + 64 + 32 + 16 + 8) { // 4-byte character
                    echo " [#" .
                        dechex(ord($input[$i])) . dechex(ord($input[$i + 1])) . dechex(ord($input[$i + 2])) . dechex(ord($input[$i + 3])) .
                        "]" . substr($input, $i, 4);
                    $i += 3;
                } else if (ord($input[$i]) < 128 + 64 + 32 + 16 + 8) { // 5-byte character
                    echo " [#" .
                        dechex(ord($input[$i])) . dechex(ord($input[$i + 1])) . dechex(ord($input[$i + 2])) . dechex(ord($input[$i + 3])) . dechex(ord($input[$i + 4])) .
                        "]" . substr($input, $i, 5);
                    $i += 4;
                } else if (ord($input[$i]) < 128 + 64 + 32 + 16 + 8 + 4) { // 6-byte character
                    echo " [#" .
                        dechex(ord($input[$i])) . dechex(ord($input[$i + 1])) . dechex(ord($input[$i + 2])) . dechex(ord($input[$i + 3])) . dechex(ord($input[$i + 4])) . dechex(ord($input[$i + 5])) .
                        "]" . substr($input, $i, 5);
                    $i += 5;
                }
            }
            echo "<br><br>";
        }
        $phonetic = "";
        for ($i = 0; $i < $inputLength;) {
            $found = false;
            for ($r = 0; $r < count($rules); $r++) {
                $rule = $rules[$r];
                $pattern = $rule[$patternPos];
                $patternLength = strlen($pattern);
                $lcontext = $rule[$lcontextPos];
                $rcontext = $rule[$rcontextPos];

                // check to see if next sequence in input matches the string in the rule
                if ($patternLength > $inputLength - $i || substr($input, $i, $patternLength) != $pattern) { // no match
                    continue;
                }

                $right = "/^$rcontext/";
                $left = "/$lcontext" . '$' . "/";

                // check that right context is satisfied
                if ($rcontext != "") {
                    if (!preg_match($right, substr($input, $i + $patternLength))) {
                        continue;
                    }
                }

                // check that left context is satisfied
                if ($lcontext != "") {
                    if (!preg_match($left, substr($input, 0, $i))) {
                        continue;
                    }
                }

                // check to see if languageArg is one of the allowable ones (used only with "any" rules)
                if (($languageArg != "1") && ($languagePos < count($rule))) {
                    $language = $rule[$languagePos]; // the required language(s) for this rule to apply
                    $logical = $rule[$logicalPos]; // do we require ALL or ANY of the required languages
                    if ($logical == "ALL") {
                        // check to see if languageArg contains all the required languages
                        if (($languageArg & $language) != $language) {
                            continue;
                        }
                    } else { // any
                        // check to see if languageArg contains at least one required language
                        if (($languageArg & $language) == 0) {
                            continue;
                        }
                    }
                }

                // check for incompatible attributes

                $candidate = $this->applyRuleIfCompatible($phonetic, $rule[$phoneticPos], $languageArg);
                if ($candidate === false) {
                    if ($this->debug) echo "rejecting rule #$r because of incompatible attributes<br>" .
                        "&nbsp;&nbsp;&nbsp;pattern=$pattern<br>" .
                        "&nbsp;&nbsp;&nbsp;lcontext=$lcontext<br>" .
                        "&nbsp;&nbsp;&nbsp;rcontext=$rcontext<br>" .
                        "&nbsp;&nbsp;&nbsp;subst=" . $rule[$phoneticPos] . "<br>" .
                        "&nbsp;&nbsp;&nbsp;result=$phonetic<br><br>";
                    continue;
                }
                $phonetic = $candidate;
                if ($this->debug) echo "applying rule #$r<br>" .
                    "&nbsp;&nbsp;&nbsp;pattern=$pattern<br>" .
                    "&nbsp;&nbsp;&nbsp;lcontext=$lcontext<br>" .
                    "&nbsp;&nbsp;&nbsp;rcontext=$rcontext<br>" .
                    "&nbsp;&nbsp;&nbsp;subst=" . $rule[$phoneticPos] . "<br>" .
                    "&nbsp;&nbsp;&nbsp;result=$phonetic<br><br>";
                $found = true;
                break;
            }
            if (!$found) { // character in name that is not in table -- e.g., space
                if ($this->debug) echo "<b>not found</b>: " . substr($input, $i, 1) . "<br><br>";
                $patternLength = 1;
            }
            $i += $patternLength;
        }
        if ($this->debug) echo "after language rules: <b>$phonetic</b><br><br>";

        // apply final rules on phonetic-alphabet, doing a substitution of certain characters
        // finalRules1 are the common approx rules, finalRules2 are approx rules for specific language

        $phonetic = $this->applyFinalRules($phonetic, $finalRules1, $languageArg, false, $this->debug); // apply common rules
        $phonetic = $this->applyFinalRules($phonetic, $finalRules2, $languageArg, true, $this->debug); // apply lang specific rules

        return $phonetic;
    }

    public function applyFinalRules($phonetic, $finalRules, $languageArg, $strip, $debug)
    {

        // optimization to save time

        if ($finalRules == "" || count($finalRules) == 0) {
            return $phonetic;
        }

        // format of $rules array

        $patternPos = 0;
        $lcontextPos = 1;
        $rcontextPos = 2;
        $phoneticPos = 3;
        $languagePos = 4;
        $logicalPos = 5;

        // expand the result

        $phonetic = $this->expand($phonetic);
        $phoneticArray = explode("|", $phonetic);

        $fileName = ""; // will be used when debugging
        if (count($finalRules[count($finalRules) - 1]) == 1) { // last item is name of the file
            $fileName = array_pop($finalRules);
            $fileName = $fileName[0];
        }

        for ($k = 0; $k < count($phoneticArray); $k++) {

            $phonetic = $phoneticArray[$k];
            if ($this->debug) echo "<br>applying final rules from ($fileName) to $phonetic<br>";
            $phonetic2 = "";

            $phoneticx = $this->normalizeLanguageAttributes($phonetic, true);
            for ($i = 0; $i < strlen($phonetic);) {
                $found = false;

                if (substr($phonetic, $i, 1) == "[") { // skip over language attribute
                    $attribStart = $i;
                    $i++;
                    while (true) {
                        if (substr($phonetic, $i++, 1) == "]") {
                            $attribEnd = $i;
                            $phonetic2 .= substr($phonetic, $attribStart, $attribEnd - $attribStart);
                            break;
                        }
                    }
                    continue;
                }

                for ($r = 0; $r < count($finalRules); $r++) {
                    $rule = $finalRules[$r];
                    $pattern = $rule[$patternPos];
                    $patternLength = strlen($pattern);
                    $lcontext = $rule[$lcontextPos];
                    $rcontext = $rule[$rcontextPos];

                    $right = "/^$rcontext/";
                    $left = "/$lcontext" . '$' . "/";

                    // check to see if next sequence in $phonetic matches the string in the rule
                    if ($patternLength > strlen($phoneticx) - $i || substr($phoneticx, $i, $patternLength) != $pattern) { // no match
                        continue;
                    }

                    // check that right context is satisfied
                    if ($rcontext != "") {
                        if (!preg_match($right, substr($phoneticx, $i + $patternLength))) {
                            continue;
                        }
                    }

                    // check that left context is satisfied
                    if ($lcontext != "") {
                        if (!preg_match($left, substr($phoneticx, 0, $i))) {
                            continue;
                        }
                    }

                    // check to see if rule applies to languageArg (used only with "any" rules)
                    if (($languageArg != "1") && ($languagePos < count($rule))) {
                        $language = $rule[$languagePos]; // the required language(s) for this rule to apply
                        $logical = $rule[$logicalPos]; // do we require ALL or ANY of the required languages
                        if ($logical == "ALL") {
                            // check to see if languageArg contains all the required languages
                            if (($languageArg & $language) != $language) {
                                continue;
                            }
                        } else { // any
                            // check to see if languageArg contains at least one required language
                            if (($languageArg & $language) == 0) {
                                continue;
                            }
                        }
                    }

                    // check for incompatible attributes

                    $candidate = $this->applyRuleIfCompatible($phonetic2, $rule[$phoneticPos], $languageArg);
                    if ($candidate === false) {
                        if ($this->debug) echo "rejecting rule #$r because of incompatible attributes<br>";
                        continue;
                    }
                    $phonetic2 = $candidate;

                    if ($this->debug) echo "after applying final rule #$r to phonetic item #$k at position $i: <b>$phonetic2</b> pattern=$pattern lcontext=$lcontext rcontext=$rcontext subst=" . $rule[$phoneticPos] . "<br>";
                    $found = true;
                    break;
                }

                if (!$found) { // character in name for which there is no subsitution in the table
                    $phonetic2 .= substr($phonetic, $i, 1);
                    if ($this->debug) echo "no rules match for phonetic item $k at position $i: <b>$phonetic2</b><br>";
                    $patternLength = 1;
                }
                $i += $patternLength;
            }
            $phoneticArray[$k] = $this->expand($phonetic2);
        }
        $phonetic = Implode("|", $phoneticArray);
        if ($strip) {
            $phonetic = $this->normalizeLanguageAttributes($phonetic, true);
        }
        if (strpos($phonetic, "|") !== false) {
            $phonetic = "(" . $this->removeDuplicateAlternates($phonetic) . ")";
        }
        return $phonetic;
    }

    public function mod($x, $y)
    {
        // use this instead of % to avoid negative results
        $mod = $x % $y;
        if ($mod < 0) {
            $mod += $y;
        }
        return $mod;
    }

    public function phoneticNumber($phonetic, $hash = true)
    {
        if (($bracket = strpos($phonetic, "[")) !== false) {
            return substr($phonetic, 0, $bracket);
        }
        return ($phonetic); // experimental !!!!
        $phoneticLetters = "!bdfghjklmnNprsSt68vwzZxAa4oe5iI9uUEyQY"; // true phonetic letters
        $phoneticLetters .= "1BCDEHJKLOTUVWX"; // metaphonetic letters
        // dummy first letter, otherwise b would be treated as 0 and have no effect on result
        $metaPhoneticLetters = ""; // added letters to be used in finalxxx.php rules
        $result = 0;

        for ($i = 0; $i < strlen($phonetic); $i++) {
            if (substr($phonetic, $i, 1) == "#") { // it's a meta phonetic letter
                if ($i == (strlen($phonetic) - 1)) {
                    echo "fatal error: invalid metaphonetic letter at position " . ($i + 1) . " in $phonetic<br>";
                    exit;
                    return -1;
                }
                $i++;
                $j = strpos($metaPhoneticLetters, substr($phonetic, $i, 1));
                if ($j !== false) {
                    $j += strlen($phoneticLetters);
                }
            } else {
                $j = strpos($phoneticLetters, substr($phonetic, $i, 1));
            }
            if ($j === false) {
                echo "fatal error: invalid phonetic letter at position " . ($i + 1) . " in $phonetic<br>";
                exit;
                return -1;
            }
            $result *= strlen($phoneticLetters) + strlen($metaPhoneticLetters);
            if ($hash) {
                $result = $result & 0x7fffffff;
            }
            $result += $j;
        }
        return dechex($result);
    }

    public function expand($phonetic)
    {
        $altStart = strpos($phonetic, "(");
        if ($altStart === false) {
            return $this->normalizeLanguageAttributes($phonetic, false);
        }
        $prefix = substr($phonetic, 0, $altStart);
        $altStart++; // get past the (
        $altEnd = strpos($phonetic, ")", $altStart);
        $altString = substr($phonetic, $altStart, $altEnd - $altStart);
        $altEnd++; // get past the )
        $suffix = substr($phonetic, $altEnd);
        $altArray = explode("|", $altString);
        $result = "";
        for ($i = 0; $i < count($altArray); $i++) {
            $alt = $altArray[$i];
            $alternate = $this->expand("$prefix$alt$suffix");
            if ($alternate != "" && $alternate != "[0]") {
                if ($result != "") {
                    $result .= "|";
                }
                $result .= $alternate;
            }
        }
        return $result;
    }

    public function phoneticNumbersWithLeadingSpace($phonetic)
    {
        $altStart = strpos($phonetic, "(");
        if ($altStart === false) {
            return " " . $this->phoneticNumber($phonetic);
        }
        $prefix = substr($phonetic, 0, $altStart);
        $altStart++; // get past the (
        $altEnd = strpos($phonetic, ")", $altStart);
        $altString = substr($phonetic, $altStart, $altEnd - $altStart);
        $altEnd++; // get past the )
        $suffix = substr($phonetic, $altEnd);
        $altArray = explode("|", $altString);
        $result = "";
        for ($i = 0; $i < count($altArray); $i++) {
            $alt = $altArray[$i];
            $result .= $this->phoneticNumbersWithLeadingSpace("$prefix$alt$suffix");
        }
        return $result;
    }

    public function phoneticNumbers($phonetic)
    {
        $phoneticArray = explode("-", $phonetic); // for names with spaces in them
        $result = "";
        for ($i = 0; $i < count($phoneticArray); $i++) {
            if ($i != 0) {
                $result .= " ";
            }
            $result .= substr($this->phoneticNumbersWithLeadingSpace($phoneticArray[$i]), 1);
        }
        return $result;
    }

    public function isPhoneticVowel($c)
    {
        return (strpos("Aa4oe5iI9uUE", $c) !== false);
    }

    public function isAOTypeVowel($c)
    {
        return (strpos("a4o59", $c) !== false);
    }

    public function isEITypeVowel($c)
    {
        return (strpos("eiIy", $c) !== false);
    }

    public function isSZTypeConsonant($c)
    {
        return (strpos("sSzZ", $c) !== false);
    }

    public function removeDuplicateAlternates($phonetic)
    {

        $altString = $phonetic;
        $altArray = explode("|", $altString);

        $result = "|";
        $altcount = 0;
        for ($i = 0; $i < count($altArray); $i++) {
            $alt = $altArray[$i];
            if (strpos($result, "|$alt|") === false) {
                $result .= "$alt|";
                $altcount++;
            }
        }

        $result = substr($result, 1, strlen($result) - 2); // remove leading and trailing |
        return $result;
    }

    public function normalizeLanguageAttributes($text, $strip)
    {
        // this is applied to a single alternative at a time -- not to a parenthisized list
        // it removes all embedded bracketed attributes, logically-ands them together, and places them at the end.

        // however if strip is true, this can indeed remove embedded bracketed attributes from a parenthesized list

        $uninitialized = -1; // all 1's
        $attrib = $uninitialized;
        while (($bracketStart = strpos($text, "[")) !== false) {
            $bracketEnd = strpos($text, "]", $bracketStart);
            if ($bracketEnd === false) {
                echo "fatal error: no closing square bracket: text=($text) strip=($strip)<br>";
                exit;
            }
            $attrib &= substr($text, $bracketStart + 1, $bracketEnd - ($bracketStart + 1));
            $text = substr($text, 0, $bracketStart) . substr($text, $bracketEnd + 1);
        }
        if ($attrib == $uninitialized || $strip) {
            return $text;
        } else if ($attrib == 0) {
            return "[0]"; // means that the attributes were incompatible and there is no alternative here
        } else {
            return $text . "[" . $attrib . "]";
        }
    }

    public function applyRuleIfCompatible($phonetic, $target, $languageArg)
    {

        // tests for compatible language rules
        // to do so, apply the rule, expand the results, and detect alternatives with incompatible attributes
        // then drop each alternative that has incompatible attributes and keep those that are compatible
        // if there are no compatible alternatives left, return false
        // otherwise return the compatible alternatives

        // apply the rule

        $candidate = $phonetic . $target;
        if (strpos($candidate, "[") === false) { // no attributes so we need test no further
            return $candidate;
        }

        // expand the result, converting incompatible attributes to [0]

        $candidate = $this->expand($candidate);
        $candidateArray = explode("|", $candidate);

        // drop each alternative that has incompatible attributes

        $candidate = "";
        $found = false;

        for ($i = 0; $i < count($candidateArray); $i++) {
            $thisCandidate = $candidateArray[$i];
            if ($languageArg != "1") {
                $thisCandidate = $this->normalizeLanguageAttributes($thisCandidate . "[$languageArg]", false);
            }
            if ($thisCandidate != "[0]") {
                //      if ($candidate != "[0]") {
                $found = true;
                if ($candidate != "") {
                    $candidate .= "|";
                }
                $candidate .= $thisCandidate;
            }
        }

        // return false if no compatible alternatives remain

        if (!$found) {
            return false;
        }

        // return the result of applying the rule

        if (strpos($candidate, "|") !== false) {
            $candidate = "($candidate)";
        }
        return $candidate;
    }
}


include_once("Language.php");