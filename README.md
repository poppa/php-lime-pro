php-lime-pro
============

A PHP interface to the web services of the CRM **Lime PRO** by
[Lundalogik](https://github.com/lundalogik).

This module has a simple SQL parser so you can query the web services pretty
much in the same way as you would query a normal database. *(At the moment
only SELECT statements are handled, and maybe support for INSERT/UPDATE will
be added in the future)*. This is applicable to the `GetXmlQueryData` web
service which is the one being used the most.

There's also a [Java version](https://github.com/poppa/java-lime-pro) and a
[C# version](https://github.com/poppa/csharp-lime-pro) of this module.

## How does it work?

There are three `namespaces` in this module (in order of relevance)

  1. Lime
  2. Lime\Sql
  3. Lime\XML

`Lime` contains functions for calling the web services. `Lime\XML` has
functions and classes (a class) for creating the XML query document being sent
to the web services and `Lime\Sql` has the SQL parsing stuff which isn't of any
interest for normal usage.


### 1. The `Lime` namespace

This namespace has one significant class, namely the `Client` class. This class
extends the builtin [`SoapClient`](http://php.net/manual/en/soapclient.soapclient.php)
so all functionality in `SoapClient` can be used in `Lime\Client`.

You can set the endpoint of the webservices, i.e the URL of the `WSDL` file,
and the `SoapClient` options globally so that you don't have to pass those as
arguments when ever you need to instantiate a new `Client` object.

To set the endpoint of the client, i.e. the location of the
`WSDL` file of the webservices, just call the static method `Client::set_endpoint()`.

```php
// Set the endpoint
Lime\Client::set_endpoint('http://url.to:8081/DataService/?wsdl');
```

To set the options for the [`SoapClient`](http://php.net/manual/en/soapclient.soapclient.php)
object, call `Client::set_options()`.

```php
// Set the options array
Lime\Client::set_options(array('trace' => 1, 'exception' => 0));
```

And then the `Client` has a `Client::query()` method for calling the
`GetXmlQueryData` web service.

```php
// Given the endpoint is set before this point

$sql = "
  SELECT DISTINCT col1, col2, col3
  FROM table
  WHERE col1=1 AND col4!='no' AND (coln='x' OR coly='z')
  ORDER BY col1, col2 DESC";

$cli = new Lime\Client;
$res = $cli->query($sql);

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

which will be sent as argument to the web service. `Lime\Client::query()` will
return an array of associative arrays if it succeeds, as shown in the example.

The `Lime` namespace also has three methods, `sql_to_node()`, `load_xml()` and
`query()`. `Lime\sql_to_node()` will turn an SQL query into a `Lime\XML\Node`
object and `Lime\load_xml()` will turn an XML tree into a `Lime\XML\Node` object.

`Lime\query()` is a convenience function for calling the `Client::query()` method.
Calling `Lime\query($sql)` is essential the same as

```php
$cli = new Lime\Client;
$cli->query($sql);
```


### 2. The `Lime\Sql` namespace

As a consumer of this module you really don't need to call anything specifically
in this namespace, that stuff is handled for you in the `Lime` namespace and
the `\Lime\Client`. But there's some stuff good knowing absout the SQL and Lime
query syntaxes.

All operators defined in the [Lime documentation](http://docs.lundalogik.com/pro/integration/lime-web-service/queries)
can be used in the SQL query.

The SQL syntax also handles `LIMIT FROM[, TO]`.

Lime XML queries also have the operators `%LIKE` and `LIKE%`. The SQL parser
handles this like in a normal SQL query so you put the wildcard `%` signs on
either side of the value and then the parser puts them on the `LIKE` operator
for you. So to match the beginning of a string you would write it like normal
SQL

```sql
WHERE field LIKE 'Some%'
```

which will become

```xml
<condition operator="LIKE%">
  <exp type="field">field</exp>
  <exp type="string">Some</exp>
</condition>
```


#### Typehints

The SQL parser determines the data types in `WHERE` clause based on whether the
value is quoted or not. If it's not quoted it's assumed to be a numeric value.
If it's quoted it's assumed to be a string value. If it's quoted a check
for if the value is a (ISO 8601) date will take place.

But in some cases you need to quote the value and have it as a numeric value,
for instance if you want to do a `IN` or `NOT IN` check on a numeric field.

If that's the case you can use typehints:

```sql
WHERE some_col NOT IN '12;13;14':numeric
```

Any thing like `:something` is assumed to be a typehint.


### 3. The `Lime\XML` namespace

This namespace has one class, `Node`, for building the Lime XML queries, and
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


## Example

This is a real world example that works for our installation of Lime.

```php
Lime\Client::set_endpoint('http://our.domain.local:8081/DataService/?wsdl');
Lime\Client::set_options(array("trace" => 1, "exception" => 0));

$sql = "
SELECT DISTINCT
       idsostype, descriptive, soscategory, soscategory.sosbusinessarea,
       webcompany, webperson, web, department, name, department.descriptive
FROM   sostype
WHERE  active = 1 AND
       soscategory.sosbusinessarea = 2701 AND
       web=1 AND (webperson=1 OR webcompany=1)
ORDER BY descriptive, department";

$res = Lime\query($sql);

foreach ($res as $row) {
  echo "* {$row['name']} ({$row['department.descriptive']})\n";
}
```

2014-12-11