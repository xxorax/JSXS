<?php
/**
 * PregFile
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
 * @version     1.0
 */

/**
 * example of PregFile, myRegex.reg :
 * -----------------------------------------------------------------------------
 * PregFile : 1.0
 * options : si
 * $country : France
 *
 * hello\s*I'm\s*( you | me | somebody )\s*\.      @@@ this is a comment @@@
 * my\s*name\s*is\s*@@@theName@@@\s*and I live in @@@country@@@
 * @@@ country will be override by 'france' if not set@@@
 * @@@ and theName will be override by '' (default) if not set@@@
 * -----------------------------------------------------------------------------
 * end of myRegex.reg
 *
 * <?php
 * $pf = new PregFile();
 * $pf->loadFile('myRegex.reg');
 * echo $pf;
 * // #hello\s*I'm\s*(you|me|somebody)\s*\.my\s*name\s*is\s*\s*andIliveinFrance#si
 *
 * $pf->setVar('myname', 'martin');
 * echo $pf;
 * // #hello\s*I'm\s*(you|me|somebody)\s*\.my\s*name\s*is\s*martin\s*andIliveinFrance#si
 * ?>
 */
class PregFile {
  
  /**
   * identifier of header name which value defined version
   *
   * @var string
   */
  const HEADER_FORMAT = __CLASS__;
  
  /**
   * actual version
   *
   * @var string
   */
  const VERSION = '1.0';
  
  /**
   * sperator between headers names and headers values
   *
   * @var string
   */
  const HEADER_SEPARATOR = ':';
  
  /**
   * identifier of header name which value defined perl preg options
   * (see {@link http://www.php.net/manual/regexp.reference.php preg options}
   *
   * @var string
   */
  const HEADER_OPTIONS = 'options';
  
  /**
   * prefix identifier of header variables
   *
   * @var string
   */
  const HEADER_VAR_PREFIX = '$';
  
  /**
   * identifier of header name which value defined var delimiter
   *
   * @var string
   */
  const HEADER_VAR_DELIMITER = 'var_delimiter';
  
  /**
   * identifier of header name which value defined var delimiter count
   *
   * @var string
   */
  const HEADER_VAR_DELIMITER_COUNT = 'var_delimiter_count';
  
  /**
   * array of name value headers
   *
   * @var array
   */
  private $_headers = array();
  
  /**
   * brut regex
   *
   * @var string
   */
  private $_regex = '';
  
  /**
   * content of regex (without whitespace)
   *
   * @var string
   */
  private $_content = '';
  
  /**
   * array of name value of regex variables
   *
   * @var array
   */
  private $_vars = array();
  
  /**
   * delimiter of final regex (1 char)
   *
   * @var string
   */
  protected $_regexDelimiter = '&';
  
  /**
   * Default delimiter identifier of variable.
   * This can be override in header.
   *
   * @var string
   */
  protected $_defaultVarDelimiter = '@';
  
  /**
   * Default number of variable delimiter defined separator
   * if var delimiter is @ and this value is 3,
   * the complete delimiter will be @@@.
   * This can be override in header.
   *
   * @var int
   */
  protected $_defaultVarDelimiterCount = 3;
  
  /**
   * render value of undefined var
   *
   * @var string
   */
  protected $_valueUndefinedVars = '';
  
  /**
   * escape value for header stockage
   *
   * @param string $value
   * @return string
   */
  protected function _escape ($value)
  {
    return addcslashes($value, "\0..\37!@\@\177..\377");
  }
  
  /**
   * unescape value
   *
   * @param string $value
   * @return string
   */
  protected function _unescape ($value)
  {
    return stripcslashes($value);
  }
  
  /**
   * is an authorised header (or var) name
   *
   * @param string @name
   * @return bool
   */
  protected function _isName ($name)
  {
    return false == preg_match('#[^-\w]#',$name);
  }
  
  /**
   * get all header values
   *
   * @return array
   */
  public function getHeaders ()
  {
    return $this->_headers;
  }
  
  /**
   * get header value
   *
   * @param string $name
   * @return string
   */
  public function getHeader ($name)
  {
    if($this->_headers && isset($this->_headers[$name])) {
      return $this->_headers[$name];
    }
    return null;
  }
  
  public function setHeader ($name, $value)
  {
    if (!$this->_isName($name)) {
      throw new Exception('the header name "'.$name.'" is not authorised');
    }
    
    $this->_headers[$name] = $value;
    return $this;
  }
  
