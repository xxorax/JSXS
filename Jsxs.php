<?php
/**
 * JSXS
 *
 * Copyright (c) 2009 Martin PANEL
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @copyright   Copyright (c) 2009 Martin PANEL (http://www.xorax.info)
 * @license     http://opensource.org/licenses/mit-license.php  MIT License
 * @version     0.7.1
 */

//*
error_reporting(E_ALL);
ini_set('pcre.backtrack_limit',10000000);
ini_set('pcre.recursion_limit',10000000);
set_time_limit(60);
//*/

/**
 * JSXS : javascript compressor
 * require php 5.1
 *
 */
class Jsxs {

  protected $_data = array();
  protected $_blocks = array();
  protected $_script;
  
  private $_compatibility;
  private $_reduce;
  private $_shrink;
  private $_concatString;
  private $_regexDirectory;
  
  protected $_regex = array(
  );
  
  protected $_regexCache = array(
  );
  
  /**
   * constructor
   *
   * @param bool $compatibility
   * @param bool $reduce
   * @param bool $shrink
   * @param bool $concatString
   */
  function __construct(
    $compatibility = true,
    $reduce = true,
    $shrink = true,
    $concatString = true
  ){
    $this->setCompatibility($compatibility);
    $this->setReduce($reduce);
    $this->setShrink($shrink);
    $this->setConcatString($concatString);
  }
  
  /**
   * set concatString option
   *
   * @param boolean $compatibility
   * @return Jsxs
   */
  public function setConcatString ($cs)
  {
    $this->_concatString = (boolean) $cs;
    return $this;
  }
  
  /**
   * get concatString option
   * if true, all string follow will be concat with the first
   * ( "string" + 'string' => 'string' )
   *
   * @return boolean
   */
  public function getConcatString ()
  {
    return $this->_concatString;
  }
  
  /**
   * set reduce option
   *
   * @param boolean $reduce
   * @return Jsxs
   */
  public function setReduce ($reduce)
  {
    $this->_reduce = (boolean) $reduce;
    return $this;
  }
  
  /**
   * get reduce option
   * if true, space and newline will be removed
   *
   * @return boolean
   */
  public function getReduce ()
  {
    return $this->_reduce;
  }
  
  /**
   * set shrink option
   *
   * @param boolean $shrink
   * @return Jsxs
   */
  public function setShrink ($shrink)
  {
    $this->_shrink = (boolean) $shrink;
    return $this;
  }
  
  /**
   * get shrink option
   * if true, all non global variables and non global function will be reduce to
   * minimum.
   *
   * @return boolean
   */
  public function getShrink ()
  {
    return $this->_shrink;
  }
  
  /**
   * set compatibility option
   *
   * @param boolean $compatibility
   * @return Jsxs
   */
  public function setCompatibility ($compatibility)
  {
    $this->_compatibility = (boolean) $compatibility;
    return $this;
  }
  
  /**
   * get compatibility option
   * if true, add ; after end bracket of functions if necessary. If reduce
   * option active, that is highly recommanded.
   *
   * @return boolean
   */
  public function getCompatibility ()
  {
    return $this->_compatibility;
  }
  
  /**
   * set PregFile directory
   *
   * $param string $dir
   * @return Jsxs
   */
  public function setRegexDirectory ($dir)
  {
    $dir = rtrim($dir, '/');
    if (!is_dir($dir)) {
      throw new Exception("'$dir' is not a directory");
    }
    
    $this->_regexDirectory = (string) $dir;
    return $this;
  }
  
  /**
   * retrieve PregFile directory (default is ./preg)
   *
   * @return Jsxs
   */
  public function getRegexDirectory ()
  {
    if (!$this->_regexDirectory) {
      $this->setRegexDirectory(dirname(__FILE__).'/preg');
    }
    return $this->_regexDirectory;
  }
  
  /**
   * get content of named PregFile, with replaced vars
   *
   * @param string $name basic name of the pregfile
   * @param array $vars variables send to PregFile
   * @param bool $cacheVars cache preg with variable value
   * @return string
   */
  protected function _getRegex ($name, array $vars = array(), $cacheVars = false)
  {
    if (!isset($this->_regex[$name])) {
      //$file = dirname(__FILE__).'/preg/'.$name.'.preg';
      $file = $this->getRegexDirectory().'/'.$name.'.preg';
      if(!file_exists($file)) {
        throw new Exception("preg file '$file' doesn't exists. regexDirectory incorrect ?");
      }
      
      $this->_regex[$name] = PregFile::getFile($file);
    }
    
    if ($cacheVars) {
      $id = $name.serialize($vars);
      if (!isset($this->_regexCache[$id])) {
        $this->_regexCache[$id] = $this->_regex[$name]->setVars($vars)->getRegex();
      }
      $s = $this->_regexCache[$id];
    } else {
      $s = $this->_regex[$name]->setVars($vars);
    }
    
    return $s;
  }
  
