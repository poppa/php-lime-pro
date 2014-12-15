<?php
/**
 * This module is an interface to the web services of
 * {@link http://www.lundalogik.se/ Lundalogik}s web services for Lime PRO.
 *
 * More info can be found at the {@link https://github.com/poppa/php-lime-pro
 * Github repository}.
 *
 * @copyright 2014 Pontus Östlund
 * @author    Pontus Östlund <poppanator@gmail.com>
 * @license   http://opensource.org/licenses/GPL-2.0 GPL License 2
 * @link      https://github.com/poppa Github
 * @package   Lime
 * @version   0.1
 */

/*
  Lime PRO webservice integration API
  Copyright (C) 2014  Pontus Östlund <poppanator@gmail.com>

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License along
  with this program; if not, write to the Free Software Foundation, Inc.,
  51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

/**
 * The Lime namespace
 */
namespace Lime;

/**
 * Convenience function for calling the {@link \Lime\Client::query()} method
 * This is the same as:
 *
 * <pre>
 *  $cli = new Lime\Client;
 *  $cli->query($query);
 * </pre>
 *
 * @param string|\Lime\XML\Node $sql_or_node
 * @return array
 */
function query($sql_or_node)
{
  if (!Client::$endpoint) {
    throw new \Exception("The Lime\Client hasn't been configured with " .
                         "an endpoint! ", 1);
  }

  static $client = null;
  if (!$client) $client = new Client;
  return $client->query($sql_or_node);
}

/**
 * Generate a Lime XML query structure from a SQL query
 *
 * @api
 *
 * @param string $sql_query
 * @return \Lime\XML\Node
 */
function sql_to_node($sql_query)
{
  $p = new Sql\Parser;
  return $p->parse($sql_query);
}

/**
 * Load XML into a {@link \Lime\XML\Node} object
 *
 * @api
 * @param string $xml
 * @return \Lime\XML\Node
 */
function load_xml($xml)
{
  $n = new XML\Node();
  return $n->parse_xml($xml);
}

/**
 * SOAP client. Extends the {@link http://php.net/manual/en/class.soapclient.php
 * SoapClient} class.
 */
class Client extends \SoapClient
{
  public static $endpoint;
  public static $options = array();

  /**
   * Set the WSDL endpoint
   *
   * @param url $endpoint
   */
  public static function set_endpoint($endpoint)
  {
    self::$endpoint = $endpoint;
  }

  /**
   * Set the SOAP client options
   *
   * @param array $options
   */
  public static function set_options(array $options)
  {
    self::$options = $options;
  }

  /**
   * Create a new client.
   *
   * @note The arguments to this constructor can be set globally via the static
   *       methods {@link \Lime\Client::set_endpoint()} and
   *       {@link \Lime\Client::set_options()}.
   *
   * @param void|string $endpoint
   * @param void|array $options
   *  SoapClient options
   */
  function __construct($endpoint=null, array $options=null)
  {
    $ep = is_null($endpoint) ? self::$endpoint : $endpoint;

    if (!$ep) {
      throw new \Exception("No WSDL endpoint is configured! ", 1);
    }

    $op = is_null($options) ? self::$options : $options;

    parent::__construct($ep, $op);
  }

  /**
   * Invoke a query to the `GetXmlQueryData` web service
   *
   * @api
   * @param string|\Lime\XML\Node $query
   *  If a string it's assumed to be an SQL query.
   * @return array
   *  An array of associative arrays
   */
  function query($query)
  {
    if (!is_object($query)) {
      $query = \Lime\sql_to_node($query);
    }
    else {
      if (!$query instanceof \Lime\XML\Node) {
        $m = "Argument \$query is not an instance of \Lime\XML\Node!";
        throw new \Exception($m, 1);
      }
    }

    $res = parent::__soapCall('GetXmlQueryData',
                              array('query' => new QueryParam($query)));

    if ($res->GetXmlQueryDataResult) {
      return self::__xml_to_array($res->GetXmlQueryDataResult);
    }

    return null;
  }