  /**
   * get all regex options
   *
   * @return string
   */
  public function getOptions ()
  {
    return $this->getHeader(self::HEADER_OPTIONS);
  }
  
  public function setOptions ($options)
  {
    if (preg_match('#[^imsxeADSUXJu]#', $options)) {
      throw new Exception('there are not regex option in first parameter');
    }
    
    $this->setHeader(self::HEADER_OPTIONS, $options);
    return $this;
  }
  /**
   * get pre-defined variable name
   *
   * @param string $name
   * @return string|null
   */
  public function getVar ($name)
  {
    if(isset($this->_vars[$name])) {
      return $this->_vars[$name];
    }
    return null;
  }
  
  /**
   * get all pre-definied variables
   *
   * @return array
   */
  public function getVars ()
  {
    return $this->_vars;
  }
  
  /**
   * return true if variable $name exist
   *
   * @param string $name
   * @return boolean
   */
  public function hasVar ($name)
  {
    return isset($this->_vars[$name]);
  }
  
  /**
   * set name and value of new or old variable
   *
   * @param string $name
   * @param string $value
   * @return PregFile
   */
  public function setVar ($name, $value = null)
  {
    if (!$this->_isName($name)) {
      throw new Exception('the variable name "'.$name.'" is not correct');
    }
    $this->_vars[$name] = $value;
    return $this;
  }
  
  /**
   * set multiple variables
   *
   * @param array $vars
   * @param bool $reset if set, $vars replace all existing.
   * @return PregFile
   */
  public function setVars (Array $vars, $reset = true)
  {
    if ($reset) {
      $this->resetVars();
    }
    foreach($vars as $name => $value) {
      $this->setVar($name, $value);
    }
    return $this;
  }
  
  /**
   * reset all vars
   *
   * @return PregFile
   */
  public function resetVars ()
  {
    $this->_vars = array();
    return $this;
  }
  
  /**
   * get PregFile variable delimiter
   * return defaultVarDelimiter if is not set in header of file
   *
   * @return string
   */
  public function getVarDelimiter ()
  {
    if(isset($this->headers[self::HEADER_VAR_DELIMITER])){
      return $this->headers[self::HEADER_VAR_DELIMITER];
    }
    
    return $this->_defaultVarDelimiter;
  }
  
  /**
   * set default variable delimiter (can be override by preg file header)
   *
   * @param string $char
   * @return PregFile
   */
  public function setDefaultVarDelimiter ($char)
  {
    $char = trim($char);
    if(empty($char) || 1 != strlen($char)) {
      throw new Exception('varDelimiter must not be empty or spaces and lenght can not be greater than 1 char');
    }
    
    $this->_defaultVarDelimiter = substr($char, 0, 1);
    return $this;
  }
  
  /**
   * get the number of delimiter
   * return defaultVarDelimiterCount if is not set in header of file
   *
   * @return int
   */
  public function getVarDelimiterCount ()
  {
    if(isset($this->headers[self::HEADER_VAR_DELIMITER_COUNT])){
      return $this->headers[self::HEADER_VAR_DELIMITER_COUNT];
    }

    return $this->_defaultVarDelimiterCount;
  }
  
  /**
   * set the number of delimiter
   *
   * @param int $int
   * @return PregFile
   */
  public function setDefaultVarDelimiterCount ($int)
  {
    $this->_defaultVarDelimiterCount = (int)$int;
    return $this;
  }
  
  /**
   * get value of undefined variables
   *
   * @param string|null $value
   * @return PregFile
   */
  public function getValueUndefinedVars ()
  {
    return $this->_valueUndefinedVars;
  }
  
  /**
   * set value of undefined variables
   *
   * @param string|null $value
   * @return PregFile
   */
  public function setValueUndefinedVars ($value = null)
  {
    $this->_valueUndefinedVars = $value;
    return $this;
  }
  
  /**
   * get regex delimiter
   *
   * @return string
   */
  public function getRegexDelimiter ()
  {
    return $this->_regexDelimiter;
  }
  
  /**
   * set regex delimiter
   *
   * @param string $char a ascii charactere
   * return PregFile
   */
  public function setRegexDelimiter ($char)
  {
    $this->_regexDelimiter = substr($char, 0, 1);
    return $this;
  }
  