  /**
   * run on script
   *
   * @param string $script
   * @return string script
   */
  public function exec($script)
  {
    $this->_script = & $script;
    //* string + regexp + comment block + operator accept var in right + operator between var
    //scalar
    $src = $this->_getRegex('scalar');

    $this->_data[]  = '###begin###';
    $this->_script = preg_replace_callback($src, array(&$this, '_store'), $script);

    if($this->getShrink()){
      $this->_shrinker();
    }
    if($this->getReduce()) {
      $this->_reducer();
      //$this->stored_reduce = $this->script;
    }

    $this->_unstore();
    
    $this->_data = null;
    unset($this->_script);
    return $script;

    //$this->script = $script;
  }
  
  /**
   * auto format operator|var|keyword identifier regex
   * $n param define result if :
   * = true : capture all identifier number of operator
   * = int : only identify the operator stored at index $n
   * = null : not capture and identify all operator (same true but not capture value)
   *
   * @param string $type char of operator identifier
   * @param boolean|string|null $n
   * @return string regex of operator
   */
  private function _IdRegex($type = 'sfobcrv', $n = null)
  {
    $max = strlen(PHP_INT_MAX)-1; // defined maximum lenght of an array, is the maximum number of var to be can stored.
    /*
    $n = ($n === true)
      ? '(\d{'.$max.'})'
      :( isset($n)
        ? str_pad($n, $max, '0', STR_PAD_LEFT)
        : '\d{'.$max.'}'
      );
    /*/
    if(isset($n)){
      if($n === true){
        $n = '(\d{'.$max.'})';
      } else {
        $n = str_pad($n, $max, '0', STR_PAD_LEFT);
      }
    } else {
      $n = '\d{'.$max.'}';
    }
    /*/
    $n = isset($n)
      ?($n === true // store data id in result of return regexp
        ? '(\d{'.$max.'})'
        : str_pad($n, $max, '0', STR_PAD_LEFT)  // select one data id
      ) : '\d{'.$max.'}';
    /**/
    
    if (strlen($type) != 1) {
      $type = '['.$type.']';
    }
    
    return '@'.$type.$n.'@';
  }
  
  /**
   * method called by exec to store javascript operators, vars and keywords
   *
   * @param array $scr result of regex
   * @return string identifier operator
   */
  private function _store($scr)
  {
    if(!empty($scr[1])){ //string
      $i = 's';
    } elseif(!empty($scr[6])) { //operator at left
    //} elseif(!empty($scr['o'])) { //operator at left
      $i = 'o';
    } elseif(!empty($scr[7])) { //operator between var
    //} elseif(!empty($scr['b'])) { //operator between var
      $i = 'b';
    } elseif(!empty($scr[8])) { //function
    //} elseif(!empty($scr['f'])) { //function
      $i = 'f';
    } elseif(!empty($scr[9])) { //var
    //} elseif(!empty($scr['v'])) { //var
      $i = 'v';
    } else { //comment + regexp
      if( !empty($scr[3]) ){ //comment
        if($this->getReduce()) return '';
        $i = 'c';
      } else { //regexp \5
        $i = 'r';
      }
    }
    //$replacement = '@'.$i.'@';
    $replacement = $this->_IdRegex($i,count($this->_data));
    $this->_data[] = $scr[0];
    return $replacement;
  }
  
  /**
   * unstore all identifer operator of the script
   *
   * @param string $script
   * @return void
   */
  private function _unstore ($script = null)
  {
    if (null === $script) {
      $script = &$this->_script;
    }
    
    $reg = $this->_IdRegex('sfobcrv', true);
    $reg = str_replace('[sfobcrv]','([sfobcrv])',$reg);
    $script = preg_replace_callback('/'.$reg.'/', array(&$this, '_unstore_call'), $script);
    return $script;

  }
  