  /**
   * Turns a response from `GetXmlQueryData` into an array of assoc arrays
   *
   * @internal
   * @param string $str_xml
   * @return array
   */
  public static function __xml_to_array($str_xml)
  {
    if (preg_match('/^<\?xml .*\?>(.*)$/', $str_xml, $m)) {
      $str_xml = $m[1];
    }

    $xml = simplexml_load_string($str_xml);
    $ret = array();

    foreach ($xml->children() as $child) {
      $tmp = array();

      foreach ($child->attributes() as $key => $val) {
        $tmp[$key] = (string) $val;
      }

      array_push($ret, $tmp);
    }

    return $ret;
  }
}

/**
 * SOAP param for `GetXmlQueryData`
 * @internal
 */
class QueryParam
{
  /**
   * Constructor
   *
   * @param string|\Lime\XML\Node $value
   */
  function __construct($value)
  {
    if (is_object($value) && $value instanceof \Lime\XML\Node)
      $value = (string) $value;

    $this->query = $value;
  }
}

/**
 * This namespace has functions and classes for creating and reading Lime
 * XML documents.
 */
namespace Lime\XML;

/**
 * Creates a Lime `query` XML structure
 *
 * @api
 * @param \Lime\XML\Node[] $nodes
 * @param array $attr
 *  If `null` the attribute `distinct=1` will be added
 * @return \Lime\XML\Node
 */
function query($nodes, $attr=null)
{
  if ($attr === null)
    $attr = array('distinct' => '1');

  return new Node('query', $attr, $nodes);
}

/**
 * Creates a `field` node.
 *
 * @api
 * @param string|array $name
 *  If $name is an array the name of the field should bein the array key
 *  `field`. So a potential value could be:
 *
 *  <pre>
 *   array('field' => 'fieldname',
 *         'sortindex' => '1',
 *         'sortorder' => 'desc')
 *  </pre>
 * @return \Lime\XML\Node
 */
function field($name)
{
  $attr = null;

  if (is_array($name)) {
    $attr = $name;
    $name = $attr['field'];
    unset($attr['field']);
  }

  return new Node('field', $attr, $name);
}

/**
 * Creates a `fields` node.
 *
 * @api
 * @param string[]|string fields
 *  If a string it will be interpreted as a `vararg` list
 * @return \Lime\XML\Node
 */
function fields($fields)
{
  if (func_num_args() > 1)
    $fields = func_get_args();

  return new Node('fields', array_map('\Lime\XML\field', $fields));
}

/**
 * Creates an `exp` node.
 *
 * @api
 * @param string $type
 * @param string|int $value
 * @return \Lime\XML\Node
 */
function exp($type, $value=null)
{
  if ($value === null)
    return new Node('exp', array('type' => $type));

  return new Node('exp', array('type' => $type), $value);
}

/**
 * Creates a `condition` node.
 *
 * @api
 * @param mixed $args
 *  This is a `vararg` argument.
 * @return \Lime\XML\Node
 */
function cond(/* mixed ... $args */)
{
  $args = func_get_args();
  array_unshift($args, 'condition');
  return call_user_func_array(array('\Lime\XML\Node', 'create'), $args);
}

/**
 * Creates a `conditions` node
 *
 * @api
 * @param mixed $args
 *  This is a `varargs` argument
 * @return \Lime\XML\Node
 */
function conds(/* mixed ... $args */)
{
  $a = func_get_args();
  array_unshift($a, 'conditions');
  return call_user_func_array(array('\Lime\XML\Node', 'create'), $a);
}

/**
 * Creates a tables/table node
 *
 * @api
 * @param string $name
 * @return \Lime\XML\Node
 */
function table($name)
{
  return new Node('tables', array(new Node('table', $name)));
}

/**
 * Object representing a node in an XML tree
 * This object implements {@link \Iterator} so its child nodes can be iterated
 * over in a `foreach` loop.
 *
 * This object can be used both for creating and reading XML structures, but
 * for the purpose of Lime it's mostly used for creating XML structures.
 */
