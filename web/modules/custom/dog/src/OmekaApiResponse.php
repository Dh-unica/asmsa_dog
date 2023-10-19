<?php

namespace Drupal\dog;

/**
 * Defines the OmekaApiResponse class.
 *
 * @package Drupal\dog
 */
class OmekaApiResponse {

  /**
   * The content of response.
   *
   * @var array
   */
  protected $content = [];

  /**
   * Total results that indicates the total number of results across all pages.
   *
   * @var int
   */
  protected $total_results = 0;

  /**
   * Construct new OmekaApiResponse instance.
   *
   * @param mixed $content
   *   The content of response from API..
   * @param int $total_results
   *   Total results that indicates the total number of results across all pages.
   */
  public function __construct($content, int $total_results = 1) {
    $this->content = $content;
    $this->total_results = $total_results;
  }

  /**
   * Retrieve the content of response (how array).
   *
   * @return array
   *   An array contains the data.
   */
  public function getContent(): array {

    // Convert to array.
    return json_decode($this->content, TRUE);
  }

  /**
   * Retrieve the total result accross all pages.
   *
   * @return int
   *   The total result.
   */
  public function getTotalResults(): int {
    return $this->total_results;
  }

  /**
   * Set the total results accross all pages.
   *
   * @param int $total_results
   *   The number of results.
   */
  public function setTotalResults(int $total_results): void {
    $this->total_results = $total_results;
  }

}
