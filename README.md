php-lime-pro
============

A PHP interface to the web services of the CRM **Lime PRO** by
[Lundalogik](https://github.com/lundalogik).

This module has a simple SQL parser so you can query the web services pretty
much in the same way as you would query a normal database. *(At the moment
only SELECT statements are handled, and maybe support for INSERT/UPDATE will
be added in the future)*. This is applicable to the `GetXmlQueryData` web
service which is the one being used the most.

## How does it work?

There are four `namespaces` in this module (in order of relevance)

  1. Lime\Client
  2. Lime\XML
  3. Lime
  4. Lime\Sql

`Lime\Client` contains functions for calling the web services. `Lime\XML` has
functions and classes (a class) for creating the XML query document being sent
to the web services and `Lime\Sql` has the SQL parsing stuff which isn't of any
interest for normal usage.

### 1. The `Lime\Client` namespace

First you need to set the endpoint of the client, i.e. the location of the
`WSDL` file of the webservices.

```php
Lime\Client\set_endpoint('http://url.to:8081/DataService/?wsdl');
```

You can also set options for the [`SoapClient`](http://php.net/manual/en/soapclient.soapclient.php)
object.

```php
// Set the entire options array
Lime\Client\set_options(array('trace' => 1, 'exception' => 0));

// Add a specific option
Lime\Client\set_config_option('exception', 0);

// Remove a specific option
Lime\Client\remove_config_option('trace');

// Get all options
$opts = Lime\Client\get_options();
```

And then the `Client` namespace has a method for calling the `GetXmlQueryData`
web service.

```php
$sql = "
  SELECT DISTINCT col1, col2, col3
  FROM table
  WHERE col1=1 AND col4!='no' AND (coln='x' OR coly='z')
  ORDER BY col1, col2 DESC";

$res = Lime\Client\query($sql);

foreach ($res as $row) {
  echo "* Data: {$row['col1']} and {$row['col2']} and {$row['col3']}\n";
}
```

The `$sql` query above will generate an XML structure as:

```xml
<query distinct="1">
  <tables>
    <table>table</table>
  </tables>
  <conditions>
    <condition operator="=">
      <exp type="field">col1</exp>
      <exp type="numeric">1</exp>
    </condition>
    <condition operator="!=">
      <exp type="field">col4</exp>
      <exp type="string">no</exp>
    </condition>
    <condition>
      <exp type="("/>
    </condition>
    <condition operator="=">
      <exp type="field">coln</exp>
      <exp type="string">x</exp>
    </condition>
    <condition operator="=" or="1">
      <exp type="field">coly</exp>
      <exp type="string">z</exp>
    </condition>
    <condition>
      <exp type=")"/>
    </condition>
  </conditions>
  <fields>
    <field sortorder="desc" sortindex="1">col1</field>
    <field sortorder="desc" sortindex="2">col2</field>
    <field>col3</field>
  </fields>
</query>
```

which will be sent as argument to the web service. `Lime\Client\query()` will
return an array of associative arrays if it succeeds, as shown in the example.

All operators defined in the [Lime documentation](http://docs.lundalogik.com/pro/integration/lime-web-service/queries)
can be used in the SQL query. The `IN` and `NOT IN` operators can be applicable
to string values but not numeric values at the moment.

The SQL syntax also handles `LIMIT FROM[, TO]`.

### 2. The `Lime\XML` namespace

This namespace has one class, `Node`, for building the XML queries to Lime and
a couple of convenience wrapper methods for the most common tasks. All these
methods returns a `Node` object.

To write the exact same query with `Node` objects as the `$sql` query above,
you could do like this:

```php
$n = new Node('query', array('distinct' => 1), array(
  new Node('tables', array(new Node('table', 'table'))),
  new Node('conditions', array(
    new Node('condition', array('operator' => '='), array(
      new Node('exp', array('type' => 'field'), 'col1'),
      new Node('exp', array('type' => 'numeric'), '1')
    )),
    new Node('condition', array('operator' => '!='), array(
      new Node('exp', array('type' => 'field'), 'col4'),
      new Node('exp', array('type' => 'string'), 'no')
    )),
    new Node('condition', array(new Node('exp', array('type' => '(')))),
    new Node('condition', array('operator' => '='), array(
      new Node('exp', array('type' => 'field'), 'coln'),
      new Node('exp', array('type' => 'string'), 'x')
    )),
    new Node('condition', array('operator' => '=', 'or' => '1'), array(
      new Node('exp', array('type' => 'field'), 'coly'),
      new Node('exp', array('type' => 'string'), 'z')
    )),
    new Node('condition', array(new Node('exp', array('type' => ')'))))
  )),
  new Node('fields', array(
    new Node('field', array('sortindex' => '1', 'sortorder' => 'desc'), 'col1'),
    new Node('field', array('sortindex' => '2', 'sortorder' => 'desc'), 'col2'),
    new Node('field', 'col1')
  ))
));
```

which is a bit more tiresome than the `$sql` variant. By using the convenience
methods in the `XML` namespace you could write the same thing with:

```php
use Lime\XML AS X;

$n = X\query(array(
  X\table('table'),
  X\conds(array(
    X\cond(array('operator' => '='), array(
      X\exp('field', 'col1'),
      X\exp('numeric', '1')
    )),
    X\cond(array('operator' => '!='), array(
      X\exp('field', 'col4'),
      X\exp('string', 'no')
    )),
    X\cond(X\exp('(')),
    X\cond(array('operator' => '='), array(
      X\exp('field', 'coln'),
      X\exp('string', 'x')
    )),
    X\cond(array('operator' => '=', 'or' => '1'), array(
      X\exp('field', 'coly'),
      X\exp('string', 'z')
    )),
    X\cond(X\exp(')'))
  )),
  X\fields(
    array('field' => 'col1', 'sortindex' => 1, 'sortorder' => 'desc'),
    array('field' => 'col2', 'sortindex' => 2, 'sortorder' => 'desc'),
    'col3'
  )
));
```

It's not as simple as writing an SQL query but it's easier than using the
`Node` object directly. In PHP 6.3 you will be able to import function names
from a namespace and that will remove the necessity of the `X\` prefix.

But the `Node` object can also be used for reading results from a SOAP call
to the Lime web services...

### 3. The `Lime` namespace

The `Lime` namespace only has two methods, `sql_to_node()` and `load_xml()`, at
the moment. The former will turn an SQL query into a `Lime\XML\Node` object and
the latter will turn an XML tree into a `Lime\XML\Node` object.

## Example

This is a real example that works for our installation of Lime.

```php
Lime\Client\set_endpoint('http://our.domain.local:8081/DataService/?wsdl');
Lime\Client\set_config(array("trace" => 1, "exception" => 0));

$sql = "
SELECT DISTINCT
       idsostype, descriptive, soscategory, soscategory.sosbusinessarea,
       webcompany, webperson, web, department, name, department.descriptive
FROM   sostype
WHERE  active = 1 AND
       soscategory.sosbusinessarea = 2701 AND
       web=1 AND (webperson=1 OR webcompany=1)
ORDER BY descriptive, department";

$res = Lime\Client\query($sql);

foreach ($res as $row) {
  echo "* {$row['name']} ({$row['department.descriptive']})\n";
}
```

2014-12-10