class Node implements \Iterator
{
  /**
   * Creates a new {@link Node} object. Same params as
   * {@link \Lime\XML\Node::__construct()}.
   *
   * @see \Lime\XML\Node::__construct()
   * @param string $a
   * @param string|array $b
   * @param string|array $c
   */
  public static function create($a, $b=null, $c=null)
  {
    return new Node($a, $b, $c);
  }

  /**
   * Current position in the {@link \Iterator}
   * @var int
   */
  private $position = 0; // Iterator

  /**
   * Node name
   * @var string
   */
  private $name = null;

  /**
   * Node attributes
   * @var array
   */
  private $attributes = array();

  /**
   * Node value/child nodes
   * @var string|array Array of {@link \Lime\XML\Node} if an array
   */
  private $value = null;

  /**
   * Creates a new Lime node object.
   *
   * <pre>
   *  $n1 = new Node('node', array('id' => 12));
   *  // Will become: &lt;node id="12" />
   *
   *  $n2 = new Node('node', 'Veni vidi vici');
   *  // Will become: &lt;node>Veni vidi vici&lt;/node>
   *
   *  $n3 = new Node('node', array('id' => 12), 'Hello world');
   *  // Will become: &lt;node id="12">Hello world&lt;/node>
   *
   *  $n4 = new Node('parent', array(
   *    new Node('child', array('name' => 'Ann', 'says' => 'Hello'), 'Daddy'),
   *    new Node('child', array('name' => 'Bo', 'says' => 'Hello'), 'Mommy')
   *  ));
   *  // Will become
   *  // &lt;parent>
   *  //   &lt;child name="Ann" says="Hello">Daddy&lt;/child>
   *  //   &lt;child name="Bo" says="Hello">Mommy&lt;/child>
   *  // &lt;/parent>
   *
   *  $n5 = new Node('parent', array('tired' => 'yes'), array(
   *    new Node('child', array('name' => 'Ann', 'says' => 'Hello'), 'Daddy'),
   *    new Node('child', array('name' => 'Bo', 'says' => 'Hello'), 'Mommy')
   *  ));
   *  // Will become
   *  // &lt;parent tired="yes">
   *  //   &lt;child name="Ann" says="Hello">Daddy&lt;/child>
   *  //   &lt;child name="Bo" says="Hello">Mommy&lt;/child>
   *  // &lt;/parent>
   * </pre>
   *
   * @param string $name
   *  The name of the node
   * @param \Lime\XML\Node[]|string $attributes
   *  If a string it will be the string value of the node.
   *  If an associative array it's the node attributes.
   *  If an array of {@link \Lime\XML\Node}s it's the nodes children.
   * @param string|\Lime\XML\Node[] $value
   *  If a string it will be the value of the node
   *  If an array of {@link \Lime\XML\Node}s it's the nodes children.
   */
  function __construct($name=null, $attributes=null, $value=null)
  {
    $this->name = $name;

    if ($value && $value instanceof self) {
      $value = array($value);
    }

    if ($attributes !== null && $value !== null) {
      $this->attributes = $attributes;
      $this->value = $value;
    }
    else if (!$attributes && $value !== null) {
      $this->value = $value;
    }
    else if ($attributes && $value === null) {
      if (is_object($attributes) && $attributes instanceof self) {
        $this->value = array($attributes);
      }
      elseif ($this->is_assoc($attributes)) {
        $this->attributes = $attributes;
      }
      else {
        $this->value = $attributes;
      }
    }
  }

  /**
   * Getter for the child nodes
   *
   * @param string $name
   *  The children to retrieve
   * @return  \Lime\XML\Node[]|void
   */
  function __get($name)
  {
    if (is_array($this->value)) {
      $ret = array();
      foreach ($this->value as $n) {
        if ($n->get_name() === $name) {
          array_push($ret, $n);
        }
      }

      return $ret;
    }
  }

  /**
   * Getter for the node value
   *
   * @return string|\Lime\XML\Node[]
   */
  function get_value()
  {
    return $this->value;
  }