  /**
   * called by unstore
   *
   * @param array $match
   * @return string;
   */
  private function _unstore_call ($match)
  {
    //if($this->reduce && $match[1] == 'c') return ''; // allready skiped in store()
    $data_type = &$match[1];
    $data_id = &$match[2];

    $data_id = ltrim($data_id,'0');
    $val = $this->_data[$data_id];

    if ($this->getReduce() && $data_type == 'o') {
      $d = 'uxbtnvfr'.$data_type[0].'\\\\'; // used escaped char : http://developer.mozilla.org/en/docs/Sandbox:JS:String
      // TODO : remove \u and \x because is not \uHHHH or \xHH
      $val = preg_replace('#((?:\\\\\\\\)*)\\\\?([^'.$d.'])#','$1$2', $val); // remove slash before not char
      $val = preg_replace('#(</?)script#','$1scr\\ipt',$val);
    }

    if (isset($this->_data[$data_id])) {
      unset($this->_data[$data_id]);
    } else $val = '@@@';
    return $val;
  }
  
  /**
   * used to call removeSpace and reducerOptimal (for simply reduce script)
   *
   * @return void;
   */
  private function _reducer ()
  {
    // (stored string or regexp) | +?+ ++? |
    //whitespace
    $reg = $this->_getRegex('whitespace', array(
      'fbov' => $this->_IdRegex('fbov'),
      'fbo' => $this->_IdRegex('fbo')
    ), true);
    $this->_script = preg_replace_callback($reg, array(&$this, '_spaceRemover'), $this->_script);
    
    $reg = preg_replace('#\s+#','','
  for\s*\{;;\}
|
  (;\})
|
  (;+)(?=;)
');
    
    if ($this->getConcatString()) {
      $reg .= '|'.$this->_IdRegex('s',true).'((?:\+'.$this->_IdRegex('s').')+)';
    }
    
    $this->_script = preg_replace_callback('#'.$reg.'#', array(&$this, '_reducerOptimal'), $this->_script);
  }
  
  /**
   * called by _reducer, remove whiteSpace
   *
   * @param array $args
   * @return string replacement
   */
  private function _spaceRemover($args)
  {
    if (isset($args[1]) && $args[1] != '') { // 0.func
      return $args[1].' .'.$args[2];
    } elseif (isset($args[4])){
      return ' ';
    } else return '';
  }
  
  /**
   * called by _reducer, remove whiteSpace
   *
   * @param array $args
   * @return string replacement
   */
  private function _reducerOptimal ($m)
  {
    if (!empty($m[3])) { // multi string to concat
      $i = ltrim($m[3],'0');
      $str = &$this->_data[$i];
      $_sep = substr($str,0,1);
      
      preg_match_all('#'.$this->_IdRegex('s',true).'#',$m[4],$m);
      
      foreach($m[1] as $d) {
        $d = ltrim($d, '0');
        $data = substr($this->_data[$d],1,-1);
        unset($this->_data[$d]);
        $str = substr($str, 0, -1).$data.$_sep;
      }
      
      return $this->_IdRegex('s',$i);
    }
    // TODO : explain why return ';' and not ''
    if (!empty($m[2])) {
      return ';'; // ;+
    }
    
    if (!empty($m[1])) {
      return '}'; // ;}
    }
    
    return $m[0]; // {;;}
  }
  
  /**
   * called by exec. it's the strat point of shrinker functions
   * match all block of {..} and send to encode() for stored and optimized
   * finaly decode it after all matched and replaced
   *
   * @return void
   */
  private function _shrinker()
  {
    $block = $this->_getRegex('block', array(
      'f' => $this->_IdRegex('f'),
      'c' => $this->_IdRegex('c')
    ));
    
    if ($this->getCompatibility()) {
      $block = $block->append($this->_getRegex('blockCompatibility', array(
        'c' => $this->_IdRegex('c'),
        'b' => $this->_IdRegex('b'),
      )));
      $this->_script .= ';'; //added ";" for skip "| $" in \3 and save time
    }
    
    $this->_blocks = array();
    //encode block
    do {
      $this->_script = preg_replace_callback($block, array(&$this,'_encode'), $this->_script, -1, $count);
    } while ($count);
    
    
    //decode last block
    $this->_script = $this->_decode($this->_script);
    
    $this->_blocks = null;
    
    // skip le last semicolumn added
    if($this->getCompatibility()) {
      $this->_script = substr($this->_script, 0, -1);
    }
  }
  
  /**
   * return char of int on base 62 (a-z, A-Z, 0-9) for generate name of var
   * var can be named : #[a-z][a-z0-9]+#i ( miss [_] )
   * change it as you want.
   *
   * @todo rewrite to human readable code
   *
   * @param int $c
   * @param boolean $first_be_str
   */
  protected function _uniqVarId($c, $first_be_str = false) {
    return ($c < 62 ? '' : $this->_uniqVarId(intval($c / 62), $first_be_str) )
      . ( $first_be_str && $c < 62
        ? ( ($c = $c % 52) > 25 ? chr($c + 39) : chr($c + 97) )
        : ( ($c = $c % 62) < 10 ? chr($c + 48) :
            ( $c < 36 ? chr($c + 97) : chr($c + 65) )
          )
      );
  }
  
