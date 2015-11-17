<?php

error_reporting(0);

/**
 * Base listener which does nothing
 */
class IdleListener implements Listener
{
  public function start_document() {}

  public function end_document() {}

  public function start_object() {}

  public function end_object() {}

  public function start_array() {}

  public function end_array() {}

  public function key($key) {}

  public function value($value) {}

  public function whitespace($whitespace) {}
}

interface Listener {
  public function start_document();
  public function end_document();

  public function start_object();
  public function end_object();

  public function start_array();
  public function end_array();

  // Key will always be a string
  public function key($key);

  // Note that value may be a string, integer, boolean, etc.
  public function value($value);

  public function whitespace($whitespace);
}

abstract class SubsetConsumer implements Listener
{
  private $keyValueStack;
  private $key;

  /**
   * @param mixed $data
   * @return boolean if data was consumed and can be discarded
   */
  abstract protected function consume($data);

  public function start_document()
  {
    $this->keyValueStack = array();
  }

  public function end_document()
  {
  }

  public function start_object()
  {
    $this->keyValueStack[] = is_null($this->key) ? array(array()) : array($this->key => array());
    $this->key = null;
  }

  public function end_object()
  {
    $keyValue = array_pop($this->keyValueStack);
    $obj = reset($keyValue);
    $this->key = key($keyValue);
    $hasBeenConsumed = $this->consume($obj);

    if (!empty($this->keyValueStack)) {
      $this->value($hasBeenConsumed ? '*consumed*' : $obj);
    }

  }

  public function start_array()
  {
    $this->start_object();
  }

  public function end_array()
  {
    $this->end_object();
  }

  public function key($key)
  {
    $this->key = $key;
  }

  public function value($value)
  {
    $keyValue = array_pop($this->keyValueStack);
    $objKey = key($keyValue);

    if ($this->key) {
      $keyValue[$objKey][$this->key] = $value;
    } else {
      $keyValue[$objKey][] = $value;
    }
    $this->keyValueStack[] = $keyValue;
  }

  public function whitespace($whitespace) {
    // noop
  }
}




class Parser {
  private $_state;
  const STATE_START_DOCUMENT     = 0;
  const STATE_DONE               = -1;
  const STATE_IN_ARRAY           = 1;
  const STATE_IN_OBJECT          = 2;
  const STATE_END_KEY            = 3;
  const STATE_AFTER_KEY          = 4;  
  const STATE_IN_STRING          = 5;
  const STATE_START_ESCAPE       = 6;
  const STATE_UNICODE            = 7;
  const STATE_IN_NUMBER          = 8;
  const STATE_IN_TRUE            = 9;
  const STATE_IN_FALSE           = 10;
  const STATE_IN_NULL            = 11;
  const STATE_AFTER_VALUE        = 12;
  const STATE_UNICODE_SURROGATE  = 13;

  const STACK_OBJECT             = 0;
  const STACK_ARRAY              = 1;
  const STACK_KEY                = 2;
  const STACK_STRING             = 3;
  private $_stack;

  private $_stream;

  /**
   * @var Listener
   */
  private $_listener;
  private $_emit_whitespace;
  private $_emit_file_position;

  private $_buffer;
  private $_buffer_size;
  private $_unicode_buffer;
  private $_unicode_high_surrogate;
  private $_unicode_escape_buffer;
  private $_line_ending;

  private $_line_number;
  private $_char_number;


  public function __construct($stream, $listener, $line_ending = "\n", $emit_whitespace = false, $buffer_size = 8192) {
    if (!is_resource($stream) || get_resource_type($stream) != 'stream') {
      throw new InvalidArgumentException("Argument is not a stream");
    }

    $this->_stream = $stream;
    $this->_listener = $listener;
    $this->_emit_whitespace = $emit_whitespace;
    $this->_emit_file_position = method_exists($listener, 'file_position');

    $this->_state = self::STATE_START_DOCUMENT;
    $this->_stack = array();

    $this->_buffer = '';
    $this->_buffer_size = $buffer_size;
    $this->_unicode_buffer = array();
    $this->_unicode_escape_buffer = '';
    $this->_unicode_high_surrogate = -1;
    $this->_line_ending = $line_ending;
  }