  /**
   * Getter for the node name
   *
   * @return string
   */
  function get_name()
  {
    return $this->name;
  }

  /**
   * Getter for the node attributes
   *
   * @return array
   */
  function get_attributes()
  {
    return $this->attributes;
  }

  /**
   * Parse an XML tree into a {@link \Lime\XML\Node} object
   *
   * @param string $xml
   * @return \Lime\XML\Node
   */
  function parse_xml($xml)
  {
    $domdoc = new \DOMDocument();
    $domdoc->loadXML($xml);
    $this->parse_node($domdoc->firstChild);
    return $this;
  }

  /**
   * Parse a subnode. Consider internal
   *
   * @internal
   * @param \DOMNode $n
   * @return \Lime\XML\Node
   */
  function parse_node(\DOMNode $n)
  {
    if ($n && $n->nodeType === \XML_ELEMENT_NODE) {
      $this->name = $n->nodeName;

      if ($n->hasAttributes())
        $this->attributes = $this->to_attr_array($n->attributes);

      $cn = $n->childNodes;

      if (sizeof($cn)) {
        $this->value = array();

        foreach ($cn as $c) {
          $nn = new Node();

          if ($c->nodeType === \XML_ELEMENT_NODE) {
            array_push($this->value, $nn->parse_node($c));
          }
          elseif ($c->nodeType === \XML_TEXT_NODE) {
            $v = trim($c->textContent);
            if (strlen($v)) {
              if (is_array($this->value)) {
                $this->value = $c->textContent;
              }
              else {
                $this->value .= $c->textContent;
              }
            }
          }
        }
      }
      else {
        $v = trim($n->textContent);
        if ($v !== "")
          $this->value = $v;
      }
    }

    return $this;
  }

  /**
   * Quote XML characters. Only replaces `&`, `<` and `>`.
   *
   * @param string $str
   * @return string
   */
  function quote_xml($str)
  {
    return str_replace(array('&','<','>'), array('&amp;','&lt;','&gt;'), $str);
  }

  /**
   * Turn the node into an XML tree. It's also possible to cast a
   * {@link \Lime\XML\Node} object into a string.
   *
   * @see \Lime\XML\Node::__toString()
   * @return string
   */
  function to_xml()
  {
    $s = "<$this->name" . $this->attr_to_string();

    if (!$this->value || !sizeof($this->value))
      return $s . '/>';

    $s .= '>';

    if (is_array($this->value)) {
      foreach ($this->value as $v) {
        $s .= (string) $v;
      }
    }
    else
      $s .= $this->quote_xml($this->value);

    return $s . "</$this->name>";
  }

  /**
   * Same as {@link Node::to_xml()} except this formats the output. Good for
   * debugging purposes.
   *
   * @return string
   */
  function to_human_readable_xml()
  {
    $s = $this->to_xml();
    $doc = new \DOMDocument();
    $doc->formatOutput = true;
    $doc->loadXML($s, \LIBXML_NOXMLDECL);
    return $doc->saveXML();
  }

  /**
   * Cast to string. Same as {@link \Lime\XML\Node::to_xml()}
   *
   * @see \Lime\XML\Node::to_xml()
   * @return string
   */
  function __toString()
  {
    return $this->to_xml();
  }

  /*
    Iterator API
  */

  /**
   * Reset the iterator
   */
  function rewind()
  {
    $this->position = 0;
  }

  /**
   * Returns the current item in the iterator
   *
   * @return Node
   */
  function current()
  {
    if (is_array($this->value))
      return $this->value[$this->position];

    return false;
  }

  /**
   * Move the iterator to the next index
   */
  function next()
  {
    $this->position++;
  }

  /**
   * Getter for the current iterator key
   *
   * @return int
   */
  function key()
  {
    return $this->position;
  }

  /**
   * Is the current iterator position valid?
   *
   * @return bool
   */
  function valid()
  {
    return is_array($this->value) && isset($this->value[$this->position]);
  }