  /**
   * called by shrinker(), encode block of {...} and, if it's function, encode
   * declaration and match all var declared in, compress name and replace to
   * short varname.
   *
   * @param array $args result of regex
   * @return string replacement
   */
  private function _encode ($args) {
    $do = $args[1];
    $func = $args[2]; // declaration function
    $block = $do.$func.$args[4]; // do or declaration function + content block
    $end = (isset($args[5]) && !$do) ? $args[5] : null; // after block if compatibility option activate
    $args = $args[3]; // function argument without ()
    
    if ($func) {
      $ids = array();
      $block = $this->_decode($block);
      
      $reg = $this->_getRegex('functionArguments', array(
        'c' => $this->_IdRegex('c')
      ), true);
      
      if (trim($args) != '' && preg_match_all($reg, $args, $m)) {
        foreach ($m[1] as $arg) { // store arguments name
          $ids[] = $arg;
        }
      }
      
      // identify variables with [\w$]{2,} instead of [\w$]+ for save PCRE memory, but not time...
      $regVarsDef = $this->_getRegex('variablesDefined', array(
        'v' => $this->_IdRegex('v')
      ));
      
      $regVars = $this->_getRegex('variablesValues');
      
      if (preg_match_all($regVarsDef, $block, $varsDef)) {
        foreach ($varsDef[1] as $varsGrp) {
          //echo $varsGrp."\n\n###\n\n";
          if (preg_match_all($regVars, $varsGrp, $vars)) {
            foreach ($vars[1] as $var) {
              if (strlen($var) > 1 && !in_array($var, $ids)) {
                $ids[] = $var;
              }
            }
          }
        }
        unset($varsDef, $varsGrp, $vars);
      }
      
      //shrink only non-global function names
      $regFuncVars = '#'.$this->_IdRegex('f').'\s+([\w$]{2,})\s*\(#';
      
      if (preg_match_all($regFuncVars, substr($block,9), $funcVars)) { // substr $block for skip first "function "
        foreach ($funcVars[1] as $funcVar) {
          if (!in_array($funcVar, $ids)) {
            $ids[] = $funcVar;
          }
        }
        unset($funcVars, $funcVar);
      }
      
      
      $count = 0;
      $shortId = '';
      
      $shortIdTag = 'SHORTID';
      $regShortVariableId = $this->_getRegex('shortVariableId', array(
        'bovf' => $this->_IdRegex('bovf'),
        'c' => $this->_IdRegex('c'),
        'shortIdTag' => $shortIdTag
      ), true);
      
      $variableIdTag = 'VARIABLEID';
      $regVariableId = $this->_getRegex('variableId', array(
        'bovf' => $this->_IdRegex('bovf'),
        'c' => $this->_IdRegex('c'),
        'variableId' => $variableIdTag
      ), true);
      
      // for all varname in block
      foreach ($ids as $id) {
        if (strlen($id) > 1) {
          // search an appropriated short varname : match an unused varname in sub-block
          do {
            $shortId = $this->_uniqVarId($count, true);
            $count++;
            $r = str_replace($shortIdTag, $shortId, $regShortVariableId);
          } while (preg_match($r, $block));
          
          $id = preg_quote($id); // escape non-alphanum character in varname, ex : _
          $r = str_replace($variableIdTag, $id, $regVariableId);
          
          // replace original varname by short varname (and prepend blank and comment)
          // substr $block for skip first "function " and so variable detection
          $block = substr($block,0,9).preg_replace($r, '$2'.$shortId, substr($block,9));
        }
      }
      
    }
    
    if($this->getCompatibility() && $end){
      $block .= ';';
    }

    $replacement = '~'.count($this->_blocks).'~';
    $this->_blocks[] = & $block;
    return $replacement;
  }
  
  /**
   * called by shrinker(), decode block of {..}
   *
   * @param array $script result of regex
   * @return string replacement
   */
  private function _decode ($script)
  {
    while (preg_match_all('/~(\d+)~/', $script, $m, PREG_SET_ORDER)) {
      foreach ($m as $v) {
        $script = str_replace('~'.$v[1].'~', $this->_blocks[$v[1]], $script);
      }
    }
    
    return $script;
  }

}
