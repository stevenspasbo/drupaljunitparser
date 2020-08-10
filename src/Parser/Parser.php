<?php

namespace DrupalJUnit\Parser;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use DrupalJUnit\Builder\Builder;

const DEFAULT_PATH = __DIR__.'/test/results';


/**
 * Drupal 8 Parser class for behat and PHPUnit.
 */
class Parser {

  /**
   * Serializer object.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  protected $builder;

  protected $report = [];

  private $errors = [];

  private $stats = [];

  protected $rawXml = '';

  protected $content = '';

  protected $html = '<html>
                        <head>
                          <style>
                             table, td, th {
                                border: 1px solid gray;
                                border-collapse: collapse;
                                font-family: "Times New Roman";
                                padding: 2px;
                                text-align: left;
                              }

                              td[value="failures"], td[value="errors"] {
                                background-color: red;
                              }
                              caption {
                                display: table-caption;
                                text-align: center;
                                font-size: 25px;
                              }
                          </style>
                        </head>
                       <body>
                        [content]
                      </body>
                    </html>';

  /**
   * Parser constructor.
   */

  public function __construct() {
    $encoders = [new XmlEncoder()];
    $normalizers = [new ObjectNormalizer()];
    $this->serializer = new Serializer($normalizers, $encoders);
    $this->builder = new Builder();
  }

  /**
   * The main parse function.
   *
   * @param string $path
   *   Path of the xml files.
   * @param string $pattern
   *   File pattern for glob match. Default is nothing.
   * @param string $type
   *   Type of results to be parsed. Default to phpunit.
   */
  function parse($path = DEFAULT_PATH, $pattern = '', $type = 'phpunit') {


    if ($type == 'behat') {
      // default headers for the report table;
      $this->stats['total_results'] = [
        'Total tests' => 0,
        'Total failures' => 0,
        'Total errors' => 0,
      ];

      $this->report['headers'] =
        [
          'Suite name' => '',
          'Feature name' => '',
          'Tests' => '',
          'Skipped' => '',
          'Failures' => '',
          'Errors' => '',
        ];
    }
    if ($type == 'phpunit' || $type === 'suite') {
      // default headers for the report table;
      $this->stats['total_results'] = [
        'Total tests' => 0,
        'Total failures' => 0,
        'Total errors' => 0,
        'Total time' => 0,
      ];
      $this->report['headers'] =
        [
          'Suite type' => '',
          'Class name' => '',
          'Tests' => '',
          'Failures' => '',
          'Errors' => '',
          'Time' => '',
          'Assertions' => '',
        ];
    }

    foreach (glob($path . '/' . $pattern . '*.xml') as $file) {
      if ($type == 'phpunit') {
        $this->parsePHPUnit($file);
      }
      elseif ($type == 'behat') {
        $this->parseBehat($file);
      }
      elseif ($type == 'suite') {
        $this->parseSuitePHPUnit($file);
      }
    }
    if ($type == 'phpunit' || $type == 'suite') {
      $this->buildPHPUnitHtml();
    }
    elseif ($type == 'behat') {
      $this->buildBehatHtml();
    }
    file_put_contents($path . '/' . $type . '_report.html', str_replace('[content]', $this->content, $this->html));
  }

  /**
   * Function to parse phpunit junit output into an array.
   *
   * @param $file string
   *   File to be processed.
   */
  private function parsePHPUnit($file) {
    // @TODO Will want to re-vist this once we move to phpunit v6.
    $this->rawXml = file_get_contents($file);
    if ($this->rawXml) {
      $xml = $this->serializer->decode($this->rawXml, 'xml');
      // Top level for a particular phpunit test suite.
      $testsuite = $xml['testsuite']['testsuite'];
      if (empty($testsuite['@name'])) {
        foreach ($testsuite as $suite) {
          $this->processTestSuite($suite);

        }
      }
      else {
        $this->processTestSuite($testsuite);
      }
    }
  }

  private function processTestSuite($testsuite) {
    $this->stats[$testsuite['@name']] = [
      'Type' => $testsuite['@name'],
      'Total test' => $testsuite['@tests'],
      'Total time' => $testsuite['@time'],
      'Total failures' => $testsuite['@failures'],
      'Total errors' => $testsuite['@errors'],
    ];
    // Run totals.
    $this->stats['total_results']['Total tests'] += $testsuite['@tests'];
    $this->stats['total_results']['Total failures'] += $testsuite['@failures'];
    $this->stats['total_results']['Total errors'] += $testsuite['@errors'];
    $this->stats['total_results']['Total time'] += $testsuite['@time'];

    foreach ($testsuite['testsuite'] as $testcase) {
      $this->report[$testsuite['@name']][] = [
        'Suite type' => $testsuite['@name'],
        'Class name' => $testcase['@name'],
        'Tests' => $testcase['@tests'],
        'Failures' => $testcase['@failures'],
        'Errors' => $testcase['@errors'],
        'Time' => $testcase['@time'],
        'Assertions' => $testcase['@assertions'],
      ];
    }
    $xml = new \SimpleXMLElement($this->rawXml);
    $testcases = $this->processTestCaseXml($xml);
    foreach ($testcases as $testcase) {
      /** @var \SimpleXMLElement $testcase */
      if ($testcase->failure || $testcase->error) {
        $attr = $testcase->attributes();
        $this->errors[$testsuite['@name']][] = [
          'Suite' => $testsuite['@name'],
          'Feature' => $attr->class,
          'Scenario' => $attr->name,
          'Error' => !empty($testcase->failure) ? $testcase->failure : $testcase->error,
        ];
      }
    }
  }