  /**
   * Is the value `$a` an associative array?
   *
   * @return bool
   */
  private function is_assoc($a)
  {
    if (!is_array($a))
      return false;

    $k = array_keys($a);
    return sizeof($k) > 0 && is_string($k[0]);
  }

  /**
   * Converts the attributes into a string
   *
   * @return string
   */
  private function attr_to_string()
  {
    if (sizeof($this->attributes)) {
      $s = "";

      foreach ($this->attributes as $k => $v) {
        $s .= " $k=\"" . htmlentities($v) . "\"";
      }

      return $s;
    }

    return '';
  }

  /**
   * Converts attributes of a DOMNode into a normal array
   *
   * @return array
   */
  private function to_attr_array(\DOMNamedNodeMap $n)
  {
    $a = array();
    foreach ($n as $k => $v) {
      $a[$k] = $v->textContent;
    }
    return $a;
  }
}

namespace Lime\Sql;

/**
 * Parser for creating a {@link \Lime\XML\Node} object fron an
 * SQL query.
 *
 * @internal
 * @ignore
 */
class Parser
{
  private static $keywords = array(
    "select", "distinct", "from", "where",
    "limit", "count", "order",
    "by", "asc", "desc");

  private static $operators = array(
    "!", "=", "!=", "<", ">", ">=", "<=", "is",
    "like", "and", "or", "in", "not", "any", "all");

  function parse($sql)
  {
    $tokens = $this->tokenize($this->split($sql));
    array_push($tokens, 0);

    $pos = 0;
    $table = null;
    $fields = array();
    $conds = array();
    $sort = null;
    $limits = array();
    $query_attr = array('distinct' => '0');

    $t = null; $andor = null;

    while ($t = $tokens[$pos]) {
      // COLUMN
      if ($t->is_a(Token::COLUMN)) {
        do {
          array_push($fields, $t->value);
          $t = $tokens[++$pos];
        } while ($t->is_a(Token::COLUMN));
      }

      // LIMIT_TO
      elseif ($t->is_a(Token::LIMIT_TO)) {
        $limits['top'] = $t->value;
      }

      // LIMIT_FROM
      elseif ($t->is_a(Token::LIMIT_FROM)) {
        $limits['first'] = $t->value;
      }

      // KEYWORD=distinct
      elseif ($t->is_a(Token::KEYWORD) && $t->lveq("distinct")) {
        $query_attr['distinct'] = '1';
      }

      // KEYWORD|COUNT
      elseif ($t->is_a(Token::COUNT)) {
        $query_attr['count'] = "1";
      }

      // KEYWORD|TABLE
      elseif ($t->is_a(Token::TABLE)) {
        $table = $t->value;
      }

      // SORT_ORDER
      elseif ($t->is_a(Token::SORT_ORDER)) {
        if (!is_array($sort)) {
          $m = "Found a sort order but no fields to sort on!";
          throw new \Exception($m, 1);
        }
        $sort['order'] = $t->is_a(Token::ORDER_ASC) ? "asc" : "desc";
      }

      // SORT_KEY
      elseif ($t->is_a(Token::SORT_KEY)) {
        if (!is_array($sort)) {
          $sort = array('fields' => array(), 'index' => 0);
        }

        $sort['index'] += 1;
        $sort['fields'][$t->value] = $sort['index'];
      }

      // MODIFIER
      elseif ($t->is_a(Token::OPERATOR) && $t->lveq('and,or')) {
        $andor = $t;
        $pos += 1;
        continue;
      }

      // PREDICATE
      elseif ($t->is_a(Token::PREDICATE)) {
        $op = $tokens[++$pos];
        $op2 = $tokens[$pos+1];

        $opval = $op->value;

        if ($op2->is_a(Token::OPERATOR)) {
          $opval .= " $op2->value";
          $pos += 1;
          $op = $op2;
          unset($op2);
        }

        $val = $tokens[++$pos];

        if (!$val->is_a(Token::VALUE)) {
          $m = "SQL syntax error! Expected a value, got something else!";
          throw new \Exception($m, 1);
        }

        if ($op->lveq('like') && strlen($val->value) > 0) {
          if ($val->value[0] === '%') {
            $val->value = substr($val->value, 1);
            $opval = '%' . $opval;
          }
          if (substr($val->value, -1) === '%') {
            $val->value = substr($val->value, 0, -1);
            $opval .= '%';
          }
        }

        if ($tokens[$pos+1]->is_a(Token::TYPEHINT)) {
          $val->datatype = $tokens[$pos+1]->value;
          $pos += 1;
        }

        $attr = array('operator' => $opval);

        if ($andor && $andor->lc_value == "or") {
          $attr['or'] = '1';
        }

        $c = \Lime\XML\cond($attr, array(
          \Lime\XML\exp('field', $t->value),
          \Lime\XML\exp($val->datatype, $val->value)
        ));

        array_push($conds, $c);

        unset($op);
        unset($val);
        unset($opval);
        unset($attr);
      }

      // GROUP_START || GROUP_END
      elseif ($t->is_a(Token::GROUP_START) ||
              $t->is_a(Token::GROUP_END))
      {
        $attr = null;

        if ($andor && $andor->lc_value === 'or')
          $attr = array('or' => '1');

        $c = \Lime\XML\cond($attr, array(\Lime\XML\exp($t->value)));
        array_push($conds, $c);

        unset($attr);
        unset($c);
      }

      $andor = null;
      $pos += 1;
    }

    if (sizeof($limits)) {
      if (!isset($limits['top']))
        $query_attr['top'] = $limits['first'];
      else {
        $query_attr['top'] = $limits['top'];
        $query_attr['first'] = $limits['first'];
      }
    }

    $q = array();

    array_push($q, \Lime\XML\table($table));

    if (sizeof($conds)) {
      array_push($q, \Lime\XML\conds($conds));
    }

    if (sizeof($fields)) {
      if (sizeof($sort)) {
        if (!isset($sort['order']))
          $sort['order'] = "ASC";

        $i = 0;

        foreach ($fields as $fld) {
          if (isset($sort['fields'][$fld])) {
            $sa = array(
              "field"     => $fld,
              "sortorder" => $sort['order'],
              "sortindex" => $sort['fields'][$fld]
            );

            $fields[$i] = $sa;
          }

          $i++;
        }
      }

      array_push($q, \Lime\XML\fields($fields));
    }

    return new \Lime\XML\Node('query', $query_attr, $q);
  }