  /**
   * return var delimiter ready to use in a regex
   * TODO : $delim char ??
   *
   * @param string $delim_char a regex delimiter character
   * @return string
   */
  public function getRegexVarDelimiter ($delim_char = '#')
  {
    $delimiter = str_repeat($this->getVarDelimiter(), $this->getVarDelimiterCount());
    $delimiter = preg_quote($delimiter, $this->getRegexDelimiter());
    return $delimiter;
  }
  
  /**
   * remove blank of content
   *
   * @return void
   */
  private function _removeBlank ($str)
  {
    return preg_replace('#\s+#', '', $str);
  }
  
  /**
   * called by _removeBlankRegex
   *
   * @param array $result
   * @return string
   */
  private function _cbRemoveBlankRegex (Array $result)
  {
    return $this->_removeBlank($result[0]);
  }
  
  /**
   * remove comment and blank in regex
   *
   * @param string $str
   * @return string
   */
  private function _removeBlankRegex ($str)
  {
    $varDelimiter = $this->getRegexVarDelimiter();
    $cut = preg_split('#'.$varDelimiter.'#', $str);
    $s = $str. print_r($cut, true);;
    $str = '';
    $i = 0;
    //while (false !== $part = array_shift($cut)) {
    foreach ($cut as $part) {
      if ($i) {
        //if (false === strpos($part, ' ')) {
        if (!preg_match('#\s#', $part)) {
          $str .= $varDelimiter.$part.$varDelimiter;
        }
        $i = 0;
      } else {
        $str .= $this->_removeBlank($part);
        $i = 1;
      }
    }
    if($str=='') die($s);
    //echo $str;
    return $str;
    
    // remove comment
    echo $str." =>\n";
    echo $str = preg_replace('#'.$varDelimiter.'(?:(?!'.$varDelimiter.').)*\s(?:(?!'.$varDelimiter.').)*'.$varDelimiter.'#s','',$str);
    echo "\n--\n";
    //$str = trim($str); // for skip D option
    return preg_replace_callback('#(?:^|'.$varDelimiter.').*?(?:'.$varDelimiter.'|$)#Ds', array($this, '_cbRemoveBlankRegex'), $str);
  }
  
  /**
   * parse header/content, verify file format and remove whitespace in regex
   *
   * @return void
   */
  protected function _parse ($data)
  {
    $part = preg_split('#\r?\n\r?\n#', $data, 2);
    if (count($part) != 2) {
      throw new Exception('no header');
    }
    $head = $part[0];
    $this->_content = $part[1];
    unset($part);
    
    $reg = '#^(?:(?P<name>[-\w]+)|' . preg_quote(self::HEADER_VAR_PREFIX,'#') . '(?P<var>[-\w]+))\s?' . preg_quote(self::HEADER_SEPARATOR, '#') . '(?P<value>.*)$#m';
    if (!preg_match_all($reg, $head, $headers, PREG_SET_ORDER)) {
      throw new Exception('header is empty');
    }

    $this->_headers = array();
    foreach ($headers as $header) {
      $header['value'] = trim($header['value']);
      
      if (!empty($header['name'])) {
        $this->setHeader($header['name'], $this->_unescape($header['value']));
      }
      
      if (!empty($header['var'])) {
        $this->setVar($header['var'], $this->_unescape($header['value']));
      }
    }
    
    if (!isset($this->_headers[self::HEADER_FORMAT])){
      print_r($this->_headers);
      throw new Exception('file format version is not set');
    }
    
    if (!version_compare(self::VERSION, $this->_headers[self::HEADER_FORMAT], '=')) {
      throw new Exception('PrefFile format version not compatible ('.self::VERSION.' > '.$this->_headers[self::HEADER_FORMAT]);
    }
    
    $this->_parseContent();
  }
  
  /**
   * parse the body : remove whitespace and escape regex delimiter
   *
   * @return void
   */
  protected function _parseContent ()
  {
    //remove whitespace out of var name
    $this->_regex = $this->_escapeDelimiter($this->_removeBlankRegex($this->_content));
  }
  
  /**
   * escape regex-string based uppon pregDelimiter
   *
   * @param string $str
   * @return string
   */
  protected function _escapeDelimiter ($str)
  {
    return preg_replace('#'.preg_quote($this->_regexDelimiter,'#').'#', '\\'.$this->_regexDelimiter, $str);
  }
  