  public function parse() {
    $this->_line_number = 1;
    $this->_char_number = 1;
    $eof = false;

    while (!feof($this->_stream) && !$eof) {
      $pos = ftell($this->_stream);
      $line = stream_get_line($this->_stream, $this->_buffer_size, $this->_line_ending);
      $ended = (bool)(ftell($this->_stream) - strlen($line) - $pos);
      // if we're still at the same place after stream_get_line, we're done
      $eof = ftell($this->_stream) == $pos; 

      $byteLen = strlen($line);
      for ($i = 0; $i < $byteLen; $i++) {
        if($this->_emit_file_position) {
          $this->_listener->file_position($this->_line_number, $this->_char_number);
        }
        $this->_consume_char($line[$i]);
        $this->_char_number++;
      }

      if ($ended) {
        $this->_line_number++;
        $this->_char_number = 1;
      }

    }
  }

  private function _consume_char($c) {
    // valid whitespace characters in JSON (from RFC4627 for JSON) include:
    // space, horizontal tab, line feed or new line, and carriage return.
    // thanks: http://stackoverflow.com/questions/16042274/definition-of-whitespace-in-json
    if (($c === " " || $c === "\t" || $c === "\n" || $c === "\r") &&
        !($this->_state === self::STATE_IN_STRING ||
          $this->_state === self::STATE_UNICODE ||
          $this->_state === self::STATE_START_ESCAPE ||
          $this->_state === self::STATE_IN_NUMBER ||
          $this->_state === self::STATE_START_DOCUMENT)) {
      // we wrap this so that we don't make a ton of unnecessary function calls
      // unless someone really, really cares about whitespace.
      if ($this->_emit_whitespace) {
        $this->_listener->whitespace($c);
      }
      return;
    }

    switch ($this->_state) {

      case self::STATE_IN_STRING:
       if ($c === '"') {
         $this->_end_string();
       } elseif ($c === '\\') {
         $this->_state = self::STATE_START_ESCAPE;
       } elseif (($c < "\x1f") || ($c === "\x7f")) {
           throw new ParsingError($this->_line_number, $this->_char_number,
             "Unescaped control character encountered: ".$c);
       } else {
         $this->_buffer .= $c;
       }
       break;

      case self::STATE_IN_ARRAY:
        if ($c === ']') {
          $this->_end_array();
        } else {
          $this->_start_value($c);
        }
        break;

      case self::STATE_IN_OBJECT:
        if ($c === '}') {
          $this->_end_object();
        } elseif ($c === '"') {
          $this->_start_key();
        } else {
          throw new ParsingError($this->_line_number, $this->_char_number,
            "Start of string expected for object key. Instead got: ".$c);
        }
        break;

      case self::STATE_END_KEY:
        if ($c !== ':') {
          throw new ParsingError($this->_line_number, $this->_char_number,
            "Expected ':' after key.");
        }
        $this->_state = self::STATE_AFTER_KEY;
        break;

      case self::STATE_AFTER_KEY:
        $this->_start_value($c);
        break;

      case self::STATE_START_ESCAPE:
        $this->_process_escape_character($c);
        break;

      case self::STATE_UNICODE:
        $this->_process_unicode_character($c);
        break;

      case self::STATE_UNICODE_SURROGATE:
        $this->_unicode_escape_buffer .= $c;
        if (mb_strlen($this->_unicode_escape_buffer) == 2) {
          $this->_end_unicode_surrogate_interstitial();
        }
        break;

      case self::STATE_AFTER_VALUE:
        $within = end($this->_stack);
        if ($within === self::STACK_OBJECT) {
          if ($c === '}') {
            $this->_end_object();
          } elseif ($c === ',') {
            $this->_state = self::STATE_IN_OBJECT;
          } else {
            throw new ParsingError($this->_line_number, $this->_char_number,
              "Expected ',' or '}' while parsing object. Got: ".$c);
          }
        } elseif ($within === self::STACK_ARRAY) {
          if ($c === ']') {
            $this->_end_array();
          } elseif ($c === ',') {
            $this->_state = self::STATE_IN_ARRAY;
          } else {
            throw new ParsingError($this->_line_number, $this->_char_number,
              "Expected ',' or ']' while parsing array. Got: ".$c);
          }
        } else {
          throw new ParsingError($this->_line_number, $this->_char_number,
            "Finished a literal, but unclear what state to move to. Last state: ".$within);
        }
        break;

      case self::STATE_IN_NUMBER:
        if (ctype_digit($c)) {
          $this->_buffer .= $c;
        } elseif ($c === '.') {
          if (strpos($this->_buffer, '.') !== false) {
            throw new ParsingError($this->_line_number, $this->_char_number,
              "Cannot have multiple decimal points in a number.");
          } elseif (stripos($this->_buffer, 'e') !== false) {
            throw new ParsingError($this->_line_number, $this->_char_number,
              "Cannot have a decimal point in an exponent.");
          }
          $this->_buffer .= $c;
        } elseif ($c === 'e' || $c === 'E') {
          if (stripos($this->_buffer, 'e') !== false) {
            throw new ParsingError($this->_line_number, $this->_char_number,
              "Cannot have multiple exponents in a number.");
          }
          $this->_buffer .= $c;
        } elseif ($c === '+' || $c === '-') {
          $last = mb_substr($this->_buffer, -1);
          if (!($last === 'e' || $last === 'E')) {
            throw new ParsingError($this->_line_number, $this->_char_number,
              "Can only have '+' or '-' after the 'e' or 'E' in a number.");
          }
          $this->_buffer .= $c;
        } else {
          $this->_end_number();
          // we have consumed one beyond the end of the number
          $this->_consume_char($c);
        }
        break;

      case self::STATE_IN_TRUE:
        $this->_buffer .= $c;
        if (mb_strlen($this->_buffer) === 4) {
          $this->_end_true();
        }
        break;

      case self::STATE_IN_FALSE:
        $this->_buffer .= $c;
        if (mb_strlen($this->_buffer) === 5) {
          $this->_end_false();
        }
        break;

      case self::STATE_IN_NULL:
        $this->_buffer .= $c;
        if (mb_strlen($this->_buffer) === 4) {
          $this->_end_null();
        }
        break;

      case self::STATE_START_DOCUMENT:
        $this->_listener->start_document();
        if ($c === '[') {
          $this->_start_array();
        } elseif ($c === '{') {
          $this->_start_object();
        } else {
          throw new ParsingError($this->_line_number, $this->_char_number,
            "Document must start with object or array.");
        }
        break;

      case self::STATE_DONE:
        throw new ParsingError($this->_line_number, $this->_char_number,
          "Expected end of document.");

      default:
        throw new ParsingError($this->_line_number, $this->_char_number,
          "Internal error. Reached an unknown state: ".$this->_state);
    }
  }