  static function is_keyword($w)
  {
    return in_array($w, self::$keywords);
  }

  static function is_operator($w)
  {
    return in_array($w, self::$operators);
  }

  private function tokenize(array $words)
  {
    array_push($words, 0);
    array_unshift($words, 0);

    $tokens = array(new Token(null));
    $pos = 1;

    while (1) {
      $w = $words[$pos];

      if ($w === 0)
        return array_slice($tokens, 1);

      $t = new Token($w);
      $p = $tokens[$pos-1];

      if (!$t->type) {
        if ($p->is_a(Token::COLUMN) || (
            $p->is_a(Token::KEYWORD) && $p->lveq('select,distinct,count')))
        {
          $t->type = Token::COLUMN;
        }
        elseif ($p->is_a(Token::KEYWORD) && $p->lveq('from')) {
          $t->type = Token::TABLE;
        }
        elseif (($p->is_a(Token::KEYWORD) && $p->lveq('where'))
               || ($p->is_a(Token::OPERATOR) && $p->lveq('and,or'))
               || $p->is_a(Token::GROUP_START))
        {
          $t->type = Token::PREDICATE;
        }
        elseif ($p->is_a(Token::OPERATOR)) {
          $t->type = Token::VALUE;
          $t->resolve_datatype();
        }
        elseif ($p->is_a(Token::KEYWORD|Token::LIMIT)) {
          $t->type = Token::LIMIT_FROM;
        }
        elseif ($p->is_a(Token::LIMIT_FROM)) {
          $t->type = Token::LIMIT_TO;
        }
        elseif ($p->is_a(Token::BY) || $p->is_a(Token::SORT_KEY)) {
          $t->type = Token::SORT_KEY;
        }
        else {
          throw new \Exception(
            sprintf("Unresolved token type: %s : Prev: %s\n", $t, $p), 1);
        }
      }

      array_push($tokens, $t);
      $pos += 1;
    }
  }