  /**
   * replace defined variables into regex
   *
   * @return string
   */
  protected function _replaceVars ()
  {
    //if(!count($this->_vars)) return false;
    $delimiter = $this->getRegexVarDelimiter();
    $r = preg_replace_callback('#'.$delimiter.'(.+)'.$delimiter.'#U', array($this, '_replaceVarsCallback'), $this->_regex);
    return $r;
  }
  
  /**
   * called by _replaceVars
   *
   * @param string $var
   * @return string
   */
  private function _replaceVarsCallback ($var)
  {
    if ($this->hasVar($var[1])) {
      $value = $this->getVar($var[1]);
    } elseif (null === $this->_valueUndefinedVars) {
      // return content
      $value = $var[0];
    } else {
      $value = $this->_valueUndefinedVars;
    }
    
    return $this->_escapeDelimiter($value);
  }
  
  /**
   * Get regex compressed with variable names.
   *
   * @return string
   */
  public function getBrutRegex ()
  {
    return $this->_regexDelimiter.$this->getBrutSimpleRegex().$this->_regexDelimiter.$this->getOptions();
  }
  
  /**
   * Get regex compressed with variable names.
   *
   * @return string
   */
  public function getBrutSimpleRegex ()
  {
    return $this->_regex;
  }
  
  /**
   * Get only regex without delimiter and option. Variable are replaced.
   *
   * @return string
   */
  public function getSimpleRegex ()
  {
    return $this->_replaceVars();
  }
  
  /**
   * Get final regex as string.
   *
   * @return string
   */
  public function getRegex ()
  {
    return $this->_regexDelimiter.$this->getSimpleRegex().$this->_regexDelimiter.$this->getOptions();
  }
  
  /**
   * Return content of PregFile (without header).
   * This is regex with whitespace and var.
   *
   * @return string
   */
  public function getContent ()
  {
    return $this->_content;
  }
  
  /**
   * Set the content of PregFile.
   * This usefull for write human readable regex in PregFile.
   *
   * @param string|PrefFile $regex
   * @return PreFile
   */
  public function setContent ($regex)
  {
    if ($regex instanceof self) {
      $this->_content = $regex->getContent();
    } else {
      $this->_content = $regex;
    }
    
    $this->_parseContent();
    return $this;
  }
  
  /**
   * Append another regex.
   *
   * @param string|PregFile $regex
   * @return PregFile
   */
  public function append ($regex)
  {
    if ($regex instanceof self) {
      $this->_regex .= $regex->getSimpleRegex();
    } else {
      $this->_regex .= $this->_removeBlankRegex($regex);
    }
    return $this;
  }
  
  /**
   * Prepend another regex.
   * If regex is string, it don't must have delimiter.
   *
   * @param string|PregFile $regex
   * @return PregFile
   */
  public function prepend ($regex)
  {
    if ($regex instanceof self) {
      $this->_regex = $regex->getSimpleRegex() . $this->_regex;
    } else {
      $this->_regex = $this->_removeBlankRegex($regex) . $this->_regex;
    }
    return $this;
  }
  
  /**
   * load file as PregFile
   *
   * @return boolean
   */
  public function loadFile ($file)
  {
    $data = @file_get_contents($file);
    if (false === $data) {
      throw new Exception("file $file cannot be read");
    }
    $this->_parse($data);
    return true;
  }
  
  /**
   * write a PregFile
   *
   * @param string $file path to file
   * @param boolean $cache if true, this write the compressed regex and not orignal content
   * @return int|boolean number of bytes written or false if error
   */
  public function write ($file, $cache = true)
  {
    $headers = array(
      self::HEADER_FORMAT => self::VERSION
    );
    
    $headers += $this->_headers;
    
    foreach ($this->_vars as $name => $value) {
      $headers[self::HEADER_VAR_PREFIX . $name] = $value;
    }
    
    $content = '';
    
    foreach ($headers as $name => $value) {
      $content .= $name . self::HEADER_SEPARATOR . $this->_escape($value) . "\n";
    }
    
    $content .= "\n";
    
    if ($cache) {
      $content .= $this->_regex;
    } else {
      $content .= $this->_content;
    }
    
    return file_put_contents($file, $content);
  }
  
  /**
   * factory method for load PregFile with or without pre-defined variables
   *
   * @param string $file
   * @param array $vars
   */
  static function getFile ($file, Array $vars = array())
  {
    $p = new self();
    $p->loadFile($file);
    $p->setVars($vars);
    return $p;
  }
  
  /**
   * get regex as string
   *
   * @return string
   */
  function __toString()
  {
    return $this->getRegex();
  }
}