  private function _is_hex_character($c) {
    return ctype_xdigit($c);
  }

  // Thanks: http://stackoverflow.com/questions/1805802/php-convert-unicode-codepoint-to-utf-8
  private function _convert_codepoint_to_character($num) {
    if($num<=0x7F)       return chr($num);
    if($num<=0x7FF)      return chr(($num>>6)+192).chr(($num&63)+128);
    if($num<=0xFFFF)     return chr(($num>>12)+224).chr((($num>>6)&63)+128).chr(($num&63)+128);
    if($num<=0x1FFFFF)   return chr(($num>>18)+240).chr((($num>>12)&63)+128).chr((($num>>6)&63)+128).chr(($num&63)+128);
    return '';
  }

  private function _is_digit($c) {
    // Only concerned with the first character in a number.
    return ctype_digit($c) || $c === '-';
  }


  private function _start_value($c) {
    if ($c === '[') {
      $this->_start_array();
    } elseif ($c === '{') {
      $this->_start_object();
    } elseif ($c === '"') {
      $this->_start_string();
    } elseif ($this->_is_digit($c)) {
      $this->_start_number($c);
    } elseif ($c === 't') {
      $this->_state = self::STATE_IN_TRUE;
      $this->_buffer .= $c;
    } elseif ($c === 'f') {
      $this->_state = self::STATE_IN_FALSE;
      $this->_buffer .= $c;
    } elseif ($c === 'n') {
      $this->_state = self::STATE_IN_NULL;
      $this->_buffer .= $c;
    } else {
      throw new ParsingError($this->_line_number, $this->_char_number,
        "Unexpected character for value: ".$c);
    }
  }