  private function split($str)
  {
    $str .= "\0\0";
    $ret = array();
    $pos = 0;

    while (1) {
      $start = $pos;

      switch ($str[$pos]) {
        case "\0": return $ret;

        case "\r":
          $pos += 1;
          if ($str[$pos] === "\n") $pos += 1;
          continue 2; // Outer while

        case "\n":
          $pos += 1;
          if ($str[$pos] === "\r") $pos += 1;
          continue 2; // Outer while

        case ',':
          $pos += 1;
          continue 2; // Outer while

        case ' ':
        case "\t":
        case "\f":
          $pos += 1;
          while ($str[$pos] === ' ' || $str[$pos] === "\t")
            $pos += 1;
          continue 2; // Outer while

        case '`':
        case '"':
        case '\'':
          $c = $str[$pos];
          $pos += 1;
          while (1) {
            if ($str[$pos] === "\0") {
              $em = substr($str, $start);
              if (strlen($em) > 50) $em = substr($em, 0, 50) . "...";
              throw new Exception(
                sprintf("Unterminated string literal at [%d]: %s!\n",
                        $start, $em), 1);
            }

            if ($str[$pos] === $c) {
              if ($str[$pos-1] != "\\") {
                $pos++;
                break;
              }
            }
            $pos++;
          }
          break;

        case '!':
        case '<':
        case '>':
          if ($str[$pos+1] === '=') {
            $pos += 2;
          }
          break;

        /*
          Range a..Z, 0..9 and %
        */
        case 'a': case 'b': case 'c': case 'd': case 'e': case 'f': case 'g':
        case 'h': case 'i': case 'j': case 'k': case 'l': case 'm': case 'n':
        case 'o': case 'p': case 'q': case 'r': case 's': case 't': case 'u':
        case 'v': case 'w': case 'x': case 'y': case 'z': case 'A': case 'B':
        case 'C': case 'D': case 'E': case 'F': case 'G': case 'H': case 'I':
        case 'J': case 'K': case 'L': case 'M': case 'N': case 'O': case 'P':
        case 'Q': case 'R': case 'S': case 'T': case 'U': case 'V': case 'W':
        case 'X': case 'Y': case 'Z': case '0': case '1': case '2': case '3':
        case '4': case '5': case '6': case '7': case '8': case '9': case '%':
        case ':':
          while (1) {
            switch ($str[$pos]) {
              /*
                Range a..Z, 0..9 and % and '.'
              */
              case 'a': case 'b': case 'c': case 'd': case 'e': case 'f':
              case 'g': case 'h': case 'i': case 'j': case 'k': case 'l':
              case 'm': case 'n': case 'o': case 'p': case 'q': case 'r':
              case 's': case 't': case 'u': case 'v': case 'w': case 'x':
              case 'y': case 'z': case 'A': case 'B': case 'C': case 'D':
              case 'E': case 'F': case 'G': case 'H': case 'I': case 'J':
              case 'K': case 'L': case 'M': case 'N': case 'O': case 'P':
              case 'Q': case 'R': case 'S': case 'T': case 'U': case 'V':
              case 'W': case 'X': case 'Y': case 'Z': case '0': case '1':
              case '2': case '3': case '4': case '5': case '6': case '7':
              case '8': case '9': case '%': case '.': case ':':
                $pos += 1;
                continue 2; // Inner while
            }
            break;
          }
          break;

        default:
          $pos += 1;
      }

      $token = substr($str, $start, $pos - $start);
      array_push($ret, $token);
    }
  }
}

/**
 * A parser token
 *
 * @internal
 * @ignore
 */