  /**
   * Parse individual test file phpunit runs.
   *
   * e.g. This would be calling a test file, /path/to/file/ExampleUnitTest.php.
   * The output for this is slightly different than if you call phpunit with
   * the --testsuite flag.
   *
   * @param $file
   *   XML file being parsed.
   */
  private function parseSuitePHPUnit($file) {
    $raw = @file_get_contents($file);
    if ($raw) {
      $xml = new \SimpleXMLElement($raw);
      if ($xml) {
        /** @var \SimpleXMLElement $suiteAttrib */
        if ($suiteAttrib = $xml->children()->attributes()) {
          $this->stats['total_results']['Total tests'] += (int) $suiteAttrib->tests;
          $this->stats['total_results']['Total failures'] += (int) $suiteAttrib->failures;
          $this->stats['total_results']['Total errors'] += (int) $suiteAttrib->errors;
          $this->stats['total_results']['Total time'] += (int) $suiteAttrib->time;
          $testType = basename($suiteAttrib->name);

          // Top level for a particular phpunit test suite.
          if (empty($this->stats[$testType])) {
            $this->stats[$testType] = [
              'Type' => $testType,
              'Total test' => 0,
              'Total time' => 0,
              'Total failures' => 0,
              'Total errors' => 0,
            ];
          }
          $this->stats[$testType]['Total test'] += (int) $suiteAttrib->tests;
          $this->stats[$testType]['Total time'] += (int) $suiteAttrib->time;
          $this->stats[$testType]['Total failures'] += (int) $suiteAttrib->failures;
          $this->stats[$testType]['Total errors'] += (int) $suiteAttrib->errors;
        }
        // Individual test failures failures
        $testsuites = $this->processTestSuiteXML($xml);
        foreach ($testsuites as $testsuite) {
          $attr = $testsuite->attributes();
          $this->report[$testType][] = [
            'Suite type' => $testType,
            'Class name' => $attr->name,
            'Tests' => $attr->tests,
            'Failures' => $attr->failures,
            'Errors' => $attr->errors,
            'Time' => $attr->time,
            'Assertions' => $attr->assertions,
          ];
        }
        $testcases = $this->processTestCaseXml($xml);
        foreach ($testcases as $testcase) {
          /** @var \SimpleXMLElement $testcase */
          if ($testcase->failure || $testcase->error) {
            $attr = $testcase->attributes();
            $this->errors[$testType][] = [
              'Suite' => $testType,
              'Feature' => $attr->class,
              'Scenario' => $attr->name,
              'Error' => !empty($testcase->failure) ? $testcase->failure : $testcase->error,
            ];
          }
        }
      }
    }
  }

  private function processTestCaseXml(\SimpleXMLElement $child, \SimpleXMLElement $parent = NULL) {
    $testcases = [];
    if (!isset($parent)) {
      $parent = $child;
    }
    // Want the individual testcases.
    if ($child->getName() === 'testcase' && $parent->attributes()->tests > 0) {

      if (!$child->attributes()->class) {
        $name = explode('::', $parent->attributes()->name, 2);
        $child->addAttribute('class', $name[0]);
      }
      $testcases[] = $child;
    }
    else {
      foreach ($child as $nest) {
        $testcases = array_merge($testcases, $this->processTestCaseXml($nest, $child));
      }
    }
    return $testcases;
  }

  private function processTestSuiteXML(\SimpleXMLElement $child, \SimpleXMLElement $parent = NULL) {
    $testSuites = [];
    if (!isset($parent)) {
      $parent = $child;
    }
    // Want the individual testsuites.
    if ($child->getName() === 'testsuite' && $parent->attributes()->tests > 0) {

      if (!$child->attributes()->class) {
        $name = explode('::', $parent->attributes()->name, 2);
        $child->addAttribute('class', $name[0]);
      }
      $testSuites[] = $child;
    }
    else {
      foreach ($child as $nest) {
        $testSuites = array_merge($testSuites, $this->processTestSuiteXML($nest, $child));
      }
    }
    return $testSuites;
  }