  private function _start_array() {
    $this->_listener->start_array();
    $this->_state = self::STATE_IN_ARRAY;
    $this->_stack[] = self::STACK_ARRAY;
  }

  private function _end_array() {
    $popped = array_pop($this->_stack);
    if ($popped !== self::STACK_ARRAY) {
      throw new ParsingError($this->_line_number, $this->_char_number,
        "Unexpected end of array encountered.");
    }
    $this->_listener->end_array();
    $this->_state = self::STATE_AFTER_VALUE;

    if (empty($this->_stack)) {
      $this->_end_document();
    }
  }


  private function _start_object() {
    $this->_listener->start_object();
    $this->_state = self::STATE_IN_OBJECT;
    $this->_stack[] = self::STACK_OBJECT;
  }

  private function _end_object() {
    $popped = array_pop($this->_stack);
    if ($popped !== self::STACK_OBJECT) {
      throw new ParsingError($this->_line_number, $this->_char_number,
        "Unexpected end of object encountered.");
    }
    $this->_listener->end_object();
    $this->_state = self::STATE_AFTER_VALUE;

    if (empty($this->_stack)) {
      $this->_end_document();
    }
  }

  private function _start_string() {
    $this->_stack[] = self::STACK_STRING;
    $this->_state = self::STATE_IN_STRING;
  }

  private function _start_key() {
    $this->_stack[] = self::STACK_KEY;
    $this->_state = self::STATE_IN_STRING;
  }

  private function _end_string() {
    $popped = array_pop($this->_stack);
    if ($popped === self::STACK_KEY) {
      $this->_listener->key($this->_buffer);
      $this->_state = self::STATE_END_KEY;
    } elseif ($popped === self::STACK_STRING) {
      $this->_listener->value($this->_buffer);
      $this->_state = self::STATE_AFTER_VALUE;
    } else {
      throw new ParsingError($this->_line_number, $this->_char_number,
        "Unexpected end of string.");
    }
    $this->_buffer = '';
  }

  private function _process_escape_character($c) {
    if ($c === '"') {
      $this->_buffer .= '"';
    } elseif ($c === '\\') {
      $this->_buffer .= '\\';
    } elseif ($c === '/') {
      $this->_buffer .= '/';
    } elseif ($c === 'b') {
      $this->_buffer .= "\x08";
    } elseif ($c === 'f') {
      $this->_buffer .= "\f";
    } elseif ($c === 'n') {
      $this->_buffer .= "\n";
    } elseif ($c === 'r') {
      $this->_buffer .= "\r";
    } elseif ($c === 't') {
      $this->_buffer .= "\t";
    } elseif ($c === 'u') {
      $this->_state = self::STATE_UNICODE;
    } else {
      throw new ParsingError($this->_line_number, $this->_char_number,
        "Expected escaped character after backslash. Got: ".$c);
    }

    if ($this->_state !== self::STATE_UNICODE) {
      $this->_state = self::STATE_IN_STRING;
    }
  }