class Token
{
  const NONE        =      0;
  const KEYWORD     =      1;
  const OPERATOR    =      2;
  const VALUE       =      4;
  const COLUMN      =      8;
  const PREDICATE   =     16;
  const TABLE       =     32;
  const GROUP_START =     64;
  const GROUP_END   =    128;
  const LIMIT       =    256;
  const LIMIT_FROM  =    512;
  const LIMIT_TO    =   1024;
  const COUNT       =   2048;
  const SELECT      =   4096;
  const ORDER       =   8192;
  const BY          =  16384;
  const SORT_ORDER  =  32768;
  const ORDER_ASC   =  65536;
  const ORDER_DESC  = 131072;
  const SORT_KEY    = 262144;
  const TYPEHINT    = 524288;

  public $value = null;
  public $type = Token::NONE;
  public $lc_value = null;
  public $datatype = null;

  function __construct($value)
  {
    if (is_null($value)) return;

    $this->value = $value;
    $this->lc_value = $lv = strtolower($value);

    if (Parser::is_keyword($lv)) {
      $this->type = Token::KEYWORD;

      if ($lv === "limit") {
        $this->type |= Token::LIMIT;
      }
      elseif ($lv === "select") {
        $this->type |= Token::SELECT;
      }
      elseif ($lv == "count") {
        $this->type |= Token::COUNT;
      }
      elseif ($lv === "order") {
        $this->type |= Token::ORDER;
      }
      elseif ($lv === "by") {
        $this->type |= Token::BY;
      }
      elseif ($lv === "asc") {
        $this->type |= Token::ORDER_ASC;
        $this->type |= Token::SORT_ORDER;
      }
      elseif ($lv == "desc") {
        $this->type |= Token::ORDER_DESC;
        $this->type |= Token::SORT_ORDER;
      }
    }
    elseif (Parser::is_operator($lv)) {
      $this->type = Token::OPERATOR;
    }
    elseif (strlen($this->lc_value) > 0 && $this->lc_value[0] === ':') {
      $this->type = Token::TYPEHINT;
      $this->value = substr($this->value, 1);
      $this->lc_value = substr($this->lc_value, 1);
    }

    if ($lv && strlen($lv) > 0) {
      if ($lv[0] === '\'' || $lv[0] === '"') {
        $this->type = Token::VALUE;
        $this->value = substr($value, 1, -1);
        $this->datatype = 'string';

        if (preg_match('/\d{4}-\d{2}-\d{2}/', $this->value))
          $this->datatype = 'date';
      }
      elseif ($value[0] === '(') {
        $this->type = Token::GROUP_START;
      }
      elseif ($value[0] === ')') {
        $this->type = self::GROUP_END;
      }

      if ($value[0] === '`') {
        $this->value = substr($value, 1, -1);
      }
    }
  }

  /**
   * Lower case value equals `$what`?
   *
   * @param string $what
   *  To check multiple alternatives pass in a comma delmited string
   *  with no spaces like `$token->lveq('one,two,three')`
   */
  function lveq($what)
  {
    return in_array($this->lc_value, explode(',', $what));
  }

  function type_as_string()
  {
    $ref = new \ReflectionClass($this);
    $arr = array();

    foreach ($ref->getConstants() as $k => $c) {
      if (!$c) continue;
      if (($this->type & $c) === $c)
        array_push($arr, $k);
    }

    if (!sizeof($arr))
      $arr = array('NONE');

    return implode('|', $arr);
  }

  function resolve_datatype()
  {
    // Strings are resolved upon instantiation
    if (!$this->datatype) {
      if ($this->value && preg_match('/\d{4}-\d{2}-\d{2}/', $this->value))
        $this->datatype = "date";
      else
        $this->datatype = "numeric";
    }
  }

  function is_a($type)
  {
    return ($this->type & $type) == $type;
  }

  function __toString()
  {
    $rc = new \ReflectionClass($this);
    return $rc->getName() . '("' . $this->value . '", ' .
           $this->type_as_string() . ')';
  }
}
?>