  /**
   * Function to parse behat junit files into array.
   *
   * @param $file
   *  File(s) to be processed by the behat processor.
   */
  private function parseBehat($file) {
    $crawler = new Crawler();
    $contents = file_get_contents($file);
    if ($contents) {
      $crawler->addXmlContent($contents);
      $suiteName = $crawler->filter('testsuites')->attr('name');
      $this->stats[$suiteName] = [
        'Suite name' => $suiteName,
        'Total tests' => 0,
        'Total failures' => 0,
        'Total errors' => 0,
      ];
      $crawler->filter('testsuite')
        ->each(function ($node, $i) use ($suiteName) {
          /** @var $node Crawler */
          $this->stats[$suiteName]['Suite name'] = $suiteName;
          $this->stats[$suiteName]['Total tests'] += $node->attr('tests');
          $this->stats[$suiteName]['Total failures'] += $node->attr('failures');
          $this->stats[$suiteName]['Total errors'] += $node->attr('errors');
          $this->stats['total_results']['Total tests'] += $node->attr('tests');
          $this->stats['total_results']['Total failures'] += $node->attr('failures');
          $this->stats['total_results']['Total errors'] += $node->attr('errors');
          $this->report[$suiteName][$i] =
            [
              'Suite name' => $suiteName,
              'Feature name' => $node->attr('name'),
              'Tests' => $node->attr('tests'),
              'Skipped' => $node->attr('skipped'),
              'Failures' => $node->attr('failures'),
              'Errors' => $node->attr('errors'),
            ];
          $feature = $node->attr('name');
          $node->filter('testcase')
            ->each(function ($attr) use ($suiteName, $feature, $i) {
              /** @var $attr Crawler */
              if ($attr->attr('status') === 'failed') {
                $this->errors[$suiteName][$i] =
                  [
                    'Suite' => $suiteName,
                    'Feature' => $feature,
                    'Scenario' => $attr->attr('name'),
                    'Status' => $attr->attr('status'),
                    'Error' => $attr->filter('failure')->attr('message'),
                  ];
              }
            });
        });
    }
  }

  /**
   * Helper function for HTML table output for behat specific junit output.
   */
  private function buildBehatHtml() {
    // Total results table.
    if (isset($this->stats['total_results'])) {
      $this->content .= '<table>' . $this->builder->createTableCaption('Run results');
      $this->content = $this->builder->getRunResults($this->stats['total_results'], $this->content);
      unset($this->stats['total_results']);
      $this->content = $this->builder->closeTable($this->content);
    }
    // Suite run stats.
    if (is_array($this->stats)) {
      $headers = reset($this->stats);
      $this->content .= '<table>' . $this->builder->createTableCaption('Aggregated suite results');
      $this->content = $this->builder->createTableHeaders($headers, $this->content);
      foreach ($this->stats as $key => $test) {
        $this->content = $this->builder->createTableRow($test, $this->content);
      }
      $this->content = $this->builder->closeTable($this->content);
    }
    // Feature run information.
    // Create the headers for the table.
    if (isset($this->report['headers'])) {
      $this->content .= '<table>' . $this->builder->createTableCaption('Feature results');
      $this->content = $this->builder->createTableHeaders($this->report['headers'], $this->content);
      unset($this->report['headers']);
      // Process all the rows of data.
      foreach ($this->report as $suite => $node) {
        foreach ($node as $test) {
          $this->content = $this->builder->createTableRow($test, $this->content);
        }
      }
      // Close the last table.
      $this->content = $this->builder->closeTable($this->content);
    }

    $this->content = $this->builder->createFailuresTable($this->errors, $this->content);
  }

  /**
   * Helper function for HTML table output for phpunit specific junit output.
   */
  private function buildPHPUnitHtml() {
    // @TODO Will want to re-vist this once we move to phpunit v6.
    if (isset($this->stats['total_results'])) {
      $this->content .= '<table>' . $this->builder->createTableCaption('Run results');
      $this->content = $this->builder->getRunResults($this->stats['total_results'], $this->content);
      unset($this->stats['total_results']);
      $this->content = $this->builder->closeTable($this->content);
    }

    if (is_array($this->stats)) {
      $this->content .= '<table>' . $this->builder->createTableCaption('Aggregated suite results');
      $this->content = $this->builder->createTableHeaders(reset($this->stats), $this->content);
      foreach ($this->stats as $suite => $node) {
        $this->content = $this->builder->createTableRow($node, $this->content);
      }
      $this->content = $this->builder->closeTable($this->content);
    }
    if (isset($this->report['headers'])) {
      $this->content .= '<table>' . $this->builder->createTableCaption('Feature results');
      $this->content = $this->builder->createTableHeaders($this->report['headers'], $this->content);
      unset($this->report['headers']);
      // Process all the rows of data.
      foreach ($this->report as $suite => $node) {
        foreach ($node as $test) {
          $this->content = $this->builder->createTableRow($test, $this->content);
        }
      }
      $this->content = $this->builder->closeTable($this->content);
    }
    $this->content = $this->builder->createFailuresTable($this->errors, $this->content);
  }
}