  private function _process_unicode_character($c) {
    if (!$this->_is_hex_character($c)) {
      throw new ParsingError($this->_line_number, $this->_char_number,
        "Expected hex character for escaped Unicode character. Unicode parsed: " . implode($this->_unicode_buffer) . " and got: ".$c);
    }
    $this->_unicode_buffer[] = $c;
    if (count($this->_unicode_buffer) === 4) {
      $codepoint = hexdec(implode($this->_unicode_buffer));

      if ($codepoint >= 0xD800 && $codepoint < 0xDC00) {
        $this->_unicode_high_surrogate = $codepoint;
        $this->_unicode_buffer = array();
        $this->_state = self::STATE_UNICODE_SURROGATE;
      } elseif ($codepoint >= 0xDC00 && $codepoint <= 0xDFFF) {
        if ($this->_unicode_high_surrogate === -1) {
          throw new ParsingError($this->_line_number, $this->_char_number,
            "Missing high surrogate for Unicode low surrogate.");
        }
        $combined_codepoint = (($this->_unicode_high_surrogate - 0xD800) * 0x400) + ($codepoint - 0xDC00) + 0x10000;

        $this->_end_unicode_character($combined_codepoint);
      } else if ($this->_unicode_high_surrogate != -1) {
        throw new ParsingError($this->_line_number, $this->_char_number,
          "Invalid low surrogate following Unicode high surrogate.");
      } else {
        $this->_end_unicode_character($codepoint);
      }
    }
  }

  private function _end_unicode_surrogate_interstitial() {
    $unicode_escape = $this->_unicode_escape_buffer;
    if ($unicode_escape != '\\u') {
      throw new ParsingError($this->_line_number, $this->_char_number,
        "Expected '\\u' following a Unicode high surrogate. Got: " . $unicode_escape);
    }
    $this->_unicode_escape_buffer = '';
    $this->_state = self::STATE_UNICODE;
  }  

  private function _end_unicode_character($codepoint) {
    $this->_buffer .= $this->_convert_codepoint_to_character($codepoint);
    $this->_unicode_buffer = array();
    $this->_unicode_high_surrogate = -1;
    $this->_state = self::STATE_IN_STRING;
  }


  private function _start_number($c) {
    $this->_state = self::STATE_IN_NUMBER;
    $this->_buffer .= $c;
  }

  private function _end_number() {
    $num = $this->_buffer;

    // thanks to #andig for the fix for big integers
    if (ctype_digit($num) && ((float)$num === (float)((int)$num))) {
      // natural number PHP_INT_MIN < $num < PHP_INT_MAX
      $num = (int)$num;
    } else {
      // real number or natural number outside PHP_INT_MIN ... PHP_INT_MAX
      $num = (float)$num;
    }

    $this->_listener->value($num);

    $this->_buffer = '';
    $this->_state = self::STATE_AFTER_VALUE;
  }


  private function _end_true() {
    $true = $this->_buffer;
    if ($true === 'true') {
      $this->_listener->value(true);
    } else {
      throw new ParsingError($this->_line_number, $this->_char_number,
        "Expected 'true'. Got: ".$true);
    }
    $this->_buffer = '';
    $this->_state = self::STATE_AFTER_VALUE;
  }

  private function _end_false() {
    $false = $this->_buffer;
    if ($false === 'false') {
      $this->_listener->value(false);
    } else {
      throw new ParsingError($this->_line_number, $this->_char_number,
        "Expected 'false'. Got: ".$false);
    }
    $this->_buffer = '';
    $this->_state = self::STATE_AFTER_VALUE;
  }

  private function _end_null() {
    $null = $this->_buffer;
    if ($null === 'null') {
      $this->_listener->value(null);
    } else {
      throw new ParsingError($this->_line_number, $this->_char_number,
        "Expected 'null'. Got: ".$null);
    }
    $this->_buffer = '';
    $this->_state = self::STATE_AFTER_VALUE;
  }


  private function _end_document() {
    $this->_listener->end_document();
    $this->_state = self::STATE_DONE;
  }

}

class ParsingError extends Exception {
  /**
   * @param int $line
   * @param int $char
   * @param string $message
   */
  public function __construct($line, $char, $message) {
    parent::__construct("Parsing error in [$line:$char]. " . $message);
  }
}

