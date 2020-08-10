<?php

namespace DrupalJUnit\Builder;

/**
 * Builder functions for the html table results.
 *
 * @package DrupalJUnit\Builder
 */
class Builder {

  /**
   * Helper function that builds a run results HTML table.
   */
  public function getRunResults (array $run, $content) {
    $content = $this->createTableHeaders($run, $content);
    $content = $this->createStatsRows($run, $content);
    return $content;
  }

  /**
   * Helper function that builds a aggregate stats table.
   */
  protected function createStatsRows (array $row, $content) {
    $content .= '<tr>';

    foreach ($row as $key => $result) {
      $content .= '<td';
      // Adding a value attribute to color the cell if its a failure.
      if (preg_match('/^.*failures.*$/i', $key) && $result != 0) {
        $content .= ' value="failures">';
      }
      elseif (preg_match('/^.*errors.*$/i', $key) && $result != 0) {
        $content .= ' value="errors">';
      }
      else {
        $content .= '>';
      }
      $content .= $result . '</td>';
    }
    $content .= '</tr>';
    $this->closeTable($content);
    return $content;
  }

  /**
   * Helper function that builds a table headers.
   */
  public function createTableHeaders(array $table, $content) {
    $content .= '<tr>
                          <th>' . implode('</th><th>', array_keys($table)) . '</th>
                      </tr>';
    return $content;
  }

  /**
   * Helper function that builds a table rows.
   */
  public function createTableRow (array $rows, $content) {
    $content .= '<tr>';
    foreach ($rows as $key => $value) {
      $content .= '<td';
      // Adding a value attribute to color the cell if its a failure.
      if (preg_match('/^.*failures.*$/i', $key)  && $value != 0) {
        $content .= ' value="failures">';
      }
      elseif (preg_match('/^.*errors.*$/i', $key) && $value != 0) {
        $content .= ' value="errors">';
      }
      else {
        $content .= '>';
      }
      $content .= $value . '</td>';
    }
    $content .= '</tr>';

    return $content;
  }

  /**
   * Helper function that builds a table for any result failures.
   *
   * Currently this is only being used for Behat results. The weirdness of
   * phpunit's output getting actual failures is a bit of a pain.
   */
  public function createFailuresTable (array $failures, $content) {
    $content .= '<table>' . $this->createTableCaption('Failures') .' <tr><th>Failing Suite</th><th>Failing Class</th><th>Failing Test</th><th>Failure message</th></tr>';
    foreach ($failures as $suite => $node) {
      foreach ($node as $failure) {
        $content .= '<tr>
                              <td>
                                  <code>' . $failure['Suite'] . '</code>
                              </td>
                              <td>
                                  <code>' . $failure['Feature'] . '</code>
                              </td>
                              <td>
                                  <code>' . $failure['Scenario'] . '</code>
                              </td>
                              <td>
                                  <code>' . htmlspecialchars($failure['Error'], ENT_HTML5 | ENT_NOQUOTES) . '</code>
                              </td>
                            </tr>';
      }
    }
    $content =  $this->closeTable($content);
    return $content;
  }

  /**
   * Adds a table captions
   */
  public function createTableCaption($strValue) {
    return'<caption>' . $strValue . '</caption>';
  }

  /**
   * CLosing a tables.
   */
  public function closeTable ($content) {
    $content .= '</table><br>';
    return $content;
  }
}