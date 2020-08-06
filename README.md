# drupaljunitparser
Parsers for PHPUnit and Behat junit output for drupal 8 test
## Installing 
```bash
composer require ndrake0027/drupaljunitparser
```
Adding directly in `composer.json`
```
"require": {
  "ndrake0027/drupaljunitparser" : "dev-master" 
},
```

Example usage:

````PHP
<?php
/********
 * Main *
 *******/

require __DIR__ . '/vendor/autoload.php';

use DrupalJUnit\Parser\Parser;

$dir = $argv[1];
$pattern = isset($argv[3]) ? $argv[3] : '';
$type = isset($argv[2]) ? $argv[2] : '';
$parser = new Parser();
$parser->parse($dir, $pattern, $type);

```` 

## Example output

### Behat output
<table>
  <caption>Run results</caption>
  <tr>
      <th>Total tests</th>
      <th>Total failures</th>
      <th>Total errors</th>
  </tr>
  <tr>
      <td>442</td>
      <td value="failures">33</td>
      <td>0</td>
  </tr>
</table><br>
<table>
  <caption>Aggregated suite results</caption>
  <tr>
      <th>Suite name</th>
      <th>Total tests</th>
      <th>Total failures</th>
      <th>Total errors</th>
  </tr>
  <tr>
      <td>access</td>
      <td>71</td>
      <td>0</td>
      <td>0</td>
  </tr>
  </table>
  <table>
          <caption>Scenario Failures</caption>
          <tr>
              <th>Failing Suite</th>
              <th>Failing Class</th>
              <th>Failing Test</th>
              <th>Failure message</th>
          </tr>
          <tr>
              <td>
                  <code>Test Suite</code>
              </td>
              <td>
                  <code>Test Class name</code>
              </td>
              <td>
                  <code>Test name</code>
              </td>
              <td>
                  <code>Some exception i.e.: (Behat\Mink\Exception\ElementNotFoundException)</code>
              </td>
          </tr>
</table>

### PHPUnit output
<html>
  <body>
  <table>
    <caption>Run results</caption>
    <tr>
      <th>Total tests</th>
      <th>Total failures</th>
      <th>Total errors</th>
      <th>Total time</th>
    </tr>
    <tr>
      <td>575</td>
      <td value="failures">24</td>
      <td>0</td>
      <td>9167.635773</td>
    </tr>
  </table>
  <table>
    <caption>Aggregated suite results</caption>
    <tr>
      <th>Type</th>
      <th>Total test</th>
      <th>Total time</th>
      <th>Total failures</th>
      <th>Total errors</th>
    </tr>
    <tr>
      <td>functionalJavascript</td>
      <td>20</td>
      <td>2480.456045</td>
      <td>0</td>
      <td>0</td>
    </tr>
   </table>
   <table>
     <caption>Feature results</caption>
     <tr>
       <th>Suite Type</th>
       <th>Class name</th>
       <th>Tests</th>
       <th>Failures</th>
       <th>Errors</th>
       <th>Time</th>
       <th>Assertions</th>
     </tr>
     <tr>
       <td>functionalJavascript</td>
       <td>Drupal\Tests\Path\FunctionalJavascript\TestClass</td>
       <td>1</td>
       <td>0</td>
       <td>0</td>
       <td>117.336066</td>
       <td>12</td>
     </tr>
   </table>
   <table>
     <caption>Scenario Failures</caption>
     <tr>
         <th>Failing Suite</th>
         <th>Failing Class</th>
         <th>Failing Test</th>
         <th>Failure message</th>
     </tr>
     <tr>
         <td>
             <code>Test Suite</code>
         </td>
         <td>
             <code>Test Class name</code>
         </td>
         <td>
             <code>Test name</code>
         </td>
         <td>
             <code>Some exception and stacktrace i.e.: RuntimeException: Could not fetch version information from https://dummy_server:port/test/cli. Please check if Chrome is running. Please see docs/troubleshooting.md if Chrome crashed unexpected. </code>
         </td>
     </tr>
   </table>
  </body>
</html>