/**
 * This basic implementation of a listener simply constructs an in-memory
 * representation of the JSON document, which is a little silly since the whole
 * point of a streaming parser is to avoid doing just that. However, it can
 * serve as a starting point for more complex listeners, and illustrates some
 * useful concepts for working with a streaming-style parser.
 */
class InMemoryListener extends IdleListener {
  private $_result;

  private $_stack;
  private $_keys;

  public function get_json() {
    return $this->_result;
  }

  public function start_document() {
    $this->_stack = array();
    $this->_keys = array();
  }

  public function start_object() {
    $this->_start_complex_value('object');
  }

  public function end_object() {
    $this->_end_complex_value();
  }

  public function start_array() {
    $this->_start_complex_value('array');
  }

  public function end_array() {
    $this->_end_complex_value();
  }

  public function key($key) {
    $this->_keys[] = $key;
  }

  public function value($value) {
    $this->_insert_value($value);
  }

  private function _start_complex_value($type) {
    // We keep a stack of complex values (i.e. arrays and objects) as we build them,
    // tagged with the type that they are so we know how to add new values.
    $current_item = array('type' => $type, 'value' => array());
    $this->_stack[] = $current_item;
  }

  private function _end_complex_value() {
    $obj = array_pop($this->_stack);

    // If the value stack is now empty, we're done parsing the document, so we can
    // move the result into place so that get_json() can return it. Otherwise, we 
    // associate the value 
    if (empty($this->_stack)) {
      $this->_result = $obj['value'];
    } else {
      $this->_insert_value($obj['value']);
    }
  }

  // Inserts the given value into the top value on the stack in the appropriate way,
  // based on whether that value is an array or an object.
  private function _insert_value($value) {
    // Grab the top item from the stack that we're currently parsing.
    $current_item = array_pop($this->_stack);

    // Examine the current item, and then:
    //   - if it's an object, associate the newly-parsed value with the most recent key
    //   - if it's an array, push the newly-parsed value to the array
    if ($current_item['type'] === 'object') {
      $current_item['value'][array_pop($this->_keys)] = $value;
    } else {
      $current_item['value'][] = $value;
    }

    // Replace the current item on the stack.
    $this->_stack[] = $current_item;
  }
}



/**
 * This basic geojson implementation of a listener simply constructs an in-memory
 * representation of the JSON document at the second level, this is useful so only
 * a single Feature will be kept in memory rather than the whole FeatureCollection.
 */
class GeoJsonParser implements Listener {
    private $_json;

    private $_stack;
    private $_key;
    // Level is required so we know how nested we are.
    private $_level;

    public function file_position($line, $char) {

    }

    public function get_json() {
        
        return $this->_json;
    }

    public function start_document() {
        $this->_stack = array();
        $this->_level = 0;
        // Key is an array so that we can can remember keys per level to avoid it being reset when processing child keys.
        $this->_key = array();
    }

    public function end_document() {
        // w00t!
    }

    public function start_object() {
        $this->_level++;
        $this->_stack[] = array();
        // Reset the stack when entering the second level
        if($this->_level == 2) {
            $this->_stack = array();
            $this->_key[$this->_level] = null;
        }
    }

    public function end_object() {
        $this->_level--;
        $obj = array_pop($this->_stack);
        if (empty($this->_stack)) {
            // doc is DONE!
            $this->_json = $obj;
        } else {
            $this->value($obj);
        }
        // Output the stack when returning to the second level
        if($this->_level == 2) {
            var_dump($this->_json);
        }
    }

    public function start_array() {
        $this->start_object();
    }

    public function end_array() {
        $this->end_object();
    }

    // Key will always be a string
    public function key($key) {
        $this->_key[$this->_level] = $key;
    }

    // Note that value may be a string, integer, boolean, null
    public function value($value) {
        $obj = array_pop($this->_stack);
        if ($this->_key[$this->_level]) {
            $obj[$this->_key[$this->_level]] = $value;
            $this->_key[$this->_level] = null;
        } else {
            $obj[] = $value;
        }
        $this->_stack[] = $obj;
    }

    public function whitespace($whitespace) {
        // do nothing
    }
}

